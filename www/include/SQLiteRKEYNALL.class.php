<?php
require_once('init.php');
require_once('System.class.php');
require_once('SQLiteDBFactory.class.php');

class SQLiteRKEYNALL {
    private $db;

    private function bindParams(&$stm, &$row) {
        if ($stm === false) {
            Logger::getInstance()->error(__METHOD__.": bindParams because of \$stm is false.");
            return;
        }

        $stm->bindParam(':kcde_1', $row['KCDE_1']);
        $stm->bindParam(':kcde_2', $row['KCDE_2']);
        $stm->bindParam(':kcde_3', $row['KCDE_3']);
        $stm->bindParam(':kcde_4', $row['KCDE_4']);
        $stm->bindParam(':kname', $row['KNAME']);
        $stm->bindParam(':krmk', $row['KRMK']);
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
        $db_path = SQLiteDBFactory::getRKEYNALLDB();
        $this->db = new SQLite3($db_path);
        $this->db->exec("PRAGMA cache_size = 100000");
        $this->db->exec("PRAGMA temp_store = MEMORY");
        $this->db->exec("BEGIN TRANSACTION");
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
            Logger::getInstance()->error(__METHOD__.': 無法連線主DB，無法進行匯入收件字快取資料庫。');
            return false;
        }

        $db = new OraDB();
        $sql = "select * from MOIADM.RKEYN_ALL t";
        $db->parse($sql);
        $db->execute();
        $rows = $db->fetchAll();
        $this->clean();
        $count = 0;
        foreach ($rows as $row) {
            $this->replace($row);
            $count++;
        }

        Logger::getInstance()->info(__METHOD__.': 匯入 '.$count.' 筆資料。 【RKEYN_ALL.db、RKEYN_ALL table】');

        return $count;
    }

    public function clean() {
        $stm = $this->db->prepare("DELETE FROM RKEYN_ALL");
        return $stm->execute() === FALSE ? false : true;
    }

    public function replace(&$row) {
        $stm = $this->db->prepare("
            REPLACE INTO RKEYN_ALL ('KCDE_1', 'KCDE_2', 'KCDE_3', 'KCDE_4', 'KNAME', 'KRMK')
            VALUES (:kcde_1, :kcde_2, :kcde_3, :kcde_4, :kname, :krmk)
        ");
        $this->bindParams($stm, $row);
        return $stm->execute() === FALSE ? false : true;
    }



    /**
     * 取得段代碼對應BY縣市 (H => 桃園)
     */
    public function getSectionsByCounty($county = 'H') {
        // KCDE_4 < 9000 to avoid invalid data
        if($stmt = $this->db->prepare("SELECT * FROM RKEYN_ALL WHERE KCDE_1 = '48' AND KCDE_2 = '${county}' AND KCDE_4 < 9000")) {
            return $this->prepareArray($stmt);
        }
        return false;
    }
    /**
     * 取得段代碼對應資料
     */
    public function getSections() {
        // KCDE_4 < 9000 to avoid invalid data
        if($stmt = $this->db->prepare("SELECT * FROM RKEYN_ALL WHERE KCDE_1 = '48' AND KCDE_4 < 9000 ORDER BY KRMK, KCDE_4")) {
            return $this->prepareArray($stmt);
        }
        return false;
    }
    /**
     * 取得各地所對應資料
     */
    public function getOffices() {
        if($stmt = $this->db->prepare("select * from RKEYN_ALL t where KCDE_1 = 'LN' ORDER BY KCDE_3")) {
            return $this->prepareArray($stmt);
        }
        return false;
    }
    /**
     * 取得各鄉鎮區對應資料
     */
    public function getDistricts() {
        if($stmt = $this->db->prepare("select * from RKEYN_ALL t where KCDE_1 = '46' ORDER BY KRMK, KCDE_4")) {
            return $this->prepareArray($stmt);
        }
        return false;
    }
}
