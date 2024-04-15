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
	 * To fix th RM38/RM39 wrong change issue
	 */
	public function fixRegWrongChangeCase($year, $code, $num) {
		if (!$this->db_wrapper->reachable()) {
			return false;
		}
		Logger::getInstance()->info(__METHOD__.": Going to set $year-$code-$num CRSMS RM39 to 'F' and RM38 = ''.");

		$year = str_pad($year, 3, '0', STR_PAD_LEFT);
		$num = str_pad($num, 6, '0', STR_PAD_LEFT);

		$tmp_date = timestampToDate(time(), 'TW');
		$parts = explode(' ', $tmp_date);
		$date_str = implode('', explode('-', $parts[0]));
		$time_str = implode('', explode(':', $parts[1]));

		$this->db_wrapper->getDB()->parse("
			UPDATE MOICAS.CRSMS
				SET RM38 = '', RM39 = 'F' , RM40 = :bv_date, RM41 = :bv_time, RM42 = '', RM30 = 'U'
			WHERE RM01 = :bv_year
			  AND RM02 = :bv_code
				AND RM03 = :bv_num
		");
		
		$this->db_wrapper->getDB()->bind(":bv_year", $year);
		$this->db_wrapper->getDB()->bind(":bv_code", $code);
		$this->db_wrapper->getDB()->bind(":bv_num", $num);
		$this->db_wrapper->getDB()->bind(":bv_date", $date_str);
		$this->db_wrapper->getDB()->bind(":bv_time", $time_str);
		return $this->db_wrapper->getDB()->execute() === FALSE ? false : true;
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
			left join MOICAS.CMCLD s ON cl04 = mc02 AND cl05 = mc01
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
	/**
	 * Remove the dangling(No CMCLD record link with) data of CMCRD
	 */
	public function removeDanglingCMCRDRecords($year = '')
	{
		global $this_year;
		if (!$this->db_wrapper->reachable()) {
			return false;
		}

		if (empty($year)) {
			$year = $this_year;
		}

		Logger::getInstance()->info(__METHOD__.": Going to remove dangling record of CMCRD record. (YEAR: $year)");

		$this->db_wrapper->getDB()->parse("
			DELETE FROM MOICAS.CMCRD
			WHERE 1=1
				AND MC01 = :bv_year
				AND (MC01,MC02) NOT IN (SELECT CL05,CL04 FROM MOICAS.CMCLD)
		");
		// CL04 - NO., e.g. Y00286
		// CL05 - YEAR, e.g. 113
		$this->db_wrapper->getDB()->bind(":bv_year", $year);
		return $this->db_wrapper->getDB()->execute() === FALSE ? false : true;
	}
	/**
	 * Remove the link data for CMCRD 
	 */
	public function removeCMCLDRecords($cl05, $cl04)
	{
		if (!$this->db_wrapper->reachable()) {
			return false;
		}

		Logger::getInstance()->info(__METHOD__.": Going to remove CMCLD record. (CL04: $cl04, CL05: $cl05)");

		$this->db_wrapper->getDB()->parse("
		  delete from MOICAS.CMCLD
			where 1=1
				and CL04 = :bv_cl04
				and CL05 = :bv_cl05
		");
		// CL04 - NO., e.g. Y00286
		// CL05 - YEAR, e.g. 113
		$this->db_wrapper->getDB()->bind(":bv_cl04", $cl04);
		$this->db_wrapper->getDB()->bind(":bv_cl05", $cl05);
		return $this->db_wrapper->getDB()->execute() === FALSE ? false : true;
	}
	/**
	 * Remove the record data for CMCLD 
	 */
	public function removeCMCRDRecords($mc01, $mc02)
	{
		if (!$this->db_wrapper->reachable()) {
			return false;
		}

		Logger::getInstance()->info(__METHOD__.": Going to remove CMCRD record. (MC01: $mc01, MC02: $mc02)");

		$this->db_wrapper->getDB()->parse("
		  delete from MOICAS.CMCRD
			where 1=1
				and mc01 = :bv_mc01
				and mc02 = :bv_mc02
		");
		// MC01 - YEAR, e.g. 113
		// MC02 - NO, e.g. Y00286
		$this->db_wrapper->getDB()->bind(":bv_mc01", $mc01);
		$this->db_wrapper->getDB()->bind(":bv_mc02", $mc02);
		return $this->db_wrapper->getDB()->execute() === FALSE ? false : true;
	}
	/**
	 * Set CMSMS operation state to 'A' (外業作業)
	 */
	public function setCMSMS_MM22_A($year, $code, $num)
	{
		if (!$this->db_wrapper->reachable()) {
			return false;
		}

		Logger::getInstance()->info(__METHOD__.": Going to set $year-$code-$num CMSMS MM22 to 'A'.");

		$this->db_wrapper->getDB()->parse("
			UPDATE MOICAS.CMSMS SET MM22='A' 
			WHERE MM01 = :bv_year
			  AND MM02 = :bv_code
				AND MM03 = :bv_num
		");
		$num = str_pad($num, 6, '0');
		$this->db_wrapper->getDB()->bind(":bv_year", $year);
		$this->db_wrapper->getDB()->bind(":bv_code", $code);
		$this->db_wrapper->getDB()->bind(":bv_num", $num);
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
		if (!$this->db_wrapper->reachable()) {
			return array();
		}
		$this->db_wrapper->getDB()->parse("
				SELECT
					t.*,
					w.KCNT AS \"RM09_CHT\"
				FROM MOICAS.CRSMS t
				LEFT JOIN MOIADM.RKEYN w ON t.RM09 = w.KCDE_2 AND w.KCDE_1 = '06'   -- 登記原因
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
		if (!$this->db_wrapper->reachable()) {
			return array();
		}
		$this->db_wrapper->getDB()->parse("
				SELECT
					t.*,
					w.KCNT AS \"RM09_CHT\"
				FROM MOICAS.CRSMS t
				LEFT JOIN MOIADM.RKEYN w ON t.RM09 = w.KCDE_2 AND w.KCDE_1 = '06'   -- 登記原因
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
		if (!$this->db_wrapper->reachable()) {
			return array();
		}
		$this->db_wrapper->getDB()->parse("
				SELECT
					t.*,
					w.KCNT AS \"RM09_CHT\"
				FROM MOICAS.CRSMS t
				LEFT JOIN MOIADM.RKEYN w ON t.RM09 = w.KCDE_2 AND w.KCDE_1 = '06'   -- 登記原因
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
		if (!$this->db_wrapper->reachable()) {
			return array();
		}
		$this->db_wrapper->getDB()->parse("
				SELECT
					t.*,
					w.KCNT AS \"RM09_CHT\"
				FROM MOICAS.CRSMS t
				LEFT JOIN MOIADM.RKEYN w ON t.RM09 = w.KCDE_2 AND w.KCDE_1 = '06'   -- 登記原因
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
