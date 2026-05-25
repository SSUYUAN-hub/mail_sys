<?php
// init_admin.php
// ⚠️  執行一次後請立即刪除此檔案！
// 用途：建立 users 資料表並初始化第一個管理者帳號

require_once __DIR__ . '/db.php';

// 防止重複執行（簡單 token 保護）
define('INIT_TOKEN', 'CHANGE_THIS_BEFORE_USE');
if (($_GET['token'] ?? '') !== INIT_TOKEN) {
    die('❌ 請在網址加上正確的 ?token=CHANGE_THIS_BEFORE_USE 才能執行此腳本。');
}

try {
    $pdo = getDB();

    // 建立 users 資料表
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS users (
            id         INT AUTO_INCREMENT PRIMARY KEY,
            username   VARCHAR(50)  NOT NULL UNIQUE,
            password   VARCHAR(255) NOT NULL,
            role       ENUM('admin','user') NOT NULL DEFAULT 'user',
            status     ENUM('pending','active','disabled') NOT NULL DEFAULT 'pending',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    // 預設管理者資訊（執行前請修改）
    $adminUsername = 'admin';
    $adminPassword = 'Admin@1234';  // ← 執行前請改掉

    // 檢查是否已存在
    $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
    $stmt->execute([$adminUsername]);
    if ($stmt->fetch()) {
        echo "⚠️  管理者帳號「{$adminUsername}」已存在，跳過建立。";
    } else {
        $hash = password_hash($adminPassword, PASSWORD_BCRYPT);
        $ins  = $pdo->prepare("
            INSERT INTO users (username, password, role, status)
            VALUES (?, ?, 'admin', 'active')
        ");
        $ins->execute([$adminUsername, $hash]);
        echo "✅ 資料表建立完成，管理者帳號已建立。<br>";
        echo "👤 帳號：{$adminUsername}<br>";
        echo "🔑 密碼：{$adminPassword}<br>";
        echo "<br><strong style='color:red'>⚠️  請立即刪除 init_admin.php，並登入後修改預設密碼！</strong>";
    }

} catch (Exception $e) {
    echo "❌ 錯誤：" . htmlspecialchars($e->getMessage());
}
