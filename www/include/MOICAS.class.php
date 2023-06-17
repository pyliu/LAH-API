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
	/**
	 * get CRSMS records by clock 
	 */
	public function getCRSMSRecordsByClock($st, $ed, $clock)
	{
		global $today;
		if (!$this->db_wrapper->reachable()) {
			return array();
		}
		if (empty($st)) {
			// default query is today
			$st = $today;
		}
		if (empty($ed)) {
			// default query is today
			$ed = $today;
		}
		$val = intval($clock);
		if ($val < 1 || $val > 23) {
			Logger::getInstance()->warning(__METHOD__.": $clock 非合理小時區間，無法查詢。");
			return array();
		}
		$this->db_wrapper->getDB()->parse("
			SELECT
				t.*,
				s.KCNT AS \"RM09_CHT\"
			FROM MOICAS.CRSMS t
			LEFT JOIN MOIADM.RKEYN s ON s.KCDE_1 = '06' AND s.KCDE_2 = t.RM09
			WHERE 1=1
				AND (t.RM07_1 BETWEEN :bv_st And :bv_ed)
				AND (t.RM101 = 'HA' OR t.RM101 IS NULL)
				AND t.RM07_2 LIKE :bv_clock || '%'
		");
		$this->db_wrapper->getDB()->bind(":bv_st", $st);
		$this->db_wrapper->getDB()->bind(":bv_ed", $ed);
		$this->db_wrapper->getDB()->bind(":bv_clock", $clock);
		$this->db_wrapper->getDB()->execute();
		return $this->db_wrapper->getDB()->fetchAll();
	}
	// 第一次登記案件 BY 日期區間
	public function getCRSMSFirstRegCase($st, $ed) {
		$this->db_wrapper->getDB()->parse("
				SELECT * FROM MOICAS.CRSMS t
				WHERE t.RM09 = '02' and t.RM07_1 BETWEEN :bv_st AND :bv_ed
				ORDER BY t.RM03
		");
		$this->db_wrapper->getDB()->bind(":bv_st", $st);
		$this->db_wrapper->getDB()->bind(":bv_ed", $ed);
		$this->db_wrapper->getDB()->execute();
		return $this->db_wrapper->getDB()->fetchAll();
	}
	// 第一次登記(子號)案件 BY 日期區間
	public function getCRSMSFirstRegSubCase($st, $ed) {
		$this->db_wrapper->getDB()->parse("
				SELECT * FROM MOICAS.CRSMS t
				WHERE 1=1
					AND (t.RM09 = '02' and t.RM07_1 BETWEEN :bv_st AND :bv_ed)
					AND t.RM03 NOT LIKE '%0'
				ORDER BY t.RM03
		");
		$this->db_wrapper->getDB()->bind(":bv_st", $st);
		$this->db_wrapper->getDB()->bind(":bv_ed", $ed);
		$this->db_wrapper->getDB()->execute();
		return $this->db_wrapper->getDB()->fetchAll();
	}
	// 搜尋案件 BY RM02, 日期區間
	public function getCRSMSRegRM02Case($rm02, $st, $ed) {
		$this->db_wrapper->getDB()->parse("
				SELECT * FROM MOICAS.CRSMS t
				WHERE 1=1
					AND (t.RM02 = :bv_rm02 and t.RM07_1 BETWEEN :bv_st AND :bv_ed)
					AND t.RM03 LIKE '%0'
				order by t.RM03
		");
		$this->db_wrapper->getDB()->bind(":bv_rm02", $rm02);
		$this->db_wrapper->getDB()->bind(":bv_st", $st);
		$this->db_wrapper->getDB()->bind(":bv_ed", $ed);
		$this->db_wrapper->getDB()->execute();
		return $this->db_wrapper->getDB()->fetchAll();
	}
	// 搜尋(子號)案件 BY RM02, 日期區間
	public function getCRSMSRegRM02SubCase($rm02, $st, $ed) {
		$this->db_wrapper->getDB()->parse("
				SELECT * FROM MOICAS.CRSMS t
				WHERE 1=1
					AND (t.RM02 = :bv_rm02 and t.RM07_1 BETWEEN :bv_st AND :bv_ed)
					AND t.RM03 NOT LIKE '%0'
				order by t.RM03
		");
		$this->db_wrapper->getDB()->bind(":bv_rm02", $rm02);
		$this->db_wrapper->getDB()->bind(":bv_st", $st);
		$this->db_wrapper->getDB()->bind(":bv_ed", $ed);
		$this->db_wrapper->getDB()->execute();
		return $this->db_wrapper->getDB()->fetchAll();
	}
	
}
