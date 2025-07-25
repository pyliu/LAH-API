<?php
require_once('init.php');
require_once('System.class.php');
require_once('SQLiteDBFactory.class.php');
require_once('SQLiteOFFICES.class.php');

class SQLiteOFFICESSTATS {
    private $db;

    private function bindParams(&$stm, &$row) {
        if ($stm === false) {
            Logger::getInstance()->error(__METHOD__.": bindParams because of \$stm is false.");
            return;
        }

        $stm->bindParam(':id', $row['id']);
        $stm->bindParam(':name', $row['name']);
        $stm->bindParam(':state', $row['state']);
        $stm->bindParam(':response', $row['response']);
        $stm->bindParam(':timestamp', $row['timestamp']);
    }

    private function prepareArray(&$stmt) {
        $result = $stmt->execute();
        $return = [];
        if ($result) {
            while($row = $result->fetchArray(SQLITE3_ASSOC)) {
                $return[] = $row;
            }
        } else {
            Logger::getInstance()->warning(__CLASS__."::".__METHOD__.": execute SQL unsuccessfully.");
        }
        return $return;
    }

    function __construct() {
        $db_path = SQLiteDBFactory::getOFFICESSTATSDB();
        $this->db = new SQLite3($db_path);
        // 對於高併發的讀寫場景，可以考慮將 SQLite 的日誌模式切換為「預寫式日誌 (Write-Ahead Logging)」。它對併發的處理更好，可以減少鎖定問題
        $this->db->exec("PRAGMA journal_mode = WAL");
        $this->db->exec("PRAGMA cache_size = 100000");
        $this->db->exec("PRAGMA temp_store = MEMORY");
        $this->db->exec("BEGIN TRANSACTION");
    }

    function __destruct() {
        $this->db->exec("END TRANSACTION");
        $this->db->close();
    }

    public function clean() {
        $stm = $this->db->prepare("DELETE FROM OFFICES_STATS");
        return $stm->execute() === FALSE ? false : true;
    }

    public function cleanNormalRecords() {
        $stm = $this->db->prepare("DELETE FROM OFFICES_STATS WHERE state = 'UP'");
        return $stm->execute() === FALSE ? false : true;
    }

    public function replace($row) {
        $stm = $this->db->prepare("
            REPLACE INTO OFFICES_STATS ('id', 'name', 'state', 'response', 'timestamp')
            VALUES (:id, :name, :state, :response, :timestamp)
        ");
        $this->bindParams($stm, $row);
        return $stm->execute() === FALSE ? false : true;
    }
    
    public function getLatestBatch() {
        $latest_id = $this->db->querySingle("SELECT serial from OFFICES_STATS ORDER BY serial DESC");
        $office = new SQLiteOFFICES();
        $total = $office->count();
        if($stmt = $this->db->prepare("
            SELECT * FROM OFFICES_STATS
            WHERE
                serial > ".($latest_id - $total)."
            ORDER BY serial
        ")) {
            return $this->prepareArray($stmt);
        }
    }
    
    public function getRecentDownRecords($limit = 100) {
        if($stmt = $this->db->prepare("
            SELECT * FROM OFFICES_STATS
            WHERE
                state <> 'UP'
            ORDER BY timestamp DESC
            LIMIT ".$limit."
        ")) {
            return $this->prepareArray($stmt);
        }
    }

    public function getRecentDownRecordsByTimestamp($offset) {
        if($stmt = $this->db->prepare("
            SELECT * FROM OFFICES_STATS
            WHERE
                state <> 'UP' AND
                timestamp > ".(time() - $offset)."
            ORDER BY timestamp DESC
        ")) {
            return $this->prepareArray($stmt);
        }
    }
    /**
     * 移除某時間前資料
     */
    public function deleteBeforeTimestamp($ts) {
        $stm = $this->db->prepare("DELETE FROM OFFICES_STATS WHERE timestamp < :bv_ts");
        $stm->bindParam(':bv_ts', $ts);
        return $stm->execute() === FALSE ? false : true;
    }
}
