<?php
// fetch_orders.php - 從 DB 讀取訂單，並在回應後背景同步

// 攔截所有意外輸出（PHP notice/warning 等），防止污染 JSON
ob_start();

require_once __DIR__ . '/auth.php';
requireLogin();

// 清除 auth.php 可能產生的任何輸出
ob_clean();

header('Content-Type: application/json; charset=utf-8');

// 關閉錯誤顯示，只記錄到 error_log
ini_set('display_errors', '0');
error_reporting(E_ALL);

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
    $pdo = getDB();

    // 強制重抓：清空 DB
    $forceRefresh = ($_GET['force'] ?? '0') === '1';
    if ($forceRefresh) {
        $pdo->exec('DELETE FROM parsed_mails');
    }

    // 從 DB 讀取已解析結果
    $rows = $pdo->query(
        'SELECT mail_id, parsed_json, claimed_by, UNIX_TIMESTAMP(fetched_at) AS fetched_ts
         FROM parsed_mails ORDER BY mail_id DESC'
    )->fetchAll();

    $orders = [];
    foreach ($rows as $row) {
        $order = json_decode($row['parsed_json'], true);
        if (!$order) continue;
        $order['mail_id']    = $row['mail_id'];
        $order['claimed_by'] = $row['claimed_by'];
        $orders[] = $order;
    }

    // 分組
    $groups = [];
    foreach ($orders as $order) {
        $key = ($order['ota_number'] !== '無' && $order['ota_number'] !== '')
            ? $order['ota_number']
            : 'unique_' . $order['mail_id'];
        $groups[$key][] = $order;
    }
    foreach ($groups as &$group) {
        usort($group, fn($a, $b) => scoreOrder($b) - scoreOrder($a));
    }
    unset($group);

    // 判斷是否需要背景同步（用獨立時間戳記檔案，不用 fetched_at 避免誤判）
    $syncFile = sys_get_temp_dir() . '/mail_last_sync.txt';
    $lastSync = file_exists($syncFile) ? (int)file_get_contents($syncFile) : 0;
    $needSync = $forceRefresh || (time() - $lastSync) > 30; // 超過 30 秒就重新同步

    $result = [
        'success'      => true,
        'groups'       => array_values($groups),
        'total_orders' => count($orders),
        'total_groups' => count($groups),
        'fetched_ts'   => $lastSync ?: time(),
        'is_syncing'   => $needSync && count($rows) === 0, // 只有完全沒資料才顯示同步中
        'from_cache'   => !$needSync,
        'cache_age'    => time() - $lastSync,
    ];

    // 清除所有意外輸出，只回傳純 JSON
    ob_clean();
    echo json_encode($result, JSON_UNESCAPED_UNICODE);

    // ── 背景同步（回應後繼續執行）────────────────────────────
    if ($needSync) {
        // 結束 HTTP 連線，讓前端繼續，PHP 繼續跑
        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
        } else {
            // 非 fastcgi 環境的替代方案
            ignore_user_abort(true);
            ob_end_flush();
            flush();
        }

        // 背景執行同步
        set_time_limit(300);

        try {
            $fetcher  = new MailFetcher();
            $allMails = $fetcher->fetchUnreadMails();

            // 目前 IMAP 中存在的 mail_id
            $currentImapIds = array_column($allMails, 'id');

            // 刪除已不在 INBOX 的
            $existingIds = $pdo->query('SELECT mail_id FROM parsed_mails')
                               ->fetchAll(PDO::FETCH_COLUMN);
            $toDelete = array_diff($existingIds, $currentImapIds);
            if (!empty($toDelete)) {
                $placeholders = implode(',', array_fill(0, count($toDelete), '?'));
                $pdo->prepare("DELETE FROM parsed_mails WHERE mail_id IN ($placeholders)")
                    ->execute(array_values($toDelete));
            }

            // 已在 DB 的 mail_id
            $existingSet = array_flip($existingIds);

            $insertStmt = $pdo->prepare(
                'INSERT INTO parsed_mails (mail_id, subject, parsed_json, claimed_by, fetched_at, updated_at)
                 VALUES (?, ?, ?, NULL, NOW(), NOW())
                 ON DUPLICATE KEY UPDATE
                     fetched_at = NOW(),
                     updated_at = NOW()'
            );

            foreach ($allMails as $mail) {
                $mailId = $mail['id'];

                if (!isset($existingSet[$mailId])) {
                    // 新信件：解析並存入，claimed_by 初始為 NULL
                    $order = OrderParser::parse($mail['html_body'], $mail['subject']);
                    if ($order['platform'] === '系統過濾信件') continue;

                    $order['mail_id']       = $mailId;
                    $order['check_in_roc']  = OrderParser::toROCDate($order['check_in']);
                    $order['check_out_roc'] = OrderParser::toROCDate($order['check_out']);

                    $insertStmt->execute([
                        $mailId,
                        $mail['subject'],
                        json_encode($order, JSON_UNESCAPED_UNICODE),
                    ]);
                } else {
                    // 舊信件：只更新時間戳，不動 claimed_by（認領狀態由 claim_mail.php 純 DB 管理）
                    $pdo->prepare(
                        'UPDATE parsed_mails SET fetched_at = NOW(), updated_at = NOW() WHERE mail_id = ?'
                    )->execute([$mailId]);
                }
            }

            // 背景同步完成，寫入時間戳
            file_put_contents(sys_get_temp_dir() . '/mail_last_sync.txt', time());

        } catch (Throwable $e) {
            error_log('[background_sync] ' . $e->getMessage());
        }
    }

} catch (Throwable $e) {
    echo json_encode([
        'success' => false,
        'error'   => $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE);
}
