<?php
require_once('init.php');
require_once('System.class.php');
require_once('SQLiteDBFactory.class.php');

class SQLiteSMSLog {
    private $db;

    private function bindParams(&$stm, &$row) {
        if ($stm === false) {
            Logger::getInstance()->error(__METHOD__.": bindParams because of \$stm is false.");
            return;
        }

        $stm->bindParam(':ma5_no', $row['ma5_no']);
        $stm->bindParam(':mobile', $row['mobile']);
        $stm->bindParam(':message', $row['message']);
        $stm->bindParam(':note', $row['note']);
        $stm->bindValue(':timestamp', time());
        $stm->bindValue(':count', $row['count']);
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
        $db_path = SQLiteDBFactory::getSMSLogDB();
        $this->db = new SQLite3($db_path);
        $this->db->exec("PRAGMA cache_size = 100000");
        $this->db->exec("PRAGMA temp_store = MEMORY");
        $this->db->exec("BEGIN TRANSACTION");
    }

    function __destruct() {
        $this->db->exec("END TRANSACTION");
        $this->db->close();
    }

    public function exists($ma5_no) {
        $ret = $this->db->querySingle("SELECT id from MOICAS_MA05_LOG WHERE MA5_NO = '$ma5_no'");
        return !empty($ret);
    }

    public function clean() {
        $stm = $this->db->prepare("DELETE FROM MOICAS_MA05_LOG");
        return $stm->execute() === FALSE ? false : true;
    }

    public function replace(&$row) {
        $stm = $this->db->prepare("
            REPLACE INTO MOICAS_MA05_LOG ('MA5_NO', 'MOBILE', 'MESSAGE', 'COUNT', 'NOTE', 'TIMESTAMP')
            VALUES (:ma5_no, :mobile, :message, :count, :note, :timestamp)
        ");
        $this->bindParams($stm, $row);
        return $stm->execute() === FALSE ? false : true;
    }

    public function addCount($ma5_no) {
        if (!$this->exists($ma5_no)) {
            Logger::getInstance()->warning(__METHOD__.": $ma5_no 不在控管系統DB內，無法記錄次數。 ".SQLiteDBFactory::getSMSLogDB());
            return false;
        }
        $stm = $this->db->prepare("
            UPDATE MOICAS_MA05_LOG
            SET COUNT = COUNT + 1
            WHERE MA5_NO = :ma5_no
        ");
        $stm->bindParam(':ma5_no', $ma5_no);
        return $stm->execute() === FALSE ? false : true;
    }
}
