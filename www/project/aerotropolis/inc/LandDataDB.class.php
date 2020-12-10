<?php
require_once('init.php');
require_once(ROOT_DIR.DIRECTORY_SEPARATOR.'inc'.DIRECTORY_SEPARATOR.'DynamicSQLite.class.php');

class LandDataDB {
    private $db;

    private function getLandDataDB() {
        $db_path = DB_PATH.DIRECTORY_SEPARATOR.'land_data.db';
        $sqlite = new DynamicSQLite($db_path);
        $sqlite->initDB();
        $sqlite->createTableBySQL('
            CREATE TABLE IF NOT EXISTS "data_mapping" (
                "code"	TEXT NOT NULL,
                "number"	TEXT NOT NULL,
                "name"	TEXT NOT NULL,
                "content"	TEXT,
                PRIMARY KEY("code","number")
            )
        ');
        $sqlite->createTableBySQL('
            CREATE INDEX IF NOT EXISTS "name" ON "data_mapping" (
                "name"
            )
        ');
        $sqlite->createTableBySQL('
            CREATE TABLE IF NOT EXISTS "people_data_mapping" (
                "id"	INTEGER NOT NULL,
                "household"	TEXT NOT NULL,
                "pid"	TEXT NOT NULL,
                "pname"	TEXT NOT NULL,
                "owned_number"	TEXT,
                PRIMARY KEY("id" AUTOINCREMENT)
            )
        ');
        $sqlite->createTableBySQL('
            CREATE INDEX IF NOT EXISTS "pname" ON "people_data_mapping" (
                "pname"
            )
        ');
        return $db_path;
    }

    function __construct() {
        $path = $this->getLandDataDB();
        $this->db = new SQLite3($path);
        $this->db->exec("PRAGMA cache_size = 100000");
        $this->db->exec("PRAGMA temp_store = MEMORY");
        $this->db->exec("BEGIN TRANSACTION");
    }

    function __destruct() {
        $this->db->exec("END TRANSACTION");
        $this->db->close();
    }
    /**
     * For Data Import
     */
    public function addLandData($code, $name, $number, $content) {
        $stm = $this->db->prepare("REPLACE INTO data_mapping ('code', 'number', 'name', 'content') VALUES (:code, :number, :name, :content)");
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

    public function addPeopleMapping($household, $pid, $pname, $owned_number) {
        $stm = $this->db->prepare("REPLACE INTO people_data_mapping ('household', 'pid', 'pname', 'owned_number') VALUES (:household, :pid, :pname, :owned_number)");
        $stm->bindParam(':household', $household);
        $stm->bindParam(':pid', $pid);
        $stm->bindParam(':pname', $pname);
        $stm->bindParam(':owned_number', $owned_number);
        return $stm->execute() === FALSE ? false : true;
    }

    public function removePeopleMapping($keyword) {
        $stm = $this->db->prepare("DELETE FROM people_data_mapping WHERE household LIKE '".$keyword."%'");
        if ($stm) {
            // $stm->bindParam(':keyword', $keyword);
            return $stm->execute() === FALSE ? false : true;
        }
        global $log;
        $log->error(__METHOD__.': prepare failed. ('."DELETE FROM people_data_mapping WHERE household LIKE CONCAT('%', :keyword, '%')".')');
        return false;
    }
    /**
     * For Query
     */
    public function preftechPeopleByColumn($column, $keyword) {
        $sql = "SELECT * FROM people_data_mapping WHERE $column LIKE '%' || :bv_keyword || '%'";
        if ($stm = $this->db->prepare($sql)) {
            $stm->bindParam(':bv_keyword', $keyword);
            $result = $stm->execute();
            $return = [];
            if ($result === false) {
                global $log;
                $log->warning(__METHOD__.": 找不到資料 ($column, $keyword)");
                return $return;
            }
            while($row = $result->fetchArray(SQLITE3_ASSOC)) {
                $return[] = $row;
            }
            return $return;
        }
        global $log;
        $log->error(__METHOD__.': prepare failed. ('.$sql.')');
        return false;
    }

    private function searchPeopleByColumn($column, $keyword) {
        $sql = "SELECT * FROM people_data_mapping WHERE $column = :bv_keyword";
        if ($stm = $this->db->prepare($sql)) {
            $stm->bindParam(':bv_keyword', $keyword);
            $result = $stm->execute();
            $return = [];
            if ($result === false) {
                global $log;
                $log->warning(__METHOD__.": 找不到資料 ($column, $keyword)");
                return $return;
            }
            while($row = $result->fetchArray(SQLITE3_ASSOC)) {
                $return[] = $row;
            }
            return $return;
        }
        global $log;
        $log->error(__METHOD__.': prepare failed. ('.$sql.')');
        return false;
    }

    public function searchPeopleByHouseholdCode($household_code) {
        return $this->searchPeopleByColumn('household', $household_code);
    }

    public function searchPeopleByPid($pid) {
        return $this->searchPeopleByColumn('pid', $pid);
    }

    public function searchPeopleByPname($pname) {
        return $this->searchPeopleByColumn('pname', $pname);
    }

    public function searchPeopleByOwnedNumber($number) {
        return $this->searchPeopleByColumn('owned_number', $number);
    }

    public function queryData($code, $number) {
        $sql = "SELECT * FROM data_mapping WHERE code = :bv_code AND number = :bv_number";
        if ($stm = $this->db->prepare($sql)) {
            $stm->bindParam(':bv_code', $code);
            $stm->bindParam(':bv_number', $number);
            $result = $stm->execute();
            $return = [];
            if ($result === false) {
                global $log;
                $log->warning(__METHOD__.": 找不到資料 ($code, $number)");
                return $return;
            }
            while($row = $result->fetchArray(SQLITE3_ASSOC)) {
                $return[] = $row;
            }
            return $return;
        }
        global $log;
        $log->error(__METHOD__.': prepare failed. ('.$sql.')');
        return false;
    }
}
