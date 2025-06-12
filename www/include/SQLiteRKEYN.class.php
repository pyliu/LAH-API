<?php
require_once('init.php');
require_once('System.class.php');
require_once('SQLiteDBFactory.class.php');

class SQLiteRKEYN {
    private $db;

    private function bindParams(&$stm, &$row) {
        if ($stm === false) {
            Logger::getInstance()->error(__METHOD__.": bindParams because of \$stm is false.");
            return;
        }

        $stm->bindParam(':category', $row['KCDE_1']);
        $stm->bindParam(':id', $row['KCDE_2']);
        $stm->bindParam(':content', $row['KCNT']);
        $stm->bindValue(':attr', $row['KRMK']);
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
        $db_path = SQLiteDBFactory::getRKEYNDB();
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
        $sql = "select * from MOIADM.RKEYN t";
        $db->parse($sql);
        $db->execute();
        $rows = $db->fetchAll();
        $this->clean();
        $count = 0;
        foreach ($rows as $row) {
            $this->replace($row);
            $count++;
        }

        Logger::getInstance()->info(__METHOD__.': 匯入 '.$count.' 筆代碼檔資料。 【RKEYN.db、RKEYN table】');
        
        return $count;
    }

    public function exists($category, $id) {
        $ret = $this->db->querySingle("SELECT KCDE_1 || '-' || KCDE_2 from RKEYN WHERE KCDE_1 = '$category' AND KCDE_2 = '$id'");
        return !empty($ret);
    }

    public function clean() {
        $stm = $this->db->prepare("DELETE FROM RKEYN");
        return $stm->execute() === FALSE ? false : true;
    }

    public function replace(&$row) {
        $stm = $this->db->prepare("
            REPLACE INTO RKEYN ('KCDE_1', 'KCDE_2', 'KCNT', 'KRMK')
            VALUES (:category, :id, :content, :attr)
        ");
        $this->bindParams($stm, $row);
        return $stm->execute() === FALSE ? false : true;
    }
    /**
     * 取得本所登記收件字
     */
    public function getRegHostCode() {
        $site = System::getInstance()->getSiteCode();
        if($stmt = $this->db->prepare("SELECT * FROM RKEYN WHERE KCDE_1 = '04' AND KRMK = 'R' AND KCDE_2 LIKE '".$site."%'")) {
            return $this->prepareArray($stmt);
        }
        return false;
    }
    /**
     * 取得本所收件跨所登記收件字
     */
    public function getRegCrossHostCode() {
        $site = System::getInstance()->getSiteCode();   // HA
        $postfix = $site[1];    // A
        if($stmt = $this->db->prepare("SELECT * FROM RKEYN WHERE KCDE_1 = '04' AND KRMK = 'R' AND KCDE_2 LIKE '%".$postfix."1'")) {
            return $this->prepareArray($stmt);
        }
        return false;
    }
    /**
     * 取得他所收件跨所登記收件字
     */
    public function getRegCrossOtherCode() {
        $site = System::getInstance()->getSiteCode();   // HA
        if($stmt = $this->db->prepare("SELECT * FROM RKEYN WHERE KCDE_1 = '04' AND KRMK = 'T' AND KCDE_2 LIKE '".$site."%'")) {
            return $this->prepareArray($stmt);
        }
        return false;
    }
    /**
     * 取得他所收件跨縣市登記收件字
     */
    public function getRegCrossCountyOtherCode() {
        $site = System::getInstance()->getSiteCode();   // HA
        if($stmt = $this->db->prepare("SELECT * FROM RKEYN WHERE KCDE_1 = '04' AND KRMK = 'T' AND KCDE_2 LIKE '%".$site."'")) {
            return $this->prepareArray($stmt);
        }
        return false;
    }
    /**
     * 取得本所收件跨縣市登記收件字
     */
    public function getRegCrossCountyHostCode() {
        $site = System::getInstance()->getSiteCode();   // HA
        $prefix = $site[0];    // H
        $postfix = $site[1];    // A
        $site_idx = ord($postfix) - ord('A') + 1;  // 1
        if($stmt = $this->db->prepare("SELECT * FROM RKEYN WHERE KCDE_1 = '04' AND KRMK = 'T' AND KCDE_2 LIKE '".$prefix.$site_idx."%'")) {
            return $this->prepareArray($stmt);
        }
        return false;
    }
    /**
     * 取得本所其他登記收件字
     */
    public function getRegOtherCode() {
        $site = System::getInstance()->getSiteCode();   // HA
        $postfix = $site[1];    // A
        if($stmt = $this->db->prepare("SELECT * FROM RKEYN WHERE KCDE_1 = '04' AND KRMK = 'R' AND KCDE_2 NOT LIKE '%".$postfix."1' AND KCDE_2 NOT LIKE '".$site."%'")) {
            return $this->prepareArray($stmt);
        }
        return false;
    }
    /**
     * 取得本所測量收件字
     */
    public function getSurCode() {
        $site = System::getInstance()->getSiteCode();
        if($stmt = $this->db->prepare("SELECT * FROM RKEYN WHERE KCDE_1 = '04' AND KRMK IN ('SL', 'SB') AND KCDE_2 LIKE '".$site."%'")) {
            return $this->prepareArray($stmt);
        }
        return false;
    }
    /**
     * 取得本所測量其他收件字
     */
    public function getSurOtherCode() {
        $site = System::getInstance()->getSiteCode();
        if($stmt = $this->db->prepare("SELECT * FROM RKEYN WHERE KCDE_1 = '04' AND KRMK IN ('SL', 'SB') AND KCDE_2 NOT LIKE '".$site."%'")) {
            return $this->prepareArray($stmt);
        }
        return false;
    }
    /**
     * 取得本所地價收件字
     */
    public function getValCode() {
        $site = System::getInstance()->getSiteCode();
        if($stmt = $this->db->prepare("SELECT * FROM RKEYN WHERE KCDE_1 = '04' AND KRMK IN ('V') AND KCDE_2 LIKE '".$site."%'")) {
            return $this->prepareArray($stmt);
        }
        return false;
    }
    /**
     * 取得本所地價其他收件字
     */
    public function getValOtherCode() {
        $site = System::getInstance()->getSiteCode();
        if($stmt = $this->db->prepare("SELECT * FROM RKEYN WHERE KCDE_1 = '04' AND KRMK IN ('V') AND KCDE_2 NOT LIKE '".$site."%'")) {
            return $this->prepareArray($stmt);
        }
        return false;
    }
    /**
     * 取得本所謄本收件字
     */
    public function getCertCode() {
        $site = System::getInstance()->getSiteCode();
        if($stmt = $this->db->prepare("SELECT * FROM RKEYN WHERE KCDE_1 = '04' AND KRMK IN ('UN') AND KCDE_2 LIKE '".$site."%'")) {
            return $this->prepareArray($stmt);
        }
        return false;
    }
    /**
     * 取得本所登記原因
     */
    public function getRegReason() {
        if($stmt = $this->db->prepare("SELECT * FROM RKEYN WHERE KCDE_1 = '06' AND KCDE_2 NOT IN ('/*  */', '-') ORDER BY KCDE_2")) {
            return $this->prepareArray($stmt);
        }
        return false;
    }
    /**
     * 取得使用分區代碼資料
     */
    public function getUseZone() {
        if($stmt = $this->db->prepare("SELECT * FROM RKEYN WHERE KCDE_1 = '11' AND KCDE_2 NOT IN ('/*  */', '-') ORDER BY KCDE_2")) {
            return $this->prepareArray($stmt);
        }
        return false;
    }
}
