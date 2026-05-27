<?php
// test_mailbox_decode.php - 確認解碼結果後請刪除此檔
require_once __DIR__ . '/auth.php';
requireLogin();

function decodeModifiedUtf7(string $str): string {
    return preg_replace_callback(
        '/&([^-]*)-/',
        function ($matches) {
            if ($matches[1] === '') return '&';
            $base64  = str_replace(',', '/', $matches[1]);
            $decoded = base64_decode($base64);
            return mb_convert_encoding($decoded, 'UTF-8', 'UTF-16BE');
        },
        $str
    );
}

$server   = getenv('MAIL_SERVER');
$username = getenv('MAIL_USERNAME');
$password = getenv('MAIL_PASSWORD');

$conn = imap_open($server, $username, $password);
if (!$conn) die('連線失敗: ' . imap_last_error());

$raw = imap_list($conn, $server, 'INBOX/%');
imap_close($conn);

echo '<pre style="font-size:14px;line-height:2;">';
echo "=== 原始 IMAP 名稱 → 解碼結果 ===\n\n";
foreach ($raw as $fullPath) {
    $relative  = str_replace($server, '', $fullPath);
    $imapName  = substr($relative, strlen('INBOX/'));
    $decoded   = decodeModifiedUtf7($imapName);
    echo "原始：{$imapName}\n解碼：{$decoded}\n\n";
}
echo '</pre>';
