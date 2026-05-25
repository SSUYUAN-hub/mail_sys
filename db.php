<?php
// db.php - TiDB 資料庫連線（PDO + MySQL driver）
require_once __DIR__ . '/vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

function getDB(): PDO {
    static $pdo = null;
    if ($pdo !== null) return $pdo;

    $host    = $_ENV['DB_HOST'];
    $port    = $_ENV['DB_PORT'] ?? '4000';
    $dbname  = $_ENV['DB_NAME'];
    $user    = $_ENV['DB_USER'];
    $pass    = $_ENV['DB_PASS'];
    $sslCert = $_ENV['DB_SSL_CA'] ?? ''; // TiDB Cloud 需要 SSL

    $dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset=utf8mb4";

    $options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
    PDO::MYSQL_ATTR_SSL_CA       => '/etc/ssl/certs/ca-certificates.crt',
    PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT => true,
];

    // TiDB Cloud Serverless 需要 SSL 連線
    if (!empty($sslCert)) {
        $options[PDO::MYSQL_ATTR_SSL_CA]     = $sslCert;
        $options[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = true;
    }

    $pdo = new PDO($dsn, $user, $pass, $options);
    return $pdo;
}
