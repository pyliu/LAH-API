<?php
require_once("MSDB.class.php");

class Messsage {
    private $jungli_in_db;

    private function getXKey() : int {
        return (random_int(1, 255) * date("H") * date("i", strtotime("1 min")) * date("s", strtotime("1 second"))) % 65535;
    }

    private function getUserInfo($name_or_id) {
        $tdoc_db = new MSDB(array(
            "MS_DB_UID" => SYSTEM_CONFIG["MS_TDOC_DB_UID"],
            "MS_DB_PWD" => SYSTEM_CONFIG["MS_TDOC_DB_PWD"],
            "MS_DB_DATABASE" => SYSTEM_CONFIG["MS_TDOC_DB_DATABASE"],
            "MS_DB_SVR" => SYSTEM_CONFIG["MS_TDOC_DB_SVR"],
            "MS_DB_CHARSET" => SYSTEM_CONFIG["MS_TDOC_DB_CHARSET"]
        ));
        $name_or_id = trim($name_or_id);
        $res = $tdoc_db->fetchAll("SELECT * FROM AP_USER WHERE DocUserID LIKE '%${name_or_id}%'");
        if (empty($res)) {
            $res = $tdoc_db->fetchAll("SELECT * FROM AP_USER WHERE AP_USER_NAME LIKE '%${name_or_id}%'");
        }
        if (empty($res)) {
            return false;
        }
        return $res[count($res) - 1];
    }

    function __construct() {
        $this->jungli_in_db = new MSDB();
    }

    function __destruct() {
        unset($this->jungli_in_db);
        $this->jungli_in_db = null;
    }
    
    public function sendMessage($title, $content, $to_who) : int {
        /*
            AP_OFF_JOB: 離職 (Y/N)
			DocUserID: 使用者代碼 (HB0123)
			AP_PCIP: 電腦IP位址
			AP_USER_NAME: 姓名
            AP_BIRTH: 生日
            AP_UNIT_NAME: 單位
			AP_WORK: 工作
			AP_JOB: 職稱
			AP_HI_SCHOOL: 最高學歷
			AP_TEST: 考試
			AP_SEL: 手機
			AP_ON_DATE: 到職日
         */
        $user_info = $this->getUserInfo($to_who);

        $pctype = "SVR";
        $sendcname = iconv("UTF-8", "BIG5//IGNORE", "地政輔助系統");
        $presn = "0";   // map to MessageMain topic
        $xkey = $this->getXKey();
        $sender = "HBADMIN";
        $receiver = $user_info["DocUserID"];
        $xname = iconv("UTF-8", "BIG5//IGNORE", trim($title));
        $xcontent = iconv("UTF-8", "BIG5//IGNORE", trim($content));
        $sendtype = "1";
        $sendIP = $_SERVER["SERVER_ADDR"];
        $recIP = $user_info["AP_PCIP"];
        $sendtime = date("Y-m-d H:i:s").".000";
        $xtime = "1";
        $intertime = "15";
        $timetype = "0";
        $done = "0";
        $createdate = $sendtime;
        $createunit = "5";
        $creator = $sender;
        $modifydate = $sendtime;
        $modunit = "5";
        $modifier = $sender;
        $enddate = date("Y-m-d 23:59:59");
        /*
        $sdate = 
        $shour = 
        $smin = 
        */
        $data = array(
            'pctype' => $pctype,
            'sendcname' => $sendcname,
            'presn' => $presn,
            'xkey' => $xkey,
            'enddate' => $enddate,
            'sender' => $sender,
            'receiver' => $receiver,
            'xname' => $xname,
            'xcontent' => $xcontent,
            'sendtype' => $sendtype,
            'sendIP' => $sendIP,
            'recIP' => $recIP,
            'xtime' => $xtime,
            'intertime' => $intertime,
            'timetype' => $timetype,
            'sendtime' => $sendtime,
            'done' => $done,
            'createdate' => $createdate,
            'createunit' => $createunit,
            'creator' => $creator,
            'modifydate' => $modifydate,
            'modunit' => $modunit,
            'modifier' => $modifier
        );
        return $this->jungli_in_db->insert("Message", $data);
        //return $this->jungli_in_db->fetch("select top 1 sn from Message order by sn desc")["sn"];
    }
}
?>
