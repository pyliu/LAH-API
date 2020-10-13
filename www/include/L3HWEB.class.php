<?php
require_once("init.php");
require_once("OraDB.class.php");

class L3HWEB {
    private $db;

    function __construct() {
        $this->db = new OraDB(CONNECTION_TYPE::L3HWEB);
    }

    function __destruct() {
        $this->db->close();
        $this->db = null;
    }
    /**
     * 同步異動更新時間
     */
    public function queryUpdateTime($site = '') {
        $prefix = "
            SELECT
                SUBSTR(sowner, 3, 2) AS SITE,
                TO_CHAR(min(snaptime), 'yyyy-mm-dd hh24:mi:ss') as UPDATE_DATETIME
            FROM sys.snap_reftime$
        ";
        $where = "";
        if (!empty($site) && 2 == strlen($site)) {
            $site = strtoupper($site);
            $where = " WHERE SUBSTR(sowner, 3, 2) = '$site' ";
        }
        $postfix = "
            GROUP BY sowner
            ORDER BY SITE, UPDATE_DATETIME
        ";
        $this->db->parse($prefix.$where.$postfix);
		$this->db->execute();
		return $this->db->fetchAll();
    }
    /**
     * 查詢是否有BROKEN狀態之TABLE
     */
    public function queryBrokenTable() {
        $sql = "
            SELECT
                SUBSTR(sowner, 3, 2) AS SITE,
                vname AS \"TABLE\",
                broken
            FROM dba_refresh
            WHERE broken = 'Y'
            ORDER BY rowner
        ";
        $this->db->parse($sql);
		$this->db->execute();
		return $this->db->fetchAll();
    }
}
?>
