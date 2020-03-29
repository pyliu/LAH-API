<?php
require_once("init.php");
require_once("OraDB.class.php");

class RegCaseData {
    static private $operators;

    private $row;

    private function getIDorName($id) {
        return RegCaseData::$operators[$id] ?? $id;
    }

    private function getDueTime($begin) {
        /*
        // RM27 - 案件辦理期限 (8 hrs a day)
        $days = round($this->row["RM27"] / 8, 0, PHP_ROUND_HALF_DOWN);
        $hours = $this->row["RM27"] % 8;
        $due_in_secs = $hours * 60 * 60 + $days * 24 * 60 * 60;
        // 遇到weekend需加上兩天時間
        if ($days > 0) {
            for ($i = 1; $i <= $days; $i++) {
                $due_date = date("N", $begin + $i * 24 * 60 * 60);
                if ($due_date > 5) {
                    $due_in_secs += 2 * 24 * 60 * 60;
                    break;
                }
            }
        }
        return $due_in_secs;
        */
        // RM29_1 - 限辦日
        $Y = substr($this->row["RM29_1"], 0, 3) + 1911;
        $M = substr($this->row["RM29_1"], 3, 2);
        $D = substr($this->row["RM29_1"], 5, 2);
        // RM29_2 - 限辦時間
        $H = substr($this->row["RM29_2"], 0, 2);
        $i = substr($this->row["RM29_2"], 2, 2);
        $s = substr($this->row["RM29_2"], 4, 2);
        $due_in_secs = mktime($H, $i, $s, $M, $D, $Y);
        return $due_in_secs - $begin;
    }

    private function convertCharset() {
        $convert = array();
        foreach ($this->row as $key=>$value) {
            if (!empty($value)) {
                $conv_str = iconv("big5", "utf-8", $value);
                if (empty($conv_str)) {
                    // has rare word inside
                    mb_regex_encoding("utf-8"); // 宣告 要進行 regex 的多位元編碼轉換格式
                    mb_substitute_character('long'); // 宣告 缺碼字改以U+16進位碼為標記取代
                    $conv_str = mb_convert_encoding($value, "utf-8", "big5");
                    //$conv_str = preg_replace('/U\+([0-9A-F]{4})/e', '"&#".intval("\\1",16).";"', $conv_str); // 將U+16進位碼標記轉換為UnicodeHTML碼
                    $conv_str = preg_replace('/U\+([0-9A-F]{4})/e', '？', $conv_str); // 將U+16進位碼標記轉換為？
                }
                $convert[$key] = $conv_str;
            } else {
                $convert[$key] = "";
            }
            //$convert[$key] = empty($value) ? $value : mb_convert_encoding($value, "utf-8", "big5");
        }
        return $convert;
    }

    private function getTimestamp($date, $time) {
        /*
        // RM27 - 案件辦理期限 (8 hrs a day)
        $days = round($this->row["RM27"] / 8, 0, PHP_ROUND_HALF_DOWN);
        $hours = $this->row["RM27"] % 8;
        $due_in_secs = $hours * 60 * 60 + $days * 24 * 60 * 60;
        // 遇到weekend需加上兩天時間
        if ($days > 0) {
            for ($i = 1; $i <= $days; $i++) {
                $due_date = date("N", $begin + $i * 24 * 60 * 60);
                if ($due_date > 5) {
                    $due_in_secs += 2 * 24 * 60 * 60;
                    break;
                }
            }
        }
        return $due_in_secs;
        */
        if (empty($date) || empty($time)) return false;
        // $date, e.g. 1090327
        // $time, e.g. 153033
        $Y = substr($date, 0, 3) + 1911;
        $M = substr($date, 3, 2);
        $D = substr($date, 5, 2);
        $H = substr($time, 0, 2);
        $i = substr($time, 2, 2);
        $s = substr($time, 4, 2);
        return mktime($H, $i, $s, $M, $D, $Y);
    }

