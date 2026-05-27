<?php
// archive_mail.php - 歸檔端點，接收 mail_id + platform，執行 IMAP 移動
require_once __DIR__ . '/auth.php';
requireLogin();

header('Content-Type: application/json; charset=utf-8');

$user = currentUser();

// 平台對應子信件匣名稱
const PLATFORM_FOLDER_MAP = [
    'Booking.com'      => 'Booking',
    'Booking (取消)'   => 'Booking',
    'Agoda'            => 'Agoda',
    'Agoda (取消)'     => 'Agoda',
    'Trip.com'         => 'CT',
    'Trip.com (取消)'  => 'CT',
    'AsiaYo'           => 'AsiaYo',
    'AsiaYo (取消)'    => 'AsiaYo',
    'Airbnb'           => 'Airbnb',
    'Airbnb (取消)'    => 'Airbnb',
    // 修改通知也歸到同平台
    'Booking.com (修改)' => 'Booking',
    'Agoda (修改)'       => 'Agoda',
    'Trip.com (修改)'    => 'CT',
    'AsiaYo (修改)'      => 'AsiaYo',
    'Airbnb (修改)'      => 'Airbnb',
];

try {
    $mailId   = (int)($_POST['mail_id']  ?? 0);
    $platform = trim($_POST['platform']  ?? '');

    if ($mailId <= 0) {
        throw new InvalidArgumentException('無效的 mail_id');
    }
    if (empty($platform)) {
        throw new InvalidArgumentException('缺少 platform 參數');
    }

    // 取得當前使用者的主信件匣
    require_once __DIR__ . '/db.php';
    $pdo  = getDB();
    $stmt = $pdo->prepare('SELECT mailbox_imap FROM users WHERE id = ?');
    $stmt->execute([$user['id']]);
    $row = $stmt->fetch();

    if (!$row || empty($row['mailbox_imap'])) {
        throw new RuntimeException('您尚未被分配信件匣，請聯絡管理者設定。');
    }

    $mailboxImap = $row['mailbox_imap'];

    // 找出平台對應子信件匣
    $platformFolder = null;
    foreach (PLATFORM_FOLDER_MAP as $key => $folder) {
        if (mb_strpos($platform, str_replace(' (取消)', '', str_replace(' (修改)', '', $key))) !== false) {
            $platformFolder = $folder;
            break;
        }
    }
    // 精確匹配優先
    if (isset(PLATFORM_FOLDER_MAP[$platform])) {
        $platformFolder = PLATFORM_FOLDER_MAP[$platform];
    }

    if ($platformFolder === null) {
        throw new RuntimeException("找不到平台「{$platform}」對應的信件匣，請聯絡管理者。");
    }

    require_once __DIR__ . '/mail_fetcher.php';
    $fetcher = new MailFetcher();
    $success = $fetcher->archiveMail($mailId, $mailboxImap, $platformFolder);

    if (!$success) {
        throw new RuntimeException('IMAP 移動失敗，請確認目標信件匣是否存在：INBOX/' . $mailboxImap . '/' . $platformFolder);
    }

    echo json_encode([
        'success'         => true,
        'mail_id'         => $mailId,
        'target'          => 'INBOX/' . $mailboxImap . '/' . $platformFolder,
        'platform_folder' => $platformFolder,
    ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error'   => $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE);
}
