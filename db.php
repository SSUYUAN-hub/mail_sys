<?php
// db.php - TiDB 資料庫連線（PDO + MySQL driver）

// 本機開發才載入 .env，Render 環境直接用系統環境變數
if (file_exists(__DIR__ . '/.env')) {
    require_once __DIR__ . '/vendor/autoload.php';
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
    $dotenv->load();
}

function getDB(): PDO {
    static $pdo = null;
    if ($pdo !== null) return $pdo;

    $host   = getenv('DB_HOST')   ?: ($_ENV['DB_HOST']   ?? '');
    $port   = getenv('DB_PORT')   ?: ($_ENV['DB_PORT']   ?? '4000');
    $dbname = getenv('DB_NAME')   ?: ($_ENV['DB_NAME']   ?? '');
    $user   = getenv('DB_USER')   ?: ($_ENV['DB_USER']   ?? '');
    $pass   = getenv('DB_PASS')   ?: ($_ENV['DB_PASS']   ?? '');

    $dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset=utf8mb4";

    $options = [
        PDO::ATTR_ERRMODE                      => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE           => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES             => false,
        PDO::MYSQL_ATTR_SSL_CA                 => '/etc/ssl/certs/ca-certificates.crt',
        PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT => false, // Render CA bundle 無法驗證 TiDB 憑證鏈，連線仍為加密
    ];

    $pdo = new PDO($dsn, $user, $pass, $options);
    return $pdo;
}