    function __construct($db_record) {
        $this->row = $db_record;
        if (is_null(RegCaseData::$operators)) {
            RegCaseData::$operators = GetDBUserMapping();
        }
    }

    function __destruct() {
        $this->row = null;
    }

    static public function toDate($str) {
        $len = strlen($str);
        if ($len == 7) {
            return substr($str, 0, 3) . "-" . substr($str, 3, 2) . "-" . substr($str, 5, 2);
        } else if ($len == 6) {
            return substr($str, 0, 2) . ":" . substr($str, 2, 2) . ":" . substr($str, 4, 2);
        } else if ($len == 13) {
            return substr($str, 0, 3) . "-" . substr($str, 3, 2) . "-" . substr($str, 5, 2) . " " . substr($str, 7, 2) . ":" . substr($str, 9, 2) . ":" . substr($str, 11, 2);
        }
        return "";
    }

    public function getJsonData($flag = 0) {
        return json_encode($this->convertCharset(), $flag);
    }

    private function getTableHtml() {
        $str = "<table id='case_results' border='1' class='table-hover text-center col-lg-12'>\n";
        $str .= "<thead id='case_table_header'><tr class='header'>".
            "<th id='fixed_th1'>收件字號</th>\n".
            "<th id='fixed_th2'>收件日期</th>\n".
            "<th id='fixed_th3'>限辦</th>\n".
            "<th id='fixed_th4'>辦理情形</th>\n".
            "<th id='fixed_th5'>收件人員</th>\n".
            "<th id='fixed_th6'>作業人員</th>\n".
            "<th id='fixed_th7'>初審人員</th>\n".
            "<th id='fixed_th8'>複審人員</th>\n".
            "<th id='fixed_th9'>准登人員</th>\n".
            "<th id='fixed_th10'>登記人員</th>\n".
            "<th id='fixed_th11'>校對人員</th>\n".
            "<th id='fixed_th12'>結案人員</th>\n".
            "</tr></thead>\n";
        $str .= "<tbody>\n";
        $str .= "<tr class='".$this->getStatusCss()."'>\n";
        $str .= "<td class='text-center px-3 ".($this->isDanger() ? "text-danger" : "")." reg_case_id'>".$this->getReceiveSerial()."</td>\n".
            "<td data-toggle='tooltip'>".$this->getReceiveDate()."</td>\n".
            "<td data-toggle='tooltip' data-placement='right' title='限辦期限：".$this->getDueDate()."'>".$this->getDueHrs()."</td>\n".
            "<td data-toggle='tooltip'>".$this->getStatus()."</td>\n".
            "<td ".$this->getReceptionistTooltipAttr().">".$this->getReceptionist()."</td>\n".
            "<td ".$this->getCurrentOperatorTooltipAttr().">".$this->getCurrentOperator()."</td>\n".
            "<td ".$this->getFirstReviewerTooltipAttr().">".$this->getFirstReviewer()."</td>\n".
            "<td ".$this->getSecondReviewerTooltipAttr().">".$this->getSecondReviewer()."</td>\n".
            "<td ".$this->getPreRegisterTooltipAttr().">".$this->getPreRegister()."</td>\n".
            "<td ".$this->getRegisterTooltipAttr().">".$this->getRegister()."</td>\n".
            "<td ".$this->getCheckerTooltipAttr().">".$this->getChecker()."</td>\n".
            "<td ".$this->getCloserTooltipAttr().">".$this->getCloser()."</td>\n";
        $str .= "</tr>\n";
        $str .= "</tbody>\n";
        $str .= "</table>\n";
        return $str;
    }

