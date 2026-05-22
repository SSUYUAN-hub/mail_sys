<?php
// order_parser.php

class OrderParser {
    public static function parse($htmlContent, $subject) {
        $doc = new DOMDocument();
        libxml_use_internal_errors(true);

        if (empty(trim($htmlContent))) {
            return self::getEmptyTemplate('純文字信件或空信');
        }

        $doc->loadHTML('<?xml encoding="UTF-8">' . $htmlContent);
        libxml_clear_errors();
        $xpath = new DOMXPath($doc);

        $data = self::getEmptyTemplate('未知平台');

        $isCancelled = (
            mb_strpos($subject, '取消') !== false ||
            mb_strpos($htmlContent, 'CANCELLED') !== false ||
            mb_strpos($htmlContent, '取消') !== false
        );

        // 全文純文字（提前定義供過濾判斷使用）
        $allText = $doc->documentElement->nodeValue;

        // 1-A. 優先排除已知非訂房來源
        // Airbnb 訊息通知（非訂房確認）過濾
        // 訂房確認特徵：含 booking_id 或 check_in 相關 data 屬性
        $isAirbnbMessage = (
            mb_strpos($htmlContent, 'muscache.com') !== false ||
            mb_strpos($subject, 'Airbnb') !== false
        ) && (
            mb_strpos($htmlContent, 'MESSAGING_NEW_MESSAGE') !== false ||
            mb_strpos($htmlContent, '回覆此電子郵件') !== false ||
            mb_strpos($htmlContent, 'thread_type=home_booking') !== false
        );

        // Agoda 訊息通知（非訂房確認）過濾
        $isAgodaMessage = (
            mb_strpos($htmlContent, 'notice-header-message') !== false ||
            mb_strpos($htmlContent, 'hermes-property-email') !== false ||
            mb_strpos($htmlContent, 'hermes-guest-email') !== false ||
            mb_strpos($htmlContent, 'special_request') !== false ||
            (mb_strpos($htmlContent, 'agoda.com') !== false &&
             mb_strpos($htmlContent, 'ltrBookingIDValue') === false &&
             mb_strpos($htmlContent, 'OwlNest') === false &&
             mb_strpos($htmlContent, 'owlting') === false &&
             mb_strpos($htmlContent, 'Hotel Voucher') === false &&
             $xpath->query("//span[@id='arrival']")->length === 0)
        );
        // Trip.com 訊息通知（特殊需求回覆、行程更新等，非訂房確認）
        // 識別特徵：有「特殊需求回覆」標題 或 header 區塊含 special-result
        $isTripMessage = (
            mb_strpos($htmlContent, 'c-ctrip.com') !== false ||
            mb_strpos($htmlContent, 'trip.com/forward') !== false
        ) && (
            mb_strpos($allText, '特殊需求回覆') !== false ||
            mb_strpos($allText, 'response to their') !== false ||
            $xpath->query("//table[@data-name='cancel-head-tip']//span[contains(.,'特殊需求')]")->length > 0 ||
            $xpath->query("//table[@data-name='cancel-head-tip']//span[contains(.,'special request')]")->length > 0
        );

        if ($isAirbnbMessage || $isAgodaMessage || $isTripMessage) {
            $data['platform'] = '系統過濾信件';
            $data['remark'] = '此信件為平台訊息通知，非訂單確認，已自動過濾。';
            return $data;
        }

        // 1-B. 過濾非訂房信件
        $isReservation = (
            mb_strpos($subject, '訂單') !== false ||
            mb_strpos($subject, '預訂') !== false ||
            mb_strpos($subject, 'Reservation') !== false ||
            mb_strpos($subject, '已確認') !== false ||
            mb_strpos($subject, 'Booking ID') !== false ||
            mb_strpos($subject, 'CANCELLED') !== false ||
            mb_strpos($htmlContent, 'Hotel Voucher') !== false
        );

        if (!$isReservation) {
            $data['platform'] = '系統過濾信件';
            $data['remark'] = '此信件非訂單通知（可能是廣告、通知或對帳單）。';
            return $data;
        }

        // 2. 判定平台
        if (mb_strpos($htmlContent, 'muscache.com') !== false || mb_strpos($subject, 'Airbnb') !== false) {
            $data['platform'] = $isCancelled ? 'Airbnb (取消)' : 'Airbnb';
        } elseif (mb_strpos($subject, 'Booking.com') !== false || mb_strpos($htmlContent, 'Booking.com') !== false) {
            $data['platform'] = $isCancelled ? 'Booking (取消)' : 'Booking.com';
        } elseif (mb_strpos($subject, 'Agoda') !== false || mb_strpos($htmlContent, 'Agoda') !== false || mb_strpos($htmlContent, 'agoda') !== false) {
            $data['platform'] = $isCancelled ? 'Agoda (取消)' : 'Agoda';
        } elseif (
            mb_strpos($subject, 'Trip.com') !== false ||
            mb_strpos($subject, 'CTrip') !== false ||
            mb_strpos($htmlContent, 'trip.com') !== false ||
            mb_strpos($subject, '已確認訂單編號') !== false ||
            mb_strpos($htmlContent, '攜程') !== false
        ) {
            $data['platform'] = $isCancelled ? 'Trip.com (取消)' : 'Trip.com';
        }

        // ============================================================
        // 格式偵測
        // A = OwlTing 轉發信 (div label + td span 結構)
        // B = Agoda 直發取消信 (span#arrival, span#first_name)
        // C = Agoda Hotel Voucher (span#ltrBookingIDValue, span#lblCustomerArrival)
        // ============================================================
        $isBookingDirect = (
            mb_strpos($htmlContent, 'admin.booking.com') !== false ||
            mb_strpos($htmlContent, 'bstatic.com') !== false
        ) && mb_strpos($htmlContent, 'OwlNest') === false && mb_strpos($htmlContent, 'owlting') === false;
        $isVoucher      = $xpath->query("//span[@id='ltrBookingIDValue']")->length > 0;
        $isDirectCancel = $xpath->query("//span[@id='arrival']")->length > 0;
        $isTripDirect   = (
            mb_strpos($htmlContent, 'c-ctrip.com') !== false ||
            mb_strpos($htmlContent, 'trip.com/forward') !== false
        ) && $xpath->query("//table[@data-name='cancel-order-info']")->length > 0;

        // ---- 2b. 入住天數 / 加床（全格式通用）----
        // 入住天數：OwlTing 格式用 label 查找（最準確）
        $nightsVal = self::findValueByLabel($xpath, '入住天數');
        if ($nightsVal !== '無' && preg_match('/(\d+)/', $nightsVal, $m)) {
            $data['nights'] = $m[1] . '晚';
        } else {
            // Trip.com 直發：住宿日期：... | 1 晚
            if (preg_match('/[|｜]\s*(\d+)\s*(?:晚|night)/iu', $allText, $m)) {
                $data['nights'] = $m[1] . '晚';
            // Agoda Voucher / 直發取消信：從日期計算
            } elseif ($data['check_in'] !== '無' && $data['check_out'] !== '無'
                      && preg_match('/^\d{4}-\d{2}-\d{2}$/', $data['check_in'])
                      && preg_match('/^\d{4}-\d{2}-\d{2}$/', $data['check_out'])) {
                $ts_in  = strtotime($data['check_in']);
                $ts_out = strtotime($data['check_out']);
                if ($ts_in && $ts_out && $ts_out > $ts_in) {
                    $data['nights'] = round(($ts_out - $ts_in) / 86400) . '晚';
                }
            }
        }

        // 加床（Agoda Voucher 格式）
        $extraBedNode = $xpath->query("//span[@id='lblNumberExtrabedsData_lblMain']");
        if ($extraBedNode->length > 0) {
            $val = trim($extraBedNode->item(0)->nodeValue);
            $data['extra_bed'] = ($val > 0) ? $val . '張' : '無';
        } elseif (preg_match('/(?:No\.\s*of\s*Extra\s*Bed|加床數)[^0-9]*(\d+)/iu', $allText, $m)) {
            $data['extra_bed'] = ($m[1] > 0) ? $m[1] . '張' : '無';
        } elseif (preg_match('/Extra\s*Bed[^0-9]*(\d+)/iu', $allText, $m)) {
            $data['extra_bed'] = ($m[1] > 0) ? $m[1] . '張' : '無';
        }

        // ---- 3. OTA 訂單編號 ----
        if ($isBookingDirect) {
            // Booking.com 直發：從 <h4> 或 <title> 抓訂單號
            $h4Nodes = $xpath->query("//h4");
            foreach ($h4Nodes as $h4) {
                if (preg_match('/(\d{8,})/', trim($h4->nodeValue), $m)) {
                    $data['ota_number'] = $m[1];
                    break;
                }
            }
            if ($data['ota_number'] === '無') {
                $titleNode = $xpath->query("//title");
                if ($titleNode->length > 0 && preg_match('/(\d{8,})/', $titleNode->item(0)->nodeValue, $m))
                    $data['ota_number'] = $m[1];
            }
        } elseif ($isVoucher) {
            $node = $xpath->query("//span[@id='ltrBookingIDValue']");
            if ($node->length > 0) $data['ota_number'] = trim($node->item(0)->nodeValue);
        } elseif ($isTripDirect) {
            // Trip.com 直發：預約編號 1128148114706430
            if (preg_match('/(?:預約編號|Reservation no\.)\s*(\d{10,})/u', $allText, $m))
                $data['ota_number'] = trim($m[1]);
        } elseif ($isDirectCancel) {
            if (preg_match('/Booking\s*ID\s*:\s*(\d+)/i', $allText, $m))
                $data['ota_number'] = trim($m[1]);
        } else {
            // OwlTing
            if (preg_match('/OTA訂單編號為:\s*([A-Za-z0-9_\-]+)/u', $allText, $m))
                $data['ota_number'] = trim($m[1]);
            elseif (preg_match('/訂單編號為:\s*([A-Za-z0-9_\-]+)/u', $allText, $m))
                $data['ota_number'] = trim($m[1]);
            elseif (preg_match('/#(\d+)#/u', $subject, $m))
                $data['ota_number'] = trim($m[1]);
        }

        // ---- 4. 奧丁丁內部編號（主旨抓 OBE）----
        if (preg_match('/OBE\w+/u', $subject, $m))
            $data['owl_number'] = trim($m[0]);
        elseif (preg_match('/訂單編號：\s*(OBE\w+)/u', $allText, $m))
            $data['owl_number'] = trim($m[1]);

        // ---- 5. 日期 ----
        if ($isVoucher) {
            // span#lblCustomerArrival / span#lblCustomerDeparture
            // 格式："8-Aug-2026 (8-08-2026)" → 抓括號內 d-m-Y
            $arrNode = $xpath->query("//span[@id='lblCustomerArrival']");
            $depNode = $xpath->query("//span[@id='lblCustomerDeparture']");
            if ($arrNode->length > 0 && $depNode->length > 0) {
                $data['check_in']  = self::parseDashDate(trim($arrNode->item(0)->nodeValue));
                $data['check_out'] = self::parseDashDate(trim($depNode->item(0)->nodeValue));
            }
        } elseif ($isBookingDirect) {
            // Booking.com 直發：title 含 "2026年6月27日"，只有入住日
            $titleNode = $xpath->query("//title");
            if ($titleNode->length > 0) {
                preg_match_all('/\d{4}年\d{1,2}月\d{1,2}日/', $titleNode->item(0)->nodeValue, $dm);
                if (isset($dm[0][0])) $data['check_in']  = self::parseCJKDate($dm[0][0]);
                if (isset($dm[0][1])) $data['check_out'] = self::parseCJKDate($dm[0][1]);
                // 退房日通常沒有，標記為需查詢
                if ($data['check_out'] === '無') $data['check_out'] = '請查後台';
            }
        } elseif ($isTripDirect) {
            // Trip.com: 住宿日期：2026 年 10 月 29 日 - 2026 年 10 月 30 日
            if (preg_match('/(?:住宿日期|Staying period)[：:]\s*(.+?)\s*[\|\|]/u', $allText, $m)) {
                $parts = preg_split('/\s*-\s*/', $m[1]);
                if (count($parts) >= 2) {
                    $data['check_in']  = self::parseCJKDate(trim($parts[0]));
                    $data['check_out'] = self::parseCJKDate(trim($parts[1]));
                }
            } elseif (preg_match_all('/\d{4}\s*年\s*\d{1,2}\s*月\s*\d{1,2}\s*日/', $allText, $dm)) {
                if (isset($dm[0][0])) $data['check_in']  = self::parseCJKDate($dm[0][0]);
                if (isset($dm[0][1])) $data['check_out'] = self::parseCJKDate($dm[0][1]);
            }
        } elseif ($isDirectCancel) {
            $arrNode = $xpath->query("//span[@id='arrival']");
            $depNode = $xpath->query("//span[@id='departure']");
            if ($arrNode->length > 0 && $depNode->length > 0) {
                $arr = preg_replace('/^.*?:\s*/u', '', trim($arrNode->item(0)->nodeValue));
                $dep = preg_replace('/^.*?:\s*/u', '', trim($depNode->item(0)->nodeValue));
                $data['check_in']  = self::parseEnglishDate($arr);
                $data['check_out'] = self::parseEnglishDate($dep);
            }
        } else {
            // OwlTing: color:#087abc div
            $dateNodes = $xpath->query("//div[contains(@style,'color:#087abc') or contains(@style,'color: #087abc')]");
            $dates = [];
            foreach ($dateNodes as $node) {
                $txt = trim($node->nodeValue);
                if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $txt)) $dates[] = $txt;
            }
            if (count($dates) >= 2) {
                $data['check_in']  = $dates[0];
                $data['check_out'] = $dates[1];
            } elseif (preg_match_all('/\d{4}-\d{2}-\d{2}/', $allText, $dm)) {
                if (isset($dm[0][0])) $data['check_in']  = $dm[0][0];
                if (isset($dm[0][1])) $data['check_out'] = $dm[0][1];
            }
        }

        // ---- 6. 金額 ----
        $amount = 0;
        if ($isVoucher) {
            // span#lblAmountPayableData "TWD 4,700.05"
            $amtNode = $xpath->query("//span[@id='lblAmountPayableData']");
            if ($amtNode->length > 0) {
                $txt = trim($amtNode->item(0)->nodeValue);
                if (preg_match('/TWD\s*([\d,\.]+)/i', $txt, $m))
                    $amount = (int)str_replace([',', '.'], '', $m[1]) / 100; // 含小數，取整
                    // 實際上 4700.05 → str_replace → 470005 / 100 = 4700，正確
                    $amount = (int)round((float)str_replace(',', '', preg_replace('/TWD\s*/i', '', $txt)));
            }
        } elseif ($isTripDirect) {
            // Trip.com: 找「您的收入」旁的 TWD XXXX.XX
            if (preg_match('/(?:您的收入|Your payout)[^T]*TWD[^>]*>([\d,\.]+)/u', $allText, $m)) {
                $amount = (int)round((float)str_replace(',', '', $m[1]));
            } elseif (preg_match('/TWD.*?([\d,]+\.\d{2})/u', $allText, $m)) {
                $amount = (int)round((float)str_replace(',', '', $m[1]));
            }
        } else {
            $amount = self::findAmountByLabel($xpath, '訂單款項');
            if ($amount <= 0) $amount = self::findAmountByLabel($xpath, '已收金額');
            if ($amount <= 0) {
                $amountNodes = $xpath->query("//span[contains(.,'TWD')] | //div[contains(.,'TWD')]");
                foreach ($amountNodes as $node) {
                    $txt = trim($node->nodeValue);
                    if (mb_strpos($txt, '取消預訂費用') !== false) continue;
                    if (mb_strpos($txt, 'Cancellation') !== false) continue;
                    // 排除含多個 TWD 的父層節點（巢狀累加）
                    if (substr_count($txt, 'TWD') > 1) continue;
                    if (preg_match('/TWD\s*([\d,\.]+)/u', $txt, $m)) {
                        $val = (int)round((float)str_replace(',', '', $m[1]));
                        if ($val > 0 && $val < 500000) { $amount = $val; break; }
                    }
                }
            }
        }
        $data['amount'] = $amount;

        // ---- 7. 旅客姓名 ----
        if ($isBookingDirect) {
            $data['customer_name']  = '請查後台';
            $data['customer_phone'] = '請查後台';
            $data['amount']         = 0;
            $data['remark']         = '此封為 Booking.com 系統通知，詳細資訊請登入後台查看。';
        } elseif ($isTripDirect) {
            // Trip.com: 旅客姓名：LIANG/HUNGYU,CHANG/YUTING
            if (preg_match('/(?:旅客姓名|Guest Name)[：:]\s*([A-Z\/,\s\-]+)/u', $allText, $m))
                $data['customer_name'] = trim($m[1]);
        } elseif ($isVoucher) {
            // span#ltrCustomerFirstNameValue / span#ltrCustomerLastNameValue
            $fnNode = $xpath->query("//span[@id='ltrCustomerFirstNameValue']");
            $lnNode = $xpath->query("//span[@id='ltrCustomerLastNameValue']");
            if ($fnNode->length > 0 && $lnNode->length > 0) {
                $fn = trim($fnNode->item(0)->nodeValue);
                $ln = trim($lnNode->item(0)->nodeValue);
                $data['customer_name'] = $ln . ' ' . $fn;
            }
        } elseif ($isDirectCancel) {
            $fnNode = $xpath->query("//span[@id='first_name']");
            $lnNode = $xpath->query("//span[@id='last_name']");
            if ($fnNode->length > 0 && $lnNode->length > 0) {
                $fn = preg_replace('/^.*?:\s*/u', '', trim($fnNode->item(0)->nodeValue));
                $ln = preg_replace('/^.*?:\s*/u', '', trim($lnNode->item(0)->nodeValue));
                $data['customer_name'] = trim($ln . ' ' . $fn);
            }
        } else {
            $data['customer_name'] = self::findValueByLabel($xpath, '旅客姓名');
        }

        // ---- 8. 電話 ----
        $data['customer_phone'] = self::findValueByLabel($xpath, '旅客電話');

        // ---- 9. 備註 ----
        if ($isTripDirect) {
            $parts = [];
            if (preg_match('/(?:房型|Room Type)[：:]\s*([^
<]+)/u', $allText, $m))
                $parts[] = '房型：' . trim($m[1]);
            if (preg_match('/(?:抵達時間|Arrival time)[：:]\s*([^
<]+)/u', $allText, $m))
                $parts[] = '抵達：' . trim($m[1]);
            $data['remark'] = $parts ? implode(' / ', $parts) : '無';
        } elseif ($isVoucher) {
            // 房型 + 優惠方案
            $roomNode    = $xpath->query("//span[@id='lblRoomTypeData_lblMain']");
            $offerNode   = $xpath->query("//span[@id='lblOfferText']");
            $benefitNode = $xpath->query("//span[@id='BenefitsListId_lblMain']");
            $parts = [];
            if ($roomNode->length > 0)    $parts[] = '房型：' . trim($roomNode->item(0)->nodeValue);
            if ($offerNode->length > 0)   $parts[] = trim($offerNode->item(0)->nodeValue);
            if ($benefitNode->length > 0) $parts[] = '設施：' . trim($benefitNode->item(0)->nodeValue);
            $data['remark'] = $parts ? implode(' / ', $parts) : '無';
        } elseif ($isDirectCancel) {
            $noteNode = $xpath->query("//span[@id='note']");
            $roomNode = $xpath->query("//span[@id='room_type']");
            $parts = [];
            if ($roomNode->length > 0) {
                $rt = preg_replace('/^Room Type\s*:\s*/i', '', trim($roomNode->item(0)->nodeValue));
                if ($rt) $parts[] = '房型：' . $rt;
            }
            if ($noteNode->length > 0) {
                $nt = preg_replace('/^Notes\s*:\s*/i', '', trim($noteNode->item(0)->nodeValue));
                if ($nt) $parts[] = '備註：' . $nt;
            }
            $data['remark'] = $parts ? implode(' / ', $parts) : '無';
        } else {
            $data['remark'] = self::findValueByLabel($xpath, '特殊需求');
        }

        return $data;
    }

    // "8-Aug-2026 (8-08-2026)" → "2026-08-08"
    private static function parseDashDate($str) {
        // 先試括號內 d-m-Y
        if (preg_match('/\((\d{1,2})-(\d{2})-(\d{4})\)/', $str, $m))
            return sprintf('%s-%s-%02d', $m[3], $m[2], (int)$m[1]);
        // 試 d-Mon-Y
        if (preg_match('/(\d{1,2})-([A-Za-z]+)-(\d{4})/', $str, $m)) {
            $ts = strtotime($m[1] . ' ' . $m[2] . ' ' . $m[3]);
            if ($ts) return date('Y-m-d', $ts);
        }
        return trim($str);
    }

    // "2026 年 10 月 29 日" → "2026-10-29"
    private static function parseCJKDate($str) {
        $str = trim($str);
        if (preg_match('/(\d{4})\s*年\s*(\d{1,2})\s*月\s*(\d{1,2})\s*日/', $str, $m))
            return sprintf('%s-%02d-%02d', $m[1], (int)$m[2], (int)$m[3]);
        // 英文格式 fallback: "Oct 29, 2026"
        $ts = strtotime($str);
        if ($ts) return date('Y-m-d', $ts);
        return $str;
    }

    // "August 8, 2026" → "2026-08-08"
    private static function parseEnglishDate($str) {
        $str = trim($str);
        $ts = strtotime($str);
        if ($ts) return date('Y-m-d', $ts);
        return $str;
    }

    // OwlTing 結構：div label → 上層 td → 下一個 td
    private static function findValueByLabel($xpath, $label) {
        $nodes = $xpath->query("//div[normalize-space(text())='{$label}']");
        if ($nodes->length > 0) {
            $labelTd = $nodes->item(0)->parentNode;
            $sibling = $labelTd->nextSibling;
            while ($sibling && $sibling->nodeType !== XML_ELEMENT_NODE)
                $sibling = $sibling->nextSibling;
            if ($sibling) {
                $txt = trim($sibling->nodeValue);
                if (!empty($txt)) return $txt;
            }
        }
        $nodes2 = $xpath->query("//td[contains(.,'{$label}')]");
        if ($nodes2->length > 0) {
            $sibling = $nodes2->item(0)->nextSibling;
            while ($sibling && $sibling->nodeType !== XML_ELEMENT_NODE)
                $sibling = $sibling->nextSibling;
            if ($sibling) {
                $txt = trim($sibling->nodeValue);
                if (!empty($txt) && $txt !== $label) return $txt;
            }
        }
        return '無';
    }

    private static function findAmountByLabel($xpath, $label) {
        $nodes = $xpath->query("//div[normalize-space(text())='{$label}']");
        if ($nodes->length > 0) {
            $labelTd = $nodes->item(0)->parentNode;
            $sibling = $labelTd->nextSibling;
            while ($sibling && $sibling->nodeType !== XML_ELEMENT_NODE)
                $sibling = $sibling->nextSibling;
            if ($sibling) {
                $txt = trim($sibling->nodeValue);
                if (preg_match('/TWD\s*([\d,\.]+)/u', $txt, $m))
                    return (int)round((float)str_replace(',', '', $m[1]));
                if (preg_match('/([\d,]+)/', $txt, $m)) {
                    $val = (int)str_replace(',', '', $m[1]);
                    if ($val > 0) return $val;
                }
            }
        }
        return 0;
    }

    private static function getEmptyTemplate($defaultPlatform) {
        return [
            'platform'       => $defaultPlatform,
            'ota_number'     => '無',
            'owl_number'     => '無',
            'check_in'       => '無',
            'check_out'      => '無',
            'nights'         => '無',
            'extra_bed'      => '無',
            'amount'         => 0,
            'customer_name'  => '無',
            'customer_phone' => '無',
            'remark'         => '無'
        ];
    }

    // 民國年轉換：2026-08-10 → 115年8月10日
    public static function toROCDate($dateStr) {
        if (!$dateStr || $dateStr === '無' || $dateStr === '請查後台') return $dateStr;
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $dateStr, $m)) {
            $roc  = (int)$m[1] - 1911;
            $mon  = (int)$m[2];
            $day  = (int)$m[3];
            return "{$roc}年{$mon}月{$day}日";
        }
        return $dateStr;
    }
}
