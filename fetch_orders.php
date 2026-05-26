<?php
// fetch_orders.php - AJAX 端點，回傳 JSON 訂單資料
require_once __DIR__ . '/auth.php';
requireLogin();
require_once __DIR__ . '/mail_fetcher.php';
require_once __DIR__ . '/order_parser.php';

header('Content-Type: application/json; charset=utf-8');

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
        $order['mail_id'] = $mail['id'];
        // 民國日期轉換
        $order['check_in_roc']  = OrderParser::toROCDate($order['check_in']);
        $order['check_out_roc'] = OrderParser::toROCDate($order['check_out']);
        $orders[] = $order;
    }

    // 依 OTA 訂單號分組
    $groups = [];
    foreach ($orders as $order) {
        $key = ($order['ota_number'] !== '無' && $order['ota_number'] !== '')
            ? $order['ota_number']
            : 'unique_' . $order['mail_id'];
        $groups[$key][] = $order;
    }

    // 每組按完整度排序
    foreach ($groups as &$group) {
        usort($group, fn($a, $b) => scoreOrder($b) - scoreOrder($a));
    }
    unset($group);

    echo json_encode([
        'success'      => true,
        'groups'       => array_values($groups),
        'total_orders' => count($orders),
        'total_groups' => count($groups),
        'updated_at'   => date('H:i:s'),
    ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    echo json_encode([
        'success' => false,
        'error'   => $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE);
}