    public function getBakedData() {
        $row = &$this->row;
        $ret = array(
            "ID" => $row["RM01"].$row["RM02"].$row["RM03"],
            "ELAPSED_TIME" => array(
                "初審" => $this->getFirstReviewerPassedTime(),
                "複審" => $this->getSecondReviewerPassedTime(),
                "准登" => $this->getPreRegisterPassedTime(),
                "登錄" => $this->getRegisterPassedTime(),
                "校對" => $this->getCheckerPassedTime(),
                "結案" => $this->getCloserPassedTime()
            ),
            "紅綠燈背景CSS" => $this->getStatusCss(),
            "燈號" => $this->getState(),
            "收件字號" => $this->getReceiveSerial(),
            "收件日期" => RegCaseData::toDate($row["RM07_1"]),
            "收件時間" => RegCaseData::toDate($row["RM07_1"])." ".RegCaseData::toDate($row["RM07_2"]),
            "測量案件" => $row["RM04"]."-".$row["RM05"]."-".$row["RM06"],
            "登記原因" => $this->getCaseReason(),
            "限辦期限" => $this->getDueDate(),
            "限辦時間" => $this->getDueHrs(),
            "作業人員" => $this->getCurrentOperator(),
            "辦理情形" => $this->getStatus(),
            "權利人統編" => empty($row["RM18"]) ? "" : $row["RM18"],
            "權利人姓名" => empty($row["RM19"]) ? "" : $row["RM19"],
            "義務人統編" => empty($row["RM21"]) ? "" : $row["RM21"],
            "義務人姓名" => empty($row["RM22"]) ? "" : $row["RM22"],
            "義務人人數" => empty($row["RM23"]) ? "" : $row["RM23"],
            "手機號碼" => empty($row["RM102"]) ? "" : $row["RM102"],
            "代理人統編" => empty($row["RM24"]) ? "" : $row["RM24"],
            "代理人姓名" => empty($row["AB02"]) ? "" : $row["AB02"],
            "段代碼" =>  empty($row["RM11"]) ? "" : $row["RM11"],
            "段小段" =>  empty($row["RM11_CNT"]) ? "" : $row["RM11_CNT"],
            "地號" =>  empty($row["RM12"]) ? "" : substr($row["RM12"], 0, 4)."-".substr($row["RM12"], 4),
            "建號" =>  empty($row["RM15"]) ? "" : substr($row["RM15"], 0, 5)."-".substr($row["RM15"], 5),
            "件數" =>  empty($row["RM32"]) ? "" : $row["RM32"],
            "登記處理註記" => empty(REG_NOTE[$row["RM39"]]) ? "" : REG_NOTE[$row["RM39"]],
            "地價處理註記" => empty(VAL_NOTE[$row["RM42"]]) ? "" : VAL_NOTE[$row["RM42"]],
            "跨所" => $row["RM99"],
            "資料管轄所" => OFFICE[$row["RM100"]] ? OFFICE[$row["RM100"]] : $row["RM100"],
            "資料收件所" => OFFICE[$row["RM101"]] ? OFFICE[$row["RM101"]] : $row["RM101"],
            "結案代碼" => $row["RM31"],
            "結案狀態" => $this->getCaseCloseStatus(),
            "電子郵件" => $row["RM95"],
            // 案件辦理情形資料
            "收件人員" => $this->getReceptionist(),
            "初審人員" => $this->getFirstReviewer(),
            "初審時間" => RegCaseData::toDate($row["RM44_1"])." ".RegCaseData::toDate($row["RM44_2"]),
            "複審人員" => $this->getSecondReviewer(),
            "複審時間" => RegCaseData::toDate($row["RM46_1"])." ".RegCaseData::toDate($row["RM46_2"]),
            "移轉課長" => $this->getIDorName($this->row["RM106"]),
            "移轉課長時間" => RegCaseData::toDate($row["RM106_1"])." ".RegCaseData::toDate($row["RM106_2"]),
            "移轉秘書" => $this->getIDorName($this->row["RM107"]),
            "移轉秘書時間" => RegCaseData::toDate($row["RM107_1"])." ".RegCaseData::toDate($row["RM107_2"]),
            "駁回日期" => RegCaseData::toDate($row["RM48_1"])." ".RegCaseData::toDate($row["RM48_2"]),
            "公告日期" => RegCaseData::toDate($row["RM49"]),
            "公告期滿日期" => RegCaseData::toDate($row["RM50"]),
            "公告天數" => $row["RM49_DAY"],
            "通知補正日期" => RegCaseData::toDate($row["RM51"]),
            "補正期滿日期" => RegCaseData::toDate($row["RM52"]),
            "補正期限" => $row["RM52_DAY"],
            "補正日期" => RegCaseData::toDate($row["RM53_1"])." ".RegCaseData::toDate($row["RM53_2"]),
            "請示人員" => $this->getIDorName($this->row["RM82"]),
            "請示時間" => RegCaseData::toDate($row["RM80"])." ".RegCaseData::toDate($row["RM81"]),
            "展期人員" => $this->getIDorName($this->row["RM88"]),
            "展期日期" => RegCaseData::toDate($row["RM86"])." ".RegCaseData::toDate($row["RM87"]),
            "展期天數" => $this->row["RM89"],
            "登錄人員" => $this->getIDorName($this->row["RM55"]),
            "登錄日期" => RegCaseData::toDate($row["RM54_1"])." ".RegCaseData::toDate($row["RM54_2"]),
            "准登人員" => $this->getIDorName($this->row["RM63"]),
            "准登日期" => RegCaseData::toDate($row["RM62_1"])." ".RegCaseData::toDate($row["RM62_2"]),
            "校對人員" => $this->getIDorName($this->row["RM57"]),
            "校對日期" => RegCaseData::toDate($row["RM56_1"])." ".RegCaseData::toDate($row["RM56_2"]),
            "結案人員" => $this->getIDorName($this->row["RM59"]),
            "結案日期" => RegCaseData::toDate($row["RM58_1"])." ".RegCaseData::toDate($row["RM58_2"]),
            "預定結案日期" => RegCaseData::toDate($row["RM29_1"])." ".RegCaseData::toDate($row["RM29_2"]),
            "結案與否" => empty($this->row["RM31"]) ? "N" : "Y【".$this->getCaseCloseStatus()."】"
        );
        return $ret + $row; // merge raw data ($row["RM01"] ... etc) and keep original key index
    }

