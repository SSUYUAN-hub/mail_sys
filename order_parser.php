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
        $isAirbnbMessage = (
            mb_strpos($htmlContent, 'muscache.com') !== false ||
            mb_strpos($subject, 'Airbnb') !== false
        ) && (
            mb_strpos($htmlContent, 'MESSAGING_NEW_MESSAGE') !== false ||
            mb_strpos($htmlContent, '回覆此電子郵件') !== false ||
            mb_strpos($htmlContent, 'thread_type=home_booking') !== false
        );

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
            mb_strpos($subject, 'AsiaYo') !== false ||
            mb_strpos($subject, '訂房已確認') !== false ||
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
        } elseif (
            mb_strpos($htmlContent, 'asiayo.com') !== false ||
            mb_strpos($htmlContent, 'AsiaYo') !== false ||
            mb_strpos($subject, 'AsiaYo') !== false
        ) {
            $data['platform'] = $isCancelled ? 'AsiaYo (取消)' : 'AsiaYo';
        } elseif (
            mb_strpos($htmlContent, 'Expedia') !== false ||
            mb_strpos($subject, 'Expedia') !== false
        ) {
            $data['platform'] = $isCancelled ? 'Expedia (取消)' : 'Expedia';
        }

        // ============================================================
        // 格式偵測
        // A = OwlTing 轉發信 (div label + td span 結構)
        // B = Agoda 直發取消信 (span#arrival, span#first_name)
        // C = Agoda Hotel Voucher (span#ltrBookingIDValue, span#lblCustomerArrival)
        // D = AsiaYo 直發信 (asiayo.com, th>td 結構)
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

        // AsiaYo 直發：有 asiayo.com 且不是 OwlTing 轉發
        $isAsiaYoDirect = (
            mb_strpos($htmlContent, 'asiayo.com') !== false ||
            mb_strpos($subject, 'AsiaYo') !== false
        ) && mb_strpos($htmlContent, 'OwlNest') === false && mb_strpos($htmlContent, 'owlting') === false;

        // ---- 2b. 入住天數 / 加床（全格式通用）----
        $nightsVal = self::findValueByLabel($xpath, '入住天數');
        if ($nightsVal !== '無' && preg_match('/(\d+)/', $nightsVal, $m)) {
            $data['nights'] = $m[1] . '晚';
        } else {
            if (preg_match('/[|｜]\s*(\d+)\s*(?:晚|night)/iu', $allText, $m)) {
                $data['nights'] = $m[1] . '晚';
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

        // 加床
        $extraBedNode = $xpath->query("//span[@id='lblNumberExtrabedsData_lblMain']");
        if ($extraBedNode->length > 0) {
            $val = trim($extraBedNode->item(0)->nodeValue);
            $data['extra_bed'] = ($val > 0) ? $val . '張' : '無';
        } elseif (preg_match('/(?:No\.\s*of\s*Extra\s*Bed|加床數)[^0-9]*(\d+)/iu', $allText, $m)) {
            $data['extra_bed'] = ($m[1] > 0) ? $m[1] . '張' : '無';
        } elseif (preg_match('/Extra\s*Bed[^0-9]*(\d+)/iu', $allText, $m)) {
            $data['extra_bed'] = ($m[1] > 0) ? $m[1] . '張' : '無';
        }

        // ---- 3. 各格式分支解析 ----

        // ============================================================
        // AsiaYo 直發信
        // ============================================================
        if ($isAsiaYoDirect) {

            // OTA 訂單號
            if (preg_match('/訂單編號[：:]\s*(\d+)/u', $allText, $m))
                $data['ota_number'] = trim($m[1]);

            // 奧丁丁號：直發無
            $data['owl_number'] = '無';

            // 日期：格式 2026/05/21
            $dateNodes = $xpath->query("//td[@align='center']");
            $dates = [];
            foreach ($dateNodes as $node) {
                $txt = trim($node->nodeValue);
                if (preg_match('/^(\d{4})\/(\d{2})\/(\d{2})$/', $txt, $m))
                    $dates[] = sprintf('%s-%s-%s', $m[1], $m[2], $m[3]);
            }
            if (count($dates) >= 2) {
                $data['check_in']  = $dates[0];
                $data['check_out'] = $dates[1];
            }

            // 天數
            $nightsNodes = $xpath->query("//td[@align='center']");
            foreach ($nightsNodes as $node) {
                $txt = trim($node->nodeValue);
                if (preg_match('/^\d+$/', $txt) && (int)$txt < 60 && (int)$txt > 0) {
                    $data['nights'] = $txt . '晚';
                    break;
                }
            }

            // 金額：已付金額(扣除 AsiaYo 服務費)
            $rows = $xpath->query("//th[contains(.,'扣除 AsiaYo 服務費')]/following-sibling::td");
            if ($rows->length > 0) {
                $val = (int)trim(preg_replace('/[^\d]/', '', $rows->item(0)->nodeValue));
                if ($val > 0) $data['amount'] = $val;
            }

            // 旅客姓名
            $nameNode = $xpath->query("//th[text()='會員姓名']/following-sibling::td");
            if ($nameNode->length > 0)
                $data['customer_name'] = trim($nameNode->item(0)->nodeValue);

            // 電話
            $phoneNode = $xpath->query("//th[text()='行動電話']/following-sibling::td");
            if ($phoneNode->length > 0)
                $data['customer_phone'] = trim($phoneNode->item(0)->nodeValue);

            // 備註：特殊需求 + 入住時間
            $parts = [];
            $specialNode = $xpath->query("//th[text()='特殊需求']/following-sibling::td");
            if ($specialNode->length > 0) {
                $v = trim($specialNode->item(0)->nodeValue);
                if ($v) $parts[] = '特殊需求：' . $v;
            }
            $arrivalNode = $xpath->query("//th[text()='入住時間']/following-sibling::td");
            if ($arrivalNode->length > 0) {
                $v = trim($arrivalNode->item(0)->nodeValue);
                if ($v) $parts[] = '預計入住：' . $v;
            }
            $data['remark'] = $parts ? implode(' / ', $parts) : '無';

        } elseif ($isBookingDirect) {
            // Booking.com 直發
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

            $titleNode = $xpath->query("//title");
            if ($titleNode->length > 0) {
                preg_match_all('/\d{4}年\d{1,2}月\d{1,2}日/', $titleNode->item(0)->nodeValue, $dm);
                if (isset($dm[0][0])) $data['check_in']  = self::parseCJKDate($dm[0][0]);
                if (isset($dm[0][1])) $data['check_out'] = self::parseCJKDate($dm[0][1]);
                if ($data['check_out'] === '無') $data['check_out'] = '請查後台';
            }

            $data['customer_name']  = '請查後台';
            $data['customer_phone'] = '請查後台';
            $data['amount']         = 0;
            $data['remark']         = '此封為 Booking.com 系統通知，詳細資訊請登入後台查看。';

        } elseif ($isVoucher) {
            // Agoda Hotel Voucher
            $node = $xpath->query("//span[@id='ltrBookingIDValue']");
            if ($node->length > 0) $data['ota_number'] = trim($node->item(0)->nodeValue);

            $arrNode = $xpath->query("//span[@id='lblCustomerArrival']");
            $depNode = $xpath->query("//span[@id='lblCustomerDeparture']");
            if ($arrNode->length > 0 && $depNode->length > 0) {
                $data['check_in']  = self::parseDashDate(trim($arrNode->item(0)->nodeValue));
                $data['check_out'] = self::parseDashDate(trim($depNode->item(0)->nodeValue));
            }

            $amtNode = $xpath->query("//span[@id='lblAmountPayableData']");
            if ($amtNode->length > 0) {
                $txt = trim($amtNode->item(0)->nodeValue);
                $data['amount'] = (int)round((float)str_replace(',', '', preg_replace('/TWD\s*/i', '', $txt)));
            }

            $fnNode = $xpath->query("//span[@id='ltrCustomerFirstNameValue']");
            $lnNode = $xpath->query("//span[@id='ltrCustomerLastNameValue']");
            if ($fnNode->length > 0 && $lnNode->length > 0) {
                $fn = trim($fnNode->item(0)->nodeValue);
                $ln = trim($lnNode->item(0)->nodeValue);
                $data['customer_name'] = $ln . ' ' . $fn;
            }

            $roomNode    = $xpath->query("//span[@id='lblRoomTypeData_lblMain']");
            $offerNode   = $xpath->query("//span[@id='lblOfferText']");
            $benefitNode = $xpath->query("//span[@id='BenefitsListId_lblMain']");
            $parts = [];
            if ($roomNode->length > 0)    $parts[] = '房型：' . trim($roomNode->item(0)->nodeValue);
            if ($offerNode->length > 0)   $parts[] = trim($offerNode->item(0)->nodeValue);
            if ($benefitNode->length > 0) $parts[] = '設施：' . trim($benefitNode->item(0)->nodeValue);
            $data['remark'] = $parts ? implode(' / ', $parts) : '無';

        } elseif ($isTripDirect) {
            // Trip.com 直發
            if (preg_match('/(?:預約編號|Reservation no\.)\s*(\d{10,})/u', $allText, $m))
                $data['ota_number'] = trim($m[1]);

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

            if (preg_match('/[|｜]\s*(\d+)\s*(?:晚|night)/iu', $allText, $m))
                $data['nights'] = $m[1] . '晚';

            if (preg_match('/(?:您的收入|Your payout)[^T]*TWD[^>]*>([\d,\.]+)/u', $allText, $m)) {
                $data['amount'] = (int)round((float)str_replace(',', '', $m[1]));
            } elseif (preg_match('/TWD.*?([\d,]+\.\d{2})/u', $allText, $m)) {
                $data['amount'] = (int)round((float)str_replace(',', '', $m[1]));
            }

            if (preg_match('/(?:旅客姓名|Guest Name)[：:]\s*([A-Z\/,\s\-]+)/u', $allText, $m))
                $data['customer_name'] = trim($m[1]);

            $parts = [];
            if (preg_match('/(?:房型|Room Type)[：:]\s*([^\n<]+)/u', $allText, $m))
                $parts[] = '房型：' . trim($m[1]);
            if (preg_match('/(?:抵達時間|Arrival time)[：:]\s*([^\n<]+)/u', $allText, $m))
                $parts[] = '抵達：' . trim($m[1]);
            $data['remark'] = $parts ? implode(' / ', $parts) : '無';

        } elseif ($isDirectCancel) {
            // Agoda 直發取消信
            if (preg_match('/Booking\s*ID\s*:\s*(\d+)/i', $allText, $m))
                $data['ota_number'] = trim($m[1]);

            $arrNode = $xpath->query("//span[@id='arrival']");
            $depNode = $xpath->query("//span[@id='departure']");
            if ($arrNode->length > 0 && $depNode->length > 0) {
                $arr = preg_replace('/^.*?:\s*/u', '', trim($arrNode->item(0)->nodeValue));
                $dep = preg_replace('/^.*?:\s*/u', '', trim($depNode->item(0)->nodeValue));
                $data['check_in']  = self::parseEnglishDate($arr);
                $data['check_out'] = self::parseEnglishDate($dep);
            }

            $fnNode = $xpath->query("//span[@id='first_name']");
            $lnNode = $xpath->query("//span[@id='last_name']");
            if ($fnNode->length > 0 && $lnNode->length > 0) {
                $fn = preg_replace('/^.*?:\s*/u', '', trim($fnNode->item(0)->nodeValue));
                $ln = preg_replace('/^.*?:\s*/u', '', trim($lnNode->item(0)->nodeValue));
                $data['customer_name'] = trim($ln . ' ' . $fn);
            }

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
            // OwlTing 標準格式（含 OwlTing 轉發的 AsiaYo、Expedia 等）

            // OTA 訂單號
            if (preg_match('/OTA訂單編號為:\s*([A-Za-z0-9_\-]+)/u', $allText, $m))
                $data['ota_number'] = trim($m[1]);
            elseif (preg_match('/訂單編號為:\s*([A-Za-z0-9_\-]+)/u', $allText, $m))
                $data['ota_number'] = trim($m[1]);
            elseif (preg_match('/#(\d+)#/u', $subject, $m))
                $data['ota_number'] = trim($m[1]);

            // 奧丁丁號
            if (preg_match('/OBE\w+/u', $subject, $m))
                $data['owl_number'] = trim($m[0]);
            elseif (preg_match('/訂單編號：\s*(OBE\w+)/u', $allText, $m))
                $data['owl_number'] = trim($m[1]);

            // 日期
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

            // 金額（優先抓已收金額，再抓訂單款項）
            $amount = self::findAmountByLabel($xpath, '已收金額');
            if ($amount <= 0) $amount = self::findAmountByLabel($xpath, '訂單款項');
            if ($amount <= 0) {
                $amountNodes = $xpath->query("//span[contains(.,'TWD')] | //div[contains(.,'TWD')]");
                foreach ($amountNodes as $node) {
                    $txt = trim($node->nodeValue);
                    if (mb_strpos($txt, '取消預訂費用') !== false) continue;
                    if (mb_strpos($txt, 'Cancellation') !== false) continue;
                    if (substr_count($txt, 'TWD') > 1) continue;
                    if (preg_match('/TWD\s*([\d,\.]+)/u', $txt, $m)) {
                        $val = (int)round((float)str_replace(',', '', $m[1]));
                        if ($val > 0 && $val < 500000) { $amount = $val; break; }
                    }
                }
            }
            $data['amount'] = $amount;

            // 旅客資訊
            $data['customer_name']  = self::findValueByLabel($xpath, '旅客姓名');
            $data['customer_phone'] = self::findValueByLabel($xpath, '旅客電話');
            $data['remark']         = self::findValueByLabel($xpath, '特殊需求');

            // ---- 房型名稱（訂購內容表格，background:#f2f2f2 的第一個資料列）----
            $roomNodes = $xpath->query("//tr[td[contains(@style,'background-color:#f2f2f2')]]/td[1]/div");
            if ($roomNodes->length > 0) {
                $rt = trim($roomNodes->item(0)->nodeValue);
                if ($rt) $data['room_type'] = $rt;
            }

            // ---- 修改通知解析 ----
            $isModified = mb_strpos($subject, '修改通知') !== false
                       || mb_strpos($allText, '修改後') !== false;

            if ($isModified) {
                $data['is_modified'] = true;
                $data['platform']    = rtrim($data['platform']) . ' (修改)';

                // 黃色高亮 span = 修改後的值 (background-color: rgb(255, 255, 153))
                $highlightedDates = [];
                $hlNodes = $xpath->query("//span[contains(@style,'255, 255, 153')]");
                foreach ($hlNodes as $node) {
                    $txt = trim($node->nodeValue);
                    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $txt)) $highlightedDates[] = $txt;
                }

                // 原始日期 = 全文所有日期排除黃色部分
                preg_match_all('/\d{4}-\d{2}-\d{2}/', $allText, $allDM);
                $origDates = array_values(array_unique(array_filter($allDM[0], fn($d) => !in_array($d, $highlightedDates))));
                if (isset($origDates[0])) $data['orig_check_in']  = $origDates[0];
                if (isset($origDates[1])) $data['orig_check_out'] = $origDates[1];

                // 修改後日期填入 check_in/check_out
                if (isset($highlightedDates[0])) {
                    $data['check_in']  = $highlightedDates[0];
                    $data['modified_fields'][] = 'check_in';
                }
                if (isset($highlightedDates[1])) {
                    $data['check_out'] = $highlightedDates[1];
                    $data['modified_fields'][] = 'check_out';
                }

                // 金額：取兩個「訂單款項」的值（原始/修改後）
                $amtVals = [];
                $amtNodes = $xpath->query("//div[normalize-space(text())='訂單款項']/parent::td/following-sibling::td//span");
                foreach ($amtNodes as $node) {
                    $txt = trim($node->nodeValue);
                    if (preg_match('/([\d,]+)\s*TWD/u', $txt, $m) || preg_match('/TWD\s*([\d,]+)/u', $txt, $m)) {
                        $amtVals[] = (int)str_replace(',', '', $m[1]);
                    }
                }
                if (isset($amtVals[0])) $data['orig_amount'] = $amtVals[0];
                if (isset($amtVals[1])) {
                    $data['amount'] = $amtVals[1];
                    if ($amtVals[1] !== $amtVals[0]) $data['modified_fields'][] = 'amount';
                } elseif (isset($amtVals[0])) {
                    $data['amount'] = $amtVals[0];
                }
            }
        }

        // ---- 奧丁丁號（OwlTing 格式補抓，非直發信）----
        if ($data['owl_number'] === '無' && !$isAsiaYoDirect && !$isBookingDirect && !$isVoucher && !$isTripDirect && !$isDirectCancel) {
            if (preg_match('/OBE\w+/u', $subject, $m))
                $data['owl_number'] = trim($m[0]);
            elseif (preg_match('/訂單編號：\s*(OBE\w+)/u', $allText, $m))
                $data['owl_number'] = trim($m[1]);
        }

        // ---- 天數補算（日期已知但天數還沒抓到）----
        if ($data['nights'] === '無' && $data['check_in'] !== '無' && $data['check_out'] !== '無'
            && preg_match('/^\d{4}-\d{2}-\d{2}$/', $data['check_in'])
            && preg_match('/^\d{4}-\d{2}-\d{2}$/', $data['check_out'])) {
            $ts_in  = strtotime($data['check_in']);
            $ts_out = strtotime($data['check_out']);
            if ($ts_in && $ts_out && $ts_out > $ts_in)
                $data['nights'] = round(($ts_out - $ts_in) / 86400) . '晚';
        }

        return $data;
    }

    // "8-Aug-2026 (8-08-2026)" → "2026-08-08"
    private static function parseDashDate($str) {
        if (preg_match('/\((\d{1,2})-(\d{2})-(\d{4})\)/', $str, $m))
            return sprintf('%s-%s-%02d', $m[3], $m[2], (int)$m[1]);
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
            'platform'        => $defaultPlatform,
            'ota_number'      => '無',
            'owl_number'      => '無',
            'check_in'        => '無',
            'check_out'       => '無',
            'nights'          => '無',
            'extra_bed'       => '無',
            'amount'          => 0,
            'customer_name'   => '無',
            'customer_phone'  => '無',
            'remark'          => '無',
            'room_type'       => '無',
            'is_modified'     => false,
            'modified_fields' => [],
            'orig_check_in'   => '無',
            'orig_check_out'  => '無',
            'orig_amount'     => 0,
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
