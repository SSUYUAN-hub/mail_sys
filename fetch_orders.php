<?php
// fetch_orders.php - 從 DB 讀取已解析的訂單，並觸發背景同步
require_once __DIR__ . '/auth.php';
requireLogin();

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/db.php';

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

    // 是否強制重抓（強制時清空 DB 讓背景重新全量解析）
    $forceRefresh = ($_GET['force'] ?? '0') === '1';
    if ($forceRefresh) {
        $pdo->exec('DELETE FROM parsed_mails');
        @unlink(sys_get_temp_dir() . '/mail_last_sync.txt');
        @unlink(sys_get_temp_dir() . '/mail_sync.lock');
    }

    // 觸發背景同步（非阻塞）
    $lockFile  = sys_get_temp_dir() . '/mail_sync.lock';
    $isSyncing = file_exists($lockFile) && (time() - filemtime($lockFile)) < 120;

    if (!$isSyncing) {
        // 用 curl 非阻塞觸發（timeout=1ms 確保不等待）
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $syncUrl = $scheme . '://' . $host . '/background_sync.php';

        $ch = curl_init($syncUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT_MS     => 200,   // 只等 200ms 連線，之後不管
            CURLOPT_NOSIGNAL       => 1,
            CURLOPT_COOKIE         => 'PHPSESSID=' . session_id(), // 傳遞 session
        ]);
        curl_exec($ch);
        curl_close($ch);
    }

    // 從 DB 讀取已解析結果
    $rows = $pdo->query(
        'SELECT mail_id, parsed_json, claimed_by, UNIX_TIMESTAMP(fetched_at) as fetched_ts
         FROM parsed_mails
         ORDER BY mail_id DESC'
    )->fetchAll();

    $orders = [];
    foreach ($rows as $row) {
        $order = json_decode($row['parsed_json'], true);
        if (!$order) continue;
        $order['mail_id']    = $row['mail_id'];
        $order['claimed_by'] = $row['claimed_by']; // null 或 keyword 字串
        $orders[] = $order;
    }

    // 依 OTA 編號分組
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

    // 最後同步時間
    $lastSyncFile = sys_get_temp_dir() . '/mail_last_sync.txt';
    $lastSync     = file_exists($lastSyncFile) ? (int)file_get_contents($lastSyncFile) : 0;

    $result = [
        'success'      => true,
        'groups'       => array_values($groups),
        'total_orders' => count($orders),
        'total_groups' => count($groups),
        'fetched_ts'   => $lastSync ?: time(),
        'is_syncing'   => $isSyncing || !$lastSync, // 首次同步中
        'from_cache'   => false,
        'cache_age'    => 0,
    ];

    echo json_encode($result, JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    echo json_encode([
        'success' => false,
        'error'   => $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE);
}
