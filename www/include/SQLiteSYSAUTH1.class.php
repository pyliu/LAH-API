<?php
require_once('init.php');
require_once('System.class.php');
require_once('DynamicSQLite.class.php');

class SQLiteSYSAUTH1 {
    private $db;

    private function bindParams(&$stm, &$row) {
        if ($stm === false) {
            global $log;
            $log->error(__METHOD__.": bindUserParams because of \$stm is false.");
            return;
        }

        $stm->bindParam(':id', $row['USER_ID']);
        $stm->bindParam(':name', $row['USER_NAME']);
        $stm->bindValue(':password', $row['USER_PSW']);
        $stm->bindParam(':group_id', $row['GROUP_ID']);
        $stm->bindParam(':valid', $row['VALID']);
    }

    private function replace(&$row) {
        $stm = $this->db->prepare("
            REPLACE INTO SYSAUTH1 ('USER_ID', 'USER_PSW', 'USER_NAME', 'GROUP_ID', 'VALID')
            VALUES (:id, :password, :name, :group_id, :valid)
        ");
        $this->bindParams($stm, $row);
        return $stm->execute() === FALSE ? false : true;
    }

    private function replaceDict(&$row) {
        $stm = $this->db->prepare("
            REPLACE INTO SYSAUTH1_ALL ('USER_ID', 'USER_NAME')
            VALUES (:id, :name)
        ");
        
        if ($stm === false) {
            global $log;
            $log->error(__METHOD__.": failed because of \$stm is false.");
            return false;
        }

        $stm->bindParam(':id', $row['USER_ID']);
        $stm->bindParam(':name', $row['USER_NAME']);

        return $stm->execute() === FALSE ? false : true;
    }
    
    private function prepareArray(&$stmt) {
        $result = $stmt->execute();
        $return = [];
        while($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $return[] = $row;
        }
        return $return;
    }

    private function initDB() {
        $path = ROOT_DIR.DIRECTORY_SEPARATOR."assets".DIRECTORY_SEPARATOR."db".DIRECTORY_SEPARATOR."SYSAUTH1.db";
        $sqlite = new DynamicSQLite($path);
        $sqlite->initDB();
        $sqlite->createTableBySQL('
            CREATE TABLE IF NOT EXISTS "SYSAUTH1" (
                "USER_ID"	TEXT NOT NULL,
                "USER_PSW"	TEXT,
                "USER_NAME"	TEXT NOT NULL,
                "GROUP_ID"	INTEGER,
                "VALID"	INTEGER NOT NULL DEFAULT 0,
                PRIMARY KEY("USER_ID")
            )
        ');
        $sqlite->createTableBySQL('
            CREATE TABLE IF NOT EXISTS "SYSAUTH1_ALL" (
                "USER_ID"	TEXT NOT NULL,
                "USER_NAME"	TEXT,
                PRIMARY KEY("USER_ID")
            )
        ');
        return $path;
    }

    private function getUsersByValid($int) {
        if($stmt = $this->db->prepare("SELECT * FROM SYSAUTH1 WHERE VALID = :valid ORDER BY USER_ID, USER_NAME")) {
            $stmt->bindParam(':valid', $int);
            return $this->prepareArray($stmt);
        } else {
            global $log;
            $log->error(__METHOD__.": 取得合法使用者資料失敗！");
        }
        return false;
    }

    function __construct() {
        $db_path = $this->initDB();
        $this->db = new SQLite3($db_path);
        $this->db->exec("PRAGMA cache_size = 100000");
        $this->db->exec("PRAGMA temp_store = MEMORY");
        $this->db->exec("BEGIN TRANSACTION");
    }

    function __destruct() {
        $this->db->exec("END TRANSACTION");
        $this->db->close();
    }

    public function exists($id) {
        $ret = $this->db->querySingle("SELECT USER_ID from SYSAUTH1 WHERE USER_ID = '".trim($id)."'");
        return !empty($ret);
    }

    public function import(&$row) {
        if (empty($row['USER_ID']) || empty($row['USER_NAME'])) {
            global $log;
            $log->warning(__METHOD__.": USER_ID or USER_NAME is empty. Import procedure can not be proceeded.");
            $log->warning(__METHOD__.": ".print_r($row, true));
            return false;
        }
        return $this->replace($row);
    }

    public function getAllUsers() {
        if($stmt = $this->db->prepare("SELECT * FROM SYSAUTH1 WHERE 1 = 1 ORDER BY USER_ID")) {
            return $this->prepareArray($stmt);
        } else {
            global $log;
            $log->error(__METHOD__.": 取得所有使用者資料失敗！");
        }
        return false;
    }

    public function getUserDictionary() {
        $result = array();
        if($stmt = $this->db->prepare("SELECT DISTINCT USER_ID, USER_NAME FROM SYSAUTH1_ALL UNION SELECT DISTINCT USER_ID, USER_NAME FROM SYSAUTH1 ORDER BY USER_ID")) {
            $handle = $stmt->execute();
            while($row = $handle->fetchArray(SQLITE3_ASSOC)) {
                $result[$row['USER_ID']] = $row['USER_NAME'];
            }
        } else {
            global $log;
            $log->error(__METHOD__.": 取得所有使用者名稱對應表失敗！");
        }
        return $result;
    }

    public function getValidUsers() {
        return $this->getUsersByValid(1);
    }

    public function getUnvalidUsers() {
        return $this->getUsersByValid(0);
    }
}
