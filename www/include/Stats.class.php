<?php
require_once('init.php');

class Stats {
    private $db;

    private function getTotal($id) {
        return $this->db->querySingle("SELECT TOTAL from stats WHERE ID = '$id'");
    }

    private function updateTotal($id, $total) {
        $stm = $this->db->prepare("UPDATE stats set TOTAL = :total WHERE  ID = :id");
        $stm->bindValue(':total', intval($total));
        $stm->bindParam(':id', $id);
        return $stm->execute() === FALSE ? false : true;
    }

    function __construct() {
        $this->db = new SQLite3(DEF_SQLITE_DB);
    }

    function __destruct() { }

    public function addOverdueMsgCount($count = 1) {
        global $log;
        $total = $this->getTotal('overdue_msg_count') + $count;
        $ret = $this->updateTotal('overdue_msg_count', $total);
        $log->info(__METHOD__.": overdue_msg_count 計數器+${count}，目前值為 ${total} 【".($ret ? "成功" : "失敗")."】");
    }

    public function addOverdueStatsDetail($data) {
        // $data => ["ID" => HB0000, "RECORDS" => array, "DATETIME" => 2020-03-04 08:50:23, "NOTE" => XXX]
        // overdue_stats_detail
        $stm = $this->db->prepare("INSERT INTO overdue_stats_detail (datetime,id,count,note) VALUES (:date, :id, :count, :note)");
        $stm->bindParam(':date', $data["DATETIME"]);
        $stm->bindParam(':id', $data["ID"]);
        $stm->bindValue(':count', count($data["RECORDS"]));
        $stm->bindParam(':note', $data["NOTE"]);
        $ret = $stm->execute();
        global $log;
        $log->info(__METHOD__.": 新增逾期統計詳情".($ret ? "成功" : "失敗【".$stm->getSQL()."】")."。");
    }

    public function addXcasesStats($data) {
        // $data => ["date" => "2020-03-04 10:10:10","found" => 2, "note" => XXXXXXXXX]
        // xcase_stats
        $stm = $this->db-prepare("INSERT INTO xcase_stats (datetime,found,note) VALUES (:date, :found, :note)");
        $stm->bindParam(':date', $data["date"]);
        $stm->bindParam(':found', $data["found"]);
        $stm->bindParam(':note', $data["note"]);
        $ret = $stm->execute();
        global $log;
        $log->info(__METHOD__.": 新增跨所註記遺失案件統計".($ret ? "成功" : "失敗【".$stm->getSQL()."】")."。");
        // 更新 total counter
        $total = $this->getTotal('xcase_found_count') + $data["found"];
        $ret = $this->updateTotal('xcase_found_count', $total);
        $log->info(__METHOD__.":xcase_found_count 計數器+".$data["found"]."，目前值為 ${total} 【".($ret ? "成功" : "失敗")."】");

    }
}
?>