    public function isDanger() {
        return empty(REG_WORD[$this->row["RM02"]]);
    }
	
    public function getReceiveSerial() {
        // 收件年+字（代碼）+號（6碼）
        if ($this->isDanger()) {
			return $this->row["RM01"]."年 ".$this->row["RM02"]." 第 ".$this->row["RM03"]." 號";
        }
        return $this->row["RM01"]."年 ".REG_WORD[$this->row["RM02"]]."(".$this->row["RM02"].")字 第 ".$this->row["RM03"]." 號";
    }
	
    public function getReceiveDate() {
        return RegCaseData::toDate($this->row["RM07_1"]);
    }

    public function getReceiveTime() {
        return RegCaseData::toDate($this->row["RM07_2"]);
    }

    public function getReceiveTimestamp() {
        return $this->getTimestamp($this->row["RM07_1"], $this->row["RM07_2"]);
    }

    public function getDueHrs() {
        return str_pad($this->row["RM27"], 2, "0", STR_PAD_LEFT)." ".($this->row["RM27"] > 1 ? "hrs" : "hr");
    }

    public function getDueDate() {
        /*
        // RM07_1 - 收件日
        $Y = substr($this->row["RM07_1"], 0, 3) + 1911;
        $M = substr($this->row["RM07_1"], 3, 2);
        $D = substr($this->row["RM07_1"], 5, 2);
        // RM07_2 - 收件時間
        $H = substr($this->row["RM07_2"], 0, 2);
        $i = substr($this->row["RM07_2"], 2, 2);
        $s = substr($this->row["RM07_2"], 4, 2);

        $begin = mktime($H, $i, $s, $M, $D, $Y);
        $due_in_secs = $begin + $this->getDueTime($begin);
        
        return (date("Y", $due_in_secs) - 1911).date("-m-d H:i:s", $due_in_secs);
        */
        return $this->toDate($this->row["RM29_1"])." ".$this->toDate($this->row["RM29_2"]);
    }

