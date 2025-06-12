<?php
require_once("init.php");
require_once("OraDB.class.php");
require_once("Cache.class.php");

class SurCaseData {
    
    private $row;

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

    private function toDate($str) {
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

    function __construct($db_record) {
        $this->row = $db_record;
    }

    function __destruct() {
        $this->row = null;
    }

    public function getStatus() {
        $status = "錯誤!";
        switch($this->row["MM22"]) {
            case "O":
                $status = "O：收件";
                break;
            case "A":
                $status = "A：外業作業";
                break;
            case "B":
                $status = "B：補正";
                break;
            case "C":
                $status = "C：延期複丈";
                break;
            case "D":
                $status = "D：核定";
                break;
            case "E":
                $status = "E：請示";
                break;
            case "F":
                $status = "F：展期";
                break;
            case "G":
                $status = "G：補正初核";
                break;
            case "H":
                $status = "H：駁回初核";
                break;
            case "I":
                $status = "I：撤回初核";
                break;
            default:
                $status = "空值";
                break;
        }
        return $status;
    }

    public function isClose() {
        return !empty($this->row["MM23"]);
    }

    public function getCloseState() {
        $state = "";
        switch($this->row["MM23"]) {
            case "A":
                $state = "A：結案";
                break;
            case "B":
                $state = "B：撤回";
                break;
            case "C":
                $state = "C：駁回";
                break;
            case "R":
                $state = "R：修檔正確";
                break;
            case "W":
                $state = "W：修檔不正確";
                break;
            case "F":
                $state = "F：歸檔";
                break;
            default:
                $state = "尚未結案";
                break;
        }
        return $state;
    }

    public function getDelayReason() {
        $reason = "";
        switch($this->row["MD12"]) {
            case "1":
                $reason = "1：因雨";
                break;
            case "2":
                $reason = "2：面積遼闊";
                break;
            case "3":
                $reason = "3：現場有障礙物無法施測";
                break;
            case "4":
                $reason = "4：其他";
                break;
            case "5":
                $reason = "5：因事改期";
                break;
            case "6":
                $reason = "6：會辦或核報";
                break;
            case "7":
                $reason = "7：報府複測";
                break;
            case "8":
                $reason = "8：緩辦";
                break;
            case "9":
                $reason = "9：一般改異議";
                break;
            default:
                $reason = "無";
                break;
        }
        return $reason;
    }

    public function getJsonData($flag = 0) {
        return json_encode($this->convertCharset(), $flag);
    }

    public function getJsonHtmlData($flag = 0) {
        $cache = Cache::getInstance();
        $operators = $cache->getUserNames();
        $row = &$this->row;
        $result = array(
            "status" => 0,
            "收件字號" => $row["MM01"]."-".$row["MM02"]."-".$row["MM03"],
            "收件時間" => $this->toDate($row["MM04_1"])." ".$this->toDate($row["MM04_2"]),
            "收件人員" => empty($operators[$row["MM31"]]) ? $row["MM31"] : "<span class='user_tag' data-id='".$row["MM31"]."' data-name='".$operators[$row["MM31"]]."'>".$operators[$row["MM31"]]."【".$row["MM31"]."】</span>",
			"申請事由" => $row["KCNT"],
            "辦理情形" => $this->getStatus(),
            "結案已否" => $this->isClose(),
            "結案狀態" => $this->getCloseState(),
            "延期原因" => $this->getDelayReason(),
            "建號" => trim(substr($row["MM10"], 0, 5), "0").(empty(trim(substr($row["MM10"], 5, 3), "0")) ? "" : "-".trim(substr($row["MM10"], 5, 3), "0")),
            "地號" => trim(substr($row["MM09"], 0, 4), "0").(empty(trim(substr($row["MM09"], 4, 4), "0")) ? "" : "-".trim(substr($row["MM09"], 4, 4), "0")),
            "延期時間" => $this->toDate($row["MD13_1"])." ".$this->toDate($row["MD13_2"]),
            "raw" => $row
        );
        return json_encode($result, $flag);
    }

    public function getBakedData() {
        $cache = Cache::getInstance();
        $operators = $cache->getUserNames();
        $row = &$this->row;
        $ret = array(
            "ID" => $row["MM01"].$row["MM02"].$row["MM03"],
            "收件字號" => $row["MM01"]."-".$row["MM02"]."-".$row["MM03"],
            "收件日期" => $this->toDate($row["MM04_1"]),
            "收件時間" => $this->toDate($row["MM04_2"]),
            "收件人員" => empty($operators[$row["MM31"]]) ? $row["MM31"] : "<span class='user_tag' data-id='".$row["MM31"]."' data-name='".$operators[$row["MM31"]]."'>".$operators[$row["MM31"]]."【".$row["MM31"]."】</span>",
			"申請事由" => $row["KCNT"] ?? $row['RM09_C_KCNT'],
            "辦理情形" => $this->getStatus(),
            "結案已否" => $this->isClose(),
            "結案狀態" => $this->getCloseState(),
            "延期原因" => $this->getDelayReason(),
            "建號" => trim(substr($row["MM10"], 0, 5), "0").(empty(trim(substr($row["MM10"], 5, 3), "0")) ? "" : "-".trim(substr($row["MM10"], 5, 3), "0")),
            "地號" => trim(substr($row["MM09"], 0, 4), "0").(empty(trim(substr($row["MM09"], 4, 4), "0")) ? "" : "-".trim(substr($row["MM09"], 4, 4), "0")),
            "延期時間" => $this->toDate($row["MD13_1"])." ".$this->toDate($row["MD13_2"])
        );
        return $ret + $row; // merge raw data ($row["MM01"] ... etc) and keep original key index
    }
}
