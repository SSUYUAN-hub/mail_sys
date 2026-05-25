<?php
// test_db.php（測試完刪掉）
$host = 'gateway01.ap-northeast-1.prod.aws.tidbcloud.com';
$port = '4000';
$user = '3XzxHL7gho1h9eq.root';
$pass = 'EJACYguBkFnlD4Ex';
$db   = 'test';

// 先試不驗證憑證
$pdo = new PDO(
    "mysql:host={$host};port={$port};dbname={$db};charset=utf8mb4",
    $user, $pass,
    [
        PDO::MYSQL_ATTR_SSL_CA => '/etc/ssl/certs/ca-certificates.crt',
        PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT => false,
    ]
);
echo "連線成功！";