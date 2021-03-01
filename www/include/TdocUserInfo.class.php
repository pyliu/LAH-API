<?php
require_once("init.php");
require_once("MSDB.class.php");
require_once("System.class.php");

class TdocUserInfo {
    private $jungli_in_db;

    function __construct() {
        $system = System::getInstance();
        $this->jungli_in_db = new MSDB(array(
            "MS_DB_UID" => $system->get("MS_TDOC_DB_UID"),
            "MS_DB_PWD" => $system->get("MS_TDOC_DB_PWD"),
            "MS_DB_DATABASE" => $system->get("MS_TDOC_DB_DATABASE"),
            "MS_DB_SVR" => $system->get("MS_TDOC_DB_SVR"),
            "MS_DB_CHARSET" => $system->get("MS_TDOC_DB_CHARSET")
        ));
    }

    function __destruct() {
        unset($this->jungli_in_db);
        $this->jungli_in_db = null;
    }

    public function getOnBoardUsers() {
        global $log;
        $log->info(__METHOD__.": 取得所內所有在職的使用者。");
        return $this->jungli_in_db->fetchAll("SELECT * FROM AP_USER WHERE AP_OFF_JOB = 'N' ORDER BY AP_UNIT_NAME, DocUserID");
    }

    public function getOffBoardUsers() {
        global $log;
        $log->info(__METHOD__.": 取得所內所有離職的使用者。");
        return $this->jungli_in_db->fetchAll("SELECT * FROM AP_USER WHERE AP_OFF_JOB = 'Y' ORDER BY AP_UNIT_NAME, DocUserID");
    }

    public function getAllUsers() {
        global $log;
        $log->info(__METHOD__.": 取得所內所有使用者(包含離職)。");
        return $this->jungli_in_db->fetchAll("SELECT * FROM AP_USER ORDER BY AP_UNIT_NAME, DocUserID");
    }


    public function searchByID($id) {
        global $log;
        $results = false;
        $id = preg_replace("/[^0-9A-Za-z]/i", "", $id);

        if (empty($id)) {
            $log->error(__METHOD__.": ${id} 不能為空值。");
            return $results;
        }

        $log->info(__METHOD__.": Search By ID: $id");
        $results = $this->jungli_in_db->fetchAll("SELECT * FROM AP_USER WHERE DocUserID LIKE '%${id}%' ORDER BY DocUserID, AP_ON_DATE");

        return $results;
    }

    public function searchByName($name) {
        global $log;
        $results = false;

        if (empty($name)) {
            $log->error(__METHOD__.": ${name} 不能為空值。");
            return $results;
        }

        $name = trim($name);
        // query by name
        $log->info(__METHOD__.": Search By Name: $name");
        $results = $this->jungli_in_db->fetchAll("SELECT * FROM AP_USER WHERE AP_USER_NAME LIKE '%${name}%' ORDER BY DocUserID, AP_USER_NAME,AP_ON_DATE");

        return $results;
    }

    public function searchByIP($ip) {
        global $log;
        $results = false;

        if(!filter_var($ip, FILTER_VALIDATE_IP)) {
            $log->error(__METHOD__.": ${ip} 位址格式不正確。");
            return $results;
        }

        $log->info(__METHOD__.": Search By IP: $ip");
        $results = $this->jungli_in_db->fetchAll("SELECT * FROM AP_USER WHERE AP_PCIP = '${ip}' ORDER BY AP_ON_DATE");
    
        return $results;
    }

    public function updateIp($id, $ip) {
        $this->jungli_in_db->update('AP_USER', array('AP_PCIP' => $ip), array('DocUserID' => $id));
        return $this->jungli_in_db->hasError();
        // return true;
    }

    public function getLastError() {
        return $this->jungli_in_db->getLastError();
    }
}
