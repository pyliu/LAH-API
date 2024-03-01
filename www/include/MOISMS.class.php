<?php
require_once("init.php");
require_once("OraDBWrapper.class.php");
require_once("System.class.php");
require_once("Cache.class.php");

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
