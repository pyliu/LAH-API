<?php
require_once("init.php");

class SQLiteDB extends SQLite3 {
    private $filepath;
    private $last_ret;

    function __construct($db_path = DEF_SQLITE_DB) {
        global $log;
        $this->filepath = $db_path;
        $this->open($this->filepath);
        $log->info("開啟 $this->filepath 成功。");
    }

    function __destruct() {
        $this->close();
    }

    public function select($sql, $only_one = false) {
        global $log;
        $log->info(__METHOD__.": ".$sql." 只取一筆: $only_one");
        $this->last_ret = $this->query($sql);
        if ($only_one) {
            return $this->last_ret->fetchArray(SQLITE3_ASSOC);
        }
        $ret = array();
        while ($row = $this->last_ret->fetchArray(SQLITE3_ASSOC)) {
            $ret[] = $row;
        }
        return $ret;
    }

    public function insert($sql) {
        global $log;
        $log->info(__METHOD__.": ".$sql);
        $ret = $this->exec($sql);
        if(!$ret){
            $log->error($this->lastErrorMsg());
            return false;
        }
        $log->info(__METHOD__.": 插入資料成功。");
        return true;
    }

    public function update($sql) {
        global $log;
        $log->info(__METHOD__.": ".$sql);
        $ret = $this->exec($sql);
        if(!$ret){
            $log->error($this->lastErrorMsg());
            return false;
        }
        $log->info(__METHOD__.": 更新資料成功。 (".$this->changes()."筆)");
        return $this->changes();
    }

    public function delete($sql) {
        global $log;
        $log->info(__METHOD__.": ".$sql);
        $ret = $this->exec($sql);
        if(!$ret){
            $log->error($this->lastErrorMsg());
            return false;
        }
        $log->info(__METHOD__.": 刪除資料成功。 (".$this->changes()."筆)");
        return $this->changes();
    }
}
?>
