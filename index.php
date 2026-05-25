<?php
// index.php
require_once 'mail_fetcher.php';
require_once 'order_parser.php';

$fetcher = new MailFetcher();
$unreadMails = $fetcher->fetchUnreadMails();

// 解析所有信件並過濾
$orders = [];
foreach ($unreadMails as $mail) {
    $order = OrderParser::parse($mail['html_body'], $mail['subject']);
    if ($order['platform'] === '系統過濾信件') continue;
    $order['mail_id'] = $mail['id'];
    $orders[] = $order;
}

// 依 OTA 訂單號分組，同訂單號摺疊
$groups = [];
foreach ($orders as $order) {
    $key = ($order['ota_number'] !== '無' && $order['ota_number'] !== '')
        ? $order['ota_number']
        : 'unique_' . $order['mail_id'];
    $groups[$key][] = $order;
}

// 完整度評分：分數越高資訊越完整
function scoreOrder($order) {
    $score = 0;
    if ($order['customer_name']  !== '無' && $order['customer_name']  !== '請查後台') $score++;
    if ($order['check_in']       !== '無' && $order['check_in']       !== '請查後台') $score++;
    if ($order['check_out']      !== '無' && $order['check_out']      !== '請查後台') $score++;
    if ($order['owl_number']     !== '無')  $score++;
    if ($order['amount']         > 0)       $score++;
    if ($order['customer_phone'] !== '無')  $score++;
    return $score;
}

