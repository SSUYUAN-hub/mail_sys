<?php
// 1. 將你提供的奧丁丁 HTML 內文放入變數中（使用 Heredoc 語法）
$htmlContent = <<<'HTML'
<!DOCTYPE html>
	<html>
	<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width,initial-scale=1.0" />
	<title>OwlTing</title>
	<style type="text/css">
		* {
			font-family:'微軟正黑體','Helvetica Neue','Heiti TC','Helvetica','Arial',sans-serif;
			margin-top: 0;
		}
		@media  only screen and (max-device-width: 600px) {
			body, p, table {
				font-size:16px !important;
			}
		}
	</style>
	</head>

	<body style="background-color:#fff3ee; font-family:'微軟正黑體','Helvetica Neue','Heiti TC','Helvetica','Arial',sans-serif;">
		<div style="text-align:center;"><img src="https://www.owlting.com/resource/owlnest/mail/ota_logo_1.png" style="width:60px; margin: 20px auto;"></div>

		<table width="480px" cellspacing="0" cellpadding="0" style="background-color:#fff; margin:auto; border-collapse:collapse; font-size:16px; font-family:'微軟正黑體','Helvetica Neue','Heiti TC','Helvetica','Arial',sans-serif;">
		<tbody>
		<tr>
			<td colspan="2" style="font-family:'微軟正黑體','Helvetica Neue','Heiti TC','Helvetica','Arial',sans-serif;text-align:center;"></td>
		</tr>

		<tr height="20px"><td colspan="2" style="padding:10px; font-family:'微軟正黑體','Helvetica Neue','Heiti TC','Helvetica','Arial',sans-serif;">
		<div style="text-align:center; margin:20px 0 30px; color:#696966;">到處去玩商旅 您好：</div>

                <div style="text-align:center; color:#642100; font-size:30px; font-weight:bold; padding-bottom:30px; margin-bottom:30px; border-bottom:5px solid #f2f2f2;">來自 yourmon.com 的新訂單！</div>

                <div width="100%" style="margin:20px 10px 10px 10px; font-size:16px; font-family:'微軟正黑體','Helvetica Neue','Heiti TC','Helvetica','Arial',sans-serif; color:#696966;">
稍早透過 yourmon.com 獲得一筆新訂單，OTA訂單編號為: 987654321，預訂資訊如下：
</div>

                <div width="100%" style="border-left: 8px solid #642100; padding-left: 15px;margin:30px 10px 10px 10px; font-size:16px; font-family:'微軟正黑體','Helvetica Neue','Heiti TC','Helvetica','Arial',sans-serif; font-weight:bold; color:#642100;">訂單編號： OBE88888816888</div>

                <table width="100%" cellspacing="0" cellpadding="2" style="border-collapse: collapse; margin-bottom:10px;">
    <tbody>
        <tr>
    <td colspan="2" width="100%"><div style="margin:3px 10px; font-size:16px; font-family:'微軟正黑體','Helvetica Neue','Heiti TC','Helvetica','Arial',sans-serif; color:#696966;">
        <table width="100%" cellspacing="0" cellpadding="2" style="border-collapse: collapse; margin-bottom:10px;">
            <tbody>
                <tr>
                    <td width="45%" style="background-color:#f2f2f2;"><div style="margin:10px 10px 0px 10px; font-size:14px; font-family:'微軟正黑體','Helvetica Neue','Heiti TC','Helvetica','Arial',sans-serif; color:#696966; text-align:center;">入住日期</div></td>
                    <td rowspan="2" style="background-color:#fff;text-align:center;"><img width="20px" src="https://www.owlting.com/market/edm/test_only/pic/todate.png"></td>
                    <td width="45%" style="background-color:#f2f2f2;"><div style="margin:10px 10px 0px 10px; font-size:14px; font-family:'微軟正黑體','Helvetica Neue','Heiti TC','Helvetica','Arial',sans-serif; color:#696966; text-align:center;">退房日期</div></td>
                </tr>
                <tr>
                    <td style="background-color:#f2f2f2;"><div style="margin:0px 10px 10px 10px; font-size:16px; font-family:'微軟正黑體','Helvetica Neue','Heiti TC','Helvetica','Arial',sans-serif; color:#087abc; vertical-align:top; text-align:center;">2026-07-15</div></td>
                    <td style="background-color:#f2f2f2;"><div style="margin:0px 10px 10px 10px; font-size:16px; font-family:'微軟正黑體','Helvetica Neue','Heiti TC','Helvetica','Arial',sans-serif; color:#087abc; vertical-align:top; text-align:center;">2026-07-17</div></td>
                </tr>
            </tbody>
        </table></div>
    </td>
