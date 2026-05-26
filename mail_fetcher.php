<?php
class MailFetcher {
    private $inbox;
    private $server;
    private $username;
    private $password;

    public function __construct() {
        $this->server   = getenv('MAIL_SERVER');
        $this->username = getenv('MAIL_USERNAME');
        $this->password = getenv('MAIL_PASSWORD');
    }

    public function fetchUnreadMails() {
    // 先印出環境變數確認
    // echo 'SERVER: ' . $this->server . '<br>';
    // echo 'USER: ' . $this->username . '<br>';
    // echo 'PASS: ' . (empty($this->password) ? '❌ 空的' : '✅ 有值') . '<br>';

    $this->inbox = imap_open($this->server, $this->username, $this->password);
    if (!$this->inbox) {
        die('❌ IMAP 連線失敗: ' . imap_last_error());
    }

            
        // 搜尋未讀信件 (UNSEEN)
        $since = date('d-M-Y', strtotime('-30 days'));
$emails = imap_search($this->inbox, "SINCE \"{$since}\"");
        $mailList = [];

        if ($emails) {
            // 排序：從最新的信件開始處理
            rsort($emails);

            foreach ($emails as $mailId) {
                $overview = imap_fetch_overview($this->inbox, $mailId, 0);
                $structure = imap_fetchstructure($this->inbox, $mailId);
                
                // 使用 mb_decode_mimeheader 解決主旨亂碼
                $subject = mb_decode_mimeheader($overview[0]->subject);
                $from = mb_decode_mimeheader($overview[0]->from);
                $date = $overview[0]->date;

                // 抓取信件內文 (HTML 格式)
                $htmlBody = $this->getHtmlBody($this->inbox, $mailId, $structure);

                $mailList[] = [
                    'id' => $mailId,
                    'from' => $from,
                    'subject' => $subject,
                    'date' => $date,
                    'html_body' => $htmlBody
                ];
            }
        }

        imap_close($this->inbox);
        return $mailList;
    }

    // 遞迴解析信件結構，精準撈出 HTML 內文
    private function getHtmlBody($inbox, $mailId, $structure, $partNum = "") {
        if ($structure->type == 1) { // Multipart
            foreach ($structure->parts as $index => $subPart) {
                $newPartNum = empty($partNum) ? ($index + 1) : $partNum . '.' . ($index + 1);
                $body = $this->getHtmlBody($inbox, $mailId, $subPart, $newPartNum);
                if ($body) return $body;
            }
        } elseif ($structure->subtype == 'HTML') {
            $body = imap_fetchbody($inbox, $mailId, empty($partNum) ? 1 : $partNum);
            if ($structure->encoding == 3) return base64_decode($body); // Base64 記憶體解碼
            if ($structure->encoding == 4) return quoted_printable_decode($body); // Quoted-Printable 解碼
            return $body;
        }
        return false;
    }

    // 提供三種不同平台、不同狀況的真實模擬信件
    private function getMockMails() {
        return [
            [
                'id' => 19,
                'from' => 'OwlNest_Booking <ownlest@owlting.com>',
                'subject' => 'Booking.com 訂單成立通知_(訂單編號_OBE94670282026052112)*此信件由系統自動發送，請勿直接回信',
                'date' => date('Y-m-d H:i:s'),
                'html_body' => $this->getBookingMockHtml() 
            ],
            [
                'id' => 17,
                'from' => 'OwlNest_Booking <ownlest@owlting.com>',
                'subject' => 'Agoda 訂單取消通知_(訂單編號_OBE72020282026052111)*此信件由系統自動發送，請勿直接回信',
                'date' => date('Y-m-d H:i:s', strtotime('-5 mins')),
                'html_body' => $this->getAgodaCancelMockHtml() 
            ],
            [
                'id' => 14,
                'from' => 'noreply_htl@trip.com',
                'subject' => '已確認訂單編號_#1359044754534463#//Booking_no._#1359044754534463#_accepted#1359044754534463#',
                'date' => date('Y-m-d H:i:s', strtotime('-15 mins')),
                'html_body' => $this->getTripMockHtml() 
            ]
        ];
    }

    private function getBookingMockHtml() {
        return '
        <div style="text-align:center; color:#642100; font-size:30px; font-weight:bold;">來自 Booking.com 的新訂單！</div>
        <div>稍早透過 Booking.com 獲得一筆新訂單，OTA訂單編號為: 5869639335，預訂資訊如下：</div>
        <div>訂單編號： OBE94670282026052112</div>
        <table>
            <tr><td><div style="color:#087abc;">2026-07-15</div></td><td><div style="color:#087abc;">2026-07-17</div></td></tr>
        </table>
        <span>TWD 2,353</span>
        <table>
            <tr><td>旅客姓名</td><td><div><span>施佳賢</span></div></td></tr>
            <tr><td>旅客電話</td><td><div><span>+886**** 509 972</span></div></td></tr>
            <tr><td>特殊需求</td><td><div><span>Guest Name: 施 佳賢, 有帶一隻紅貴賓約2kg以內，不會隨地大小便</span></div></td></tr>
        </table>';
    }

    private function getAgodaCancelMockHtml() {
        return '
        <div style="text-align:center; color:#c0392b; font-size:30px; font-weight:bold;">Agoda 訂單取消通知 (CANCELLED)</div>
        <div>您的奧丁丁通道已收到一筆取消，OTA訂單編號為: 1729785363</div>
        <div>訂單編號： OBE72020282026052111</div>
        <table>
            <tr><td><div style="color:#087abc;">2026-08-20</div></td><td><div style="color:#087abc;">2026-08-22</div></td></tr>
        </table>
        <span>TWD 0</span>
        <table>
            <tr><td>旅客姓名</td><td><div><span>林大華</span></div></td></tr>
            <tr><td>旅客電話</td><td><div><span>+886**** 123 456</span></div></td></tr>
            <tr><td>特殊需求</td><td><div><span>【訂單已取消】旅客因行程變更取消此筆預訂。</span></div></td></tr>
        </table>';
    }

    private function getTripMockHtml() {
        return '
        <div style="font-size:24px; color:#ff9900;">Trip.com 訂單確認成功</div>
        <div>親愛的旅宿夥伴，攜程/Trip.com 訂單編號為: 1359044754534463 </div>
        <div>訂單編號： OBE56990282226052112</div>
        <p>入住日期：2026-10-01 / 退房日期：2026-10-05</p>
        <div>訂單總額: TWD 5,800 </div>
        <table>
            <tr><td>旅客姓名</td><td><span>陳小美 (Chen Xiao Mei)</span></td></tr>
            <tr><td>旅客電話</td><td><span>+86**** 1399 999</span></td></tr>
            <tr><td>特殊需求</td><td><span>高樓層、禁菸房、需要兩大枕頭。</span></td></tr>
        </table>';
    }
}