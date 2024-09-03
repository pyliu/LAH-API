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
	 * 調教資料集，解決SQL查詢表格過慢問題
	 * -- 檢查最近一次是什麼時後做ANALYZE
   * select OWNER,TABLE_NAME,LAST_ANALYZED from all_tables where table_name='RINDX';
	 */
	public function analyzeMOICATTable($name) {
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
		Logger::getInstance()->info(__METHOD__.": Going to analyze MOICAT.$name table $action statistics.");
		// sampling by 10% records 👉 ANALYZE TABLE MOICAS.CRSMS ESTIMATE STATISTICS SAMPLE 10 PERCENT;
		$this->db_wrapper->getDB()->parse("ANALYZE TABLE MOICAT.".$name." $action statistics");
		
		return $this->db_wrapper->getDB()->execute() === FALSE ? false : true;
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

	public function getLatestCertSeqRecord() {
		global $today;
		if (!$this->db_wrapper->reachable()) {
			return array();
		}
		if (empty($today)) {
			$tw_date = new Datetime("now");
			$tw_date->modify("-1911 year");
			$today = ltrim($tw_date->format("Ymd"), "0");	
		}
		$year = substr($today, 0, 3);
		$month = substr($today, 3, 2);
		$day = substr($today, 5, 2);
		$this->db_wrapper->getDB()->parse("
			-- 權狀序號查詢
			select * from MOICAT.RXSEQ t
			where 1=1
				and xsdate like :bv_ymd || '%'
				and rownum = 1
			order by xsdate desc
		");
		$this->db_wrapper->getDB()->bind(":bv_ymd", $year.$month.$day);
		$this->db_wrapper->getDB()->execute();
		return $this->db_wrapper->getDB()->fetch();
	}
	/**
	 * Find reg case temp records
	 */
	public function getRINDXRecords($year, $code, $num) {
		$year = str_pad($year, 3, '0', STR_PAD_LEFT);
		$num = str_pad($num, 6, '0', STR_PAD_LEFT);
		$this->db_wrapper->getDB()->parse("
			-- 登記暫存檔
			select * from MOICAT.RINDX t
			where 1=1
				and t.II03 = :bv_year
				and t.II04_1 = :bv_code
				and t.II04_2 = :bv_num
		");
		$this->db_wrapper->getDB()->bind(":bv_year", $year);
		$this->db_wrapper->getDB()->bind(":bv_code", $code);
		$this->db_wrapper->getDB()->bind(":bv_num", $num);
		$this->db_wrapper->getDB()->execute();
		return $this->db_wrapper->getDB()->fetchAll();
	}
	/**
	 * To fix reg case temp record to F
	 */
	public function fixRINDXCode($year, $code, $num) {
		if (!$this->db_wrapper->reachable()) {
			return false;
		}
		
		Logger::getInstance()->info(__METHOD__.": Going to set $year-$code-$num MOICAT.RINDX IP_CODE to 'F'.");

		$year = str_pad($year, 3, '0', STR_PAD_LEFT);
		$num = str_pad($num, 6, '0', STR_PAD_LEFT);

		$this->db_wrapper->getDB()->parse("
			UPDATE MOICAT.RINDX SET IP_CODE = 'F' 
			WHERE II03 = :bv_year
			  AND II04_1 = :bv_code
				AND II04_2 = :bv_num
		");
		
		$this->db_wrapper->getDB()->bind(":bv_year", $year);
		$this->db_wrapper->getDB()->bind(":bv_code", $code);
		$this->db_wrapper->getDB()->bind(":bv_num", $num);
		return $this->db_wrapper->getDB()->execute() === FALSE ? false : true;
	}
}