    public function getCaseReason() {
        return $this->row["KCNT"] ?? $this->row["RM09_CHT"] ?? $this->row["RM09"];
    }

    public function getStatus() {
        return CASE_STATUS[$this->row["RM30"]];
    }

    public function getState() {
        // RM30 - 案件辦理情形
        if ($this->row["RM30"] == "F" || $this->row["RM30"] == "Z"  || !empty($this->row["RM31"])) {
            return "success";
        }
        
        // RM07_1 - 收件日
        $Y = substr($this->row["RM07_1"], 0, 3) + 1911;
        $M = substr($this->row["RM07_1"], 3, 2);
        $D = substr($this->row["RM07_1"], 5, 2);
        // RM07_2 - 收件時間
        $H = substr($this->row["RM07_2"], 0, 2);
        $i = substr($this->row["RM07_2"], 2, 2);
        $s = substr($this->row["RM07_2"], 4, 2);
        
        $now         = mktime();
        $begin       = mktime($H, $i, $s, $M, $D, $Y);
        $due_in_secs = $this->getDueTime($begin);
        
        // overdue
        if ($now - $begin > $due_in_secs) {
            return "danger";
        }
        // reach the due (within 4hrs)
        if ($now - $begin > $due_in_secs - 4 * 60 * 60) {
            return "warning";
        }

        return "light";
    }

    public function getStatusCss() {
        // RM30 - 案件辦理情形
        if ($this->row["RM30"] == "F" || $this->row["RM30"] == "Z"  || $this->row["RM31"] == "A") {
            return "bg-success text-white";
        }
        
        // RM07_1 - 收件日
        $Y = substr($this->row["RM07_1"], 0, 3) + 1911;
        $M = substr($this->row["RM07_1"], 3, 2);
        $D = substr($this->row["RM07_1"], 5, 2);
        // RM07_2 - 收件時間
        $H = substr($this->row["RM07_2"], 0, 2);
        $i = substr($this->row["RM07_2"], 2, 2);
        $s = substr($this->row["RM07_2"], 4, 2);
        
        $now         = mktime();
        $begin       = mktime($H, $i, $s, $M, $D, $Y);
        $due_in_secs = $this->getDueTime($begin);
        
        // overdue
        if ($now - $begin > $due_in_secs) {
            return "bg-danger text-white";
        }
        // reach the due (within 4hrs)
        if ($now - $begin > $due_in_secs - 4 * 60 * 60) {
            return "bg-warning";
        }
    }

    public function getCaseCloseStatus() {
        switch ($this->row['RM31']) {
            case "A":
                return "結案";
            case "B":
                return "撤回";
            case "C":
                return "併案";
            case "D":
                return "駁回";
            case "E":
                return "請示";
            default:
                return "未結案";
        }
    }

    public function getReceptionist() {
        return $this->getIDorName($this->row["RM96"]);
    }

    public function getReceptionistTooltipAttr() {
        return empty($this->row["RM96"]) || $this->row["RM96"] == "XXXXXXXX" ? "" : "class='user_tag' @click.stop='window.vueAp.fetchUserInfo' data-id='".$this->row["RM96"]."' data-name='".$this->getIDorName($this->row["RM96"])."' data-toggle='tooltip' title='收件人員：".$this->row["RM96"]."'";
    }

    public function getCurrentOperator() {
        return $this->getIDorName($this->row["RM30_1"]);
    }

    public function getCurrentOperatorID() {
        return $this->row["RM30_1"];
    }

    public function getCurrentOperatorTooltipAttr() {
        return empty($this->row["RM30_1"]) || $this->row["RM30_1"] == "XXXXXXXX" ? "" : "class='user_tag' @click.stop='window.vueAp.fetchUserInfo' data-id='".$this->row["RM30_1"]."' data-name='".$this->getIDorName($this->row["RM30_1"])."' data-toggle='tooltip' title='作業人員：".$this->row["RM30_1"]."'";
    }

