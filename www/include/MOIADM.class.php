<?php
require_once("init.php");
require_once("OraDBWrapper.class.php");
require_once("System.class.php");
require_once("Cache.class.php");

class MOIADM {
	private $db_wrapper = null;

	function __construct() {
		$this->db_wrapper = new OraDBWrapper();
	}

	function __destruct() {
		$this->db_wrapper = null;
	}
	/**
	 * Find write back history record in rdy status (default)
	 */
	public function getPublicationHistory($date = '', $status = 'rdy') {
		if (!$this->db_wrapper->reachable()) {
			return false;
		}
		// default use today
		$date = empty($date) ? date('Y/m/d') : $date;
		Logger::getInstance()->info(__METHOD__.': Going to fetch MOIADM.PUBLICATION_HISTORY in '.$status.' status on '.$date);
		if (empty($status)) {
			$this->db_wrapper->getDB()->parse("
				-- 回寫資料查詢
				SELECT t.*
					FROM MOIADM.PUBLICATION_HISTORY t
				WHERE 1=1
					AND t.DATE_TIME LIKE :bv_date || '%'
				ORDER BY t.DATE_TIME DESC
			");
		} else {
			$this->db_wrapper->getDB()->parse("
				SELECT t.*
					FROM MOIADM.PUBLICATION_HISTORY t
				WHERE 1=1
					--AND SUBSTR(t.DATE_TIME, 0, 10) = :bv_date
					AND t.DATE_TIME LIKE :bv_date || '%'
					AND t.PUBLICATION_STATUS = :bv_status
					--AND t.to_org_id = 'H0' -- To which county/city
				ORDER BY t.DATE_TIME DESC
			");
			$this->db_wrapper->getDB()->bind(":bv_status", $status);
		}
		$this->db_wrapper->getDB()->bind(":bv_date", $date);
		$this->db_wrapper->getDB()->execute();
		$records = $this->db_wrapper->getDB()->fetchAll();
		Logger::getInstance()->info(__METHOD__.': Found '.count($records).' record(s) in MOIADM.PUBLICATION_HISTORY with '.(empty($status) ? 'all' : $status).' status on '.$date);
		return $records;
	}
	/**
	 * 調教資料集，解決SQL查詢表格過慢問題
	 * -- 檢查最近一次是什麼時後做ANALYZE
   * select OWNER,TABLE_NAME,LAST_ANALYZED from all_tables where table_name='PUBLICATION_HISTORY';
	 */
	public function analyzeMOIADMTable($name) {
		if (empty($name)) {
			return false;
		}
		
		if (!$this->db_wrapper->reachable()) {
			return false;
		}
		
		Logger::getInstance()->info(__METHOD__.": Going to analyze MOIADM.$name table and delete statistics.");
		$this->db_wrapper->getDB()->parse("ANALYZE TABLE MOIADM.".$name." delete statistics");
		
		return $this->db_wrapper->getDB()->execute() === FALSE ? false : true;
	}
	/**
	 * Find SMSLog records
	 */
	public function getSMSLogRecords($keyword) {
		if (!$this->db_wrapper->reachable()) {
			return array();
		}
		$this->db_wrapper->getDB()->parse("
			-- SMS Log 查詢
			select * from MOIADM.SMSLOG t
			where 1=1
				and (ms14 like '%' || :bv_keyword || '%' OR MS_MAIL like '%' || :bv_keyword || '%' OR MS07_1 like '%' || :bv_keyword || '%')
			order by ms07_1 desc, ms07_2 desc
		");
		$this->db_wrapper->getDB()->bind(":bv_keyword", $keyword);
		$this->db_wrapper->getDB()->execute();
		return $this->db_wrapper->getDB()->fetchAll();
	}
	/**
	 * Find SMSLog records by date
	 */
	public function getSMSLogRecordsByDate($date) {
		if (!$this->db_wrapper->reachable()) {
			return array();
		}
		$this->db_wrapper->getDB()->parse("
			select * from MOIADM.SMSLOG t
			where 1=1
				and (MS07_1 like '%' || :bv_keyword || '%')
			order by ms07_1 desc, ms07_2 desc
		");
		$this->db_wrapper->getDB()->bind(":bv_keyword", $date);
		$this->db_wrapper->getDB()->execute();
		return $this->db_wrapper->getDB()->fetchAll();
	}
	/**
	 * Find SMSLog records by cell phone
	 */
	public function getSMSLogRecordsByCell($cell) {
		if (!$this->db_wrapper->reachable()) {
			return array();
		}
		$this->db_wrapper->getDB()->parse("
			select * from MOIADM.SMSLOG t
			where 1=1
				and (MS14 like '%' || :bv_keyword || '%')
			order by ms07_1 desc, ms07_2 desc
		");
		$this->db_wrapper->getDB()->bind(":bv_keyword", $cell);
		$this->db_wrapper->getDB()->execute();
		return $this->db_wrapper->getDB()->fetchAll();
	}
	/**
	 * Find SMSLog records by email
	 */
	public function getSMSLogRecordsByEmail($mail) {
		if (!$this->db_wrapper->reachable()) {
			return array();
		}
		$this->db_wrapper->getDB()->parse("
			select * from MOIADM.SMSLOG t
			where 1=1
				and (MS_MAIL like '%' || :bv_keyword || '%')
			order by ms07_1 desc, ms07_2 desc
		");
		$this->db_wrapper->getDB()->bind(":bv_keyword", $mail);
		$this->db_wrapper->getDB()->execute();
		return $this->db_wrapper->getDB()->fetchAll();
	}
	/**
	 * Find SMSLog records by note
	 */
	public function getSMSLogRecordsByNote($note) {
		if (!$this->db_wrapper->reachable()) {
			return array();
		}
		$this->db_wrapper->getDB()->parse("
			select * from MOIADM.SMSLOG t
			where 1=1
				and (MS_NOTE like '%' || :bv_keyword || '%')
			order by ms07_1 desc, ms07_2 desc
		");
		$this->db_wrapper->getDB()->bind(":bv_keyword", $note);
		$this->db_wrapper->getDB()->execute();
		return $this->db_wrapper->getDB()->fetchAll();
	}
}
