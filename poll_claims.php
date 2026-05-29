<?php
// poll_claims.php - 輪詢認領狀態、偵測今日入住新信件
ob_start();
require_once __DIR__ . '/auth.php';
requireLogin();
ob_clean();
ini_set('display_errors', '0');
header('Content-Type: application/json; charset=utf-8');

try {
    require_once __DIR__ . '/db.php';
    $pdo = getDB();

    // 回傳所有信件的認領狀態
    $rows = $pdo->query(
        'SELECT mail_id, claimed_by, parsed_json FROM parsed_mails'
    )->fetchAll();

    $claims = [];
    // 偵測今日入住的 mail_id 清單（用來讓前端判斷是否有未顯示的新今日訂單）
    $todayCheckInIds = [];
    $today = date('Y-m-d');

    foreach ($rows as $row) {
        $claims[$row['mail_id']] = $row['claimed_by'];

        // 解析 check_in 判斷是否今日入住
        if (!empty($row['parsed_json'])) {
            $order = json_decode($row['parsed_json'], true);
            if ($order && isset($order['check_in']) && $order['check_in'] === $today) {
                $todayCheckInIds[] = (int)$row['mail_id'];
            }
        }
    }

    // 最後同步時間
    $lastSyncFile = sys_get_temp_dir() . '/mail_last_sync.txt';
    $lastSync     = file_exists($lastSyncFile) ? (int)file_get_contents($lastSyncFile) : 0;

    // 是否有背景同步正在跑
    $lockFile  = sys_get_temp_dir() . '/mail_sync.lock';
    $isSyncing = file_exists($lockFile) && (time() - filemtime($lockFile)) < 120;

    $count = count($rows);

    echo json_encode([
        'success'          => true,
        'claims'           => $claims,          // mail_id => claimed_by
        'today_checkin_ids'=> $todayCheckInIds, // 今日入住的所有 mail_id
        'last_sync'        => $lastSync,
        'is_syncing'       => $isSyncing,
        'mail_count'       => $count,
    ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error'   => $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE);
}
