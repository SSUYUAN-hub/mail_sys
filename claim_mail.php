<?php
// claim_mail.php - 認領/取消認領信件
ob_start();
require_once __DIR__ . '/auth.php';
requireLogin();
ob_clean();
ini_set('display_errors', '0');
header('Content-Type: application/json; charset=utf-8');

$user = currentUser();

try {
    require_once __DIR__ . '/db.php';
    require_once __DIR__ . '/mail_fetcher.php';

    $mailId = (int)($_POST['mail_id'] ?? 0);
    $action = trim($_POST['action'] ?? ''); // 'claim' or 'unclaim'

    if ($mailId <= 0) throw new InvalidArgumentException('無效的 mail_id');
    if (!in_array($action, ['claim', 'unclaim'])) throw new InvalidArgumentException('無效的 action');

    // 取得使用者的 mailbox_imap（作為 keyword 識別名稱）
    $pdo  = getDB();
    $stmt = $pdo->prepare('SELECT mailbox_imap, username FROM users WHERE id = ?');
    $stmt->execute([$user['id']]);
    $row = $stmt->fetch();

    if (!$row || empty($row['mailbox_imap'])) {
        throw new RuntimeException('您尚未被分配信件匣，無法使用認領功能。');
    }

    $mailboxImap  = $row['mailbox_imap']; // 如 &YB2QYA- 或 CT（僅供顯示/歸檔用）
    $username     = $row['username'];

    // IMAP keyword 規範（RFC 3501）禁止 & ( ) { \ " % * ] 等字元
    // mailbox_imap 可能含 Modified UTF-7 的 & 字元，不符合規範會導致 setflag 靜默失敗
    // username 已限制為 [a-zA-Z0-9_\-]，完全符合 RFC 3501 atom 規範，直接使用
    $keyword = $username;

    $fetcher = new MailFetcher();

    if ($action === 'claim') {
        // 先確認沒被別人認領
        $currentClaim = $fetcher->getClaimedBy($mailId);
        if ($currentClaim && $currentClaim !== $keyword) {
            throw new RuntimeException('此信件已被「' . $currentClaim . '」認領。');
        }
        $fetcher->claimMail($mailId, $keyword);
        // 更新 DB
        $pdo->prepare('UPDATE parsed_mails SET claimed_by = ?, updated_at = NOW() WHERE mail_id = ?')
            ->execute([$keyword, $mailId]);
    } else {
        // 只能取消自己的認領
        $currentClaim = $fetcher->getClaimedBy($mailId);
        if ($currentClaim && $currentClaim !== $keyword) {
            throw new RuntimeException('您只能取消自己的認領。');
        }
        $fetcher->unclaimMail($mailId, $keyword);
        // 更新 DB
        $pdo->prepare('UPDATE parsed_mails SET claimed_by = NULL, updated_at = NOW() WHERE mail_id = ?')
            ->execute([$mailId]);
    }

    echo json_encode([
        'success'    => true,
        'mail_id'    => $mailId,
        'action'     => $action,
        'claimed_by' => $action === 'claim' ? $keyword : null,
        'keyword'    => $keyword,
    ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error'   => $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE);
}