    public function getFirstReviewer() {
        return $this->getIDorName($this->row["RM45"]);
    }

    public function getFirstReviewerID() {
        return $this->row["RM45"];
    }

    public function getFirstReviewerTimestamp() {
        return $this->getTimestamp($this->row["RM44_1"], $this->row["RM44_2"]);
    }

    public function getFirstReviewerPassedTime() {
        $first_reviewed_in_secs = $this->getFirstReviewerTimestamp();
        if ($first_reviewed_in_secs === false) return 0;
        $received_in_secs = $this->getReceiveTimestamp();
        return $first_reviewed_in_secs - $received_in_secs;
    }

    public function getFirstReviewerTooltipAttr() {
        return empty($this->row["RM45"]) || $this->row["RM45"] == "XXXXXXXX" ? "" : "class='user_tag' @click.stop='window.vueAp.fetchUserInfo' data-id='".$this->row["RM45"]."' data-name='".$this->getIDorName($this->row["RM45"])."' data-toggle='tooltip' title='初審人員：".$this->row["RM45"]."'";
    }

    public function getSecondReviewer() {
        return $this->getIDorName($this->row["RM47"]);
    }

    public function getSecondReviewerTooltipAttr() {
        return empty($this->row["RM47"]) || $this->row["RM47"] == "XXXXXXXX" ? "" : "class='user_tag' @click.stop='window.vueAp.fetchUserInfo' data-id='".$this->row["RM47"]."' data-name='".$this->getIDorName($this->row["RM47"])."' data-toggle='tooltip' title='複審人員：".$this->row["RM47"]."'";
    }

    public function getSecondReviewerTimestamp() {
        return $this->getTimestamp($this->row["RM46_1"], $this->row["RM46_2"]);
    }

    public function getSecondReviewerPassedTime() {
        $second_reviewed_in_secs = $this->getSecondReviewerTimestamp();
        if ($second_reviewed_in_secs === false) return 0;
        $received_in_secs = $this->getReceiveTimestamp();
        $first_reviewed_consumed_in_secs = $this->getFirstReviewerPassedTime();
        return $second_reviewed_in_secs - $received_in_secs - $first_reviewed_consumed_in_secs;
    }

    public function getPreRegister() {
        return $this->getIDorName($this->row["RM63"]);
    }

    public function getPreRegisterTooltipAttr() {
        return empty($this->row["RM63"]) || $this->row["RM63"] == "XXXXXXXX" ? "" : "class='user_tag' @click.stop='window.vueAp.fetchUserInfo' data-id='".$this->row["RM63"]."' data-name='".$this->getIDorName($this->row["RM63"])."' data-toggle='tooltip' title='准登人員：".$this->row["RM63"]."'";
    }

    public function getPreRegisterTimestamp() {
        return $this->getTimestamp($this->row["RM62_1"], $this->row["RM62_2"]);
    }

    public function getPreRegisterPassedTime() {
        $pre_register_in_secs = $this->getPreRegisterTimestamp();
        if ($pre_register_in_secs === false) return 0;
        $received_in_secs = $this->getReceiveTimestamp();
        $first_reviewed_consumed_in_secs = $this->getFirstReviewerPassedTime();
        $second_reviewed_consumed_in_secs = $this->getSecondReviewerPassedTime();
        return $pre_register_in_secs - $received_in_secs - $first_reviewed_consumed_in_secs - $second_reviewed_consumed_in_secs;
    }

    public function getRegister() {
        return $this->getIDorName($this->row["RM55"]);
    }

