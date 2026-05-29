<?php
// claim_mail.php - 認領/取消認領信件（純 DB，不依賴 IMAP keyword）
ob_start();
require_once __DIR__ . '/auth.php';
requireLogin();
ob_clean();
ini_set('display_errors', '0');
header('Content-Type: application/json; charset=utf-8');

$user = currentUser();

try {
    require_once __DIR__ . '/db.php';

    $mailId = (int)($_POST['mail_id'] ?? 0);
    $action = trim($_POST['action'] ?? ''); // 'claim' or 'unclaim'

    if ($mailId <= 0) throw new InvalidArgumentException('無效的 mail_id');
    if (!in_array($action, ['claim', 'unclaim'])) throw new InvalidArgumentException('無效的 action');

    // 取得使用者的 mailbox_imap 以計算顯示名稱
    $pdo  = getDB();
    $stmt = $pdo->prepare('SELECT mailbox_imap, username FROM users WHERE id = ?');
    $stmt->execute([$user['id']]);
    $row = $stmt->fetch();

    if (!$row || empty($row['mailbox_imap'])) {
        throw new RuntimeException('您尚未被分配信件匣，無法使用認領功能。');
    }

    $mailboxImap = $row['mailbox_imap'];

    // 計算顯示名稱：解碼 Modified UTF-7，如 &YB2QYA- → 思遠
    $displayName = preg_replace_callback('/&([^-]*)-/', function ($m) {
        if ($m[1] === '') return '&';
        $base64  = str_replace(',', '/', $m[1]);
        $decoded = base64_decode($base64);
        return mb_convert_encoding($decoded, 'UTF-8', 'UTF-16BE');
    }, $mailboxImap);

    // claimed_by 存顯示名稱（如「思遠」），這樣所有帳號看到的標籤都一致
    $claimLabel = $displayName;

    if ($action === 'claim') {
        // 先確認沒被別人認領
        $checkStmt = $pdo->prepare('SELECT claimed_by FROM parsed_mails WHERE mail_id = ?');
        $checkStmt->execute([$mailId]);
        $currentClaim = $checkStmt->fetchColumn();

        if ($currentClaim && $currentClaim !== $claimLabel) {
            throw new RuntimeException('此信件已被「' . $currentClaim . '」認領。');
        }

        $pdo->prepare('UPDATE parsed_mails SET claimed_by = ?, updated_at = NOW() WHERE mail_id = ?')
            ->execute([$claimLabel, $mailId]);

    } else {
        // 只能取消自己的認領
        $checkStmt = $pdo->prepare('SELECT claimed_by FROM parsed_mails WHERE mail_id = ?');
        $checkStmt->execute([$mailId]);
        $currentClaim = $checkStmt->fetchColumn();

        if ($currentClaim && $currentClaim !== $claimLabel) {
            throw new RuntimeException('您只能取消自己的認領。');
        }

        $pdo->prepare('UPDATE parsed_mails SET claimed_by = NULL, updated_at = NOW() WHERE mail_id = ?')
            ->execute([$mailId]);
    }

    echo json_encode([
        'success'    => true,
        'mail_id'    => $mailId,
        'action'     => $action,
        'claimed_by' => $action === 'claim' ? $claimLabel : null,
        'display'    => $claimLabel,
    ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error'   => $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE);
}
