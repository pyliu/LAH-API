<?php
require_once('init.php');
require_once(ROOT_DIR.DIRECTORY_SEPARATOR.'DynamicSQLite.class.php');

class LandDataDB {
    private $db;

    private function getLandDataDB() {
        $db_path = DB_PATH.DIRECTORY_SEPARATOR.'land_data.db';
        $sqlite = new DynamicSQLite($db_path);
        $sqlite->initDB();
        $sqlite->createTableBySQL('
            CREATE TABLE IF NOT EXISTS "data_mapping" (
                "id"	INTEGER,
                "code"	TEXT NOT NULL,
                "name"	TEXT NOT NULL,
                "number"	TEXT NOT NULL,
                "content"	TEXT,
                PRIMARY KEY("id" AUTOINCREMENT)
            )
        ');
        $sqlite->createTableBySQL('
            CREATE INDEX IF NOT EXISTS "code_number" ON "data_mapping" (
                "code",
                "number"
            )
        ');
        $sqlite->createTableBySQL('
            CREATE INDEX IF NOT EXISTS "code" ON "data_mapping" (
                "code"
            )
        ');
        $sqlite->createTableBySQL('
            CREATE TABLE IF NOT EXISTS "people_data_mapping" (
                "id"	INTEGER,
                "houshold"	TEXT NOT NULL,
                "pid"	TEXT NOT NULL,
                "pname"	TEXT NOT NULL,
                "owned_number"	TEXT,
                PRIMARY KEY("id" AUTOINCREMENT)
            )
        ');
        $sqlite->createTableBySQL('
            CREATE INDEX IF NOT EXISTS "pid_owned" ON "people_data_mapping" (
                "pid",
                "owned_number"
            )
        ');
        return $db_path;
    }

    function __construct() {
        $path = $this->getLandDataDB();
        $this->db = new SQLite3($path);
    }

    function __destruct() { $this->db->close(); }

    public function addLandData($code, $name, $number, $content) {
        $stm = $this->db->prepare("INSERT INTO data_mapping ('code', 'name', 'number', 'content') VALUES (:code, :name, :number, :content)");
        $stm->bindParam(':code', $code);
        $stm->bindParam(':number', $number);
        $stm->bindParam(':name', $name);
        $stm->bindParam(':content', $content);
        return $stm->execute() === FALSE ? false : true;
    }

    public function removeLandData($code) {
        $stm = $this->db->prepare("DELETE FROM data_mapping WHERE code = :code");
        $stm->bindParam(':code', $code);
        return $stm->execute() === FALSE ? false : true;
    }
    /**
     * Early LAH Stats
     */
    /*
    public function getTotal($id) {
        return $this->db->querySingle("SELECT TOTAL from stats WHERE ID = '$id'");
    }

    public function updateTotal($id, $total) {
        $stm = $this->db->prepare("UPDATE stats set TOTAL = :total WHERE  ID = :id");
        $stm->bindValue(':total', intval($total));
        $stm->bindParam(':id', $id);
        return $stm->execute() === FALSE ? false : true;
    }

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
        if (!$ret) {
            global $log;
            $log->error(__METHOD__.": 新增逾期統計詳情失敗【".$stm->getSQL()."】");
        }
    }

    public function addXcasesStats($data) {
        // $data => ["date" => "2020-03-04 10:10:10","found" => 2, "note" => XXXXXXXXX]
        // xcase_stats
        $stm = $this->db->prepare("INSERT INTO xcase_stats (datetime,found,note) VALUES (:date, :found, :note)");
        $stm->bindParam(':date', $data["date"]);
        $stm->bindParam(':found', $data["found"]);
        $stm->bindParam(':note', $data["note"]);
        $ret = $stm->execute();
        global $log;
        $log->info(__METHOD__.": 新增跨所註記遺失案件統計".($ret ? "成功" : "失敗【".$stm->getSQL()."】")."。");
        // 更新 total counter
        $total = $this->getTotal('xcase_found_count') + $data["found"];
        $ret = $this->updateTotal('xcase_found_count', $total);
        $log->info(__METHOD__.": xcase_found_count 計數器+".$data["found"]."，目前值為 ${total} 【".($ret ? "成功" : "失敗")."】");

    }

    public function addBadSurCaseStats($data) {
        // $data => ["date" => "2020-03-04 10:10:10","found" => 2, "note" => XXXXXXXXX]
        // xcase_stats
        $stm = $this->db->prepare("INSERT INTO found_bad_sur_case_stats (datetime,found,note) VALUES (:date, :found, :note)");
        $stm->bindParam(':date', $data["date"]);
        $stm->bindParam(':found', $data["found"]);
        $stm->bindParam(':note', $data["note"]);
        $ret = $stm->execute();
        global $log;
        $log->info(__METHOD__.": 新增複丈問題案件統計".($ret ? "成功" : "失敗【".$stm->getSQL()."】")."。");
        // 更新 total counter
        $total = $this->getTotal('bad_sur_case_found_count') + $data["found"];
        $ret = $this->updateTotal('bad_sur_case_found_count', $total);
        $log->info(__METHOD__.": bad_sur_case_found_count 計數器+".$data["found"]."，目前值為 ${total} 【".($ret ? "成功" : "失敗")."】");

    }

    public function addStatsRawData($id, $data) {
        // $data => php array
        // overdue_stats_detail
        $stm = $this->db->prepare("INSERT INTO stats_raw_data (id,data) VALUES (:id, :data)");
        $param = serialize($data);
        $stm->bindParam(':data', $param);
        $stm->bindParam(':id', $id);
        $ret = $stm->execute();
        if (!$ret) {
            global $log;
            $log->error(__METHOD__.": 新增統計 RAW DATA 失敗【".$id.", ".$stm->getSQL()."】");
        }
        return $ret;
    }

    public function removeAllStatsRawData($year_month) {
        $stm = $this->db->prepare("DELETE FROM stats_raw_data WHERE id LIKE '%_".$year_month."'");
        $ret = $stm->execute();
        if (!$ret) {
            global $log;
            $log->error(__METHOD__.": 移除統計 RAW DATA 失敗【".$year_month.", ".$stm->getSQL()."】");
        }
        return $ret;
    }

    public function removeStatsRawData($id) {
        // $data => php array
        // overdue_stats_detail
        $stm = $this->db->prepare("DELETE FROM stats_raw_data WHERE id = :id");
        $stm->bindParam(':id', $id);
        $ret = $stm->execute();
        if (!$ret) {
            global $log;
            $log->error(__METHOD__.": 移除統計 RAW DATA 失敗【".$id.", ".$stm->getSQL()."】");
        }
        return $ret;
    }

    public function getStatsRawData($id) {
        $data = $this->db->querySingle("SELECT data from stats_raw_data WHERE id = '$id'");
        return empty($data) ? false : unserialize($data);
    }
    */
}