    public function getRegisterTooltipAttr() {
        return empty($this->row["RM55"]) || $this->row["RM55"] == "XXXXXXXX" ? "" : "class='user_tag' @click.stop='window.vueAp.fetchUserInfo' data-id='".$this->row["RM55"]."' data-name='".$this->getIDorName($this->row["RM55"])."' data-toggle='tooltip' title='登記人員：".$this->row["RM55"]."'";
    }

    public function getRegisterTimestamp() {
        return $this->getTimestamp($this->row["RM54_1"], $this->row["RM54_2"]);
    }

    public function getRegisterPassedTime() {
        $register_in_secs = $this->getRegisterTimestamp();
        if ($register_in_secs === false) return 0;
        $received_in_secs = $this->getReceiveTimestamp();
        $first_reviewed_consumed_in_secs = $this->getFirstReviewerPassedTime();
        $second_reviewed_consumed_in_secs = $this->getSecondReviewerPassedTime();
        $pre_register_consumed_in_secs = $this->getPreRegisterPassedTime();
        return $register_in_secs - $received_in_secs - $first_reviewed_consumed_in_secs - $second_reviewed_consumed_in_secs - $pre_register_consumed_in_secs;
    }

    public function getChecker() {
        return $this->getIDorName($this->row["RM57"]);
    }

    public function getCheckerTooltipAttr() {
        return empty($this->row["RM57"]) || $this->row["RM57"] == "XXXXXXXX" ? "" : "class='user_tag' @click.stop='window.vueAp.fetchUserInfo' data-id='".$this->row["RM57"]."' data-name='".$this->getIDorName($this->row["RM57"])."' data-toggle='tooltip' title='校對人員：".$this->row["RM57"]."'";
    }

    public function getCheckerTimestamp() {
        return $this->getTimestamp($this->row["RM56_1"], $this->row["RM56_2"]);
    }

    public function getCheckerPassedTime() {
        $checker_in_secs = $this->getCheckerTimestamp();
        if ($checker_in_secs === false) return 0;
        $received_in_secs = $this->getReceiveTimestamp();
        $first_reviewed_consumed_in_secs = $this->getFirstReviewerPassedTime();
        $second_reviewed_consumed_in_secs = $this->getSecondReviewerPassedTime();
        $pre_register_consumed_in_secs = $this->getPreRegisterPassedTime();
        $register_consumed_in_secs = $this->getRegisterPassedTime();
        return $checker_in_secs - $received_in_secs - $first_reviewed_consumed_in_secs - $second_reviewed_consumed_in_secs - $pre_register_consumed_in_secs - $register_consumed_in_secs;
    }

    public function getCloser() {
        return $this->getIDorName($this->row["RM59"]);
    }

    public function getCloserTooltipAttr() {
        return empty($this->row["RM59"]) || $this->row["RM59"] == "XXXXXXXX" ? "" : "class='user_tag' @click.stop='window.vueAp.fetchUserInfo' data-id='".$this->row["RM59"]."' data-name='".$this->getIDorName($this->row["RM59"])."' data-toggle='tooltip' title='結案人員：".$this->row["RM59"]."'";
    }

    public function getCloserTimestamp() {
        return $this->getTimestamp($this->row["RM58_1"], $this->row["RM58_2"]);
    }

    public function getCloserPassedTime() {
        $closer_in_secs = $this->getCloserTimestamp();
        if ($closer_in_secs === false) return 0;
        $received_in_secs = $this->getReceiveTimestamp();
        $first_reviewed_consumed_in_secs = $this->getFirstReviewerPassedTime();
        $second_reviewed_consumed_in_secs = $this->getSecondReviewerPassedTime();
        $pre_register_consumed_in_secs = $this->getPreRegisterPassedTime();
        $register_consumed_in_secs = $this->getRegisterPassedTime();
        $checker_consumed_in_secs = $this->getCheckerPassedTime();
        return $closer_in_secs - $received_in_secs - $first_reviewed_consumed_in_secs - $second_reviewed_consumed_in_secs - $pre_register_consumed_in_secs - $register_consumed_in_secs - $checker_consumed_in_secs;
    }

}
?>