// 每組按完整度降冪排列，第一筆當主列，其餘收折
foreach ($groups as $ota_key => &$group) {
    usort($group, fn($a, $b) => scoreOrder($b) - scoreOrder($a));
}
unset($group);
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <title>羅東幸福商旅 - 自動訂房核對系統</title>
    <style>
        html {
            font-size: 125%;
        }
        body {
            font-family: "Microsoft JhengHei", sans-serif;
            background-color: #f8f9fa;
            margin: 0;
            padding: 20px;
        }
        .container {
            max-width: 1600px;
            margin: 0 auto;
            background: white;
            padding: 25px;
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 3px solid #642100;
            padding-bottom: 15px;
            margin-bottom: 20px;
        }
        h1 { color: #642100; margin: 0; font-size: 1.5rem; }
        .btn-refresh {
            background-color: #27ae60;
            color: white;
            border: none;
            padding: 0.6rem 1.25rem;
            font-size: 1rem;
            font-weight: bold;
            border-radius: 5px;
            cursor: pointer;
        }
        .btn-refresh:hover { background-color: #219653; }

        /* 表格 */
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { border: 1px solid #dee2e6; padding: 0.625rem; text-align: left; font-size: 0.875rem; }
        th {
            background-color: #f1f3f5;
            color: #495057;
            font-weight: bold;
            position: sticky;
            top: 0;
            z-index: 10;
            box-shadow: 0 2px 4px rgba(0,0,0,0.08);
        }
        tr.main-row:hover { background-color: #fdf6f0; }

        /* Badge */
        .badge {
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-size: 0.75rem;
            font-weight: bold;
            color: white;
            white-space: nowrap;
        }
        .bg-booking  { background-color: #003580; }
        .bg-agoda    { background-color: #e12d6e; }
        .bg-trip     { background-color: #ff9900; }
        .bg-airbnb   { background-color: #FF385C; }
        .bg-asiayo   { background-color: #97af15; }
        .bg-expedia  { background-color: #00A8E0; }
        .bg-cancelled { background-color: #c0392b; }
        .bg-unknown  { background-color: #6c757d; }

        /* 備註按鈕 */
        .btn-remark {
            background: #fff3cd;
            border: 1px solid #ffc107;
            border-radius: 4px;
            padding: 0.25rem 0.625rem;
            font-size: 0.75rem;
            cursor: pointer;
            color: #856404;
            white-space: nowrap;
        }
        .btn-remark:hover { background: #ffe69c; }

        /* 摺疊行 */
        .collapse-row { background-color: #f8f9ff; }
        .collapse-row td { padding: 0.375rem 0.625rem; font-size: 0.8125rem; color: #555; }
        .btn-expand {
            background: none;
            border: 1px solid #adb5bd;
            border-radius: 4px;
            padding: 0.125rem 0.5rem;
            font-size: 0.6875rem;
            cursor: pointer;
            color: #495057;
        }
        .btn-expand:hover { background: #e9ecef; }

        /* Modal */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0; left: 0;
            width: 100%; height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }
        .modal-overlay.active { display: flex; }
        .modal-box {
            background: white;
            border-radius: 8px;
            padding: 24px;
            max-width: 560px;
            width: 90%;
            max-height: 80vh;
            overflow-y: auto;
            box-shadow: 0 8px 32px rgba(0,0,0,0.2);
            position: relative;
        }
        .modal-title {
            font-size: 1rem;
            font-weight: bold;
            color: #642100;
            margin-bottom: 16px;
            padding-bottom: 10px;
            border-bottom: 2px solid #f0e0d6;
        }
        .modal-content {
            font-size: 0.875rem;
            line-height: 1.8;
            color: #333;
            white-space: pre-wrap;
            word-break: break-word;
        }
        .modal-close {
            position: absolute;
            top: 12px; right: 16px;
            background: none;
            border: none;
            font-size: 1.375rem;
            cursor: pointer;
            color: #888;
            line-height: 1;
        }
        .modal-close:hover { color: #333; }

        .count-text { font-size: 1rem; color: #666; }
        .remark-short { color: #c0392b; font-size: 0.8125rem; }
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <div>
            <h1>🏨 羅東幸福商旅 - 訂房信件自動核對平台</h1>
            <div class="count-text">共 <b><?php echo count($orders); ?></b> 筆訂單（<b><?php echo count($groups); ?></b> 組）</div>
        </div>
        <button class="btn-refresh" onclick="window.location.reload();">🔄 立即同步最新信件</button>
    </div>

    <table>
        <thead>
            <tr>
                <th>信件ID</th>
                <th>來源平台</th>
                <th>旅客姓名</th>
                <th style="min-width:110px;">入住 / 退房日期</th>
                <th style="min-width:50px;">天數</th>
                <th>加床</th>
                <th>OTA 訂單編號</th>
                <th>奧丁丁內部號</th>
                <th>聯絡電話</th>
                <th>總金額</th>
                <th>客房備註 (特殊需求)</th>
            </tr>
        </thead>
        <tbody>
        <?php if (empty($groups)): ?>
            <tr>
                <td colspan="11" style="text-align:center;color:#999;">🎉 太棒了！目前沒有未處理的訂房信件。</td>
            </tr>
        <?php else: ?>
            <?php foreach ($groups as $ota_key => $group):
                $main   = $group[0];
                $extras = array_slice($group, 1);
                $count  = count($group);

                // badge
                $badgeClass   = 'bg-unknown';
                $platformName = $main['platform'];
                if      (strpos($platformName, 'AsiaYo')  !== false) $badgeClass = 'bg-asiayo';
                elseif  (strpos($platformName, 'Expedia') !== false) $badgeClass = 'bg-expedia';
                elseif  (strpos($platformName, 'Airbnb')  !== false) $badgeClass = 'bg-airbnb';
                elseif  (strpos($platformName, 'Booking') !== false) $badgeClass = 'bg-booking';
                elseif  (strpos($platformName, 'Agoda')   !== false) $badgeClass = 'bg-agoda';
                elseif  (strpos($platformName, 'Trip')    !== false) $badgeClass = 'bg-trip';
                if      (strpos($platformName, '(取消)')  !== false) $badgeClass = 'bg-cancelled';

                $isTrip  = strpos($platformName, 'Trip') !== false;
                $remark  = htmlspecialchars($main['remark']);
                $groupId = 'g_' . $main['mail_id'];
            ?>
            <!-- 主列 -->
            <tr class="main-row">
                <td>
                    <?php echo $main['mail_id']; ?>
                    <?php if ($count > 1): ?>
                        <br>
                        <button class="btn-expand" onclick="toggleGroup('<?php echo $groupId; ?>', this)">
                            +<?php echo $count - 1; ?> 封 ▼
                        </button>
                    <?php endif; ?>
                </td>
                <td><span class="badge <?php echo $badgeClass; ?>"><?php echo htmlspecialchars($platformName); ?></span></td>
                <td><b><?php echo htmlspecialchars($main['customer_name']); ?></b></td>
                <td style="white-space:nowrap;min-width:110px;">
                    <span style="color:#087abc;font-weight:bold;"><?php echo htmlspecialchars(OrderParser::toROCDate($main['check_in'])); ?></span><br>
                    <span style="color:#888;font-size:0.75rem;">至</span>&nbsp;<?php echo htmlspecialchars(OrderParser::toROCDate($main['check_out'])); ?>
                </td>
                <td style="text-align:center;"><?php echo htmlspecialchars($main['nights']); ?></td>
                <td style="text-align:center;">
                    <?php if ($main['extra_bed'] !== '無' && $main['extra_bed'] !== ''): ?>
                        <span style="color:#e67e22;font-weight:bold;">🛏 <?php echo htmlspecialchars($main['extra_bed']); ?></span>
                    <?php else: ?>
                        <span style="color:#aaa;">—</span>
                    <?php endif; ?>
                </td>
                <td><code><?php echo htmlspecialchars($main['ota_number']); ?></code></td>
                <td><small><?php echo htmlspecialchars($main['owl_number']); ?></small></td>
                <td><?php echo htmlspecialchars($main['customer_phone']); ?></td>
                <td><b style="color:#27ae60;">TWD <?php echo number_format($main['amount']); ?></b></td>
                <td>
                    <?php if ($isTrip && strlen($main['remark']) > 30): ?>
                        <button class="btn-remark" onclick="openModal(<?php echo $main['mail_id']; ?>)">
                            📋 查看備註
                        </button>
                        <span id="remark_<?php echo $main['mail_id']; ?>" style="display:none;"><?php echo $remark; ?></span>
                    <?php else: ?>
                        <div class="remark-short"><?php echo $remark; ?></div>
                    <?php endif; ?>
                </td>
            </tr>

            <?php if ($count > 1): ?>
            <!-- 摺疊的同訂單號其他封 -->
            <tr class="collapse-row" id="<?php echo $groupId; ?>" style="display:none;">
                <td colspan="11">
                    <table style="width:100%;border:none;margin:0;background:transparent;">
                        <thead>
                            <tr style="background:#e8eaf6;">
                                <th style="border:none;font-size:0.75rem;position:static;box-shadow:none;">信件ID</th>
                                <th style="border:none;font-size:0.75rem;position:static;box-shadow:none;">平台</th>
                                <th style="border:none;font-size:0.75rem;position:static;box-shadow:none;">旅客</th>
                                <th style="border:none;font-size:0.75rem;position:static;box-shadow:none;">日期</th>
                                <th style="border:none;font-size:0.75rem;position:static;box-shadow:none;">天數</th>
                                <th style="border:none;font-size:0.75rem;position:static;box-shadow:none;">加床</th>
                                <th style="border:none;font-size:0.75rem;position:static;box-shadow:none;">金額</th>
                                <th style="border:none;font-size:0.75rem;position:static;box-shadow:none;">備註</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($extras as $ex):
                            $exIsTrip = strpos($ex['platform'], 'Trip') !== false;
                            $exRemark = htmlspecialchars($ex['remark']);
                            $exBadge  = 'bg-unknown';
                            if      (strpos($ex['platform'], 'AsiaYo')  !== false) $exBadge = 'bg-asiayo';
                            elseif  (strpos($ex['platform'], 'Expedia') !== false) $exBadge = 'bg-expedia';
                            elseif  (strpos($ex['platform'], 'Airbnb')  !== false) $exBadge = 'bg-airbnb';
                            elseif  (strpos($ex['platform'], 'Booking') !== false) $exBadge = 'bg-booking';
                            elseif  (strpos($ex['platform'], 'Agoda')   !== false) $exBadge = 'bg-agoda';
                            elseif  (strpos($ex['platform'], 'Trip')    !== false) $exBadge = 'bg-trip';
                            if      (strpos($ex['platform'], '(取消)')  !== false) $exBadge = 'bg-cancelled';
                        ?>
                        <tr>
                            <td style="border:none;font-size:0.75rem;"><?php echo $ex['mail_id']; ?></td>
                            <td style="border:none;font-size:0.75rem;"><span class="badge <?php echo $exBadge; ?>"><?php echo htmlspecialchars($ex['platform']); ?></span></td>
                            <td style="border:none;font-size:0.75rem;"><?php echo htmlspecialchars($ex['customer_name']); ?></td>
                            <td style="border:none;font-size:0.75rem;white-space:nowrap;">
                                <span style="color:#087abc;"><?php echo htmlspecialchars(OrderParser::toROCDate($ex['check_in'])); ?></span>
                                →&nbsp;<?php echo htmlspecialchars(OrderParser::toROCDate($ex['check_out'])); ?>
                            </td>
                            <td style="border:none;font-size:0.75rem;text-align:center;"><?php echo htmlspecialchars($ex['nights']); ?></td>
                            <td style="border:none;font-size:0.75rem;text-align:center;">
                                <?php if ($ex['extra_bed'] !== '無' && $ex['extra_bed'] !== ''): ?>
                                    <span style="color:#e67e22;font-weight:bold;">🛏 <?php echo htmlspecialchars($ex['extra_bed']); ?></span>
                                <?php else: ?>
                                    <span style="color:#aaa;">—</span>
                                <?php endif; ?>
                            </td>
                            <td style="border:none;font-size:0.75rem;color:#27ae60;">TWD <?php echo number_format($ex['amount']); ?></td>
                            <td style="border:none;font-size:0.75rem;">
                                <?php if ($exIsTrip && strlen($ex['remark']) > 30): ?>
                                    <button class="btn-remark" onclick="openModal(<?php echo $ex['mail_id']; ?>)">📋 查看備註</button>
                                    <span id="remark_<?php echo $ex['mail_id']; ?>" style="display:none;"><?php echo $exRemark; ?></span>
                                <?php else: ?>
                                    <span class="remark-short"><?php echo $exRemark; ?></span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </td>
            </tr>
            <?php endif; ?>

            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- Modal 彈窗 -->
<div class="modal-overlay" id="remarkModal" onclick="closeModalOutside(event)">
    <div class="modal-box">
        <button class="modal-close" onclick="closeModal()">✕</button>
        <div class="modal-title">📋 客房備註 / 特殊需求</div>
        <div class="modal-content" id="modalContent"></div>
    </div>
</div>

<script>
function toggleGroup(groupId, btn) {
    const row = document.getElementById(groupId);
    if (row.style.display === 'none') {
        row.style.display = 'table-row';
        btn.textContent = btn.textContent.replace('▼', '▲');
    } else {
        row.style.display = 'none';
        btn.textContent = btn.textContent.replace('▲', '▼');
    }
}

function openModal(mailId) {
    const remark = document.getElementById('remark_' + mailId).textContent;
    document.getElementById('modalContent').textContent = remark;
    document.getElementById('remarkModal').classList.add('active');
}

function closeModal() {
    document.getElementById('remarkModal').classList.remove('active');
}

function closeModalOutside(e) {
    if (e.target === document.getElementById('remarkModal')) closeModal();
}

document.addEventListener('keydown', e => {
    if (e.key === 'Escape') closeModal();
});
</script>
</body>
</html>
