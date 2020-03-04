<?php
require_once('init.php');
require_once(ROOT_DIR.'/include/SQLiteDB.class.php');

class Stats {
    private $db;

    function __construct() {
        $this->db = new SQLiteDB();
    }

    function __destruct() { }

    public function addOverdueMsgCount($count = 1) {
        global $log;
        $arr = $this->db->select("SELECT TOTAL from stats WHERE ID = 'overdue_msg_count'", true);
        $current = $arr["TOTAL"] + $count;
        $this->db->update("UPDATE stats set TOTAL = '".$current."' WHERE  ID = 'overdue_msg_count'");
        $log->info(__METHOD__.": 計數器+${count}，目前值為 ${current}");
    }

    public function addOverdueStatsDetail($data) {
        // $data => ["ID" => HB0000, "RECORDS" => array, "DATETIME" => 2020-03-04 08:50:23, "NOTE" => XXX]
        // overdue_stats_detail
        global $log;
        $sql = "INSERT INTO overdue_stats_detail (datetime,id,count,note) VALUES ('".$data["DATETIME"]."', '".$data["ID"]."',".count($data["RECORDS"]).",'".$data["NOTE"]."')";
        $ret = $this->db->insert($sql);
        $log->info(__METHOD__.": 新增逾期統計詳情".($ret ? "成功" : "失敗【${sql}】")."。");
    }
}
?>