</tr>

        <tr><td style="height:5px;"></td></tr>

        <tr>
    <td colspan="2" width="100%"><div style="margin:3px 10px; font-size:16px; font-family:'微軟正黑體','Helvetica Neue','Heiti TC','Helvetica','Arial',sans-serif; color:#696966;">
        <span style="">訂購內容</span>
        <table width="100%" cellspacing="0" cellpadding="2" style="border-collapse: collapse; margin-top:5px;">
            <tbody>
                <tr style="border-bottom:1px solid #cecece;border-top:1px solid #cecece;">
                    <td width="35%" style="background-color:#e1f4ff;">
                        <div style="margin:5px; font-size:14px; font-family:'微軟正黑體','Helvetica Neue','Heiti TC','Helvetica','Arial',sans-serif; color:#696966;">房型名稱</div></td>
                    <td width="55%" style="background-color:#e1f4ff;">
                        <div style="margin:5px; font-size:14px; font-family:'微軟正黑體','Helvetica Neue','Heiti TC','Helvetica','Arial',sans-serif; color:#696966;">專案名稱</div></td>
                    <td style="background-color:#e1f4ff;">
                        <div style="margin:5px; font-size:14px; font-family:'微軟正黑體','Helvetica Neue','Heiti TC','Helvetica','Arial',sans-serif; color:#696966; text-align:center;">數量</div></td>
                </tr>


                <tr style="border-bottom:1px solid #cecece;">
                    <td style="background-color:#f2f2f2;">
                        <div style="margin:5px; font-size:14px; font-family:'微軟正黑體','Helvetica Neue','Heiti TC','Helvetica','Arial',sans-serif; color:#696966;">
                            A 經濟100人房
                        </div></td>
                    <td style="background-color:#f2f2f2;">
                        <div style="margin:5px; font-size:14px; font-family:'微軟正黑體','Helvetica Neue','Heiti TC','Helvetica','Arial',sans-serif; color:#696966;">
                            Room Only
                        </div></td>
                    <td style="background-color:#f2f2f2;">
                        <div style="margin:5px; font-size:14px; font-family:'微軟正黑體','Helvetica Neue','Heiti TC','Helvetica','Arial',sans-serif; color:#696966; text-align:center;">
                            1
                        </div></td>
                </tr>
                 
            </tbody>
        </table></div>
    </td>
</tr>

        <tr><td style="height:5px;"></td></tr>

         
        <tr><td style="height:3px;"></td></tr>

    </tbody>
</table>

                <table width="100%" cellspacing="0" cellpadding="2" style="border-collapse: collapse; margin-bottom:10px;">
    <tbody>

    <tr>
    <td width="40%" style="vertical-align:top;"><div style="margin:3px 10px; font-size:16px; font-family:'微軟正黑體','Helvetica Neue','Heiti TC','Helvetica','Arial',sans-serif; color:#696966; font-weight: normal">人數</div></td>
    <td width="60%" style="text-align:left;"><div style="margin:3px 10px; font-family:'微軟正黑體','Helvetica Neue','Heiti TC','Helvetica','Arial',sans-serif; color:#696966; font-weight: normal"><span style="">大人 1 人,小孩 0 人,嬰兒 0 人</span></div></td>
</tr>

     
    <tr>
    <td width="40%" style="vertical-align:top;"><div style="margin:3px 10px; font-size:16px; font-family:'微軟正黑體','Helvetica Neue','Heiti TC','Helvetica','Arial',sans-serif; color:#696966; font-weight: normal">入住天數</div></td>
    <td width="60%" style="text-align:left;"><div style="margin:3px 10px; font-family:'微軟正黑體','Helvetica Neue','Heiti TC','Helvetica','Arial',sans-serif; color:#696966; font-weight: normal"><span style="">2</span></div></td>
</tr>

     
            <tr>
    <td width="40%" style="vertical-align:top;"><div style="margin:3px 10px; font-size:16px; font-family:'微軟正黑體','Helvetica Neue','Heiti TC','Helvetica','Arial',sans-serif; color:#696966; font-weight: normal">剩餘尾款</div></td>
    <td width="60%" style="text-align:left;"><div style="margin:3px 10px; font-family:'微軟正黑體','Helvetica Neue','Heiti TC','Helvetica','Arial',sans-serif; color:#696966; font-weight: normal"><span style="">TWD 8888</span></div></td>
</tr>
     
    <tr>
    <td width="40%" style="vertical-align:top;"><div style="margin:3px 10px; font-size:16px; font-family:'微軟正黑體','Helvetica Neue','Heiti TC','Helvetica','Arial',sans-serif; color:#696966; font-weight: normal">訂單款項</div></td>
    <td width="60%" style="text-align:left;"><div style="margin:3px 10px; font-family:'微軟正黑體','Helvetica Neue','Heiti TC','Helvetica','Arial',sans-serif; color:#696966; font-weight: normal"><span style="">TWD 2353</span></div></td>
