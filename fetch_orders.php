<?php
// fetch_orders.php - AJAX 端點，支援 Session 快取（3 分鐘有效）
require_once __DIR__ . '/auth.php';
requireLogin();

header('Content-Type: application/json; charset=utf-8');

const CACHE_TTL = 180; // 快取秒數（3 分鐘）

// 是否強制重抓
$forceRefresh = ($_GET['force'] ?? '0') === '1';

// 快取有效則直接回傳
if (!$forceRefresh
    && isset($_SESSION['orders_cache'])
    && isset($_SESSION['orders_cache_time'])
    && (time() - $_SESSION['orders_cache_time']) < CACHE_TTL
) {
    $cached = $_SESSION['orders_cache'];
    $cached['from_cache'] = true;
    $cached['cache_age']  = time() - $_SESSION['orders_cache_time'];
    echo json_encode($cached, JSON_UNESCAPED_UNICODE);
    exit;
}

// 快取不存在或已過期，重新抓取
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
    $fetcher     = new MailFetcher();
    $unreadMails = $fetcher->fetchUnreadMails();

    $orders = [];
    foreach ($unreadMails as $mail) {
        $order = OrderParser::parse($mail['html_body'], $mail['subject']);
        if ($order['platform'] === '系統過濾信件') continue;
        $order['mail_id']       = $mail['id'];
        $order['check_in_roc']  = OrderParser::toROCDate($order['check_in']);
        $order['check_out_roc'] = OrderParser::toROCDate($order['check_out']);
        $orders[] = $order;
    }

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

    $result = [
        'success'      => true,
        'groups'       => array_values($groups),
        'total_orders' => count($orders),
        'total_groups' => count($groups),
        'fetched_ts'   => time(), // Unix timestamp，讓前端自己轉時區
        'from_cache'   => false,
        'cache_age'    => 0,
    ];

    // 存入 Session 快取
    $_SESSION['orders_cache']      = $result;
    $_SESSION['orders_cache_time'] = time();

    echo json_encode($result, JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    echo json_encode([
        'success' => false,
        'error'   => $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE);
}
