<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/db.php';
requireLogin();
$user = currentUser();

// 取得目前使用者的 mailbox_imap，解碼為顯示名稱作為認領標籤比對用
$pdo = getDB();
$stmt = $pdo->prepare('SELECT mailbox_imap FROM users WHERE id = ?');
$stmt->execute([$user['id']]);
$myMailbox = $stmt->fetchColumn() ?: '';

// 解碼：只取最後一段（去掉 INBOX/ 前綴），再解碼 Modified UTF-7
// 例：INBOX/&YB2QYA- → 思遠；CT → CT
// DB 的 claimed_by 存的就是此顯示名稱，前端用它判斷「是否是我的認領」
$_mbSeg = end(preg_split('/[\/.]/', $myMailbox) ?: ['']);
$myDisplayName = preg_replace_callback('/&([^-]*)-/', function ($m) {
    if ($m[1] === '') return '&';
    $b = str_replace(',', '/', $m[1]);
    $d = base64_decode($b);
    return mb_convert_encoding($d, 'UTF-8', 'UTF-16BE');
}, $_mbSeg) ?: $user['username'];
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>羅東幸福商旅 - 自動訂房核對系統</title>
    <style>
        html { font-size: 125%; }
        *, *::before, *::after { box-sizing: border-box; }
        body {
            font-family: "Microsoft JhengHei", sans-serif;
            background-color: #f8f9fa;
            margin: 0; padding: 0;
        }
        .topbar {
            background: #642100;
            color: white;
            padding: 0.75rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 8px rgba(0,0,0,0.2);
            position: sticky;
            top: 0;
            z-index: 100;
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
            white-space: nowrap;
        }
        .btn-back:hover { background: rgba(255,255,255,0.28); }
        .topbar-right { display: flex; align-items: center; gap: 0.75rem; }
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
            text-decoration: none;
            transition: background 0.2s;
        }
        .btn-logout:hover { background: rgba(255,255,255,0.28); }

        .container {
            max-width: 1600px;
            margin: 1.5rem auto;
            background: white;
            padding: 1.5rem;
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 3px solid #642100;
            padding-bottom: 1rem;
            margin-bottom: 1.25rem;
            flex-wrap: wrap;
            gap: 0.75rem;
        }
        .page-header h1 { color: #642100; margin: 0; font-size: 1.3rem; }
        .header-right { display: flex; align-items: center; gap: 0.75rem; flex-wrap: wrap; }

        .status-bar { display: flex; align-items: center; gap: 0.5rem; font-size: 0.82rem; color: #666; }
        .status-dot {
            width: 10px; height: 10px;
            border-radius: 50%;
            background: #adb5bd;
            flex-shrink: 0;
            transition: background 0.3s;
        }
        .status-dot.loading { background: #f39c12; animation: pulse 1s infinite; }
        .status-dot.success { background: #27ae60; }
        .status-dot.error   { background: #c0392b; }
        @keyframes pulse { 0%,100%{opacity:1} 50%{opacity:0.3} }

        .cache-badge {
            display: inline-block;
            background: #e8f5e9;
            color: #2e7d32;
            border: 1px solid #a5d6a7;
            border-radius: 4px;
            padding: 0.15rem 0.5rem;
            font-size: 0.72rem;
            font-weight: bold;
        }

        .btn-refresh {
            background: #27ae60;
            color: white;
            border: none;
            padding: 0.55rem 1.1rem;
            font-size: 0.9rem;
            font-weight: bold;
            border-radius: 5px;
            cursor: pointer;
            font-family: inherit;
            transition: background 0.2s, opacity 0.2s;
            white-space: nowrap;
        }
        .btn-refresh:hover:not(:disabled) { background: #219653; }
        .btn-refresh:disabled { opacity: 0.6; cursor: not-allowed; }

        .countdown { font-size: 0.78rem; color: #999; white-space: nowrap; }
        .count-text { font-size: 0.9rem; color: #666; margin-top: 0.2rem; }

        .loading-overlay {
            text-align: center;
            padding: 4rem 2rem;
            color: #888;
        }
        .spinner {
            display: inline-block;
            width: 2.5rem; height: 2.5rem;
            border: 4px solid #f0e0d6;
            border-top-color: #642100;
            border-radius: 50%;
            animation: spin 0.9s linear infinite;
            margin-bottom: 1rem;
        }
        @keyframes spin { to { transform: rotate(360deg); } }

        .error-box {
            background: #fde8e8;
            border: 1px solid #f5b7b1;
            border-radius: 6px;
            padding: 1rem 1.25rem;
            color: #c0392b;
            font-size: 0.875rem;
            margin: 1rem 0;
        }

        table { width: 100%; border-collapse: collapse; margin-top: 0.625rem; }
        th, td { border: 1px solid #dee2e6; padding: 0.625rem; text-align: left; font-size: 0.875rem; }
        th {
            background: #f1f3f5;
            color: #495057;
            font-weight: bold;
            position: sticky;
            top: 56px;
            z-index: 10;
            box-shadow: 0 2px 4px rgba(0,0,0,0.08);
        }
        tr.main-row:hover { background: #fdf6f0; }
        tr.modified-row { background: #fffde7 !important; border-left: 4px solid #f39c12; }
        tr.modified-row:hover { background: #fff9c4 !important; }

        .badge { padding: 0.25rem 0.5rem; border-radius: 4px; font-size: 0.75rem; font-weight: bold; color: white; white-space: nowrap; }
        .bg-booking   { background: #003580; }
        .bg-agoda     { background: #e12d6e; }
        .bg-trip      { background: #ff9900; }
        .bg-airbnb    { background: #FF385C; }
        .bg-asiayo    { background: #97af15; }
        .bg-expedia   { background: #00A8E0; }
        .bg-cancelled { background: #c0392b; }
        .bg-unknown   { background: #6c757d; }

        .btn-archive {
            background: #e8f5e9;
            border: 1px solid #27ae60;
            border-radius: 4px;
            padding: 0.25rem 0.5rem;
            font-size: 0.75rem;
            cursor: pointer;
            color: #1e8449;
            white-space: nowrap;
            font-family: inherit;
            transition: background 0.2s, opacity 0.2s;
        }
        .btn-archive:hover:not(:disabled) { background: #c8e6c9; }
        .btn-archive:disabled { opacity: 0.5; cursor: not-allowed; }
        .btn-remark {            background: #fff3cd;
            border: 1px solid #ffc107;
            border-radius: 4px;
            padding: 0.25rem 0.625rem;
            font-size: 0.75rem;
            cursor: pointer;
            color: #856404;
            white-space: nowrap;
            font-family: inherit;
        }
        .btn-remark:hover { background: #ffe69c; }

        .collapse-row { background: #f8f9ff; }
        .collapse-row td { padding: 0.375rem 0.625rem; font-size: 0.8125rem; color: #555; }
        .btn-expand {
            background: none;
            border: 1px solid #adb5bd;
            border-radius: 4px;
            padding: 0.125rem 0.5rem;
            font-size: 0.6875rem;
            cursor: pointer;
            color: #495057;
            font-family: inherit;
        }
        .btn-expand:hover { background: #e9ecef; }
        .remark-short { color: #c0392b; font-size: 0.8125rem; }

        .modal-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }
        .modal-overlay.active { display: flex; }
        .modal-box {
            background: white;
            border-radius: 8px;
            padding: 1.5rem;
            max-width: 560px;
            width: 90%;
            max-height: 80vh;
            overflow-y: auto;
            box-shadow: 0 8px 32px rgba(0,0,0,0.2);
            position: relative;
        }
        .modal-title { font-size: 1rem; font-weight: bold; color: #642100; margin-bottom: 1rem; padding-bottom: 0.625rem; border-bottom: 2px solid #f0e0d6; }
        .modal-content { font-size: 0.875rem; line-height: 1.8; color: #333; white-space: pre-wrap; word-break: break-word; }
        .modal-close { position: absolute; top: 0.75rem; right: 1rem; background: none; border: none; font-size: 1.375rem; cursor: pointer; color: #888; }
        .modal-close:hover { color: #333; }

        /* 認領標籤 */
        .claim-cell { text-align: center; min-width: 80px; }
        .btn-claim {
            background: #f0f4ff;
            border: 1px solid #aab8ff;
            border-radius: 4px;
            padding: 0.25rem 0.6rem;
            font-size: 0.75rem;
            cursor: pointer;
            color: #3451b2;
            font-family: inherit;
            transition: background 0.2s;
            white-space: nowrap;
        }
        .btn-claim:hover { background: #dce4ff; }
        .btn-claim:disabled { opacity: 0.5; cursor: not-allowed; }
        .claim-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
            background: #fff3cd;
            border: 1px solid #ffc107;
            border-radius: 4px;
            padding: 0.2rem 0.5rem;
            font-size: 0.75rem;
            color: #856404;
            font-weight: bold;
            white-space: nowrap;
        }
        .claim-badge.mine {
            background: #d1ecf1;
            border-color: #17a2b8;
            color: #0c5460;
            cursor: pointer;
        }
        .claim-badge.mine:hover { background: #b8daff; }
        .syncing-bar {
            background: #fff3cd;
            border: 1px solid #ffc107;
            border-radius: 6px;
            padding: 0.5rem 1rem;
            font-size: 0.82rem;
            color: #856404;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
    </style>
</head>
<body>

<div class="topbar">
    <div class="topbar-left">
        <a href="portal.php" class="btn-back">← 返回主選單</a>
        <span class="topbar-title">🏨 羅東幸福商旅 - 訂房核對平台</span>
    </div>
    <div class="topbar-right">
        <span class="topbar-user">👤 <?php echo htmlspecialchars($user['username']); ?></span>
        <a href="logout.php" class="btn-logout">登出</a>
    </div>
</div>

<script>
// MY_KEYWORD = 當前使用者的信件匣顯示名稱（如「思遠」）
// DB 的 claimed_by 存的就是顯示名稱，前端用此做「是否是我的認領」判斷與顯示
const MY_KEYWORD = <?php echo json_encode($myDisplayName); ?>;
const MY_DISPLAY  = <?php echo json_encode($myDisplayName); ?>;
</script>
<div class="container">
    <div class="page-header">
        <div>
            <h1>📬 訂房信件自動核對</h1>
            <div class="count-text" id="countText">正在載入...</div>
        </div>
        <div class="header-right">
            <div class="status-bar">
                <div class="status-dot loading" id="statusDot"></div>
                <span id="statusText">連線中...</span>
                <span id="cacheBadge"></span>
            </div>
            <span class="countdown" id="countdown"></span>
            <button class="btn-refresh" id="btnRefresh" onclick="fetchOrders(true)">
                🔄 立即同步
            </button>
        </div>
    </div>

    <div id="mainContent">
        <div class="loading-overlay">
            <div class="spinner"></div>
            <div>正在載入訂單資料...</div>
            <div style="font-size:0.8rem;color:#aaa;margin-top:0.5rem;">首次需連接信箱，約需 10～30 秒</div>
        </div>
    </div>
</div>

<div class="modal-overlay" id="remarkModal" onclick="closeModalOutside(event)">
    <div class="modal-box">
        <button class="modal-close" onclick="closeModal()">✕</button>
        <div class="modal-title">📋 客房備註 / 特殊需求</div>
        <div class="modal-content" id="modalContent"></div>
    </div>
</div>

<script>
let remarkStore    = {};
let countdownTimer = null;
const CACHE_TTL    = 180; // 秒，與後端一致

function getBadgeClass(p) {
    if (p.includes('AsiaYo'))  return 'bg-asiayo';
    if (p.includes('Expedia')) return 'bg-expedia';
    if (p.includes('Airbnb'))  return 'bg-airbnb';
    if (p.includes('Booking')) return 'bg-booking';
    if (p.includes('Agoda'))   return 'bg-agoda';
    if (p.includes('Trip'))    return 'bg-trip';
    if (p.includes('取消'))    return 'bg-cancelled';
    return 'bg-unknown';
}

// 民國年轉換（前端用）
function toROCDate(dateStr) {
    if (!dateStr || dateStr === '無') return dateStr;
    const m = dateStr.match(/^(\d{4})-(\d{2})-(\d{2})$/);
    if (!m) return dateStr;
    return `${parseInt(m[1])-1911}年${parseInt(m[2])}月${parseInt(m[3])}日`;
}

function escHtml(s) {
    if (s === null || s === undefined) return '';
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#39;');
}

function buildMainRow(o, groupId, extraCount) {
    const badge = getBadgeClass(o.platform);
    const isTrip = o.platform.includes('Trip');
    const remark = o.remark || '無';
    const hasLong = isTrip && remark.length > 30;
    if (hasLong) remarkStore[o.mail_id] = remark;

    const expandBtn = extraCount > 0
        ? `<br><button class="btn-expand" onclick="toggleGroup('${groupId}',this)">+${extraCount} 封 ▼</button>`
        : '';
    const extraBed = (o.extra_bed && o.extra_bed !== '無')
        ? `<span style="color:#e67e22;font-weight:bold;">🛏 ${escHtml(o.extra_bed)}</span>`
        : `<span style="color:#aaa;">—</span>`;
    const remarkCell = hasLong
        ? `<button class="btn-remark" onclick="openModal(${o.mail_id})">📋 查看備註</button>`
        : `<div class="remark-short">${escHtml(remark)}</div>`;

    // 修改通知：日期欄顯示原始→修改後
    const isModified = o.is_modified === true;
    const modFields  = o.modified_fields || [];

    function dateCell(checkInRoc, checkOutRoc, origInRoc, origOutRoc, modFields) {
        if (!modFields.includes('check_in') && !modFields.includes('check_out')) {
            return `<span style="color:#087abc;font-weight:bold;">${escHtml(checkInRoc)}</span><br>
                    <span style="color:#888;font-size:0.75rem;">至</span>&nbsp;${escHtml(checkOutRoc)}`;
        }
        const inPart  = modFields.includes('check_in')
            ? `<span style="color:#888;text-decoration:line-through;font-size:0.75rem;">${escHtml(origInRoc)}</span><br>
               <span style="color:#e67e22;font-weight:bold;">→ ${escHtml(checkInRoc)}</span>`
            : `<span style="color:#087abc;font-weight:bold;">${escHtml(checkInRoc)}</span>`;
        const outPart = modFields.includes('check_out')
            ? `<span style="color:#888;text-decoration:line-through;font-size:0.75rem;">${escHtml(origOutRoc)}</span><br>
               <span style="color:#e67e22;font-weight:bold;">→ ${escHtml(checkOutRoc)}</span>`
            : `${escHtml(checkOutRoc)}`;
        return inPart + '<br><span style="color:#888;font-size:0.75rem;">至</span>&nbsp;' + outPart;
    }

    const origInRoc  = o.orig_check_in  && o.orig_check_in  !== '無' ? toROCDate(o.orig_check_in)  : '';
    const origOutRoc = o.orig_check_out && o.orig_check_out !== '無' ? toROCDate(o.orig_check_out) : '';

    const amountCell = modFields.includes('amount')
        ? `<span style="color:#888;text-decoration:line-through;font-size:0.75rem;">TWD ${Number(o.orig_amount||0).toLocaleString()}</span><br>
           <b style="color:#e67e22;">→ TWD ${Number(o.amount).toLocaleString()}</b>`
        : `<b style="color:#27ae60;">TWD ${Number(o.amount).toLocaleString()}</b>`;

    const roomType = (o.room_type && o.room_type !== '無') ? escHtml(o.room_type) : '<span style="color:#aaa;">—</span>';

    // 認領欄
    const claimedBy = o.claimed_by || null;
    let claimCell = '';
    if (claimedBy) {
        const isMine = claimedBy === MY_KEYWORD;
        // 解碼顯示名稱
        const displayName = isMine ? MY_DISPLAY : claimedBy;
        claimCell = isMine
            ? `<span class="claim-badge mine" title="點擊取消認領" onclick="toggleClaim(${o.mail_id}, 'unclaim', this)">🔖 ${escHtml(displayName)} ✕</span>`
            : `<span class="claim-badge">🔖 ${escHtml(displayName)}</span>`;
    } else {
        claimCell = `<button class="btn-claim" onclick="toggleClaim(${o.mail_id}, 'claim', this)">☐ 認領</button>`;
    }

    return `
    <tr class="main-row${isModified ? ' modified-row' : ''}" id="row_${o.mail_id}">
        <td>${escHtml(String(o.mail_id))}${expandBtn}</td>
        <td><span class="badge ${badge}">${escHtml(o.platform)}</span></td>
        <td><code style="font-size:0.75rem;">${escHtml(o.ota_number)}</code></td>
        <td><b>${escHtml(o.customer_name)}</b></td>
        <td style="white-space:nowrap;min-width:110px;">${dateCell(o.check_in_roc, o.check_out_roc, origInRoc, origOutRoc, modFields)}</td>
        <td style="text-align:center;">${escHtml(o.nights)}</td>
        <td style="text-align:center;">${extraBed}</td>
        <td style="min-width:130px;max-width:180px;"><div style="font-size:0.8rem;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;word-break:break-all;line-height:1.4;">${roomType}</div></td>
        <td>${escHtml(o.customer_phone)}</td>
        <td>${amountCell}</td>
        <td>${remarkCell}</td>
        <td class="claim-cell" id="claim_${o.mail_id}">${claimCell}</td>
        <td style="text-align:center;">
            ${o.platform !== '系統過濾信件'
                ? `<button class="btn-archive" onclick="archiveMail(${o.mail_id}, '${escHtml(o.platform)}', this)">📂 歸檔</button>`
                : `<span style="color:#aaa;font-size:0.75rem;">—</span>`
            }
        </td>
    </tr>`;
}

function buildCollapseRow(extras, groupId) {
    if (!extras.length) return '';
    const rows = extras.map(ex => {
        const badge = getBadgeClass(ex.platform);
        const isTrip = ex.platform.includes('Trip');
        const remark = ex.remark || '無';
        const hasLong = isTrip && remark.length > 30;
        if (hasLong) remarkStore[ex.mail_id] = remark;
        const extraBed = (ex.extra_bed && ex.extra_bed !== '無')
            ? `<span style="color:#e67e22;font-weight:bold;">🛏 ${escHtml(ex.extra_bed)}</span>`
            : `<span style="color:#aaa;">—</span>`;
        const remarkCell = hasLong
            ? `<button class="btn-remark" onclick="openModal(${ex.mail_id})">📋 查看備註</button>`
            : `<span class="remark-short">${escHtml(remark)}</span>`;
        return `<tr>
            <td style="border:none;font-size:0.75rem;">${escHtml(String(ex.mail_id))}</td>
            <td style="border:none;font-size:0.75rem;"><span class="badge ${badge}">${escHtml(ex.platform)}</span></td>
            <td style="border:none;font-size:0.75rem;">${escHtml(ex.customer_name)}</td>
            <td style="border:none;font-size:0.75rem;white-space:nowrap;">
                <span style="color:#087abc;">${escHtml(ex.check_in_roc)}</span> →&nbsp;${escHtml(ex.check_out_roc)}
            </td>
            <td style="border:none;font-size:0.75rem;text-align:center;">${escHtml(ex.nights)}</td>
            <td style="border:none;font-size:0.75rem;text-align:center;">${extraBed}</td>
            <td style="border:none;font-size:0.75rem;color:#27ae60;">TWD ${Number(ex.amount).toLocaleString()}</td>
            <td style="border:none;font-size:0.75rem;">${remarkCell}</td>
        </tr>`;
    }).join('');

    return `<tr class="collapse-row" id="${groupId}" style="display:none;"><td colspan="12">
        <table style="width:100%;border:none;margin:0;background:transparent;">
            <thead><tr style="background:#e8eaf6;">
                <th style="border:none;font-size:0.75rem;position:static;box-shadow:none;">信件ID</th>
                <th style="border:none;font-size:0.75rem;position:static;box-shadow:none;">平台</th>
                <th style="border:none;font-size:0.75rem;position:static;box-shadow:none;">旅客</th>
                <th style="border:none;font-size:0.75rem;position:static;box-shadow:none;">日期</th>
                <th style="border:none;font-size:0.75rem;position:static;box-shadow:none;">天數</th>
                <th style="border:none;font-size:0.75rem;position:static;box-shadow:none;">加床</th>
                <th style="border:none;font-size:0.75rem;position:static;box-shadow:none;">金額</th>
                <th style="border:none;font-size:0.75rem;position:static;box-shadow:none;">備註</th>
            </tr></thead>
            <tbody>${rows}</tbody>
        </table>
    </td></tr>`;
}

function renderTable(groups) {
    remarkStore = {};
    if (!groups.length) {
        return `<p style="text-align:center;color:#999;padding:3rem 0;">🎉 太棒了！目前沒有未處理的訂房信件。</p>`;
    }
    let html = `<table><thead><tr>
        <th>信件ID</th><th>來源平台</th><th>OTA 訂單編號</th><th>旅客姓名</th>
        <th style="min-width:110px;">入住 / 退房日期</th>
        <th style="min-width:50px;">天數</th><th>加床</th><th style="min-width:130px;">房型</th>
        <th>聯絡電話</th><th>總金額</th><th>客房備註 (特殊需求)</th>
        <th style="min-width:80px;text-align:center;">認領</th>
        <th style="min-width:70px;text-align:center;">歸檔</th>
    </tr></thead><tbody>`;
    groups.forEach(group => {
        const main    = group[0];
        const extras  = group.slice(1);
        const groupId = 'g_' + main.mail_id;
        html += buildMainRow(main, groupId, extras.length);
        html += buildCollapseRow(extras, groupId);
    });
    return html + '</tbody></table>';
}

// ── 本地時間格式化 ──────────────────────────────────────
function formatLocalTime(unixTs) {
    // unixTs 是後端 PHP time()，單位秒
    const d = new Date(unixTs * 1000);
    const h = String(d.getHours()).padStart(2, '0');
    const m = String(d.getMinutes()).padStart(2, '0');
    const s = String(d.getSeconds()).padStart(2, '0');
    return `${h}:${m}:${s}`;
}

// ── 主抓取函式 ──────────────────────────────────────────
async function fetchOrders(forceRefresh = false) {
    setStatus('loading', forceRefresh ? '同步中...' : '載入中...');
    document.getElementById('btnRefresh').disabled = true;
    stopCountdown();

    const content = document.getElementById('mainContent');
    // 只有第一次才顯示 loading 動畫
    if (!content.dataset.loaded) {
        content.innerHTML = `<div class="loading-overlay">
            <div class="spinner"></div>
            <div>正在連接信箱，讀取最新信件...</div>
            <div style="font-size:0.8rem;color:#aaa;margin-top:0.5rem;">首次約需 10～30 秒，請稍候</div>
        </div>`;
    }

    try {
        const url = forceRefresh ? 'fetch_orders.php?force=1' : 'fetch_orders.php';
        const res  = await fetch(url, { cache: 'no-store' });
        const data = await res.json();

        if (!data.success) throw new Error(data.error || '伺服器回傳錯誤');

        // 首次同步中（DB 完全沒資料）：顯示等待提示並輪詢
        if (data.is_syncing && data.total_orders === 0) {
            content.innerHTML = `<div class="syncing-bar" style="justify-content:center;padding:3rem;">
                <div class="spinner" style="width:1.5rem;height:1.5rem;border-width:3px;margin-right:0.5rem;"></div>
                背景同步中，正在解析信件，完成後自動顯示...
            </div>`;
            content.dataset.loaded = '';
            // 每 5 秒輪詢一次，等資料進 DB
            setTimeout(() => fetchOrders(false), 5000);
            document.getElementById('btnRefresh').disabled = false;
            return;
        }

        content.innerHTML  = renderTable(data.groups);
        content.dataset.loaded = '1';

        // 若仍在同步中（有新信件待解析）顯示提示
        if (data.is_syncing) {
            const bar = document.createElement('div');
            bar.className = 'syncing-bar';
            bar.id = 'syncingBar';
            bar.innerHTML = '🔄 背景持續同步新信件中...';
            content.prepend(bar);
        } else {
            document.getElementById('syncingBar')?.remove();
        }

        document.getElementById('countText').textContent =
            `共 ${data.total_orders} 筆訂單（${data.total_groups} 組）`;

        // ── 用後端 Unix timestamp 轉本地時間 ──
        const timeStr = formatLocalTime(data.fetched_ts);

        if (data.from_cache) {
            const ageMin = Math.floor(data.cache_age / 60);
            const ageSec = data.cache_age % 60;
            const ageStr = ageMin > 0 ? `${ageMin}分${ageSec}秒前` : `${ageSec}秒前`;
            setStatus('success', `資料擷取時間：${timeStr}`);
            document.getElementById('cacheBadge').innerHTML =
                `<span class="cache-badge">📦 快取 ${ageStr}</span>`;
        } else {
            setStatus('success', `資料擷取時間：${timeStr}`);
            document.getElementById('cacheBadge').innerHTML = '';
        }

        // 從快取回傳時，倒數剩餘快取時間；否則從頭倒數
        const remainSec = data.from_cache
            ? Math.max(0, CACHE_TTL - data.cache_age)
            : CACHE_TTL;
        startCountdown(remainSec);
        startPollClaims(); // 啟動認領狀態輪詢

    } catch (err) {
        setStatus('error', '連線失敗');
        if (!content.dataset.loaded) {
            content.innerHTML = `<div class="error-box">❌ 讀取失敗：${escHtml(err.message)}<br>請按「立即同步」重試。</div>`;
        } else {
            const errDiv = document.createElement('div');
            errDiv.className = 'error-box';
            errDiv.style.marginBottom = '1rem';
            errDiv.textContent = `⚠️ 自動更新失敗（${err.message}），請按「立即同步」重試。`;
            content.prepend(errDiv);
            setTimeout(() => errDiv.remove(), 6000);
        }
        startCountdown(CACHE_TTL);
    } finally {
        document.getElementById('btnRefresh').disabled = false;
    }
}

// ── 認領函式 ────────────────────────────────────────────────
async function toggleClaim(mailId, action, el) {
    el.disabled = true;
    const oldHtml = el.outerHTML;
    try {
        const body = new URLSearchParams({ mail_id: mailId, action });
        const res  = await fetch('claim_mail.php', { method: 'POST', body });
        const data = await res.json();
        if (!data.success) throw new Error(data.error || '操作失敗');

        // 立即更新畫面
        updateClaimCell(mailId, action === 'claim' ? data.keyword : null);
    } catch (err) {
        alert('⚠️ ' + err.message);
        el.disabled = false;
    }
}

function updateClaimCell(mailId, claimedBy) {
    const cell = document.getElementById('claim_' + mailId);
    if (!cell) return;
    if (claimedBy) {
        const isMine = claimedBy === MY_KEYWORD;
        const displayName = isMine ? MY_DISPLAY : claimedBy;
        cell.innerHTML = isMine
            ? `<span class="claim-badge mine" title="點擊取消認領" onclick="toggleClaim(${mailId}, 'unclaim', this)">🔖 ${escHtml(displayName)} ✕</span>`
            : `<span class="claim-badge">🔖 ${escHtml(displayName)}</span>`;
    } else {
        cell.innerHTML = `<button class="btn-claim" onclick="toggleClaim(${mailId}, 'claim', this)">☐ 認領</button>`;
    }
}

// ── 認領狀態輪詢（每 10 秒）────────────────────────────────
let pollTimer = null;
async function pollClaims() {
    try {
        const res  = await fetch('poll_claims.php', { cache: 'no-store' });
        const data = await res.json();
        if (!data.success) return;

        // 更新所有認領狀態
        Object.entries(data.claims).forEach(([mailId, claimedBy]) => {
            updateClaimCell(parseInt(mailId), claimedBy);
        });
    } catch (e) { /* 網路問題靜默忽略 */ }
}

function startPollClaims() {
    stopPollClaims();
    pollClaims(); // 立即執行一次
    pollTimer = setInterval(pollClaims, 10000);
}
function stopPollClaims() {
    if (pollTimer) { clearInterval(pollTimer); pollTimer = null; }
}

function setStatus(type, text) {
    document.getElementById('statusDot').className = 'status-dot ' + type;
    document.getElementById('statusText').textContent = text;
}

function startCountdown(sec) {
    stopCountdown();
    const el = document.getElementById('countdown');
    el.textContent = `${sec}s 後自動更新`;
    countdownTimer = setInterval(() => {
        sec--;
        el.textContent = sec > 0 ? `${sec}s 後自動更新` : '更新中...';
        if (sec <= 0) { stopCountdown(); fetchOrders(); }
    }, 1000);
}

function stopCountdown() {
    if (countdownTimer) { clearInterval(countdownTimer); countdownTimer = null; }
    document.getElementById('countdown').textContent = '';
}

function toggleGroup(groupId, btn) {
    const row = document.getElementById(groupId);
    if (!row) return;
    const hidden = row.style.display === 'none';
    row.style.display = hidden ? 'table-row' : 'none';
    btn.textContent = btn.textContent.replace(hidden ? '▼':'▲', hidden ? '▲':'▼');
}

// ── 歸檔函式 ────────────────────────────────────────────────
async function archiveMail(mailId, platform, btn) {
    btn.disabled = true;
    btn.textContent = '歸檔中...';

    try {
        const body = new URLSearchParams({ mail_id: mailId, platform });
        const res  = await fetch('archive_mail.php', { method: 'POST', body });
        const data = await res.json();

        if (!data.success) throw new Error(data.error || '歸檔失敗');

        // 移除整個 group（主列 + collapse列）
        const tr = btn.closest('tr');
        const groupId = tr.querySelector('button.btn-expand')
            ? tr.querySelector('button.btn-expand').getAttribute('onclick').match(/'([^']+)'/)?.[1]
            : null;
        if (groupId) {
            const collapseRow = document.getElementById(groupId);
            if (collapseRow) collapseRow.remove();
        }
        tr.remove();

        // 更新計數
        const countEl = document.getElementById('countText');
        if (countEl) {
            const match = countEl.textContent.match(/共\s*(\d+)\s*筆.*?（(\d+)\s*組）/);
            if (match) {
                const newOrders = Math.max(0, parseInt(match[1]) - 1);
                const newGroups = Math.max(0, parseInt(match[2]) - 1);
                countEl.textContent = `共 ${newOrders} 筆訂單（${newGroups} 組）`;
            }
        }

    } catch (err) {
        btn.disabled = false;
        btn.textContent = '📂 歸檔';
        alert('⚠️ ' + err.message);
    }
}

function openModal(mailId) {
    document.getElementById('modalContent').textContent = remarkStore[mailId] || '';
    document.getElementById('remarkModal').classList.add('active');
}
function closeModal() { document.getElementById('remarkModal').classList.remove('active'); }
function closeModalOutside(e) { if (e.target === document.getElementById('remarkModal')) closeModal(); }
document.addEventListener('keydown', e => { if (e.key === 'Escape') closeModal(); });

// 啟動：不強制重抓，從 DB 讀取
fetchOrders(false);
</script>
</body>
</html>
