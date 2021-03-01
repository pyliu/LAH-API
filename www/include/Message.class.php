<?php
require_once("MSDB.class.php");
require_once("SQLiteUser.class.php");
require_once("System.class.php");

class Message {
    private $jungli_in_db;

    private function getXKey() : int {
        return (random_int(1, 255) * date("H") * date("i", strtotime("1 min")) * date("s", strtotime("1 second"))) % 65535;
    }

    private function getUserInfo($name_or_id_or_ip) {
        $sqlite_user = new SQLiteUser();
        $name_or_id_or_ip = trim($name_or_id_or_ip);
        $res = $sqlite_user->getUser($name_or_id_or_ip);
        if (empty($res)) {
            $res = $sqlite_user->getUserByName($name_or_id_or_ip);
        }
        if (empty($res)) {
            $res = $sqlite_user->getUserByIP($name_or_id_or_ip);
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

    public function lastError() {
        return $this->jungli_in_db->getLastError();
    }
    
    public function update($data, $where) {
        if (is_array($data) && is_array($where)) {
            $this->jungli_in_db->update("Message", $data, $where);
            return true;
        }
        return false;
    }

    public function delete($sn) {
        return $this->jungli_in_db->delete("Message", array("sn" => $sn));
    }

    // send message right away
    public function send($title, $content, $to_who, $trigger_datetime = 'now', $drop_seconds_range = 28800, $system = false) : int {
        if (is_numeric($drop_seconds_range)) {
            $user_info = $this->getUserInfo($to_who);
            if ($user_info != false) {
                $sendcname = "地政系管輔助系統";
                $sender = "HBADMIN";
                $sendIP = getLocalhostIP();
                if (!$system) {
                    global $client_ip;
                    $sender_info = $this->getUserInfo($client_ip);
                    $sendcname = $sender_info["name"] ?? $sendcname;
                    $sender = $sender_info["id"];
                    $sendIP = $sender_info["ip"];
                }
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
                $pctype = "SVR";
                $presn = "0";   // map to MessageMain topic
                $xkey = $this->getXKey();
                $receiver = $user_info["id"];
                $xname = trim($title);  // nvarchar(50)
                $xcontent = trim($content); // nvarchar(1000)
                $sendtype = "1";
                $recIP = $user_info["ip"];
                $sendtime = ($trigger_datetime == 'now' ? date("Y-m-d H:i:s") : $trigger_datetime).".000";
                $xtime = "1";
                $intertime = "15";
                $timetype = "0";
                $done = "0";
                $createdate = date("Y-m-d H:i:s").".000";
                $createunit = "5";
                $creator = $sender;
                $modifydate = $createdate;
                $modunit = "5";
                $modifier = $sender;

                $end_datetime = new Datetime("now");
                if ($drop_seconds_range > 0) {
                    $end_datetime->add(new DateInterval('PT'.$drop_seconds_range.'S'));
                } else {
                    $end_datetime->sub(new DateInterval('PT'.abs($drop_seconds_range).'S'));
                }
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
        global $log;
        $log->error(__METHOD__.": \$drop_seconds_range 需為秒數 (${drop_seconds_range})");
        return -2;
    }

    public function sysSend($title, $content, $to_who, $drop_seconds_range = 14400) : int {
        // $drop_seconds_range => default +4 hours
        return $this->send($title, $content, $to_who, 'now', $drop_seconds_range, true);
    }
    /**
     * Send message with a st/ed date string
     * $title: string
     * $content: string
     * $who: string
     * $send_datetime: string, date("Y-m-d H:i:s") format
     * $drop_datetime: string, date("Y-m-d H:i:s") format
     */
    public function sendByInterval($title, $content, $to_who, $send_datetime, $drop_datetime) : int {
        global $log;
        $d0 = new DateTime('now');
        $d1 = new DateTime($send_datetime);
        $d2 = new DateTime($drop_datetime);

        $diff = $d2->diff($d1);
        if ($diff->invert != 1) {
            $log->error(__METHOD__.": 時間區間有問題 => 開始: ${send_datetime} 忽略:${drop_datetime}");
            return -3;
        }

        $diff = $d2->diff($d0);
        if ($diff->invert == 1) {
            $seconds = (60 * $diff->h + $diff->i) * 60 + $diff->s;
            $log->info(__METHOD__.": 設定時間區間 => 開始: ${send_datetime}, 忽略: ${drop_datetime}, seconds: ".$seconds);
            return $this->send($title, $content, $to_who, $send_datetime, $seconds, false);
        }
        $log->error(__METHOD__.": 忽略時間已超過現在時間 => 忽略: ${drop_datetime} 現在: ".date('Y-m-d H:i:s'));
        return -4;
    }

    public function getMessageByUser($name_or_id_or_ip, $top = 5) {
        global $log;
        if (!is_numeric($top) || $top < 1) {
            $log->warning(__METHOD__.": top 必須是正整數！(${top})");
            return false;
        }
        if (empty($name_or_id_or_ip)) {
            $log->warning(__METHOD__.": 輸入參數為空白，無法查詢使用者！");
            return false;
        }
        $user = $this->getUserInfo($name_or_id_or_ip);
        if ($user !== false) {
            $id = $user["id"];

            $system = System::getInstance();

            $tdoc_db = new MSDB(array(
                "MS_DB_UID" => $system->get("MS_DB_UID"),
                "MS_DB_PWD" => $system->get("MS_DB_PWD"),
                "MS_DB_DATABASE" => $system->get("MS_DB_DATABASE"),
                "MS_DB_SVR" => $system->get("MS_DB_SVR"),
                "MS_DB_CHARSET" => $system->get("MS_DB_CHARSET")
            ));
            $sql = "SELECT TOP ${top} * FROM Message WHERE receiver = '${id}' ORDER BY sendtime DESC";
            return $tdoc_db->fetchAll($sql);
        }
        $log->warning(__METHOD__.": 找不到使用者資料！【${name_or_id_or_ip}】");
        return false;
    }

    public function getUnreadMessageByUser($name_or_id_or_ip) {
        global $log;
        if (empty($name_or_id_or_ip)) {
            $log->warning(__METHOD__.": 輸入參數為空白，無法查詢使用者！");
            return false;
        }
        $user = $this->getUserInfo($name_or_id_or_ip);
        if ($user !== false) {
            $id = $user["id"];

            $system = System::getInstance();

            $tdoc_db = new MSDB(array(
                "MS_DB_UID" => $system->get("MS_DB_UID"),
                "MS_DB_PWD" => $system->get("MS_DB_PWD"),
                "MS_DB_DATABASE" => $system->get("MS_DB_DATABASE"),
                "MS_DB_SVR" => $system->get("MS_DB_SVR"),
                "MS_DB_CHARSET" => $system->get("MS_DB_CHARSET")
            ));
            $sql = "SELECT * FROM Message WHERE receiver = '${id}' AND done <> '1' ORDER BY sendtime DESC";
            return $tdoc_db->fetchAll($sql);
        }
        $log->warning(__METHOD__.": 找不到使用者資料！【${name_or_id_or_ip}】");
        return false;
    }

    public function setRead($sn) {
        return $this->update(array( 'done' => 1 ), array( 'sn' => $sn ));
    }

    public function setUnread($sn) {
        return $this->update(array( 'done' => 0 ), array( 'sn' => $sn ));
    }
}
