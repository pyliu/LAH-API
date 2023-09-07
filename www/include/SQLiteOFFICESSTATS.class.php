<?php
require_once('init.php');
require_once('System.class.php');
require_once('SQLiteDBFactory.class.php');

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
        while($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $return[] = $row;
        }
        return $return;
    }

    function __construct() {
        $db_path = SQLiteDBFactory::getOFFICESSTATSDB();
        $this->db = new SQLite3($db_path);
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

    public function replace($row) {
        $stm = $this->db->prepare("
            REPLACE INTO OFFICES_STATS ('id', 'name', 'state', 'response', 'timestamp')
            VALUES (:id, :name, :state, :response, :timestamp)
        ");
        $this->bindParams($stm, $row);
        return $stm->execute() === FALSE ? false : true;
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
