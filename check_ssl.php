<?php
// 確認系統 CA 檔案存在哪裡
$paths = [
    '/etc/ssl/certs/ca-certificates.crt',
    '/etc/ssl/cert.pem',
    '/etc/pki/tls/certs/ca-bundle.crt',
    '/usr/local/share/ca-certificates',
];
foreach ($paths as $p) {
    echo $p . ' → ' . (file_exists($p) ? '✅ 存在' : '❌ 不存在') . '<br>';
}

// 確認環境變數有沒有吃到
echo '<hr>';
echo 'DB_HOST: ' . (getenv('DB_HOST') ?: '❌ 空的') . '<br>';
echo 'DB_USER: ' . (getenv('DB_USER') ?: '❌ 空的') . '<br>';
echo 'DB_NAME: ' . (getenv('DB_NAME') ?: '❌ 空的') . '<br>';