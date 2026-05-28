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

    /**
     * 偵測 INBOX 下的第一層子信件匣清單
     * 回傳陣列，每筆包含：
     *   - imap_name   : 原始 IMAP 名稱（含 UTF-7 編碼，存入 DB 用）
     *   - display_name: 解碼後的可讀名稱（顯示用）
     */
    public function fetchMainMailboxes(): array {
        $conn = imap_open($this->server, $this->username, $this->password);
        if (!$conn) {
            throw new RuntimeException('IMAP 連線失敗：' . imap_last_error());
        }

        // % 只取第一層，不遞迴進子資料夾
        $raw = imap_list($conn, $this->server, 'INBOX/%');
        imap_close($conn);

        if (!$raw) return [];

        $result = [];
        foreach ($raw as $fullPath) {
            // 去掉 server prefix，只保留 INBOX/xxx 部分，再去掉開頭 /
            $relative = str_replace($this->server, '', $fullPath);
            $imapName = ltrim($relative, '/');

            // 直接使用手動解碼 Modified UTF-7（imap_utf8 不適用於資料夾名稱）
            $displayName = self::decodeModifiedUtf7($imapName);

            $result[] = [
                'imap_name'    => $imapName,
                'display_name' => $displayName,
            ];
        }

        // 依顯示名稱排序
        usort($result, fn($a, $b) => strcmp($a['display_name'], $b['display_name']));

        return $result;
    }

    /**
     * 手動解碼 IMAP Modified UTF-7 資料夾名稱
     * 格式：&<base64>- 代表一段非 ASCII 字元，其餘為純 ASCII
     */
    private static function decodeModifiedUtf7(string $str): string {
        return preg_replace_callback(
            '/&([^-]*)-/',
            function ($matches) {
                if ($matches[1] === '') return '&'; // &- 是跳脫的 &
                // Modified UTF-7 用 , 取代 /，先還原再 base64 解碼
                $base64  = str_replace(',', '/', $matches[1]);
                $decoded = base64_decode($base64);
                // 結果為 UTF-16BE，轉換成 UTF-8
                return mb_convert_encoding($decoded, 'UTF-8', 'UTF-16BE');
            },
            $str
        );
    }

    public function fetchUnreadMails(): array {
        $this->inbox = imap_open($this->server, $this->username, $this->password);
        if (!$this->inbox) {
            die('❌ IMAP 連線失敗: ' . imap_last_error());
        }

        $since  = date('d-M-Y', strtotime('-30 days'));
        $emails = imap_search($this->inbox, "SINCE \"{$since}\"");
        $mailList = [];

        if ($emails) {
            rsort($emails);

            foreach ($emails as $mailId) {
                $overview  = imap_fetch_overview($this->inbox, $mailId, 0);
                $structure = imap_fetchstructure($this->inbox, $mailId);

                $subject = mb_decode_mimeheader($overview[0]->subject);
                $from    = mb_decode_mimeheader($overview[0]->from);
                $date    = $overview[0]->date;

                $htmlBody = $this->getHtmlBody($this->inbox, $mailId, $structure);

                $mailList[] = [
                    'id'        => $mailId,
                    'from'      => $from,
                    'subject'   => $subject,
                    'date'      => $date,
                    'html_body' => $htmlBody,
                ];
            }
        }

        imap_close($this->inbox);
        return $mailList;
    }

    /**
     * 將信件移動到指定使用者的平台子信件匣
     *
     * @param int    $mailId       IMAP 信件 ID
     * @param string $mailboxImap  使用者主信件匣原始名稱（如 Lin 或 &WWdOAU4B-）
     * @param string $platformFolder 平台子信件匣名稱（如 Booking、CT）
     * @return bool
     */
    public function archiveMail(int $mailId, string $mailboxImap, string $platformFolder): bool {
        $conn = imap_open($this->server, $this->username, $this->password);
        if (!$conn) {
            throw new RuntimeException('IMAP 連線失敗：' . imap_last_error());
        }

        // 列出目標信件匣下所有子資料夾，找出實際存在的路徑
        // Mail2000 可能用 . 或 / 作為分隔符，動態偵測
        $parentFolder = $mailboxImap; // 如 INBOX/&YB2QYA-

        // 列出 parentFolder 下第一層子資料夾
        $subList = imap_list($conn, $this->server, $parentFolder . '/%');
        if (!$subList) {
            // 嘗試用 . 分隔
            $subList = imap_list($conn, $this->server, $parentFolder . '.%');
        }

        $targetFolder = null;
        if ($subList) {
            foreach ($subList as $fullPath) {
                $rel = str_replace($this->server, '', $fullPath);
                $rel = ltrim($rel, '/');
                // 取最後一段比對平台資料夾名稱
                $parts = preg_split('/[\/\.]/', $rel);
                $last  = end($parts);
                if (strcasecmp($last, $platformFolder) === 0) {
                    $targetFolder = $rel;
                    break;
                }
            }
        }

        // 若動態偵測失敗，退回預設路徑
        if (!$targetFolder) {
            $targetFolder = $mailboxImap . '/' . $platformFolder;
        }

        $moved = imap_mail_move($conn, (string)$mailId, $targetFolder);
        if ($moved) {
            imap_expunge($conn);
        } else {
            // 回傳更詳細的錯誤供除錯
            $lastErr = imap_last_error();
            imap_close($conn);
            throw new RuntimeException('IMAP 移動失敗，目標路徑：' . $targetFolder . '，錯誤：' . $lastErr);
        }

        imap_close($conn);
        return $moved;
    }

    private function getHtmlBody($inbox, $mailId, $structure, $partNum = '') {
        if ($structure->type == 1) {
            foreach ($structure->parts as $index => $subPart) {
                $newPartNum = empty($partNum) ? ($index + 1) : $partNum . '.' . ($index + 1);
                $body = $this->getHtmlBody($inbox, $mailId, $subPart, $newPartNum);
                if ($body) return $body;
            }
        } elseif ($structure->subtype == 'HTML') {
            $body = imap_fetchbody($inbox, $mailId, empty($partNum) ? 1 : $partNum);
            if ($structure->encoding == 3) return base64_decode($body);
            if ($structure->encoding == 4) return quoted_printable_decode($body);
            return $body;
        }
        return false;
    }
}
