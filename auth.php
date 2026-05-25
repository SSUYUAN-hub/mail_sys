<?php
// auth.php - Session 驗證，每個需要登入的頁面 require 此檔

if (session_status() === PHP_SESSION_NONE) {
    session_start([
        'cookie_lifetime' => 0,          // 關閉瀏覽器即失效
        'cookie_httponly' => true,        // 防 XSS 存取 cookie
        'cookie_samesite' => 'Strict',    // 防 CSRF
    ]);
}

function requireLogin(): void {
    if (empty($_SESSION['user_id'])) {
        header('Location: login.php');
        exit;
    }
}

function requireAdmin(): void {
    requireLogin();
    if ($_SESSION['role'] !== 'admin') {
        header('Location: portal.php');
        exit;
    }
}

function currentUser(): array {
    return [
        'id'       => $_SESSION['user_id']  ?? null,
        'username' => $_SESSION['username'] ?? '',
        'role'     => $_SESSION['role']     ?? 'user',
    ];
}

function isAdmin(): bool {
    return ($_SESSION['role'] ?? '') === 'admin';
}
