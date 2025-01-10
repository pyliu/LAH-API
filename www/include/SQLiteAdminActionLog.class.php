<?php
require_once('init.php');
require_once('SQLiteDBFactory.class.php');

class SQLiteAdminActionLog {
    // singleton
    private static $_instance = null;
    public static function getInstance() {
        if (!(self::$_instance instanceof SQLiteAdminActionLog)) {
            self::$_instance = new SQLiteAdminActionLog();
        }
        return self::$_instance;
    }

    private $db;

    private function bindParams(&$stm, &$row) {
        if ($stm === false) {
            Logger::getInstance()->error(__METHOD__.": \$stm is false, can not bind parameters.");
            Logger::getInstance()->warning(__METHOD__.": ".print_r($row, true));
        } else {
            try {
                Logger::getInstance()->info(__CLASS__.'::'.__METHOD__.': '.print_r($row, true));
                $stm->bindParam(':ip', $row['ip']);
                $stm->bindParam(':action', $row['action']);
                $stm->bindParam(':path', $row['path']);
                $stm->bindParam(':note', $row['note']);
                $stm->bindValue(':timestamp', time());
                return true;
            } catch (Exception $ex) {
                Logger::getInstance()->error(__CLASS__.'::'.__METHOD__.": ".$ex->getMessage());
            }
        }
        return false;
    }

    private function prepareArray(&$stmt) {
        try {
            $result = $stmt->execute();
            $return = [];
            if ($result) {
                while($row = $result->fetchArray(SQLITE3_ASSOC)) {
                    $return[] = $row;
                }
            } else {
                Logger::getInstance()->warning(__CLASS__."::".__METHOD__.": execute SQL unsuccessfully.");
            }
        } catch (Exception $ex) {
            Logger::getInstance()->error(__CLASS__.'::'.__METHOD__.": ".$ex->getMessage());
        }
        return $return;
    }

    private function timestampToAdDate($timestamp) {
        // Check if timestamp is in milliseconds
        if (strlen((string)$timestamp) > 10) { 
            $timestamp /= 1000; // Convert milliseconds to seconds
        }
        // Create DateTime object from timestamp
        $date = new DateTime("@$timestamp");
        // Format the date as "Y-m-d H:i:s"
        return $date->format('Y-m-d H:i:s'); 
    }

    private function __construct() {
        $db_path = SQLiteDBFactory::getAdminActionLogDB();
        $this->db = new SQLite3($db_path);
        $this->db->exec("PRAGMA cache_size = 100000");
        $this->db->exec("PRAGMA temp_store = MEMORY");
        $this->db->exec("BEGIN TRANSACTION");
    }
    // private because of singleton
    private function __clone() { }

    function __destruct() {
        $this->db->exec("END TRANSACTION");
        $this->db->close();
    }

    public function clean() {
        try {
            $stm = $this->db->prepare("DELETE FROM admin_action_log");
            return $stm->execute() === FALSE ? false : true;
        } catch (Exception $ex) {
            Logger::getInstance()->error(__CLASS__.'::'.__METHOD__.": ".$ex->getMessage());
        }
        return false;
    }

    public function replace(&$row) {
        try {
            $stm = $this->db->prepare("
                REPLACE INTO admin_action_log ('ip', 'timestamp', 'action', 'path', 'note')
                VALUES (:ip, :timestamp, :action, :path, :note)
            ");
            $this->bindParams($stm, $row);
            return $stm->execute() === FALSE ? false : true;
        } catch (Exception $ex) {
            Logger::getInstance()->error(__CLASS__.'::'.__METHOD__.": ".$ex->getMessage());
        }
    }
    /**
     * use timestamp to get the records
     */
    public function get($st, $ed) {
        try {
            $st_ad = $this->timestampToAdDate($st);
            $ed_ad = $this->timestampToAdDate($ed);
            Logger::getInstance()->info(__CLASS__.'::'.__METHOD__.": 取得 ADMIN 寫入操作記錄檔資料 $st_ad ~ $ed_ad");
            $stm = $this->db->prepare("
                select * from admin_action_log
                where timestamp between :st and :ed
            ");
            $stm->bindParam(':st', $st);
            $stm->bindParam(':ed', $ed);
            return $this->prepareArray($stm);
        } catch (Exception $ex) {
            Logger::getInstance()->error(__CLASS__.'::'.__METHOD__.": ".$ex->getMessage());
        }
        return array();
    }
}
