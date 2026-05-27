<?php
// test_mailbox_decode.php - 確認後請刪除
require_once __DIR__ . '/auth.php';
requireLogin();

$server   = getenv('MAIL_SERVER');
$username = getenv('MAIL_USERNAME');
$password = getenv('MAIL_PASSWORD');

$conn = imap_open($server, $username, $password);
if (!$conn) die('連線失敗: ' . imap_last_error());

$raw = imap_list($conn, $server, 'INBOX/%');
imap_close($conn);

echo '<pre style="font-size:13px;line-height:2;">';
echo "SERVER prefix: " . htmlspecialchars($server) . "\n\n";
foreach ($raw as $fullPath) {
    echo "完整路徑: " . htmlspecialchars($fullPath) . "\n";
    $relative = str_replace($server, '', $fullPath);
    echo "去掉prefix後: " . htmlspecialchars($relative) . "\n\n";
}
echo '</pre>';
