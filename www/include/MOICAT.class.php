<?php
require_once("init.php");
require_once("OraDBWrapper.class.php");
require_once("System.class.php");
require_once("Cache.class.php");

class MOICAT {
	private $db_wrapper = null;

	function __construct() {
		$this->db_wrapper = new OraDBWrapper();
	}

	function __destruct() {
		$this->db_wrapper = null;
	}
	/**
	 * Find cert seq records
	 */
	public function getCertSeqRecords($year = '', $month = '', $day = '') {
		global $today;
		if (!$this->db_wrapper->reachable()) {
			return array();
		}
		if (empty($today)) {
			$tw_date = new Datetime("now");
			$tw_date->modify("-1911 year");
			$today = ltrim($tw_date->format("Ymd"), "0");	
		}
		if (empty($year)) {
			$year = substr($today, 0, 3);
		}
		if (empty($month)) {
			$month = substr($today, 3, 2);
		}
		if (empty($day)) {
			$day = substr($today, 5, 2);
		}
		$this->db_wrapper->getDB()->parse("
			-- 權狀序號查詢
			select * from MOICAT.RXSEQ t
			where 1=1
				and xsdate like :bv_ymd || '%'
			order by xsdate
		");
		$this->db_wrapper->getDB()->bind(":bv_ymd", $year.$month.$day);
		$this->db_wrapper->getDB()->execute();
		return $this->db_wrapper->getDB()->fetchAll();
	}
}
