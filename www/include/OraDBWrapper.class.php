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

	public function getSiteCode() {
		return $this->site_code;
	}

	public function getSiteNumber() {
		return $this->site_number;
	}

	public function reachable() {
		return $this->db_ok;
	}

	public function getDB() {
		return $this->db;
	}
	/**
	 * get possible error message
	 * 回傳值: array(
	 * 	"code" =>  Oracle 錯誤代碼 (integer),
	 * 	"message" => Oracle 錯誤訊息 (string),
	 *  "offset" =>  (PHP 4.3.0 起): SQL 語句中錯誤發生的位元組位置 (integer)。如果沒有語句，則為 0。
	 *  "sqltext" => (PHP 4.3.0 起): 導致錯誤的 SQL 語句 (string)。如果沒有語句，則為空字串。
	 * ) OR false
	 */
	public function getError() {
		return $this->db->getError();
	}
}
