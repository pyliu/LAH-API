<?php
require_once("init.php");
require_once("OraDBWrapper.class.php");
require_once("System.class.php");
require_once("Cache.class.php");

/** json response schema
	{ key: 'MS03', label: '收件年', sortable: true },
	{ key: 'MS04_1', label: '收件字', sortable: true },
	{ key: 'MS04_2', label: '收件字號', sortable: true },
	{ key: 'MS_TYPE', label: '案件種類', sortable: true },
	{ key: 'MS07_1', label: '傳送日期', sortable: true },
	{ key: 'MS07_2', label: '傳送時間', sortable: true },
	{ key: 'MS14', label: '手機號碼', sortable: true },
	{ key: 'MS_MAIL', label: '電子郵件', sortable: true },
	// { key: 'MS30', label: '傳送狀態', sortable: true },
	{ key: 'MS31', label: '傳送結果', sortable: true },
	// { key: 'MS33', label: '傳送紀錄', sortable: true },
	{ key: 'MS_NOTE', label: '傳送內容', sortable: true }
 */
class MOISMS {
	private $db_wrapper = null;

	function __construct() {
		$this->db_wrapper = new OraDBWrapper();
	}

	function __destruct() {
		$this->db_wrapper = null;
	}
	/**
	 * Find SMSLog records
	 */
	public function getMOIADMSMSLogRecords($keyword) {
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
	public function getMOIADMSMSLogRecordsByDate($date) {
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
	public function getMOIADMSMSLogRecordsByCell($cell) {
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
	public function getMOIADMSMSLogRecordsByEmail($mail) {
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
	public function getMOIADMSMSLogRecordsByNote($note) {
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
