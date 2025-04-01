<?php
require_once("init.php");
require_once("OraDBWrapper.class.php");
require_once("System.class.php");
require_once("Cache.class.php");

/** MOIADM.SMSLOG schema
	{ key: 'MS03', label: '收件年', sortable: true },
	{ key: 'MS04_1', label: '收件字', sortable: true },
	{ key: 'MS04_2', label: '收件字號', sortable: true },
	{ key: 'MS_TYPE', label: '案件種類', sortable: true },
	{ key: 'MS07_1', label: '傳送日期', sortable: true },
	{ key: 'MS07_2', label: '傳送時間', sortable: true },
	{ key: 'MS14', label: '手機號碼', sortable: true },
	{ key: 'MS_MAIL', label: '電子郵件', sortable: true },
	{ key: 'MS30', label: '傳送狀態', sortable: true },
	{ key: 'MS31', label: '傳送結果', sortable: true },
	{ key: 'MS33', label: '傳送紀錄', sortable: true },
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
	 * Find MOIADM.SMSLog records
	 */
	public function getMOIADMSMSLogRecords($keyword) {
		if (!$this->db_wrapper->reachable()) {
			return array();
		}
		Logger::getInstance()->info(__METHOD__.': 取得 MOIADM SMSLog 資料 keyword: '.$keyword.'。 db is '.gettype($this->db_wrapper->getDB()));
		$this->db_wrapper->getDB()->parse("
			-- SMS Log 查詢
			select
			   MS03 AS SMS_YEAR,
				 MS04_1 AS SMS_CODE,
				 MS04_2 AS SMS_NUMBER,
				 --MS_TYPE AS SMS_TYPE,
				 (CASE
				   WHEN t.MS_TYPE = 'M' THEN '".mb_convert_encoding('地籍異動即時通', 'BIG5', 'UTF-8')."'
					 WHEN t.MS_TYPE = 'W' THEN '".mb_convert_encoding('指定送達處所', 'BIG5', 'UTF-8')."'
					 ELSE t.MS_TYPE
				 END) AS SMS_TYPE,
				 MS07_1 AS SMS_DATE,
				 MS07_2 AS SMS_TIME,
				 MS14 AS SMS_CELL,
				 MS_MAIL AS SMS_MAIL,
				 MS31 AS SMS_RESULT,
				 MS_NOTE AS SMS_CONTENT
		  from MOIADM.SMSLOG t
			where 1=1
				and (
					MS14 like '%' || :bv_keyword || '%' OR
					MS_MAIL like '%' || :bv_keyword || '%' OR
					MS07_1 like '%' || :bv_keyword || '%' OR
					MS_NOTE like '%' || :bv_keyword || '%'
				)
			order by ms07_1 desc, ms07_2 desc
		");
		$this->db_wrapper->getDB()->bind(":bv_keyword", $keyword);
		$this->db_wrapper->getDB()->execute();
		return $this->db_wrapper->getDB()->fetchAll();
	}
	/**
	 * Find MOIADM.SMSLog records by date
	 */
	public function getMOIADMSMSLogRecordsByDate($st, $ed) {
		if (!$this->db_wrapper->reachable()) {
			return array();
		}
		Logger::getInstance()->info(__METHOD__.': 取得 MOIADM SMSLog 資料 BY 區間 '.$st.' ~ '.$ed.'。 db is '.gettype($this->db_wrapper->getDB()));
		$this->db_wrapper->getDB()->parse("
			-- SMS Log 查詢
			select
			   MS03 AS SMS_YEAR,
				 MS04_1 AS SMS_CODE,
				 MS04_2 AS SMS_NUMBER,
				 --MS_TYPE AS SMS_TYPE,
				 (CASE
				   WHEN t.MS_TYPE = 'M' THEN '".mb_convert_encoding('地籍異動即時通', 'BIG5', 'UTF-8')."'
					 WHEN t.MS_TYPE = 'W' THEN '".mb_convert_encoding('指定送達處所', 'BIG5', 'UTF-8')."'
					 ELSE t.MS_TYPE
				 END) AS SMS_TYPE,
				 MS07_1 AS SMS_DATE,
				 MS07_2 AS SMS_TIME,
				 MS14 AS SMS_CELL,
				 MS_MAIL AS SMS_MAIL,
				 MS31 AS SMS_RESULT,
				 MS_NOTE AS SMS_CONTENT
		  from MOIADM.SMSLOG t
			where 1=1
				and (
					MS07_1 BETWEEN :bv_st AND :bv_ed
				)
			order by ms07_1 desc, ms07_2 desc
		");
		$this->db_wrapper->getDB()->bind(":bv_st", $st);
		$this->db_wrapper->getDB()->bind(":bv_ed", $ed);
		$this->db_wrapper->getDB()->execute();
		return $this->db_wrapper->getDB()->fetchAll();
	}
	/**
	 * Find SMS98.LOG_SMS records
	 */
	public function getSMS98LOG_SMSRecords($keyword) {
		if (!$this->db_wrapper->reachable()) {
			return array();
		}
		Logger::getInstance()->info(__METHOD__.': 取得 SMS98 LOG_SMS 資料 keyword: '.$keyword.'。 db is '.gettype($this->db_wrapper->getDB()));
		$this->db_wrapper->getDB()->parse("
			-- LOG_SMS 查詢
			select
				t.M01 AS SMS_YEAR,
				t.M02 AS SMS_CODE,
				t.M03 AS SMS_NUMBER,
				'".mb_convert_encoding('案件辦理情形', 'BIG5', 'UTF-8')."' AS SMS_TYPE,
				TO_CHAR( t.send_time, 'YYYYMMDD' ) - 19110000 AS SMS_DATE,
				TO_CHAR( t.send_time, 'HH24MISS' ) AS SMS_TIME,
				t.PHONE AS SMS_CELL,
				t.ID AS SMS_MAIL,
				(CASE WHEN t.LOG_REMARK = 'OK!' THEN 'S' ELSE t.LOG_REMARK END) AS SMS_RESULT,
				t.SMS_BODY AS SMS_CONTENT,
				'' AS SMS_APIMSG
			from SMS98.LOG_SMS t
			where 1=1
				and (
					t.PHONE like '%' || :bv_keyword || '%' OR
					t.ID like '%' || :bv_keyword || '%' OR
					TO_CHAR( t.send_time, 'YYYYMMDD' ) - 19110000 like '%' || :bv_keyword || '%' OR
					t.SMS_BODY like '%' || :bv_keyword || '%'
				)
			order by t.send_time desc
		");
		$this->db_wrapper->getDB()->bind(":bv_keyword", $keyword);
		$this->db_wrapper->getDB()->execute();
		return $this->db_wrapper->getDB()->fetchAll();
	}
	/**
	 * Find SMS98.LOG_SMS records by date
	 */
	public function getSMS98LOG_SMSRecordsByDate($st, $ed) {
		if (!$this->db_wrapper->reachable()) {
			return array();
		}
		Logger::getInstance()->info(__METHOD__.': 取得 SMS98 LOG_SMS 資料 BY 區間 '.$st.' ~ '.$ed.'。 db is '.gettype($this->db_wrapper->getDB()));
		$this->db_wrapper->getDB()->parse("
			-- LOG_SMS 查詢
			select
				t.M01 AS SMS_YEAR,
				t.M02 AS SMS_CODE,
				t.M03 AS SMS_NUMBER,
				'".mb_convert_encoding('案件辦理情形', 'BIG5', 'UTF-8')."' AS SMS_TYPE,
				TO_CHAR( t.send_time, 'YYYYMMDD' ) - 19110000 AS SMS_DATE,
				TO_CHAR( t.send_time, 'HH24MISS' ) AS SMS_TIME,
				t.PHONE AS SMS_CELL,
				t.ID AS SMS_MAIL,
				(CASE WHEN t.LOG_REMARK = 'OK!' THEN 'S' ELSE t.LOG_REMARK END) AS SMS_RESULT,
				t.SMS_BODY AS SMS_CONTENT,
				'' AS SMS_APIMSG
			from SMS98.LOG_SMS t
			where 1=1
				and (
					TO_CHAR( t.send_time, 'YYYYMMDD' ) - 19110000 BETWEEN :bv_st AND :bv_ed
				)
			order by t.send_time desc
		");
		$this->db_wrapper->getDB()->bind(":bv_st", $st);
		$this->db_wrapper->getDB()->bind(":bv_ed", $ed);
		$this->db_wrapper->getDB()->execute();
		return $this->db_wrapper->getDB()->fetchAll();
	}
	/**
	 * Find MOICAS.SMS_MA04 records
	 */
	public function getMOICASSMS_MA04Records($keyword) {
		if (!$this->db_wrapper->reachable()) {
			return array();
		}
		Logger::getInstance()->info(__METHOD__.': 取得 MOICAS SMS_MA04 資料 keyword: '.$keyword.'。 db is '.gettype($this->db_wrapper->getDB()));
		$this->db_wrapper->getDB()->parse("
			-- SMS_MA04 查詢
			select
				SUBSTR(t.MA4_NO, 1, 3) AS SMS_YEAR,
				SUBSTR(t.MA4_NO, 4, 4) AS SMS_CODE,
				SUBSTR(t.MA4_NO, 8, 6) AS SMS_NUMBER,
				'".mb_convert_encoding('跨域代收代寄', 'BIG5', 'UTF-8')."' AS SMS_TYPE,
				t.EDITDATE AS SMS_DATE,
				t.EDITTIME AS SMS_TIME,
				t.MA4_MP AS SMS_CELL,
				t.MA4_MID AS SMS_MAIL,
				'S' AS SMS_RESULT,
				t.MA4_CONT AS SMS_CONTENT,
				'' AS SMS_APIMSG
			from MOICAS.SMS_MA04 t
			where 1=1
				and (
					t.MA4_MP like '%' || :bv_keyword || '%' OR
					t.MA4_MID like '%' || :bv_keyword || '%' OR
					t.EDITDATE like '%' || :bv_keyword || '%' OR
					t.MA4_CONT like '%' || :bv_keyword || '%'
				)
			order by t.EDITDATE desc, t.EDITTIME desc
		");
		$this->db_wrapper->getDB()->bind(":bv_keyword", $keyword);
		$this->db_wrapper->getDB()->execute();
		return $this->db_wrapper->getDB()->fetchAll();
	}
	/**
	 * Find MOICAS.SMS_MA04 records by date
	 */
	public function getMOICASSMS_MA04RecordsByDate($st, $ed) {
		if (!$this->db_wrapper->reachable()) {
			return array();
		}
		Logger::getInstance()->info(__METHOD__.': 取得 MOICAS SMS_MA04 資料 BY 區間 '.$st.' ~ '.$ed.'。 db is '.gettype($this->db_wrapper->getDB()));
		$this->db_wrapper->getDB()->parse("
			-- SMS_MA04 查詢
			select
				SUBSTR(t.MA4_NO, 1, 3) AS SMS_YEAR,
				SUBSTR(t.MA4_NO, 4, 4) AS SMS_CODE,
				SUBSTR(t.MA4_NO, 8, 6) AS SMS_NUMBER,
				'".mb_convert_encoding('跨域代收代寄', 'BIG5', 'UTF-8')."' AS SMS_TYPE,
				t.EDITDATE AS SMS_DATE,
				t.EDITTIME AS SMS_TIME,
				t.MA4_MP AS SMS_CELL,
				t.MA4_MID AS SMS_MAIL,
				'S' AS SMS_RESULT,
				t.MA4_CONT AS SMS_CONTENT,
				'' AS SMS_APIMSG
			from MOICAS.SMS_MA04 t
			where 1=1
				and (
					t.EDITDATE BETWEEN :bv_st AND :bv_ed
				)
			order by t.EDITDATE desc, t.EDITTIME desc
		");
		$this->db_wrapper->getDB()->bind(":bv_st", $st);
		$this->db_wrapper->getDB()->bind(":bv_ed", $ed);
		$this->db_wrapper->getDB()->execute();
		return $this->db_wrapper->getDB()->fetchAll();
	}
	/**
	 * Find MOICAS.SMS_MA05 records
	 */
	public function getMOICASSMS_MA05Records($keyword) {
		if (!$this->db_wrapper->reachable()) {
			return array();
		}
		Logger::getInstance()->info(__METHOD__.': 取得 MOICAS SMS_MA05 資料 keyword: '.$keyword.'。 db is '.gettype($this->db_wrapper->getDB()));
		$this->db_wrapper->getDB()->parse("
			-- SMS_MA05 查詢
			select
				SUBSTR(t.MA5_NO, 1, 3) AS SMS_YEAR,
				SUBSTR(t.MA5_NO, 4, 4) AS SMS_CODE,
				SUBSTR(t.MA5_NO, 8, 6) AS SMS_NUMBER,
				'".mb_convert_encoding('其他(住址隱匿/代收代寄/手動)', 'BIG5', 'UTF-8')."' AS SMS_TYPE,
				t.MA5_SDATE AS SMS_DATE,
				t.MA5_STIME AS SMS_TIME,
				t.MA5_MP AS SMS_CELL,
				t.MA5_MID AS SMS_MAIL,
				(CASE WHEN t.MA5_STATUS = '2' THEN 'S' ELSE t.MA5_STATUS END) AS SMS_RESULT,
				t.MA5_CONT AS SMS_CONTENT,
				t.API_SENDMSG AS SMS_APIMSG
			from MOICAS.SMS_MA05 t
			where 1=1
				and (
					t.MA5_MP like '%' || :bv_keyword || '%' OR
					t.MA5_MID like '%' || :bv_keyword || '%' OR
					t.MA5_SDATE like '%' || :bv_keyword || '%' OR
					t.MA5_CONT like '%' || :bv_keyword || '%'
				)
			order by t.MA5_SDATE desc, t.MA5_STIME desc
		");
		$this->db_wrapper->getDB()->bind(":bv_keyword", $keyword);
		$this->db_wrapper->getDB()->execute();
		return $this->db_wrapper->getDB()->fetchAll();
	}
	/**
	 * Find MOICAS.SMS_MA05 records by date
	 */
	public function getMOICASSMS_MA05RecordsByDate($st, $ed) {
		if (!$this->db_wrapper->reachable()) {
			return array();
		}
		Logger::getInstance()->info(__METHOD__.': 取得 MOICAS SMS_MA05 資料 BY 區間 '.$st.' ~ '.$ed.'。 db is '.gettype($this->db_wrapper->getDB()));
		$this->db_wrapper->getDB()->parse("
			-- SMS_MA05 查詢
			select
				SUBSTR(t.MA5_NO, 1, 3) AS SMS_YEAR,
				SUBSTR(t.MA5_NO, 4, 4) AS SMS_CODE,
				SUBSTR(t.MA5_NO, 8, 6) AS SMS_NUMBER,
				'".mb_convert_encoding('其他(住址隱匿/代收代寄/手動)', 'BIG5', 'UTF-8')."' AS SMS_TYPE,
				t.MA5_SDATE AS SMS_DATE,
				t.MA5_STIME AS SMS_TIME,
				t.MA5_MP AS SMS_CELL,
				t.MA5_MID AS SMS_MAIL,
				(CASE WHEN t.MA5_STATUS = '2' THEN 'S' ELSE t.MA5_STATUS END) AS SMS_RESULT,
				t.MA5_CONT AS SMS_CONTENT,
				t.API_SENDMSG AS SMS_APIMSG
			from MOICAS.SMS_MA05 t
			where 1=1
				and (
					t.MA5_SDATE BETWEEN :bv_st AND :bv_ed
				)
			order by t.MA5_SDATE desc, t.MA5_STIME desc
		");
		$this->db_wrapper->getDB()->bind(":bv_st", $st);
		$this->db_wrapper->getDB()->bind(":bv_ed", $ed);
		$this->db_wrapper->getDB()->execute();
		return $this->db_wrapper->getDB()->fetchAll();
	}
	/**
	 * Find failure data and insert into MOIADM.SMSWAIT table by date.
	 */
	public function resendFailureMessageByDate($tw_date) {
		if (!$this->db_wrapper->reachable()) {
			return array();
		}
		Logger::getInstance()->info(__METHOD__.": 取得 $tw_date MOIADM.SMSLOG 裡失敗資料並插入重送佇列 MOIADM.SMSWAIT。");
		$this->db_wrapper->getDB()->parse("
			INSERT INTO SSMSWAIT
			(MS03,
			MS04_1,
			MS04_2,
			MS07_1,
			MS07_2,
			MS30,
			MS_TYPE,
			MS14,
			MS_NOTE,
			MS32)
			SELECT MS03,
						MS04_1,
						MS04_2,
						(select to_char(sysdate, 'YYYYMMDD') - 19110000 from dual),
						(select to_char(sysdate, 'HH24MISS') from dual),
						MS30,
						MS_TYPE,
						MS14,
						MS_NOTE,
						1
				FROM ssmslog A
			where ms07_1 = :bv_date
				and MS_TYPE = 'M'
				AND MS31 = 'F'
				AND MS14 IS NOT NULL
				AND NOT EXISTS (SELECT 'X'
								FROM ssmslog B
							WHERE B.MS03 = A.MS03
								AND B.MS04_1 = A.MS04_1
								AND B.MS04_2 = A.MS04_2
								AND B.MS30 = A.MS30
								AND B.MS_TYPE = A.MS_TYPE
								AND B.MS14 = A.MS14
								AND B.MS31 = 'S')
			ORDER BY MS14, MS07_1, MS07_2
		");
		$this->db_wrapper->getDB()->bind(":bv_date", $tw_date);
		$result = $this->db_wrapper->getDB()->execute() === FALSE ? false : true;
		Logger::getInstance()->info(__METHOD__.": 插入重送佇列 ".$result ? "成功" : "失敗"."。");
		return $result;
	}
}
