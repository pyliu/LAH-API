<?php
require_once("init.php");
require_once("OraDBWrapper.class.php");
require_once("System.class.php");
require_once("Cache.class.php");

class MOICAS
{
	private $db_wrapper = null;
	private $site = 'HA';
	private $site_number = 1;

	private function checkCaseID(&$id) {
		$id = str_replace('-', '', $id);
		if (!empty($id)) {
			$year = substr($id, 0, 3);
			$code = substr($id, 3, 4);
			$number = str_pad(substr($id, 7, 6), 6, "0", STR_PAD_LEFT);
			if (
				preg_match("/^[0-9A-Za-z]{3}$/i", $year) &&
				preg_match("/^[0-9A-Za-z]{4}$/i", $code) &&
				preg_match("/^[0-9A-Za-z]{6}$/i", $number)
			) {
				Logger::getInstance()->info(__METHOD__.": $id passed the id verification.");
				$nid = $year.$code.$number;
				if ($id != $nid) {
					// recomposition the $id
					$id = $nid;
					Logger::getInstance()->info(__METHOD__.": update the case id to '$nid'.");
				}
				return true;
			}
		}
		Logger::getInstance()->warning(__METHOD__.": $id failed the id verification.");
		return false;
	}

	function __construct() {
		$this->db_wrapper = new OraDBWrapper();
		$this->site = strtoupper(System::getInstance()->get('SITE')) ?? 'HA';
		$site_code = $this->site[1];	// A ... H
		$this->site_number = ord($site_code) - ord('A');
	}

