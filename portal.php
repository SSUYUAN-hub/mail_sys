<?php
// portal.php
require_once __DIR__ . '/auth.php';
requireLogin();
$user = currentUser();
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>羅東幸福商旅 - 主選單</title>
    <style>
        html { font-size: 125%; }
        *, *::before, *::after { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            background: linear-gradient(135deg, #fff3ee 0%, #fde8d8 100%);
            font-family: "Microsoft JhengHei", sans-serif;
            display: flex;
            flex-direction: column;
        }
        /* 頂部列 */
        .topbar {
            background: #642100;
            color: white;
            padding: 0.75rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 8px rgba(0,0,0,0.2);
        }
        .topbar-title { font-size: 1rem; font-weight: bold; }
        .topbar-user  { font-size: 0.85rem; opacity: 0.9; }
        .btn-logout {
            background: rgba(255,255,255,0.15);
            color: white;
            border: 1px solid rgba(255,255,255,0.4);
            border-radius: 5px;
            padding: 0.35rem 0.875rem;
            font-size: 0.8rem;
            cursor: pointer;
            font-family: inherit;
            margin-left: 1rem;
            transition: background 0.2s;
            text-decoration: none;
        }
        .btn-logout:hover { background: rgba(255,255,255,0.28); }

        /* 主內容 */
        .main {
            flex: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 2rem;
        }
        .welcome {
            text-align: center;
            margin-bottom: 2.5rem;
        }
        .welcome h2 {
            color: #642100;
            font-size: 1.4rem;
            margin: 0 0 0.5rem;
        }
        .welcome p {
            color: #888;
            font-size: 0.9rem;
            margin: 0;
        }
        /* 選單卡片 */
        .menu-grid {
            display: flex;
            gap: 1.5rem;
            flex-wrap: wrap;
            justify-content: center;
        }
        .menu-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(100,33,0,0.1);
            padding: 2.5rem 2rem;
            text-align: center;
            width: 220px;
            cursor: pointer;
            text-decoration: none;
            color: inherit;
            transition: transform 0.18s, box-shadow 0.18s;
            border: 2px solid transparent;
        }
        .menu-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 28px rgba(100,33,0,0.18);
            border-color: #642100;
        }
        .menu-icon { font-size: 3rem; margin-bottom: 1rem; }
        .menu-label {
            font-size: 1rem;
            font-weight: bold;
            color: #642100;
            margin-bottom: 0.4rem;
        }
        .menu-desc { font-size: 0.78rem; color: #999; line-height: 1.5; }

        /* 角色 badge */
        .role-badge {
            display: inline-block;
            padding: 0.15rem 0.5rem;
            border-radius: 4px;
            font-size: 0.7rem;
            font-weight: bold;
            margin-left: 0.5rem;
            vertical-align: middle;
        }
        .role-admin { background: #642100; color: white; }
        .role-user  { background: #6c757d; color: white; }
    </style>
</head>
<body>
<div class="topbar">
    <div class="topbar-title">🏨 羅東幸福商旅 - 訂房核對平台</div>
    <div style="display:flex;align-items:center;">
        <span class="topbar-user">
            👤 <?php echo htmlspecialchars($user['username']); ?>
            <span class="role-badge <?php echo $user['role'] === 'admin' ? 'role-admin' : 'role-user'; ?>">
                <?php echo $user['role'] === 'admin' ? '管理者' : '一般使用者'; ?>
            </span>
        </span>
        <a href="logout.php" class="btn-logout">登出</a>
    </div>
</div>

<div class="main">
    <div class="welcome">
        <h2>歡迎回來，<?php echo htmlspecialchars($user['username']); ?>！</h2>
        <p>請選擇要使用的功能</p>
    </div>

    <div class="menu-grid">
        <!-- 讀取信件 -->
        <a href="index.php" class="menu-card">
            <div class="menu-icon">📬</div>
            <div class="menu-label">讀取信件</div>
            <div class="menu-desc">自動抓取並解析<br>OTA 訂房通知信件</div>
        </a>

        <!-- 帳號管理 -->
        <a href="admin.php" class="menu-card">
            <div class="menu-icon"><?php echo $user['role'] === 'admin' ? '👥' : '🔑'; ?></div>
            <div class="menu-label">帳號管理</div>
            <div class="menu-desc">
                <?php if ($user['role'] === 'admin'): ?>
                    審核帳號、管理所有<br>使用者帳號與權限
                <?php else: ?>
                    修改個人密碼
                <?php endif; ?>
            </div>
        </a>
    </div>
</div>
</body>
</html>
