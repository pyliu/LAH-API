<?php
require_once("init.php");

class SQLiteDB extends SQLite3 {
    private $filepath;

    private function createStatsTable() {
        global $log;
        $sql = "
            CREATE TABLE IF NOT EXISTS stats (
                ID INT PRIMARY KEY NOT NULL,
                NAME TEXT NOT NULL,
                COUNT INT NOT NULL DEFAULT 0
            );
        ";
        $ret = $this->exec($sql);
        if(!$ret){
            $log->error($this->lastErrorMsg());
        }
    }

    function __construct($db_path = DEF_SQLITE_DB) {
        global $log;
        $this->filepath = $db_path;
        $this->open($this->filepath);
        $log->info("開啟 $this->filepath 成功。");
        $this->createStatsTable();
    }

    function __destruct() {
        $this->close();
    }
}
?>
