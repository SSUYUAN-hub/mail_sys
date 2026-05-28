<?php
// poll_claims.php - 輪詢認領狀態與同步進度
require_once __DIR__ . '/auth.php';
requireLogin();

header('Content-Type: application/json; charset=utf-8');

try {
    require_once __DIR__ . '/db.php';
    $pdo = getDB();

    // 回傳所有信件的認領狀態 + 最後同步時間
    $rows = $pdo->query(
        'SELECT mail_id, claimed_by, UNIX_TIMESTAMP(updated_at) as updated_ts FROM parsed_mails'
    )->fetchAll();

    $claims = [];
    foreach ($rows as $row) {
        $claims[$row['mail_id']] = $row['claimed_by'];
    }

    // 最後同步時間
    $lastSyncFile = sys_get_temp_dir() . '/mail_last_sync.txt';
    $lastSync     = file_exists($lastSyncFile) ? (int)file_get_contents($lastSyncFile) : 0;

    // 是否有背景同步正在跑
    $lockFile  = sys_get_temp_dir() . '/mail_sync.lock';
    $isSyncing = file_exists($lockFile) && (time() - filemtime($lockFile)) < 120;

    // DB 中的信件數量（用來判斷首次同步是否完成）
    $count = (int)$pdo->query('SELECT COUNT(*) FROM parsed_mails')->fetchColumn();

    echo json_encode([
        'success'    => true,
        'claims'     => $claims,     // mail_id => claimed_by (null 表示未認領)
        'last_sync'  => $lastSync,   // Unix timestamp
        'is_syncing' => $isSyncing,
        'mail_count' => $count,
    ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error'   => $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE);
}