	function __destruct() {
		$this->db_wrapper = null;
	}
	/**
	 * Public interface for 檢測本地端案件是否存在
	 */
	public function checkCaseExists($id) {
		if (!$this->db_wrapper->reachable()) {
			return -1;
		}

		if (!$this->checkCaseID($id)) {
			return -1;
		}
		
		$year = substr($id, 0, 3);
		$code = substr($id, 3, 4);
		$num = substr($id, 7, 6);
		$db_user = "MOICAS";

		Logger::getInstance()->info(__METHOD__.": 找本地 $db_user.CRSMS 的案件資料【$year, $code, $num"."】");

		$this->db_wrapper->getDB()->parse("
			SELECT * FROM $db_user.CRSMS t
			WHERE 1=1
				AND RM01 = :bv_year
				AND RM02 = :bv_code
				AND RM03 = :bv_number
		");
		$this->db_wrapper->getDB()->bind(":bv_year", $year);
		$this->db_wrapper->getDB()->bind(":bv_code", $code);
		$this->db_wrapper->getDB()->bind(":bv_number", $num);
		$this->db_wrapper->getDB()->execute();
		$row = $this->db_wrapper->getDB()->fetch(true);

		// 無此資料
		if (empty($row)) {
			Logger::getInstance()->warning(__METHOD__.": 本地 $db_user.CRSMS 查無 $year-$code-$num 案件資料");
			return false;
		}

		return true;
	}
	/**
	 * 調教資料集，解決SQL查詢表格過慢問題
	 * -- 檢查最近一次是什麼時後做ANALYZE
   * select OWNER,TABLE_NAME,LAST_ANALYZED from all_tables where table_name='CRSMS';
	 */
	public function analyzeMOICASTable($name) {
		/**
		 * Here are some additional things to keep in mind when using ANALYZE TABLE:
		 * ANALYZE TABLE can be a time-consuming operation, so it is best to run it during off-peak hours.
		 * You can use the ESTIMATE STATISTICS clause to control how many rows are sampled to generate the statistics. A larger sample will produce more accurate statistics, but it will also take longer to run.
		 * You can use the COMPUTE STATISTICS clause to specify which statistics to gather. The default is to gather all of the statistics, but you can also choose to gather only specific statistics, such as the number of rows or the distribution of values in a column.
		 * In general, it is a good practice to use ANALYZE TABLE regularly to keep the statistics for your tables up-to-date. This can help to improve the performance of your Oracle database applications.
		 */
		if (empty($name)) {
			return false;
		}
		
		if (!$this->db_wrapper->reachable()) {
			return false;
		}
		$action = 'delete';//'compute';
		// Logger::getInstance()->info(__METHOD__.": Going to analyze MOICAS.$name table and delete statistics.");
		// $this->db_wrapper->getDB()->parse("ANALYZE TABLE MOICAS.".$name." delete statistics");
		Logger::getInstance()->info(__METHOD__.": Going to analyze MOICAS.$name table $action statistics.");
		// sampling by 10% records 👉 ANALYZE TABLE MOICAS.CRSMS ESTIMATE STATISTICS SAMPLE 10 PERCENT;
		$this->db_wrapper->getDB()->parse("ANALYZE TABLE MOICAS.".$name." $action statistics");
		
		return $this->db_wrapper->getDB()->execute() === FALSE ? false : true;
	}
	/**
	 * To fix th RM38/RM39 wrong change issue
	 */
	public function fixRegWrongChangeCase($year, $code, $num, $trindxFix = false) {
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

		$db = $this->db_wrapper->getDB();

		$db->parse("
			UPDATE MOICAS.CRSMS
				SET RM38 = '', RM39 = 'F' , RM40 = :bv_date, RM41 = :bv_time, RM42 = '', RM30 = 'U'
			WHERE RM01 = :bv_year
			  AND RM02 = :bv_code
				AND RM03 = :bv_num
				AND RM30 NOT IN ('Z', 'F')
		");
		
		$db->bind(":bv_year", $year);
		$db->bind(":bv_code", $code);
		$db->bind(":bv_num", $num);
		$db->bind(":bv_date", $date_str);
		$db->bind(":bv_time", $time_str);
		$result = $db->execute() === FALSE ? false : true;
		Logger::getInstance()->info(__METHOD__.": 設定 RM39 為 F 及 RM30 為 U ".($result ? '成功' : '失敗'));
		if ($trindxFix && $result) {
			Logger::getInstance()->info(__METHOD__.": 一併更改 TRINDX IP_CODE 為 F ... ");
			$db->parse("
				UPDATE MOICAT.RINDX
					SET IP_CODE = 'F'
				WHERE II03 = :bv_year
					AND II04_1 = :bv_code
					AND II04_2 = :bv_num
			");
			
			$db->bind(":bv_year", $year);
			$db->bind(":bv_code", $code);
			$db->bind(":bv_num", $num);

			$result = $db->execute() === FALSE ? false : true;
			Logger::getInstance()->info(__METHOD__.": 更新 TRINDX IP_CODE 為 F ".($result ? '成功' : '失敗'));
		}

		return $result;
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
	// 第一次登記案件(排除子號) BY 日期區間
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
				WHERE 
					t.RM09 = '02'
					and t.RM07_1 BETWEEN :bv_st AND :bv_ed
					and t.RM03 LIKE '%0'
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
	// 查詢本所關注案件異動
	public function getCRSMSUpdateCase($tw_date = '') {
		if (!$this->db_wrapper->reachable()) {
			return array();
		}
		if (empty($tw_date)) {
			$tmp_date = timestampToDate(time(), 'TW');
			$parts = explode(' ', $tmp_date);
			// ex: 1130506
			$tw_date = implode('', explode('-', $parts[0]));
		}
		$this->db_wrapper->getDB()->parse("
			-- 找案件異動時間(本所關注案件)
			SELECT
				t.*,
				s.KCNT AS \"RM09_CHT\"
				FROM MOICAS.CRSMS t
				LEFT JOIN MOIADM.RKEYN s ON s.KCDE_1 = '06' AND s.KCDE_2 = t.RM09
			WHERE 1 = 1
				AND (rm07_1 = :bv_date or rm44_1 = :bv_date or rm46_1 = :bv_date or
						rm48_1 = :bv_date or rm53_1 = :bv_date or rm54_1 = :bv_date or
						rm56_1 = :bv_date or rm58_1 = :bv_date or rm62_1 = :bv_date or
						rm80 = :bv_date or rm83 = :bv_date or rm86 = :bv_date or
						-- 不看歸檔時間，偏差很大!
						-- rm91_1 = :bv_date or
						rm93_1 = :bv_date or rm106_1 = :bv_date or rm107_1 = :bv_date)
						-- 只關心本所處理案件
				AND (t.RM99 IS NULL OR t.RM101 = :bv_site)
			ORDER BY rm107_2 desc,
								rm106_2 desc,
								rm93_2 desc,
								rm87 desc,
								rm84 desc,
								rm81 desc,
								rm53_2 desc,
								rm48_2 desc,
								rm58_2 desc,
								rm56_2 desc,
								rm54_2 desc,
								rm62_2 desc,
								rm46_2 desc,
								rm44_2 desc,
								rm07_2 desc
		");
		$this->db_wrapper->getDB()->bind(":bv_date", $tw_date);
		$this->db_wrapper->getDB()->bind(":bv_site", $this->site);
		$this->db_wrapper->getDB()->execute();
		return $this->db_wrapper->getDB()->fetchAll();
	}
	// 查詢本所關注案件異動LOG
	public function getConcernCRSMSLog($tw_date = '', $last_query_time = '000000') {
		if (!$this->db_wrapper->reachable()) {
			return array();
		}
		if (empty($tw_date)) {
			$tmp_date = timestampToDate(time(), 'TW');
			$parts = explode(' ', $tmp_date);
			// ex: 1130506
			$tw_date = implode('', explode('-', $parts[0]));
		}
		$this->db_wrapper->getDB()->parse("
			SELECT
				u.*,
				s.KCNT AS \"RM09_CHT\",
				t.RM103,	-- 異動人員
				t.RM104,	-- 異動類別
				t.RM105_1,-- 異動日期
				t.RM105_2 -- 異動時間
			FROM MOICAS.CRSMSLOG t
			LEFT JOIN MOIADM.RKEYN s ON s.KCDE_1 = '06' AND s.KCDE_2 = t.RM09
			LEFT JOIN MOICAS.CRSMS u ON t.RM01 = u.RM01 AND t.RM02 = u.RM02 AND t.RM03 = u.RM03
			WHERE 1=1
			  AND rm105_1 = :bv_date
				AND rm105_2 >= :bv_time
				-- only cares about our own case
				AND (t.RM99 IS NULL OR t.RM101 = :bv_site)
			ORDER BY rm105_2 DESC
		");
		$this->db_wrapper->getDB()->bind(":bv_date", $tw_date);
		$this->db_wrapper->getDB()->bind(":bv_time", $last_query_time);
		$this->db_wrapper->getDB()->bind(":bv_site", $this->site);
		$this->db_wrapper->getDB()->execute();
		return $this->db_wrapper->getDB()->fetchAll();
	}

	// 取得謄本紀錄SQL
	private function getCUSMMSQL($date_or_pid) {
		$where_cond = "and (
			t.mu05 LIKE '%' || :bv_pid || '%'     -- 申請人統編
			or t.mu08 LIKE '%' || :bv_pid || '%'  -- 代理人統編
			or t.mu06 LIKE '%' || :bv_pid || '%'  -- 申請人姓名
			or t.mu09 LIKE '%' || :bv_pid || '%'  -- 代理人姓名
		) -- 統編/姓名";
		if ($date_or_pid === 'date') {
			$where_cond = "and t.mu12 between :bv_begin and :bv_end -- 收件日期";
		}
		return "
			-- 謄本記錄查詢 BY 統編
			select
			t.mu01 AS \"收件年\",
			t.mu02 AS \"收件字\",
			t.mu03 AS \"收件號\",
			(CASE
				WHEN t.mu04 = '1' THEN '".mb_convert_encoding('現場申請', ORACLE_ENCODING, 'UTF-8')."'
				WHEN t.mu04 = '2' THEN '".mb_convert_encoding('隨案謄本', ORACLE_ENCODING, 'UTF-8')."'
				WHEN t.mu04 = '3' THEN '".mb_convert_encoding('內部使用', ORACLE_ENCODING, 'UTF-8')."'
				WHEN t.mu04 = '4' THEN '".mb_convert_encoding('傳真申請', ORACLE_ENCODING, 'UTF-8')."'
				ELSE t.mu04
			END) AS \"申請方式\",
			(CASE
				WHEN t.mu42 = '00' THEN '".mb_convert_encoding('公務用', ORACLE_ENCODING, 'UTF-8')."'
				WHEN t.mu42 = '01' THEN '".mb_convert_encoding('第一類', ORACLE_ENCODING, 'UTF-8')."'
				WHEN t.mu42 = '02' THEN '".mb_convert_encoding('第二類', ORACLE_ENCODING, 'UTF-8')."'
				WHEN t.mu42 = '04' THEN '".mb_convert_encoding('第三類', ORACLE_ENCODING, 'UTF-8')."'
				ELSE t.mu42
			END) AS \"申請類別\",
			t.mu05 AS \"申請人統編\",
			t.mu06 AS \"申請人姓名\",
			t.mu08 AS \"代理人統編\",
			t.mu09 AS \"代理人姓名\",
			t.mu12 AS \"收件日期\",
			t.mu13 AS \"收件時間\",
			(CASE
				WHEN s.md04 = 'A' THEN '".mb_convert_encoding('登記電子資料謄本', ORACLE_ENCODING, 'UTF-8')."'
				WHEN s.md04 = 'C' THEN '".mb_convert_encoding('地價電子資料謄本', ORACLE_ENCODING, 'UTF-8')."'
				WHEN s.md04 = 'D' THEN '".mb_convert_encoding('建物平面圖謄本', ORACLE_ENCODING, 'UTF-8')."'
				WHEN s.md04 = 'E' THEN '".mb_convert_encoding('人工登記簿謄本', ORACLE_ENCODING, 'UTF-8')."'
				WHEN s.md04 = 'F' THEN '".mb_convert_encoding('閱覽', ORACLE_ENCODING, 'UTF-8')."'
				WHEN s.md04 = 'G' THEN '".mb_convert_encoding('列印電子資料', ORACLE_ENCODING, 'UTF-8')."'
				WHEN s.md04 = 'H' THEN '".mb_convert_encoding('申請', ORACLE_ENCODING, 'UTF-8')."'
				WHEN s.md04 = 'I' THEN '".mb_convert_encoding('其他', ORACLE_ENCODING, 'UTF-8')."'
				WHEN s.md04 = 'J' THEN '".mb_convert_encoding('異動索引', ORACLE_ENCODING, 'UTF-8')."'
				ELSE s.md04
			END) AS \"申請類別\",
			s.md06 AS \"段代碼\",
			u.kname AS \"段小段\",
			s.md09 AS \"鄉鎮市區代碼\",
			v.kname AS \"鄉鎮市區\",
			(CASE
				WHEN s.md07 = 'C' THEN '".mb_convert_encoding('土地', ORACLE_ENCODING, 'UTF-8')."'
				WHEN s.md07 = 'F' THEN '".mb_convert_encoding('建物', ORACLE_ENCODING, 'UTF-8')."'
				ELSE s.md07
			END) AS \"地建別\",
			s.md08 AS \"地建號\",
			t.mu28 AS \"電子資料登記謄本張數\",
			t.mu29 AS \"登記簿謄本張數\",
			t.mu30 AS \"地價謄本張數\",
			t.mu31 AS \"地籍圖謄本張數\",
			t.mu32 AS \"建物平面圖謄本張數\"
			--t.*,
			--s.*
			from MOICAS.CUSMM t
			left join MOICAS.CUSMD2 s on t.mu01 = s.md01 and t.mu02 = s.md02 and t.mu03 = s.md03
			left join MOIADM.RKEYN_ALL u on u.kcde_1 = '48' and u.kcde_2 = 'H' and u.kcde_3 = s.md09 and u.kcde_4 = s.md06
			left join MOIADM.RKEYN_ALL v on v.kcde_1 = '46' and v.kcde_2 = 'H' and v.kcde_3 = s.md09
			where 1=1
				$where_cond
			order by t.mu12 desc, t.mu13 desc
		";
	}

	// 取得謄本紀錄 BY 統編/姓名
  public function getCUSMMByQuery($pid) {
		if (!$this->db_wrapper->reachable() || empty($pid)) {
			return array();
		}
		$this->db_wrapper->getDB()->parse($this->getCUSMMSQL('pid'));
		$this->db_wrapper->getDB()->bind(":bv_pid", mb_convert_encoding($pid, ORACLE_ENCODING, 'UTF-8'));
		$this->db_wrapper->getDB()->execute();
		return $this->db_wrapper->getDB()->fetchAll();
	}
	// 取得謄本紀錄 BY 收件日期
  public function getCUSMMByDate($tw_date_beg, $tw_date_end) {
		if (!$this->db_wrapper->reachable()) {
			return array();
		}
		$this->db_wrapper->getDB()->parse($this->getCUSMMSQL('date'));
		$this->db_wrapper->getDB()->bind(":bv_begin", $tw_date_beg);
		$this->db_wrapper->getDB()->bind(":bv_end", $tw_date_end);
		$this->db_wrapper->getDB()->execute();
		return $this->db_wrapper->getDB()->fetchAll();
	}
	// 取得地政系統工作天設定
	public function getWorkdays($tw_year = null) {
		if (!$this->db_wrapper->reachable()) {
			return array();
		}
		if (empty($tw_year)) {
			// 使用 null 合併運算符簡化判斷
			$tw_year = $GLOBALS['this_year'] ?? null; // 嘗試使用全域變數
			if ($tw_year === null) {
					// timestampToDate is a custummized global method
					$tmp_date = timestampToDate(time(), 'TW');
					$date_parts = explode('-', explode(' ', $tmp_date)[0]);
					$tw_year = $date_parts[0];
			}
		}
		$this->db_wrapper->getDB()->parse("
			SELECT * FROM MOICAS.SYSHOL t
			WHERE SYSDAT LIKE :bv_year || '%'
			ORDER BY SYSDAT
		");
		$this->db_wrapper->getDB()->bind(":bv_year", $tw_year);
		$this->db_wrapper->getDB()->execute();
		return $this->db_wrapper->getDB()->fetchAll();
	}
	// 根據日期區間取的初審案件
	public function getInitialReviewCase($st, $ed, $id) {
		if (!$this->db_wrapper->reachable()) {
			return array();
		}
		$this->db_wrapper->getDB()->parse("
				SELECT
					ssc.*,
					s.KCNT AS \"RM09_CHT\"
				FROM MOICAS.CRSMS ssc
				LEFT JOIN MOIADM.RKEYN s ON s.KCDE_1 = '06' AND s.KCDE_2 = ssc.RM09
				WHERE 1=1
				  AND ssc.RM03 LIKE '%0'
					AND ssc.RM45 = :bv_rm45
					AND ssc.RM44_1 BETWEEN :bv_st AND :bv_ed
					AND ((ssc.RM99 IS NULL) OR (ssc.RM99 IS NOT NULL AND ssc.RM101 = :bv_site))
				ORDER BY ssc.RM07_1
		");
		$site = strtoupper(System::getInstance()->get('SITE')) ?? 'HA';
		$this->db_wrapper->getDB()->bind(":bv_rm45", $id);
		$this->db_wrapper->getDB()->bind(":bv_site", $site);
		$this->db_wrapper->getDB()->bind(":bv_st", $st);
		$this->db_wrapper->getDB()->bind(":bv_ed", $ed);

		Logger::getInstance()->info(__METHOD__.": received params: $site $id $st $ed");

		$this->db_wrapper->getDB()->execute();
		return $this->db_wrapper->getDB()->fetchAll();
	}
	// 根據日期區間取的複審案件
	public function getFinalReviewCase($st, $ed, $id) {
		if (!$this->db_wrapper->reachable()) {
			return array();
		}
		$this->db_wrapper->getDB()->parse("
				SELECT
					ssc.*,
					s.KCNT AS \"RM09_CHT\"
				FROM MOICAS.CRSMS ssc
				LEFT JOIN MOIADM.RKEYN s ON s.KCDE_1 = '06' AND s.KCDE_2 = ssc.RM09
				WHERE 1=1
				  AND ssc.RM03 LIKE '%0'
					AND ssc.RM47 = :bv_rm47
					AND ssc.RM46_1 BETWEEN :bv_st AND :bv_ed
					AND ((ssc.RM99 IS NULL) OR (ssc.RM99 IS NOT NULL AND ssc.RM101 = :bv_site))
				ORDER BY ssc.RM07_1
		");
		$site = strtoupper(System::getInstance()->get('SITE')) ?? 'HA';
		$this->db_wrapper->getDB()->bind(":bv_rm47", $id);
		$this->db_wrapper->getDB()->bind(":bv_site", $site);
		$this->db_wrapper->getDB()->bind(":bv_st", $st);
		$this->db_wrapper->getDB()->bind(":bv_ed", $ed);

		Logger::getInstance()->info(__METHOD__.": received params: $site $id $st $ed");

		$this->db_wrapper->getDB()->execute();
		return $this->db_wrapper->getDB()->fetchAll();
	}
	// 根據日期區間取的課長案件
	public function getChiefReviewCase($st, $ed, $id) {
		if (!$this->db_wrapper->reachable()) {
			return array();
		}
		$this->db_wrapper->getDB()->parse("
				SELECT
					ssc.*,
					s.KCNT AS \"RM09_CHT\"
				FROM MOICAS.CRSMS ssc
				LEFT JOIN MOIADM.RKEYN s ON s.KCDE_1 = '06' AND s.KCDE_2 = ssc.RM09
				WHERE 1=1
				  AND ssc.RM03 LIKE '%0'
					AND ssc.RM106 = :bv_rm47
					AND ssc.RM106_1 BETWEEN :bv_st AND :bv_ed
					AND ((ssc.RM99 IS NULL) OR (ssc.RM99 IS NOT NULL AND ssc.RM101 = :bv_site))
				ORDER BY ssc.RM07_1
		");
		$site = strtoupper(System::getInstance()->get('SITE')) ?? 'HA';
		$this->db_wrapper->getDB()->bind(":bv_rm47", $id);
		$this->db_wrapper->getDB()->bind(":bv_site", $site);
		$this->db_wrapper->getDB()->bind(":bv_st", $st);
		$this->db_wrapper->getDB()->bind(":bv_ed", $ed);

		Logger::getInstance()->info(__METHOD__.": received params: $site $id $st $ed");

		$this->db_wrapper->getDB()->execute();
		return $this->db_wrapper->getDB()->fetchAll();
	}
	// 過去權利人為私人設定的案件(排除連件、年齡大於?歲)
	public function getDaysAgoOverAgeSetCase($age = 60, $days = 365) {
		if (!$this->db_wrapper->reachable()) {
			return array();
		}
		// RM09 👉 83 設定、97 判決設定、98 和解設定、99 調解設定、EP 設定目的變更、ES 讓與或設定抵押權限制變更 
		$this->db_wrapper->getDB()->parse("
				-- 查詢過去1年權利人為私人設定(排除連件)的案件(年齡大於60歲)
				SELECT rm01, rm02, rm03, rm07_1, rm18, rm19
				FROM (
					select rm01, rm02, rm03, rm07_1, rm18, rm19, lbir_2
					from scrsms, srlnid
					where rm18 = lidn
						and rm09 in ('83', '97', '98', '99', 'EP', 'ES')
						and lcde in ('1', '2', 'C') -- LCDE 為 1, 2 或 C(本國人、外國人以及大陸地區自然人)
						and rm07_1 >= TO_CHAR(TO_CHAR(SYSDATE, - :bv_days, 'YYYYMMDD') - 19110000) -- 搜尋過去一年
						and rm31 is not null
					minus
					select rm01, rm02, rm03, rm07_1, rm18, rm19, lbir_2
					from scrtocr,
									(select rm01, rm02, rm03, rm07_1, rm18, rm19, lbir_2
										from scrsms, srlnid
									where rm18 = lidn
										and rm09 in ('83', '97', '98', '99', 'EP', 'ES')
										and lcde in ('1', '2', 'C')
										and rm07_1 >= TO_CHAR(TO_CHAR(SYSDATE - :bv_days, 'YYYYMMDD') - 19110000) -- 搜尋過去一年
										and rm31 is not null
									)
					where (rm01 = to01 and rm02 = to02 and rm03 = to03)
						or (rm01 = to04 and rm02 = to05 and rm03 = to06)
				)
				WHERE (SUBSTR(to_char(sysdate, 'yyyyMMdd'), 1, 4) - 1911 - SUBSTR(lbir_2, 1, 3) >= :bv_age)
				ORDER BY rm07_1 DESC
		");

		$this->db_wrapper->getDB()->bind(":bv_days", $days);
		$this->db_wrapper->getDB()->bind(":bv_age", $age);

		Logger::getInstance()->info(__METHOD__.": $days ago, over $age");

		$this->db_wrapper->getDB()->execute();
		return $this->db_wrapper->getDB()->fetchAll();
	}
	// 查詢預設1分鐘內為私人設定的案件(預設年齡大於60歲或是空值)
	// Scheduler for Watchdog class execution window is 15 mins
	public function getPossibleFruadCase($mins = 1, $age = 59) {
		if (!$this->db_wrapper->reachable()) {
			return array();
		}
		if (intval($mins) < 1) {
			Logger::getInstance()->warning(__METHOD__.": $mins 不是正常的整數。");
			return array();
		}
		// RM09 👉 83 設定、97 判決設定、98 和解設定、99 調解設定、EP 設定目的變更、ES 讓與或設定抵押權限制變更 
		$this->db_wrapper->getDB()->parse("
				SELECT *
				FROM (
				    SELECT t.*,
						      v.kcnt AS \"RM09_CHT\",
									SUBSTR(TO_CHAR(SYSDATE, 'YYYYMMDD'), 1, 4) - 1911 - SUBSTR(u.lbir_2, 1, 3) AS age,
									COALESCE(u.ladr_a, u.ladr) AS address, -- ladr_a 優先
									TO_DATE(TO_CHAR(SYSDATE, 'YYYYMMDD') || t.rm07_2, 'YYYYMMDDHH24MISS') AS rm07_2_time -- 將 rm07_2 轉換為時間類型
						FROM scrsms t
						LEFT JOIN srlnid u ON u.lidn = t.rm18
						LEFT JOIN srkeyn v ON v.kcde_1 = '06' AND v.kcde_2 = t.rm09
						WHERE 1 = 1
							-- 自然人ID
							AND LENGTH(t.RM18) = 10
							AND ASCII(UPPER(SUBSTR(t.RM18, 1, 1))) BETWEEN 65 AND 90
							-- 今天
							AND rm07_1 = TO_CHAR(TO_CHAR(SYSDATE, 'YYYYMMDD') - 19110000)
							-- 設定(將下一行移出註解以啟用)
							AND t.RM09 = '83'
							-- 本所關注案件
							AND (t.RM99 <> 'Y' OR (t.RM99 = 'Y' AND t.RM101 = :bv_site))
				) v
				WHERE 1 = 1
					-- 年齡大於?或無法計算
					AND (age > :bv_age OR age IS NULL)
					-- 篩選 ? 分鐘內的資料
					AND rm07_2_time >= SYSDATE - INTERVAL '".intval($mins)."' MINUTE
				ORDER BY rm07_2_time DESC
		");

		$site = SYSTEM::getInstance()->getSiteCode();
		$this->db_wrapper->getDB()->bind(":bv_site", $site);
		$this->db_wrapper->getDB()->bind(":bv_age", $age);

		Logger::getInstance()->info(__METHOD__.": $mins minutes ago, over $age or null");

		$this->db_wrapper->getDB()->execute();
		$arr = $this->db_wrapper->getDB()->fetchAll();

		Logger::getInstance()->info(__METHOD__.": 取得 $mins 分鐘內為私人設定的案件(年齡大於 $age 歲或是空值)：".count($arr)." 筆。");

		return $arr;
	}

	public function getCounterPrinterMap() {
		if (!$this->db_wrapper->reachable()) {
			return array();
		}
		$this->db_wrapper->getDB()->parse("
			Select CP.*, S1.USER_NAME
				From MOICAS.CER_PRINTER CP
				Left Join SSYSAUTH1 S1
					On CP.USER_ID = S1.USER_ID
			Where 1 = 1
				AND S1.VALID = '1'
			Order By CP.USER_ID
		");
		
		$this->db_wrapper->getDB()->execute();
		$arr = $this->db_wrapper->getDB()->fetchAll();

		Logger::getInstance()->info(__METHOD__.": 取得目前地政系統人員使用印表機對應表：".count($arr)." 筆。");

		return $arr;
	}
}
