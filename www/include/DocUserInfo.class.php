<?php
require_once("init.php");
require_once("MSDB.class.php");
require_once("System.class.php");

class DocUserInfo {
    private $doc_db;

    function __construct() {
        $system = System::getInstance();
        $this->doc_db = new MSDB(array(
            'MS_DB_UID' => $system->get('MS_DOC_DB_UID'),
            'MS_DB_PWD' => $system->get('MS_DOC_DB_PWD'),
            'MS_DB_DATABASE' => $system->get('MS_DOC_DB_DATABASE'),
            'MS_DB_SVR' => $system->get('MS_DOC_DB_SVR'),
            'MS_DB_CHARSET' => $system->get('MS_DOC_DB_CHARSET')
        ));
    }

    function __destruct() {
        unset($this->doc_db);
        $this->doc_db = null;
    }

    public function getOnBoardUsers() {
        global $log;
        $log->info(__METHOD__.": 取得所內舊資料庫(doc)所有在職的使用者。");
        return $this->doc_db->fetchAll("SELECT * FROM DOC_USER WHERE NOW_JOB = 'N' AND USER_ID LIKE 'HB%' ORDER BY USER_NAME, USER_ID");
    }

    public function getOffBoardUsers() {
        global $log;
        $log->info(__METHOD__.": 取得所內舊資料庫(doc)所有離職的使用者。");
        return $this->doc_db->fetchAll("SELECT * FROM DOC_USER WHERE NOW_JOB = 'Y' AND USER_ID LIKE 'HB%' ORDER BY USER_NAME, USER_ID");
    }

    public function getAllUsers() {
        global $log;
        $log->info(__METHOD__.": 取得所內舊資料庫(doc)所有使用者(包含離職)。");
        return $this->doc_db->fetchAll("SELECT * FROM DOC_USER WHERE USER_ID LIKE 'HB%' ORDER BY USER_NAME, USER_ID");
    }

    public function updateExt($id, $ext) {
        $this->doc_db->update('DOC_USER', array('SUB_TEL' => $ext), array('USER_ID' => $id));
        return $this->hasError();
    }

    public function hasError() {
        return $this->doc_db->hasError();
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
        $results = $this->doc_db->fetchAll("SELECT * FROM DOC_USER WHERE USER_ID LIKE '%${id}%' ORDER BY USER_ID");

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
        $results = $this->doc_db->fetchAll("SELECT * FROM DOC_USER WHERE USER_NAME LIKE '%${name}%' ORDER BY USER_ID, USER_NAME");

        return $results;
    }
}
