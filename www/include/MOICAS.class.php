<?php
require_once("init.php");
require_once("OraDB.class.php");
require_once("System.class.php");
require_once("Cache.class.php");

class MOICAS
{

	private $db;
	private $db_ok = true;
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
	/**
	 * Find empty record that causes user from SUR section can't generate notification application pdf ... 
	 */
	public function getCMCRDTempRecords($year = '')
	{
		if (!$this->db_ok) {
			return array();
		}
		if (empty($year)) {
			// default query this year
			$year = date('Y') - 1911;
		}
		$this->db->parse("
			select * from MOICAS.CMCRD t
			where 1=1
				and mc01 = :bv_year
				and mc02 like :bv_Y_record
				--and (mc03 is null or mc03 = '')
			order by mc02
		");
		$this->db->bind(":bv_year", $year);
		$this->db->bind(":bv_Y_record", 'Y%');
		$this->db->execute();
		return $this->db->fetchAll();
	}

	public function removeCMCRDRecords($mc01, $mc02)
	{
		if (!$this->db_ok) {
			return false;
		}
		$this->db->parse("
		  delete from MOICAS.CMCRD
			where 1=1
				and mc01 = :bv_mc01
				and mc02 = :bv_mc02
		");
		$this->db->bind(":bv_mc01", $mc01);
		$this->db->bind(":bv_mc02", $mc02);
		return $this->db->execute() === FALSE ? false : true;
	}
}
