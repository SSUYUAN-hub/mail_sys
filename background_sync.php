<?php
// background_sync.php
// 背景同步：抓 IMAP 新信件、解析、存 DB、更新認領狀態
// 由 fetch_orders.php 非阻塞觸發，不直接對使用者回應

// 防止重複執行（簡易 lock）
$lockFile = sys_get_temp_dir() . '/mail_sync.lock';
if (file_exists($lockFile) && (time() - filemtime($lockFile)) < 120) {
    exit; // 120 秒內已有執行中的程序
}
file_put_contents($lockFile, time());

// 不輸出任何內容，讓觸發端立即返回
if (ob_get_level()) ob_end_clean();

// 允許最長 5 分鐘執行
set_time_limit(300);
ignore_user_abort(true);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/mail_fetcher.php';
require_once __DIR__ . '/order_parser.php';

function scoreOrder($order) {
    $score = 0;
    if ($order['customer_name']  !== '無' && $order['customer_name']  !== '請查後台') $score++;
    if ($order['check_in']       !== '無' && $order['check_in']       !== '請查後台') $score++;
    if ($order['check_out']      !== '無' && $order['check_out']      !== '請查後台') $score++;
    if ($order['owl_number']     !== '無')  $score++;
    if ($order['amount']         > 0)       $score++;
    if ($order['customer_phone'] !== '無')  $score++;
    return $score;
}

try {
    $pdo     = getDB();
    $fetcher = new MailFetcher();

    // 1. 抓 IMAP 所有信件（含 flags）
    $allMails = $fetcher->fetchUnreadMails(); // 回傳含 id, subject, html_body, flags

    // 2. 取得目前 DB 中已有的 mail_id
    $existingIds = $pdo->query('SELECT mail_id FROM parsed_mails')
                       ->fetchAll(PDO::FETCH_COLUMN);
    $existingIds = array_flip($existingIds);

    // 3. 取得 IMAP 目前還存在的所有 mail_id
    $currentImapIds = array_column($allMails, 'id');

    // 4. 刪除已不在 INBOX 的（已被歸檔或刪除）
    if (!empty($existingIds)) {
        $toDelete = array_diff(array_keys($existingIds), $currentImapIds);
        if (!empty($toDelete)) {
            $placeholders = implode(',', array_fill(0, count($toDelete), '?'));
            $pdo->prepare("DELETE FROM parsed_mails WHERE mail_id IN ($placeholders)")
                ->execute(array_values($toDelete));
        }
    }

    // 5. 解析新信件（DB 裡沒有的）並更新認領狀態
    $upsertStmt = $pdo->prepare(
        'INSERT INTO parsed_mails (mail_id, subject, parsed_json, claimed_by, fetched_at, updated_at)
         VALUES (?, ?, ?, ?, NOW(), NOW())
         ON DUPLICATE KEY UPDATE
             claimed_by = VALUES(claimed_by),
             updated_at = NOW()'
    );

    $insertStmt = $pdo->prepare(
        'INSERT INTO parsed_mails (mail_id, subject, parsed_json, claimed_by, fetched_at, updated_at)
         VALUES (?, ?, ?, ?, NOW(), NOW())
         ON DUPLICATE KEY UPDATE
             subject     = VALUES(subject),
             parsed_json = VALUES(parsed_json),
             claimed_by  = VALUES(claimed_by),
             updated_at  = NOW()'
    );

    foreach ($allMails as $mail) {
        $mailId    = $mail['id'];
        $claimedBy = $mail['claimed_by'] ?? null; // MailFetcher 從 flags 解析

        if (!isset($existingIds[$mailId])) {
            // 新信件：解析並存入
            $order = OrderParser::parse($mail['html_body'], $mail['subject']);
            if ($order['platform'] === '系統過濾信件') continue;

            $order['mail_id']       = $mailId;
            $order['check_in_roc']  = OrderParser::toROCDate($order['check_in']);
            $order['check_out_roc'] = OrderParser::toROCDate($order['check_out']);

            $insertStmt->execute([
                $mailId,
                $mail['subject'],
                json_encode($order, JSON_UNESCAPED_UNICODE),
                $claimedBy,
            ]);
        } else {
            // 舊信件：只更新認領狀態
            $pdo->prepare('UPDATE parsed_mails SET claimed_by = ?, updated_at = NOW() WHERE mail_id = ?')
                ->execute([$claimedBy, $mailId]);
        }
    }

    // 6. 寫入同步完成時間
    $pdo->prepare("REPLACE INTO parsed_mails (mail_id, subject, parsed_json, fetched_at, updated_at)
                   SELECT mail_id, subject, parsed_json, fetched_at, NOW() FROM parsed_mails LIMIT 0")
        ->execute(); // 只為觸發 updated_at，實際用 cache key 記錄

    // 用 apcu 或 file 記錄最後同步時間
    file_put_contents(sys_get_temp_dir() . '/mail_last_sync.txt', time());

} catch (Throwable $e) {
    error_log('[background_sync] ' . $e->getMessage());
} finally {
    @unlink($lockFile);
}
