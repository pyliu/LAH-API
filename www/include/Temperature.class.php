<?php
require_once('init.php');

class Temperature {
    private $db;

    function __construct() {
        $this->db = new SQLite3(TEMPERATURE_SQLITE_DB);
    }

    function __destruct() { }

    public function get($id) {
        if (empty($id)) {
            $stm = $this->db->prepare('SELECT * FROM temperature');
        } else {
            $stm = $this->db->prepare('SELECT * FROM temperature WHERE id = :id');
            $stm->bindParam(':id', $id);
        }
        $ret = $stm->execute();

        global $log;
        $log->info(__METHOD__.": 取得溫度紀錄".($ret ? "成功" : "失敗【".$stm->getSQL()."】")."。");
        $array = array();
        while ($row = $ret->fetchArray()) {
            $array[] = $row;
        }

        return $array;
    }

    public function set($id, $temperature_value, $note = '') {
        $stm = $this->db->prepare("INSERT INTO temperature (datetime,id,value,note) VALUES (:date,:id,:value,:note)");
        $stm->bindParam(':date', date("Y-m-d H:i:s"));
        $stm->bindParam(':id', $id);
        $stm->bindParam(':value', $temperature_value);
        $stm->bindParam(':note', $note);
        $ret = $stm->execute();
        global $log;
        $log->info(__METHOD__.": 新增體溫紀錄".($ret ? "成功" : "失敗【".$stm->getSQL()."】")."。");
        return $ret;
    }
}
?>