</tr>

            <tr>
    <td width="40%" style="vertical-align:top;"><div style="margin:3px 10px; font-size:16px; font-family:'微軟正黑體','Helvetica Neue','Heiti TC','Helvetica','Arial',sans-serif; color:#696966; font-weight: normal">付款狀態</div></td>
    <td width="60%" style="text-align:left;"><div style="margin:3px 10px; font-family:'微軟正黑體','Helvetica Neue','Heiti TC','Helvetica','Arial',sans-serif; color:#696966; font-weight: normal"><span style="">待結清</span></div></td>
</tr>
        </tbody>
</table>

                <div width="100%" style="border-left: 8px solid #642100; padding-left: 15px;margin:30px 10px 10px 10px; font-size:16px; font-family:'微軟正黑體','Helvetica Neue','Heiti TC','Helvetica','Arial',sans-serif; font-weight:bold; color:#642100;">旅客資訊</div>

                <table width="100%" cellspacing="0" cellpadding="0" style="border-collapse: collapse; margin-bottom:10px;">
    <tbody>

    <tr>
    <td width="40%" style="vertical-align:top;"><div style="margin:3px 10px; font-size:16px; font-family:'微軟正黑體','Helvetica Neue','Heiti TC','Helvetica','Arial',sans-serif; color:#696966; font-weight: normal">旅客姓名</div></td>
    <td width="60%" style="text-align:left;"><div style="margin:3px 10px; font-family:'微軟正黑體','Helvetica Neue','Heiti TC','Helvetica','Arial',sans-serif; color:#696966; font-weight: normal"><span style="">王大明</span></div></td>
</tr>

        <tr>
    <td width="40%" style="vertical-align:top;"><div style="margin:3px 10px; font-size:16px; font-family:'微軟正黑體','Helvetica Neue','Heiti TC','Helvetica','Arial',sans-serif; color:#696966; font-weight: normal">旅客電話</div></td>
    <td width="60%" style="text-align:left;"><div style="margin:3px 10px; font-family:'微軟正黑體','Helvetica Neue','Heiti TC','Helvetica','Arial',sans-serif; color:#696966; font-weight: normal"><span style="">+886**** 999 999</span></div></td>
</tr>

         
         
         
     
     

        <tr>
    <td width="40%" style="vertical-align:top;"><div style="margin:3px 10px; font-size:16px; font-family:'微軟正黑體','Helvetica Neue','Heiti TC','Helvetica','Arial',sans-serif; color:#696966; font-weight: normal">旅客信箱</div></td>
    <td width="60%" style="text-align:left;"><div style="margin:3px 10px; font-family:'微軟正黑體','Helvetica Neue','Heiti TC','Helvetica','Arial',sans-serif; color:#696966; font-weight: normal"><span style="">ab*********@guest.yourmon.com</span></div></td>
</tr>

            <tr>
    <td width="40%" style="vertical-align:top;"><div style="margin:3px 10px; font-size:16px; font-family:'微軟正黑體','Helvetica Neue','Heiti TC','Helvetica','Arial',sans-serif; color:#696966; font-weight: normal">特殊需求</div></td>
    <td width="60%" style="text-align:left;"><div style="margin:3px 10px; font-family:'微軟正黑體','Helvetica Neue','Heiti TC','Helvetica','Arial',sans-serif; color:#696966; font-weight: normal"><span style="">Guest Name: 王 大明, Approximate time of arrival: between 19:00 and 20:00有帶一隻紅貴賓-玩具型 約2kg以內不會隨地大小便, booker_is_genius</span></div></td>
</tr>
     
    </tbody>
</table>
                </td></tr>
            <tr height="20px"><td colspan="2"></td></tr>
                            <tr style="background-color:#f2f2f2;">
    <td colspan="2">
        <div width="100%" style="margin:20px 10px 0px 10px; font-size:14px; font-family:'微軟正黑體','Helvetica Neue','Heiti TC','Helvetica','Arial',sans-serif; font-size:14px; color:#696966; text-align:center;">
            若對訂單有任何相關問題，請聯繫您下榻的旅宿處理，謝謝。
        </div>
    </td>
</tr>
                        <tr style="background-color:#f2f2f2;">
    <td colspan="2" style="text-align:center; font-size:14px; color:#696966; font-family:'微軟正黑體','Helvetica Neue','Heiti TC','Helvetica','Arial',sans-serif;">
        <div style="margin:10px 0 20px 0;"> 本系統服務由 OwlNest 提供
    </div></td>
