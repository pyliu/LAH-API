<?php
require_once("init.php");
require_once("OraDB.class.php");
require_once("System.class.php");
require_once("SQLiteSYSAUTH1.class.php");

class LXHWEB {
    private $db = null;
    private $conn_type = CONNECTION_TYPE::L3HWEB;

    private function getDB() {
        if ($this->db === null) {
            $this->db = new OraDB($this->conn_type);
        }
        return $this->db;
    }

    function __construct($conn_type = CONNECTION_TYPE::L3HWEB) {
        $this->conn_type = $conn_type;
    }

    function __destruct() {
        $this->getDB()->close();
        $this->db = null;
    }
    /**
     * 各所同步異動更新時間
     */
    public function querySiteUpdateTime($site = '') {
        $prefix = "
            SELECT
                SUBSTR(sowner, 3, 2) AS SITE,
                TO_CHAR(min(snaptime), 'yyyy-mm-dd hh24:mi:ss') as UPDATE_DATETIME
            FROM sys.snap_reftime$
        ";
        $where = "";
        if (!empty($site) && 2 == strlen($site)) {
            $site = strtoupper($site);
            $where = " WHERE SUBSTR(sowner, 3, 2) = '".strtoupper($site)."' ";
        }
        $postfix = "
            GROUP BY sowner
            ORDER BY SITE, UPDATE_DATETIME
        ";
        $this->getDB()->parse($prefix.$where.$postfix);
		$this->getDB()->execute();
		return $this->getDB()->fetchAll();
    }
    /**
     * 查詢各所表格更新時間
     */
    public function queryTableUpdateTime($site = '') {
        $prefix = "
            SELECT 
                SUBSTR(sowner, 3, 2) AS \"所別\",
                vname AS \"表格\",
                TO_CHAR(snaptime, 'yyyy-mm-dd hh24:mi:ss') as \"更新時間\"
            FROM sys.snap_reftime$
        ";
        $where = "";
        if (!empty($site) && 2 == strlen($site)) {
            $site = strtoupper($site);
            $where = " WHERE SUBSTR(sowner, 3, 2) = '".strtoupper($site)."' ";
        }
        $postfix = "
            ORDER BY \"所別\", \"更新時間\"
        ";
        $this->getDB()->parse($prefix.$where.$postfix);
		$this->getDB()->execute();
		return $this->getDB()->fetchAll();
    }
    /**
     * 查詢是否有BROKEN狀態之TABLE
     */
    public function queryBrokenTable() {
        $sql = "
            SELECT
                SUBSTR(rowner, 3, 2) AS \"所別\",
                rname AS \"表格名稱\",
                broken AS \"損毀狀態\"
            FROM dba_refresh
            WHERE broken = 'Y'
            ORDER BY \"所別\", \"表格名稱\"
        ";
        $this->getDB()->parse($sql);
		$this->getDB()->execute();
		return $this->getDB()->fetchAll();
    }
    /**
     * 查詢同步異動各所之 SYSAUTH1 TABLE
     */
    public function querySYSAUTH1UserNames() {
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
        $this->getDB()->parse($sql);
		$this->getDB()->execute();
		$rows = $this->getDB()->fetchAll();
        $filtered = array();
        $sysauth1 = new SQLiteSYSAUTH1();
        foreach ($rows as $row) {
            $sysauth1->import($row);
            // $user_name = mb_convert_encoding(preg_replace('/\d+/', "", $row["USER_NAME"]), "UTF-8", "BIG5");
            $user_name = $row["USER_NAME"];
            if (array_key_exists($row['USER_ID'], $filtered)) {
                if (strlen($user_name) < strlen($filtered[$row['USER_ID']])) {
                    $filtered[$row['USER_ID']] = $user_name;
                }
            } else  {
                $filtered[$row['USER_ID']] = $user_name;
            }
        }
        return $filtered;
    }
    /**
     * 查詢跨所地價案件跨所註記遺失
     */
    // I could use local DB table(MOIPRC.PSCRN) here but this class uses LXHWEB instead.
    public function getMissingXNoteXValCasesLocal() {
        // find the cases we own, e.g. L1HA0H03.PSCRN, SS04_1 => 'HA[B-H]1'
        $alphabet = ltrim(System::getInstance()->getSiteCode(), 'H');
        $sql = "
            SELECT *
            FROM L1H${alphabet}0H03.PSCRN t
            WHERE 1 = 1
                AND t.SS04_1 in ('H${alphabet}A1', 'H${alphabet}B1', 'H${alphabet}C1', 'H${alphabet}D1', 'H${alphabet}E1', 'H${alphabet}F1', 'H${alphabet}G1', 'H${alphabet}H1')
                AND t.SS99 IS NULL
        ";
        $this->getDB()->parse($sql);
		$this->getDB()->execute();
		return $this->getDB()->fetchAll();
    }
    // find the cases not we own but concern, e.g. check L1H[B-H]0H03.PSCRN, SS04_1 => 'H[B-H]A1'
    public function getMissingXNoteXValCases($remote) {
        $local = ltrim(System::getInstance()->getSiteCode(), 'H');
        $remote = strlen($remote) > 1 ? ltrim($remote, 'H') : $remote;
        if ($local === $remote) {
            return $this->getMissingXNoteXValCasesLocal();
        }
        $sql = "
            SELECT *
            FROM L1H${remote}0H03.PSCRN t
            WHERE 1 = 1
                AND t.SS04_1 = 'H${remote}${local}1'
                AND t.SS99 IS NULL
        ";
        $this->getDB()->parse($sql);
		$this->getDB()->execute();
		return $this->getDB()->fetchAll();
    }
}
