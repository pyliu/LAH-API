<?php
require_once("MSDB.class.php");

class Message {
    private $jungli_in_db;

    private function getXKey() : int {
        return (random_int(1, 255) * date("H") * date("i", strtotime("1 min")) * date("s", strtotime("1 second"))) % 65535;
    }

    private function getUserInfo($name_or_id_or_ip) {
        $tdoc_db = new MSDB(array(
            "MS_DB_UID" => SYSTEM_CONFIG["MS_TDOC_DB_UID"],
            "MS_DB_PWD" => SYSTEM_CONFIG["MS_TDOC_DB_PWD"],
            "MS_DB_DATABASE" => SYSTEM_CONFIG["MS_TDOC_DB_DATABASE"],
            "MS_DB_SVR" => SYSTEM_CONFIG["MS_TDOC_DB_SVR"],
            "MS_DB_CHARSET" => SYSTEM_CONFIG["MS_TDOC_DB_CHARSET"]
        ));
        $name_or_id_or_ip = trim($name_or_id_or_ip);
        $res = $tdoc_db->fetchAll("SELECT * FROM AP_USER WHERE DocUserID LIKE '%${name_or_id_or_ip}%' AND AP_OFF_JOB <> 'Y'");
        if (empty($res)) {
            $res = $tdoc_db->fetchAll("SELECT * FROM AP_USER WHERE AP_USER_NAME LIKE '%${name_or_id_or_ip}%' AND AP_OFF_JOB <> 'Y' ORDER BY AP_ON_DATE");
        }
        if (empty($res)) {
            $res = $tdoc_db->fetchAll("SELECT * FROM AP_USER WHERE AP_PCIP = '${name_or_id_or_ip}' AND AP_OFF_JOB <> 'Y' ORDER BY DocUserID");
        }
        if (empty($res) || count($res) != 1) {
            return false;
        }
        return $res[0];
    }

    function __construct() {
        $this->jungli_in_db = new MSDB();
    }

    function __destruct() {
        unset($this->jungli_in_db);
        $this->jungli_in_db = null;
    }
    
    public function update($data, $where) {
        if (is_array($data) && is_array($where)) {
            $this->jungli_in_db->update("Message", $data, $where);
        }
    }

    public function send($title, $content, $to_who, $date_offset = "+8 hour") : int {
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
        if ($user_info != false) {
        
            global $client_ip;
            $sender_info = $this->getUserInfo($client_ip);

            $pctype = "SVR";
            $sendcname = $sender_info["AP_USER_NAME"] ?? "地政系管輔助系統";
            $presn = "0";   // map to MessageMain topic
            $xkey = $this->getXKey();
            $sender = $sender_info["DocUserID"] ?? "HBADMIN";
            $receiver = $user_info["DocUserID"];
            $xname = trim($title);  // nvarchar(50)
            $xcontent = trim($content); // nvarchar(1000)
            $sendtype = "1";
            $sendIP = $sender_info["AP_PCIP"] ?? $_SERVER["SERVER_ADDR"];
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

            $end_datetime = new Datetime("now");
            $end_datetime->modify($date_offset);
            $enddate = $end_datetime->format("Y-m-d H:i:s");
            
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
        }
        return -1;
    }
}
?>
