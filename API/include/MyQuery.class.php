<?php
require_once("init.php");
require_once("OraDB.class.php");
require_once("System.class.php");

class MyQuery {
	private $db;
	private $db_ok = true;
	private $site = 'HA';
	private $site_code = 'A';
	private $site_number = 1;
	private $user_id;

    private function isDBReachable($txt = __METHOD__) {
        $this->db_ok = System::getInstance()->isDBReachable();
        if (!$this->db_ok) {
            Logger::getInstance()->error('資料庫無法連線，無法取得資料。['.$txt.']');
        }
        return $this->db_ok;
    }

    function __construct($user_id) {
		if ($this->isDBReachable()) {
			$type = OraDB::getPointDBTarget();
			$this->db = new OraDB($type);
		}
		$this->site = strtoupper(System::getInstance()->get('SITE')) ?? 'HA';
		if (!empty($this->site)) {
			$this->site_code = $this->site[1];
			$this->site_number = ord($this->site_code) - ord('A');
		}
		$this->user_id = $user_id;
    }

    function __destruct() {
		if ($this->db) {
			$this->db->close();
		}
        $this->db = null;
    }

	public function queryMyOpenRegCase() {

	}
}
