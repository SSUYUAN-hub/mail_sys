<?php
// login.php
require_once __DIR__ . '/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start(['cookie_lifetime' => 0, 'cookie_httponly' => true, 'cookie_samesite' => 'Strict']);
}

// 已登入直接跳入口
if (!empty($_SESSION['user_id'])) {
    header('Location: portal.php');
    exit;
}

$error   = '';
$success = '';
$mode    = $_POST['mode'] ?? 'login'; // login | register

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($mode === 'register') {
        // ---- 註冊 ----
        $password2 = $_POST['password2'] ?? '';

        if (empty($username) || empty($password) || empty($password2)) {
            $error = '請填寫所有欄位。';
        } elseif (mb_strlen($username) < 3 || mb_strlen($username) > 50) {
            $error = '帳號長度需為 3～50 字元。';
        } elseif (!preg_match('/^[a-zA-Z0-9_\-]+$/', $username)) {
            $error = '帳號只能包含英文、數字、底線、連字號。';
        } elseif (mb_strlen($password) < 6) {
            $error = '密碼至少 6 個字元。';
        } elseif ($password !== $password2) {
            $error = '兩次密碼不一致。';
        } else {
            try {
                $pdo  = getDB();
                $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
                $stmt->execute([$username]);
                if ($stmt->fetch()) {
                    $error = '此帳號已被使用，請換一個。';
                } else {
                    $hash = password_hash($password, PASSWORD_BCRYPT);
                    $ins  = $pdo->prepare("INSERT INTO users (username, password, role, status) VALUES (?, ?, 'user', 'pending')");
                    $ins->execute([$username, $hash]);
                    $success = '註冊成功！請等候管理者審核後即可登入。';
                    $mode    = 'login';
                }
            } catch (Exception $e) {
                $error = '系統錯誤，請稍後再試。';
            }
        }

    } else {
        // ---- 登入 ----
        if (empty($username) || empty($password)) {
            $error = '請輸入帳號與密碼。';
        } else {
            try {
                $pdo  = getDB();
                $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
                $stmt->execute([$username]);
                $user = $stmt->fetch();

                if (!$user || !password_verify($password, $user['password'])) {
                    $error = '帳號或密碼錯誤。';
                } elseif ($user['status'] === 'pending') {
                    $error = '帳號尚未審核，請等候管理者開通。';
                } elseif ($user['status'] === 'disabled') {
                    $error = '此帳號已被停用，請聯繫管理者。';
                } else {
                    // 登入成功
                    session_regenerate_id(true);
                    $_SESSION['user_id']  = $user['id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['role']     = $user['role'];
                    header('Location: portal.php');
                    exit;
                }
            } catch (Exception $e) {
                $error = '系統錯誤，請稍後再試。';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>羅東幸福商旅 - 登入</title>
    <style>
        html { font-size: 125%; }
        *, *::before, *::after { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #fff3ee 0%, #fde8d8 100%);
            font-family: "Microsoft JhengHei", sans-serif;
        }
        .card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 8px 32px rgba(100,33,0,0.12);
            padding: 2.5rem 2rem;
            width: 100%;
            max-width: 420px;
        }
        .logo {
            text-align: center;
            margin-bottom: 1.5rem;
        }
        .logo h1 {
            color: #642100;
            font-size: 1.3rem;
            margin: 0.5rem 0 0;
        }
        .logo p {
            color: #999;
            font-size: 0.8rem;
            margin: 0.25rem 0 0;
        }
        .tabs {
            display: flex;
            border-bottom: 2px solid #f0e0d6;
            margin-bottom: 1.5rem;
        }
        .tab {
            flex: 1;
            text-align: center;
            padding: 0.6rem;
            cursor: pointer;
            font-size: 0.9rem;
            color: #999;
            border: none;
            background: none;
            font-family: inherit;
            border-bottom: 3px solid transparent;
            margin-bottom: -2px;
            transition: all 0.2s;
        }
        .tab.active {
            color: #642100;
            font-weight: bold;
            border-bottom-color: #642100;
        }
        .form-group {
            margin-bottom: 1rem;
        }
        label {
            display: block;
            font-size: 0.85rem;
            color: #555;
            margin-bottom: 0.35rem;
            font-weight: bold;
        }
        input[type="text"],
        input[type="password"] {
            width: 100%;
            padding: 0.65rem 0.875rem;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 0.9rem;
            font-family: inherit;
            transition: border-color 0.2s;
            outline: none;
        }
        input:focus { border-color: #642100; }
        .btn-submit {
            width: 100%;
            padding: 0.75rem;
            background: #642100;
            color: white;
            border: none;
            border-radius: 6px;
            font-size: 1rem;
            font-weight: bold;
            font-family: inherit;
            cursor: pointer;
            margin-top: 0.5rem;
            transition: background 0.2s;
        }
        .btn-submit:hover { background: #8a2d00; }
        .alert {
            padding: 0.75rem 1rem;
            border-radius: 6px;
            font-size: 0.85rem;
            margin-bottom: 1rem;
        }
        .alert-error   { background: #fde8e8; color: #c0392b; border: 1px solid #f5b7b1; }
        .alert-success { background: #eafaf1; color: #1e8449; border: 1px solid #a9dfbf; }
        .hint {
            font-size: 0.75rem;
            color: #aaa;
            margin-top: 0.25rem;
        }
    </style>
</head>
<body>
<div class="card">
    <div class="logo">
        <div style="font-size:2.5rem;">🏨</div>
        <h1>羅東幸福商旅</h1>
        <p>訂房信件自動核對平台</p>
    </div>

    <!-- Tab 切換 -->
    <div class="tabs">
        <button class="tab <?php echo $mode === 'login' ? 'active' : ''; ?>"
            onclick="switchMode('login')">登入</button>
        <button class="tab <?php echo $mode === 'register' ? 'active' : ''; ?>"
            onclick="switchMode('register')">申請帳號</button>
    </div>

    <?php if ($error):   ?><div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
    <?php if ($success): ?><div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div><?php endif; ?>

    <!-- 登入表單 -->
    <form method="POST" id="form-login" style="<?php echo $mode === 'register' ? 'display:none;' : ''; ?>">
        <input type="hidden" name="mode" value="login">
        <div class="form-group">
            <label>帳號</label>
            <input type="text" name="username" autocomplete="username" required
                value="<?php echo $mode === 'login' ? htmlspecialchars($_POST['username'] ?? '') : ''; ?>">
        </div>
        <div class="form-group">
            <label>密碼</label>
            <input type="password" name="password" autocomplete="current-password" required>
        </div>
        <button type="submit" class="btn-submit">登入</button>
    </form>

    <!-- 註冊表單 -->
    <form method="POST" id="form-register" style="<?php echo $mode === 'login' ? 'display:none;' : ''; ?>">
        <input type="hidden" name="mode" value="register">
        <div class="form-group">
            <label>帳號</label>
            <input type="text" name="username" autocomplete="username" required
                value="<?php echo $mode === 'register' ? htmlspecialchars($_POST['username'] ?? '') : ''; ?>">
            <div class="hint">3～50 字元，可用英文、數字、底線、連字號</div>
        </div>
        <div class="form-group">
            <label>密碼</label>
            <input type="password" name="password" autocomplete="new-password" required>
            <div class="hint">至少 6 個字元</div>
        </div>
        <div class="form-group">
            <label>確認密碼</label>
            <input type="password" name="password2" autocomplete="new-password" required>
        </div>
        <button type="submit" class="btn-submit">送出申請</button>
    </form>
</div>

<script>
function switchMode(mode) {
    document.getElementById('form-login').style.display    = mode === 'login'    ? '' : 'none';
    document.getElementById('form-register').style.display = mode === 'register' ? '' : 'none';
    document.querySelectorAll('.tab').forEach((t, i) => {
        t.classList.toggle('active', (i === 0 && mode === 'login') || (i === 1 && mode === 'register'));
    });
}
</script>
</body>
</html>
