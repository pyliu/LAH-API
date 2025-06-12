<?php
require_once("init.php");
require_once("OraDB.class.php");

class BKHXWEB {
    private $db;

    function __construct($conn_type = CONNECTION_TYPE::BK) {
        $this->db = new OraDB($conn_type);
    }

    function __destruct() {
        $this->db->close();
        $this->db = null;
    }
    /**
     * 各所同步異動更新時間
     */
    public function querySwitchoverStatus() {
        $sql = 'SELECT switchover_status from v$database';
        $this->db->parse($sql);
		$this->db->execute();
		return $this->db->fetchAll();
    }
}
