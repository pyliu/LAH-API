<?php
require_once('init.php');
require_once('DynamicSQLite.class.php');

define('TEMPERATURE_SQLITE_DB', DB_DIR.DIRECTORY_SEPARATOR."Temperature.db");

class Temperature {
    private $db;

    private function init() {
        $db = TEMPERATURE_SQLITE_DB;
        $sqlite = new DynamicSQLite($db);
        $sqlite->initDB();
        $sqlite->createTableBySQL('
            CREATE TABLE IF NOT EXISTS "temperature" (
                "datetime"	TEXT NOT NULL,
                "id"	TEXT NOT NULL,
                "value"	REAL NOT NULL DEFAULT 36.0,
                "note"	TEXT,
                PRIMARY KEY("datetime","id")
            )
        ');
    }

    function __construct() {
        $this->init();
        $this->db = new SQLite3(TEMPERATURE_SQLITE_DB);
    }

    function __destruct() { }

    public function get($id, $limit = 30) {
        $id = trim($id);
        if (empty($id)) {
            $stm = $this->db->prepare('SELECT * FROM temperature ORDER BY datetime DESC');
        } else {
            $stm = $this->db->prepare('SELECT * FROM temperature WHERE id = :id ORDER BY datetime DESC');
            $stm->bindParam(':id', $id);
        }
        $ret = $stm->execute();

        global $log;
        $log->info(__METHOD__.": 取得溫度紀錄".($ret ? "成功" : "失敗【".$stm->getSQL()."】")."。");
        $array = array();
        $count = 0;
        while (($row = $ret->fetchArray()) && $count < $limit) {
            $array[] = $row;
            $count++;
        }

        return $array;
    }

    public function getByDate($date) {
        $stm = $this->db->prepare('SELECT * FROM temperature WHERE datetime LIKE :date ORDER BY datetime DESC');
        $stm->bindValue(':date', $date."%");
        $ret = $stm->execute();

        global $log;
        $log->info(__METHOD__.": 取得 ${date} 溫度紀錄".($ret ? "成功" : "失敗【".$stm->getSQL()."】")."。");
        $array = array();
        while ($row = $ret->fetchArray()) {
            $array[] = $row;
        }

        return $array;
    }

    public function getByIdAndDate($id, $date) {
        $id = trim($id);
        $stm = $this->db->prepare('SELECT * FROM temperature WHERE id = :id AND datetime LIKE :date ORDER BY datetime DESC');
        $stm->bindParam(':id', $id);
        $stm->bindValue(':date', $date."%");
        $ret = $stm->execute();

        global $log;
        $log->info(__METHOD__.": 取得 ${$id} ${date} 溫度紀錄".($ret ? "成功" : "失敗【".$stm->getSQL()."】")."。");
        $array = array();
        while ($row = $ret->fetchArray()) {
            $array[] = $row;
        }

        return $array;
    }

    public function getAMPMTemperatures($id, $AMPM) {
        $id = trim($id);
        if (empty($id)) {
            $stm = $this->db->prepare('SELECT * FROM temperature WHERE datetime BETWEEN :st AND :ed ORDER BY datetime DESC');
        } else {
            $stm = $this->db->prepare('SELECT * FROM temperature WHERE id = :id AND datetime BETWEEN :st AND :ed ORDER BY datetime DESC');
            $stm->bindValue(':id', $id);
        }

        $today = date("Y-m-d");
        if ($AMPM == 'AM') {
            $stm->bindValue(':st', "$today 00:00:00");
            $stm->bindValue(':ed', "$today 11:59:59");
        } else {
            $stm->bindValue(':st', "$today 12:00:00");
            $stm->bindValue(':ed', "$today 23:59:59");
        }

        $ret = $stm->execute();

        $array = array();
        while ($row = $ret->fetchArray()) {
            $array[] = $row;
        }

        return $array;
    }

    public function add($id, $temperature_value, $note = '') {
        global $log;
        $id = trim($id);
        $AMPM = date('A');
        $records = $this->getAMPMTemperatures($id, $AMPM);
        if (count($records) != 0) {
            $log->warning(__METHOD__.": ${id} ${AMPM} 已有體溫紀錄。");
            return false;
        }
        $stm = $this->db->prepare("INSERT INTO temperature (datetime,id,value,note) VALUES (:date,:id,:value,:note)");
        $stm->bindParam(':date', date("Y-m-d H:i:s"));
        $stm->bindParam(':id', $id);
        $stm->bindParam(':value', $temperature_value);
        $stm->bindParam(':note', $note);
        $ret = $stm->execute();

        $log->info(__METHOD__.": 新增體溫紀錄".($ret ? "成功" : "失敗【".$stm->getSQL()."】")."。");

        return $ret;
    }

    public function remove($id, $datetime) {
        $id = trim($id);
        $stm = $this->db->prepare("DELETE FROM temperature WHERE datetime = :date AND id = :id");
        $stm->bindParam(':date', $datetime);
        $stm->bindParam(':id', $id);
        $ret = $stm->execute();
        global $log;
        $log->info(__METHOD__.": 刪除體溫紀錄".($ret ? "成功" : "失敗【".$stm->getSQL()."】")."。");
        return $ret;
    }
}
