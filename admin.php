<?php
// admin.php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/db.php';
requireLogin();

$user    = currentUser();
$isAdmin = isAdmin();
$pdo     = getDB();
$msg     = '';
$msgType = '';

// ============================================================
// POST 動作處理
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // ---- 改自己密碼（管理者 + 一般使用者都可以）----
    if ($action === 'change_own_password') {
        $oldPw  = $_POST['old_password']  ?? '';
        $newPw  = $_POST['new_password']  ?? '';
        $newPw2 = $_POST['new_password2'] ?? '';

        $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
        $stmt->execute([$user['id']]);
        $row = $stmt->fetch();

        if (!password_verify($oldPw, $row['password'])) {
            $msg = '原密碼錯誤。'; $msgType = 'error';
        } elseif (mb_strlen($newPw) < 6) {
            $msg = '新密碼至少 6 個字元。'; $msgType = 'error';
        } elseif ($newPw !== $newPw2) {
            $msg = '兩次新密碼不一致。'; $msgType = 'error';
        } else {
            $hash = password_hash($newPw, PASSWORD_BCRYPT);
            $pdo->prepare("UPDATE users SET password = ? WHERE id = ?")->execute([$hash, $user['id']]);
            $msg = '密碼已更新。'; $msgType = 'success';
        }

    // ---- 以下僅管理者 ----
    } elseif ($isAdmin) {

        $targetId = (int)($_POST['target_id'] ?? 0);

        // 審核通過
        if ($action === 'approve') {
            $pdo->prepare("UPDATE users SET status = 'active' WHERE id = ? AND role != 'admin'")->execute([$targetId]);
            $msg = '帳號已審核通過。'; $msgType = 'success';

        // 停用
        } elseif ($action === 'disable') {
            if ($targetId === $user['id']) {
                $msg = '不能停用自己的帳號。'; $msgType = 'error';
            } else {
                $pdo->prepare("UPDATE users SET status = 'disabled' WHERE id = ? AND role != 'admin'")->execute([$targetId]);
                $msg = '帳號已停用。'; $msgType = 'success';
            }

        // 重新啟用
        } elseif ($action === 'enable') {
            $pdo->prepare("UPDATE users SET status = 'active' WHERE id = ?")->execute([$targetId]);
            $msg = '帳號已重新啟用。'; $msgType = 'success';

        // 刪除
        } elseif ($action === 'delete') {
            if ($targetId === $user['id']) {
                $msg = '不能刪除自己的帳號。'; $msgType = 'error';
            } else {
                $pdo->prepare("DELETE FROM users WHERE id = ? AND role != 'admin'")->execute([$targetId]);
                $msg = '帳號已刪除。'; $msgType = 'success';
            }

        // 管理者重設他人密碼
        } elseif ($action === 'reset_password') {
            $newPw  = $_POST['new_password']  ?? '';
            $newPw2 = $_POST['new_password2'] ?? '';
            if (mb_strlen($newPw) < 6) {
                $msg = '密碼至少 6 個字元。'; $msgType = 'error';
            } elseif ($newPw !== $newPw2) {
                $msg = '兩次密碼不一致。'; $msgType = 'error';
            } else {
                $hash = password_hash($newPw, PASSWORD_BCRYPT);
                $pdo->prepare("UPDATE users SET password = ? WHERE id = ?")->execute([$hash, $targetId]);
                $msg = '密碼已重設。'; $msgType = 'success';
            }

        // 修改使用者名稱
        } elseif ($action === 'rename') {
            $newName = trim($_POST['new_username'] ?? '');
            if (mb_strlen($newName) < 3 || mb_strlen($newName) > 50) {
                $msg = '帳號長度需為 3～50 字元。'; $msgType = 'error';
            } elseif (!preg_match('/^[a-zA-Z0-9_\-]+$/', $newName)) {
                $msg = '帳號只能包含英文、數字、底線、連字號。'; $msgType = 'error';
            } else {
                try {
                    $pdo->prepare("UPDATE users SET username = ? WHERE id = ?")->execute([$newName, $targetId]);
                    $msg = '帳號名稱已更新。'; $msgType = 'success';
                } catch (Exception $e) {
                    $msg = '此帳號名稱已被使用。'; $msgType = 'error';
                }
            }
        }
    }
}

// 讀取使用者清單（管理者）
$allUsers = [];
if ($isAdmin) {
    $allUsers = $pdo->query("SELECT id, username, role, status, created_at FROM users ORDER BY created_at DESC")->fetchAll();
}

