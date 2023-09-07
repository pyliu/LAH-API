<?php
require_once('init.php');
require_once('System.class.php');
require_once('SQLiteDBFactory.class.php');

class SQLiteOFFICES {
    private $db;

    private function bindParams(&$stm, &$row) {
        if ($stm === false) {
            Logger::getInstance()->error(__METHOD__.": bindParams because of \$stm is false.");
            return;
        }

        $stm->bindParam(':id', $row['IID']);
        $stm->bindParam(':name', $row['INAME']);
        $stm->bindParam(':alias', str_replace('alias=', '', $row['INOTE']));
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
        $db_path = SQLiteDBFactory::getOFFICESDB();
        $this->db = new SQLite3($db_path);
        $this->db->exec("PRAGMA cache_size = 100000");
        $this->db->exec("PRAGMA temp_store = MEMORY");
        $this->db->exec("BEGIN TRANSACTION");
        if (!$this->exists('HA')) {
            $this->importFromOraDB();
        }
    }

    function __destruct() {
        $this->db->exec("END TRANSACTION");
        $this->db->close();
    }

    public function importFromOraDB() {
        // check if l3hweb is reachable
        $main_db_ip = System::getInstance()->get('ORA_DB_HXWEB_IP');
        $main_db_port = System::getInstance()->get('ORA_DB_HXWEB_PORT');
        $latency = pingDomain($main_db_ip, $main_db_port);
    
        // not reachable
        if ($latency > 999 || $latency == '') {
            Logger::getInstance()->error(__METHOD__.': 無法連線主DB，無法進行匯入所有辦公室快取資料庫。');
            return false;
        }

        $db = new OraDB();
        $sql = "select * from MOICAC.LANDIP t where iname like '%".mb_convert_encoding('地政事務所', "big5")."' order by IID";
        $db->parse($sql);
        $db->execute();
        $rows = $db->fetchAll();
        $this->clean();
        $count = 0;
        foreach ($rows as $row) {
            $this->replace($row);
            $count++;
        }

        Logger::getInstance()->info(__METHOD__.': 匯入 '.$count.' 筆地政事務所檔資料。 【OFFICES.db、LANDIP table】');
        
        return $count;
    }

    public function exists($id) {
        $ret = $this->db->querySingle("SELECT id from OFFICES WHERE id = '$id'");
        return !empty($ret);
    }

    public function clean() {
        $stm = $this->db->prepare("DELETE FROM OFFICES");
        return $stm->execute() === FALSE ? false : true;
    }

    public function replace(&$row) {
        $stm = $this->db->prepare("
            REPLACE INTO OFFICES ('ID', 'NAME', 'ALIAS')
            VALUES (:id, :name, :alias)
        ");
        $this->bindParams($stm, $row);
        return $stm->execute() === FALSE ? false : true;
    }
    /**
     * 取得辦公室資料
     */
    public function get($id) {
        if($stmt = $this->db->prepare("SELECT * FROM OFFICES WHERE ID = '$id'")) {
            return $this->prepareArray($stmt);
        }
        return false;
    }
    /**
     * 取得所有辦公室資料
     */
    public function getAll($filter = false) {
        if ($filter) {
            if($stmt = $this->db->prepare("SELECT * FROM OFFICES WHERE (ID != 'CB' AND ID != 'CC') ORDER BY ID")) {
                return $this->prepareArray($stmt);
            }
        } else {
            if($stmt = $this->db->prepare("SELECT * FROM OFFICES ORDER BY ID")) {
                return $this->prepareArray($stmt);
            }
        }
        return false;
    }
    /**
     * 取得總數
     */
    public function count() {
        return count($this->getAll(true));
    }
}