</tr>
        </tbody>
        </table>
    <img alt="" src="https://1s5m7ccv.r.ap-northeast-1.awstrack.me/I0/0106019e4b3c0627-4b9f787e-7377-4e28-9741-83ab63157da1-000000/T6QL9n2DA879di4by4x4mJgUOkY=258" style="display: none; width: 1px; height: 1px;">
</body>
</html>
HTML;


// 2. 建立 DOM 解析物件
$doc = new DOMDocument();
libxml_use_internal_errors(true);
$doc->loadHTML('<?xml encoding="UTF-8">' . $htmlContent);
libxml_clear_errors();

$xpath = new DOMXPath($doc);

// --- 開始精準擷取資料 ---

// A. 預訂平台
$platform = "未知平台";
$titleNode = $xpath->query("//div[contains(text(), '來自')]")->item(0);
if ($titleNode) {
    if (preg_match('/來自\s*(.*?)\s*的新訂單/', $titleNode->nodeValue, $matches)) {
        $platform = trim($matches[1]);
    }
}

// B. OTA 訂單編號
$otaNumber = "無";
$textNode = $xpath->query("//div[contains(text(), 'OTA訂單編號為')]")->item(0);
if ($textNode) {
    if (preg_match('/OTA訂單編號為:\s*(\d+)/', $textNode->nodeValue, $matches)) {
        $otaNumber = $matches[1];
    }
}

// C. 奧丁丁系統訂單編號
$owlNestNumber = "無";
$owlNode = $xpath->query("//div[contains(text(), '訂單編號：')]")->item(0);
if ($owlNode) {
    $owlNestNumber = trim(str_replace("訂單編號：", "", $owlNode->nodeValue));
}

// D. 入住與退房日期
$dateNodes = $xpath->query("//td/div[contains(@style, 'color:#087abc')]");
$checkIn = "無";
$checkOut = "無";
if ($dateNodes->length >= 2) {
    $checkIn = trim($dateNodes->item(0)->nodeValue);
    $checkOut = trim($dateNodes->item(1)->nodeValue);
}

// E. 付款金額
$amount = 0;
$amountNodes = $xpath->query("//span[contains(text(), 'TWD')]");
if ($amountNodes->length > 0) {
    $amountText = trim($amountNodes->item(0)->nodeValue);
    $amount = (int)filter_var($amountText, FILTER_SANITIZE_NUMBER_INT);
}

// F. 旅客姓名、電話與備註
$customerName = "無";
$customerPhone = "無";
$remark = "無";

$nameLabel = $xpath->query("//td[div[text()='旅客姓名']]/following-sibling::td/div/span");
if ($nameLabel->length > 0) $customerName = trim($nameLabel->item(0)->nodeValue);

$phoneLabel = $xpath->query("//td[div[text()='旅客電話']]/following-sibling::td/div/span");
if ($phoneLabel->length > 0) $customerPhone = trim($phoneLabel->item(0)->nodeValue);

$remarkLabel = $xpath->query("//td[div[text()='特殊需求']]/following-sibling::td/div/span");
if ($remarkLabel->length > 0) $remark = trim($remarkLabel->item(0)->nodeValue);


// 3. 漂亮地把資料呈現在網頁上
echo "<h2 style='font-family: sans-serif; color: #642100;'>🎉 到處去玩商旅 - 訂單自動擷取測試成功！</h2>";
echo "<table border='1' cellpadding='10' style='border-collapse:collapse; font-family:sans-serif; width: 500px; border-color: #cecece;'>";
echo "<tr style='background-color:#e1f4ff;'><th>資料欄位</th><th>擷取結果</th></tr>";
echo "<tr><td><b>預訂平台</b></td><td>{$platform}</td></tr>";
echo "<tr><td><b>OTA 訂單編號</b></td><td>{$otaNumber}</td></tr>";
echo "<tr><td><b>奧丁丁內部編號</b></td><td>{$owlNestNumber}</td></tr>";
echo "<tr><td><b>入住日期</b></td><td>{$checkIn}</td></tr>";
echo "<tr><td><b>退房日期</b></td><td>{$checkOut}</td></tr>";
echo "<tr><td><b>付款金額</b></td><td>TWD " . number_format($amount) . "</td></tr>";
echo "<tr><td><b>預訂人姓名</b></td><td>{$customerName}</td></tr>";
echo "<tr><td><b>聯絡電話</b></td><td>{$customerPhone}</td></tr>";
echo "<tr style='color: #c0392b;'><td><b>客房備註 (特殊需求)</b></td><td>{$remark}</td></tr>";
echo "</table>";
?>