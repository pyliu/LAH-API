<?php
require_once("init.php");
require_once("OraDBWrapper.class.php");
require_once("System.class.php");
require_once("Cache.class.php");

class MOICAS
{
	private $db_wrapper = null;
	function __construct() {
		$this->db_wrapper = new OraDBWrapper();
	}

	function __destruct() {
		$this->db_wrapper = null;
	}
	/**
	 * Find empty record that causes user from SUR section can't generate notification application pdf ... 
	 */
	public function getCMCRDTempRecords($year = '')
	{
		if (!$this->db_wrapper->reachable()) {
			return array();
		}
		if (empty($year)) {
			// default query this year
			$year = date('Y') - 1911;
		}
		$this->db_wrapper->getDB()->parse("
			select * from MOICAS.CMCRD t
			where 1=1
				and mc01 = :bv_year
				and mc02 like :bv_Y_record
				--and (mc03 is null or mc03 = '')
			order by mc02
		");
		$this->db_wrapper->getDB()->bind(":bv_year", $year);
		$this->db_wrapper->getDB()->bind(":bv_Y_record", 'Y%');
		$this->db_wrapper->getDB()->execute();
		return $this->db_wrapper->getDB()->fetchAll();
	}

	public function removeCMCRDRecords($mc01, $mc02)
	{
		if (!$this->db_wrapper->reachable()) {
			return false;
		}
		$this->db_wrapper->getDB()->parse("
		  delete from MOICAS.CMCRD
			where 1=1
				and mc01 = :bv_mc01
				and mc02 = :bv_mc02
		");
		$this->db_wrapper->getDB()->bind(":bv_mc01", $mc01);
		$this->db_wrapper->getDB()->bind(":bv_mc02", $mc02);
		return $this->db_wrapper->getDB()->execute() === FALSE ? false : true;
	}
}
