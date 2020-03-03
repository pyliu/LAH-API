<?php
require_once('init.php');
require_once(ROOT_DIR.'/include/SQLiteDB.class.php');

class Stats {
    private $db;

    function __construct() {
        $this->db = new SQLiteDB();
    }

    function __destruct() { }

    public function addOverdueMsgCount() {
        global $log;
        $arr = $this->db->select("SELECT COUNT from stats WHERE ID = 'overdue_msg_count'", true);
        $current = $arr["COUNT"];
        $this->db->update("UPDATE stats set COUNT = '".++$current."' WHERE  ID = 'overdue_msg_count'");
        $log->info(__METHOD__.": 計數器+1，目前值為 $current");
    }
}
?>
