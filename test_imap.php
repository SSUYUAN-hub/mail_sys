<?php
require_once __DIR__ . '/auth.php';
requireLogin();

$server   = getenv('MAIL_SERVER');
$username = getenv('MAIL_USERNAME');
$password = getenv('MAIL_PASSWORD');

$imap = imap_open($server, $username, $password);

if (!$imap) {
    die('連線失敗: ' . imap_last_error());
}

// 取得所有資料夾
$folders = imap_list($imap, $server, '*');

echo '<pre>';
foreach ($folders as $folder) {
    echo $folder . "\n";
}
echo '</pre>';

imap_close($imap);