// 讀取待審核數量
$pendingCount = 0;
if ($isAdmin) {
    $pendingCount = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE status = 'pending'")->fetchColumn();
}
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>羅東幸福商旅 - 帳號管理</title>
    <style>
        html { font-size: 125%; }
        *, *::before, *::after { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            background: #f8f9fa;
            font-family: "Microsoft JhengHei", sans-serif;
        }
        /* Topbar */
        .topbar {
            background: #642100;
            color: white;
            padding: 0.75rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 8px rgba(0,0,0,0.2);
        }
        .topbar-left { display: flex; align-items: center; gap: 1rem; }
        .topbar-title { font-size: 1rem; font-weight: bold; }
        .btn-back {
            background: rgba(255,255,255,0.15);
            color: white;
            border: 1px solid rgba(255,255,255,0.4);
            border-radius: 5px;
            padding: 0.3rem 0.75rem;
            font-size: 0.8rem;
            cursor: pointer;
            font-family: inherit;
            text-decoration: none;
            transition: background 0.2s;
        }
        .btn-back:hover { background: rgba(255,255,255,0.28); }
        .topbar-user { font-size: 0.85rem; opacity: 0.9; }
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
            text-decoration: none;
            transition: background 0.2s;
        }
        .btn-logout:hover { background: rgba(255,255,255,0.28); }

        /* 主容器 */
        .container {
            max-width: 1100px;
            margin: 2rem auto;
            padding: 0 1.5rem;
        }
        .section {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.07);
            padding: 1.75rem;
            margin-bottom: 1.5rem;
        }
        .section-title {
            font-size: 1.05rem;
            font-weight: bold;
            color: #642100;
            border-left: 5px solid #642100;
            padding-left: 0.75rem;
            margin: 0 0 1.25rem;
        }

        /* Alert */
        .alert {
            padding: 0.75rem 1rem;
            border-radius: 6px;
            font-size: 0.85rem;
            margin-bottom: 1.25rem;
        }
        .alert-error   { background: #fde8e8; color: #c0392b; border: 1px solid #f5b7b1; }
        .alert-success { background: #eafaf1; color: #1e8449; border: 1px solid #a9dfbf; }

        /* 表單 */
        .form-row {
            display: flex;
            gap: 0.75rem;
            flex-wrap: wrap;
            align-items: flex-end;
            margin-bottom: 0.75rem;
        }
        .form-group { display: flex; flex-direction: column; gap: 0.3rem; }
        label { font-size: 0.8rem; color: #555; font-weight: bold; }
        input[type="password"],
        input[type="text"] {
            padding: 0.55rem 0.75rem;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 0.875rem;
            font-family: inherit;
            outline: none;
            min-width: 200px;
        }
        input:focus { border-color: #642100; }
        .btn {
            padding: 0.55rem 1.1rem;
            border: none;
            border-radius: 6px;
            font-size: 0.85rem;
            font-weight: bold;
            font-family: inherit;
            cursor: pointer;
            transition: opacity 0.2s;
            white-space: nowrap;
        }
        .btn:hover { opacity: 0.85; }
        .btn-primary  { background: #642100; color: white; }
        .btn-success  { background: #27ae60; color: white; }
        .btn-warning  { background: #e67e22; color: white; }
        .btn-danger   { background: #c0392b; color: white; }
        .btn-secondary { background: #6c757d; color: white; }
        .btn-sm { padding: 0.3rem 0.65rem; font-size: 0.78rem; }

        /* 使用者表格 */
        table { width: 100%; border-collapse: collapse; }
        th, td {
            border: 1px solid #dee2e6;
            padding: 0.65rem 0.75rem;
            font-size: 0.85rem;
            text-align: left;
        }
        th {
            background: #f1f3f5;
            color: #495057;
            font-weight: bold;
        }
        tr:hover { background: #fdf6f0; }

        /* Status badge */
        .status-badge {
            display: inline-block;
            padding: 0.2rem 0.5rem;
            border-radius: 4px;
            font-size: 0.72rem;
            font-weight: bold;
        }
        .status-active   { background: #d5f5e3; color: #1e8449; }
        .status-pending  { background: #fef9e7; color: #b7950b; }
        .status-disabled { background: #f9ebea; color: #c0392b; }
        .role-badge {
            display: inline-block;
            padding: 0.2rem 0.5rem;
            border-radius: 4px;
            font-size: 0.72rem;
            font-weight: bold;
        }
        .role-admin { background: #642100; color: white; }
        .role-user  { background: #adb5bd; color: white; }

        /* 待審核提示 */
        .pending-banner {
            background: #fef9e7;
            border: 1px solid #f9ca24;
            border-radius: 8px;
            padding: 0.75rem 1rem;
            font-size: 0.85rem;
            color: #b7950b;
            margin-bottom: 1rem;
            font-weight: bold;
        }

        /* Modal */
        .modal-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.45);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }
        .modal-overlay.active { display: flex; }
        .modal-box {
            background: white;
            border-radius: 10px;
            padding: 1.75rem;
            width: 100%;
            max-width: 420px;
            box-shadow: 0 8px 32px rgba(0,0,0,0.18);
            position: relative;
        }
        .modal-title {
            font-size: 1rem;
            font-weight: bold;
            color: #642100;
            margin: 0 0 1.25rem;
            padding-bottom: 0.75rem;
            border-bottom: 2px solid #f0e0d6;
        }
        .modal-close {
            position: absolute;
            top: 1rem; right: 1.25rem;
            background: none; border: none;
            font-size: 1.3rem; cursor: pointer; color: #888;
        }
        .modal-close:hover { color: #333; }
        .modal-form-group { margin-bottom: 0.875rem; }
        .modal-form-group label { display: block; font-size: 0.82rem; color: #555; font-weight: bold; margin-bottom: 0.3rem; }
        .modal-form-group input {
            width: 100%;
            padding: 0.6rem 0.75rem;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 0.875rem;
            font-family: inherit;
            outline: none;
        }
        .modal-form-group input:focus { border-color: #642100; }
        .modal-actions { display: flex; gap: 0.75rem; justify-content: flex-end; margin-top: 1.25rem; }
    </style>
</head>
<body>

<div class="topbar">
    <div class="topbar-left">
        <a href="portal.php" class="btn-back">← 返回主選單</a>
        <span class="topbar-title">帳號管理</span>
    </div>
    <div style="display:flex;align-items:center;">
        <span class="topbar-user">👤 <?php echo htmlspecialchars($user['username']); ?></span>
        <a href="logout.php" class="btn-logout">登出</a>
    </div>
</div>

<div class="container">

    <?php if ($msg): ?>
    <div class="alert alert-<?php echo $msgType; ?>"><?php echo htmlspecialchars($msg); ?></div>
    <?php endif; ?>

    <!-- ===== 修改自己密碼（所有人） ===== -->
    <div class="section">
        <div class="section-title">🔑 修改我的密碼</div>
        <form method="POST">
            <input type="hidden" name="action" value="change_own_password">
            <div class="form-row">
                <div class="form-group">
                    <label>原密碼</label>
                    <input type="password" name="old_password" required autocomplete="current-password">
                </div>
                <div class="form-group">
                    <label>新密碼（至少 6 字元）</label>
                    <input type="password" name="new_password" required autocomplete="new-password">
                </div>
                <div class="form-group">
                    <label>確認新密碼</label>
                    <input type="password" name="new_password2" required autocomplete="new-password">
                </div>
                <button type="submit" class="btn btn-primary">更新密碼</button>
            </div>
        </form>
    </div>

    <?php if ($isAdmin): ?>

    <!-- ===== 待審核提示 ===== -->
    <?php if ($pendingCount > 0): ?>
    <div class="pending-banner">
        ⏳ 目前有 <b><?php echo $pendingCount; ?></b> 個帳號申請待審核，請在下方清單審核。
    </div>
    <?php endif; ?>

    <!-- ===== 使用者清單（管理者） ===== -->
    <div class="section">
        <div class="section-title">👥 使用者管理</div>
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>帳號</th>
                    <th>角色</th>
                    <th>狀態</th>
                    <th>建立時間</th>
                    <th>操作</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($allUsers as $u): ?>
            <tr>
                <td><?php echo $u['id']; ?></td>
                <td><?php echo htmlspecialchars($u['username']); ?></td>
                <td>
                    <span class="role-badge <?php echo $u['role'] === 'admin' ? 'role-admin' : 'role-user'; ?>">
                        <?php echo $u['role'] === 'admin' ? '管理者' : '一般'; ?>
                    </span>
                </td>
                <td>
                    <?php
                    $statusMap = ['active' => '啟用', 'pending' => '待審核', 'disabled' => '已停用'];
                    $statusClass = ['active' => 'status-active', 'pending' => 'status-pending', 'disabled' => 'status-disabled'];
                    $s = $u['status'];
                    ?>
                    <span class="status-badge <?php echo $statusClass[$s] ?? ''; ?>">
                        <?php echo $statusMap[$s] ?? $s; ?>
                    </span>
                </td>
                <td style="font-size:0.78rem;color:#888;"><?php echo $u['created_at']; ?></td>
                <td>
                    <?php if ($u['role'] !== 'admin'): ?>
                        <div style="display:flex;gap:0.4rem;flex-wrap:wrap;">
                            <!-- 審核 -->
                            <?php if ($u['status'] === 'pending'): ?>
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="action" value="approve">
                                <input type="hidden" name="target_id" value="<?php echo $u['id']; ?>">
                                <button class="btn btn-success btn-sm">✓ 審核通過</button>
                            </form>
                            <?php endif; ?>

                            <!-- 停用 / 啟用 -->
                            <?php if ($u['status'] === 'active'): ?>
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="action" value="disable">
                                <input type="hidden" name="target_id" value="<?php echo $u['id']; ?>">
                                <button class="btn btn-warning btn-sm">⏸ 停用</button>
                            </form>
                            <?php elseif ($u['status'] === 'disabled'): ?>
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="action" value="enable">
                                <input type="hidden" name="target_id" value="<?php echo $u['id']; ?>">
                                <button class="btn btn-success btn-sm">▶ 啟用</button>
                            </form>
                            <?php endif; ?>

                            <!-- 改帳號名稱 -->
                            <button class="btn btn-secondary btn-sm"
                                onclick="openRenameModal(<?php echo $u['id']; ?>, '<?php echo htmlspecialchars($u['username'], ENT_QUOTES); ?>')">
                                ✏️ 改帳號
                            </button>

                            <!-- 重設密碼 -->
                            <button class="btn btn-secondary btn-sm"
                                onclick="openResetModal(<?php echo $u['id']; ?>, '<?php echo htmlspecialchars($u['username'], ENT_QUOTES); ?>')">
                                🔑 重設密碼
                            </button>

                            <!-- 刪除 -->
                            <form method="POST" style="display:inline;"
                                onsubmit="return confirm('確定要刪除帳號「<?php echo htmlspecialchars($u['username'], ENT_QUOTES); ?>」？此動作無法復原。')">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="target_id" value="<?php echo $u['id']; ?>">
                                <button class="btn btn-danger btn-sm">🗑 刪除</button>
                            </form>
                        </div>
                    <?php else: ?>
                        <span style="color:#aaa;font-size:0.78rem;">（管理者帳號）</span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?php endif; ?>
</div>

<!-- Modal：改帳號名稱 -->
<div class="modal-overlay" id="renameModal" onclick="closeModalOutside(event,'renameModal')">
    <div class="modal-box">
        <button class="modal-close" onclick="closeModal('renameModal')">✕</button>
        <div class="modal-title">✏️ 修改帳號名稱</div>
        <form method="POST">
            <input type="hidden" name="action" value="rename">
            <input type="hidden" name="target_id" id="rename_target_id">
            <div class="modal-form-group">
                <label>目前帳號</label>
                <input type="text" id="rename_current" disabled>
            </div>
            <div class="modal-form-group">
                <label>新帳號名稱</label>
                <input type="text" name="new_username" id="rename_new" required
                    placeholder="3～50 字元，英數底線連字號">
            </div>
            <div class="modal-actions">
                <button type="button" class="btn btn-secondary" onclick="closeModal('renameModal')">取消</button>
                <button type="submit" class="btn btn-primary">確認修改</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal：重設密碼 -->
<div class="modal-overlay" id="resetModal" onclick="closeModalOutside(event,'resetModal')">
    <div class="modal-box">
        <button class="modal-close" onclick="closeModal('resetModal')">✕</button>
        <div class="modal-title">🔑 重設使用者密碼</div>
        <form method="POST">
            <input type="hidden" name="action" value="reset_password">
            <input type="hidden" name="target_id" id="reset_target_id">
            <div class="modal-form-group">
                <label>帳號</label>
                <input type="text" id="reset_username" disabled>
            </div>
            <div class="modal-form-group">
                <label>新密碼（至少 6 字元）</label>
                <input type="password" name="new_password" required autocomplete="new-password">
            </div>
            <div class="modal-form-group">
                <label>確認新密碼</label>
                <input type="password" name="new_password2" required autocomplete="new-password">
            </div>
            <div class="modal-actions">
                <button type="button" class="btn btn-secondary" onclick="closeModal('resetModal')">取消</button>
                <button type="submit" class="btn btn-primary">確認重設</button>
            </div>
        </form>
    </div>
</div>

<script>
function openRenameModal(id, username) {
    document.getElementById('rename_target_id').value = id;
    document.getElementById('rename_current').value   = username;
    document.getElementById('rename_new').value       = '';
    document.getElementById('renameModal').classList.add('active');
}
function openResetModal(id, username) {
    document.getElementById('reset_target_id').value = id;
    document.getElementById('reset_username').value  = username;
    document.getElementById('resetModal').classList.add('active');
}
function closeModal(id) {
    document.getElementById(id).classList.remove('active');
}
function closeModalOutside(e, id) {
    if (e.target === document.getElementById(id)) closeModal(id);
}
document.addEventListener('keydown', e => {
    if (e.key === 'Escape') {
        closeModal('renameModal');
        closeModal('resetModal');
    }
});
</script>
</body>
</html>
