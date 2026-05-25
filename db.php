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

    $host   = $_ENV['DB_HOST']   ?? getenv('DB_HOST');
    $port   = $_ENV['DB_PORT']   ?? getenv('DB_PORT')   ?: '4000';
    $dbname = $_ENV['DB_NAME']   ?? getenv('DB_NAME');
    $user   = $_ENV['DB_USER']   ?? getenv('DB_USER');
    $pass   = $_ENV['DB_PASS']   ?? getenv('DB_PASS');

    $dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset=utf8mb4";

    $options = [
        PDO::ATTR_ERRMODE                    => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE         => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES           => false,
        // Render (Ubuntu) 內建系統 CA，直接指定固定路徑
        PDO::MYSQL_ATTR_SSL_CA               => '/etc/ssl/certs/ca-certificates.crt',
        PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT => true,
    ];

    $pdo = new PDO($dsn, $user, $pass, $options);
    return $pdo;
}
