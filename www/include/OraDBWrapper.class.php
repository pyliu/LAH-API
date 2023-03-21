<?php
require_once("init.php");
require_once("OraDB.class.php");
require_once("System.class.php");
require_once("Cache.class.php");

class OraDBWrapper {
    
    private $db = null;
	private $db_ok = false;
	private $site = 'HA';
	private $site_code = 'A';
	private $site_number = 1;

	private function isDBReachable($txt = __METHOD__)
	{
		$this->db_ok = System::getInstance()->isDBReachable();
		if (!$this->db_ok) {
			Logger::getInstance()->error('資料庫無法連線，無法取得資料。[' . $txt . ']');
		}
		return $this->db_ok;
	}

	function __construct()
	{
		if ($this->isDBReachable()) {
			$type = OraDB::getPointDBTarget();
			$this->db = new OraDB($type);
		}
		$this->site = strtoupper(System::getInstance()->get('SITE')) ?? 'HA';
		if (!empty($this->site)) {
			$this->site_code = $this->site[1];
			$this->site_number = ord($this->site_code) - ord('A');
		}
	}

	function __destruct()
	{
		if ($this->db) {
			$this->db->close();
		}
		$this->db = null;
	}

    public function getSite() {
        return $this->site;
    }

    public function reachable() {
        return $this->db_ok;
    }

    public function getDB() {
        return $this->db;
    }
}
