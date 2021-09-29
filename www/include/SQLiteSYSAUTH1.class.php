<?php
require_once('init.php');
require_once('System.class.php');
require_once('SQLiteDBFactory.class.php');
require_once('OraDB.class.php');

class SQLiteSYSAUTH1 {
    private $db;

    private function bindParams(&$stm, &$row) {
        if ($stm === false) {
            Logger::getInstance()->error(__METHOD__.": bindUserParams because of \$stm is false.");
            return;
        }

        $stm->bindParam(':id', $row['USER_ID']);
        $stm->bindParam(':name', preg_replace("/(桃園所|中壢所|大溪所|楊梅所|蘆竹所|八德所|平鎮所|龜山所|桃園|中壢|大溪|楊梅|蘆竹|八德|平鎮|龜山)/i", '', $row['USER_NAME']));
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
            
            Logger::getInstance()->error(__METHOD__.": failed because of \$stm is false.");
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

    private function getUsersByValid($int) {
        if($stmt = $this->db->prepare("SELECT * FROM SYSAUTH1 WHERE VALID = :valid ORDER BY USER_ID, USER_NAME")) {
            $stmt->bindParam(':valid', $int);
            return $this->prepareArray($stmt);
        } else {
            
            Logger::getInstance()->error(__METHOD__.": 取得合法使用者資料失敗！");
        }
        return false;
    }

    function __construct() {
        $db_path = SQLiteDBFactory::getSYSAUTH1DB();
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

    public function importFromL3HWEBDB() {
        // check if l3hweb is reachable
        $l3hweb_ip = System::getInstance()->get('ORA_DB_L3HWEB_IP');
        $l3hweb_port = System::getInstance()->get('ORA_DB_L3HWEB_PORT');
        $latency = pingDomain($l3hweb_ip, $l3hweb_port);
    
        // not reachable
        if ($latency > 999 || $latency == '') {
            Logger::getInstance()->error(__METHOD__.': 無法連線L3HWEB，無法進行匯入使用者名稱。');
            return false;
        }

        $db = new OraDB(CONNECTION_TYPE::L3HWEB);
        $sql = "
            SELECT DISTINCT * FROM L1HA0H03.SYSAUTH1
            UNION
            SELECT DISTINCT * FROM L1HB0H03.SYSAUTH1
            UNION
            SELECT DISTINCT * FROM L1HC0H03.SYSAUTH1
            UNION
            SELECT DISTINCT * FROM L1HD0H03.SYSAUTH1
            UNION
            SELECT DISTINCT * FROM L1HE0H03.SYSAUTH1
            UNION
            SELECT DISTINCT * FROM L1HF0H03.SYSAUTH1
            UNION
            SELECT DISTINCT * FROM L1HG0H03.SYSAUTH1
            UNION
            SELECT DISTINCT * FROM L1HH0H03.SYSAUTH1
        ";
        $db->parse($sql);
        $db->execute();
        $rows = $db->fetchAll();
        $count = 0;
        foreach ($rows as $row) {
            $this->import($row);
            $count++;
        }

        Logger::getInstance()->info(__METHOD__.': 匯入 '.$count.' 筆使用者資料。 【SYSAUTH1.db，SYSAUTH1 table】');
        
        return $count;
    }

    public function import(&$row) {
        if (empty($row['USER_ID']) || empty($row['USER_NAME'])) {
            Logger::getInstance()->warning(__METHOD__.": USER_ID or USER_NAME is empty. Import procedure can not be proceeded.");
            Logger::getInstance()->warning(__METHOD__.": ".print_r($row, true));
            return false;
        }
        return $this->replace($row);
    }

    public function getAllUsers() {
        if($stmt = $this->db->prepare("SELECT * FROM SYSAUTH1 WHERE 1 = 1 ORDER BY USER_ID")) {
            return $this->prepareArray($stmt);
        } else {
            
            Logger::getInstance()->error(__METHOD__.": 取得所有使用者資料失敗！");
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
            
            Logger::getInstance()->error(__METHOD__.": 取得所有使用者名稱對應表失敗！");
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
