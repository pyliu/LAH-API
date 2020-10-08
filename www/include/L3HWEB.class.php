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
        $this->db->parse("
            SELECT
                SUBSTR(sowner, 3, 2) AS SITE,
                TO_CHAR(min(snaptime), 'yyyy-mm-dd hh24:mi:ss') as UPDATE_DATETIME
            FROM sys.snap_reftime$
            GROUP BY sowner
            ORDER BY SITE, UPDATE_DATETIME
        ");
		$this->db->execute();
		return $this->db->fetchAll();
    }
}
?>
