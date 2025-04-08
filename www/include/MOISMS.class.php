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
	 * 使用晶茂在 webapp 裡的 /message/* API去連線到局端送111簡訊(測試中...)
	 */
	public function sendToMoi(string $tel, string $subject, string $msg): bool {
    $ret = true;
    $success = "0";

    try {
        // STEP ONE 登入
        $ch = curl_init("http://" . getMA0_LINE() . "/message/apisend/login.jsp");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
            'userid' => getMA0_ID(),
            'password' => getMA0_PWD(),
        ]));
        curl_setopt($ch, CURLOPT_TIMEOUT, 4); // 4 秒 timeout

        $res = curl_exec($ch);

        if (curl_errno($ch)) {
            error_log("cURL Error: " . curl_error($ch));
            $ret = false;
        } else if (!str_starts_with($res, "0: success!")) {
            error_log("STEP ONE 登入-發送失敗:" . $tel . ":" . $res);
            $i = strpos($res, ":");
            $apiResult = trim(substr($res, $i + 1));
            setAPI_SENDMSG($apiResult);
            $ret = false;
        }

        curl_close($ch);

        if ($ret) {
            // STEP TWO 指定訊息接收者
            $ch = curl_init("http://" . getMA0_LINE() . "/message/apisend/receiver_sms.jsp");
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
                'receiver' => $tel,
            ]));
            curl_setopt($ch, CURLOPT_TIMEOUT, 4);

            $res = curl_exec($ch);

            if (curl_errno($ch)) {
                error_log("cURL Error: " . curl_error($ch));
                $ret = false;
            } else if (!str_starts_with($res, "0: success!")) {
                error_log("STEP TWO 指定訊息接收者-發送失敗:" . $tel . ":" . $res);
                $i = strpos($res, ":");
                $apiResult = trim(substr($res, $i + 1));
                setAPI_SENDMSG($apiResult);
                $ret = false;
            }

            curl_close($ch);
        }

        if ($ret) {
            // STEP THREE 指定訊息標題,訊息內容,傳送形式
            $ch = curl_init("http://" . getMA0_LINE() . "/message/apisend/sendmessage.jsp");
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
                'subject' => base64_encode($subject),
                'messagebody' => base64_encode($msg),
                'sendtype' => "0",
            ]));
            curl_setopt($ch, CURLOPT_TIMEOUT, 4);

            $res = curl_exec($ch);

            if (curl_errno($ch)) {
                error_log("cURL Error: " . curl_error($ch));
                $ret = false;
            } else if (!str_starts_with($res, "0: success!")) {
                error_log("STEP THREE 指定訊息標題,訊息內容,傳送形式-發送失敗:" . $tel . ":" . $res);
                $i = strpos($res, ":");
                $apiResult = trim(substr($res, $i + 1));
                setAPI_SENDMSG($apiResult);
                $ret = false;
            }

            curl_close($ch);
        }

        if ($ret) {
            // STEP FOUR 發送訊息
            $ch = curl_init("http://" . getMA0_LINE() . "/message/apisend/send.jsp");
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 4);

            $res = curl_exec($ch);

            if (curl_errno($ch)) {
                error_log("cURL Error: " . curl_error($ch));
                $ret = false;
            } else if (!str_starts_with($res, "0: success!")) {
                error_log("STEP FOUR 發送訊息-發送失敗:" . $tel . ":" . $res);
                $i = strpos($res, ":");
                $apiResult = trim(substr($res, $i + 1));
                setAPI_SENDMSG($apiResult);
                $ret = false;
            } else {
                error_log("發送成功:" . $tel . ":" . $res);
            }

            curl_close($ch);
        }

    } catch (Exception $ex) {
        error_log("非預期-發送失敗:" . $tel);
        error_log($ex->getMessage());
        $ret = false;
    }

    return $ret;
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
	 * 地籍異動即時通/指定送達處所 使用這個表格紀錄
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
					 WHEN t.MS_TYPE = 'Z' THEN '".mb_convert_encoding('智慧控管系統', 'BIG5', 'UTF-8')."'
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
	 * 看起來是簡訊建檔功能(H0CAS027)使用的表格
	 * 資料看起來多是寄送跨域代收案件通知申請人使用
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
				(CASE
					WHEN t.MA4_CONT LIKE '%".mb_convert_encoding('隱匿', 'BIG5', 'UTF-8')."%' THEN '".mb_convert_encoding('住址隱匿', 'BIG5', 'UTF-8')."'
					WHEN t.MA4_CONT LIKE '%".mb_convert_encoding('跨縣市', 'BIG5', 'UTF-8')."%' THEN '".mb_convert_encoding('跨域代收代寄', 'BIG5', 'UTF-8')."'
					ELSE '".mb_convert_encoding('手動', 'BIG5', 'UTF-8')."'
				END) AS SMS_TYPE,
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
				(CASE
					WHEN t.MA4_CONT LIKE '%".mb_convert_encoding('隱匿', 'BIG5', 'UTF-8')."%' THEN '".mb_convert_encoding('住址隱匿', 'BIG5', 'UTF-8')."'
					WHEN t.MA4_CONT LIKE '%".mb_convert_encoding('跨縣市', 'BIG5', 'UTF-8')."%' THEN '".mb_convert_encoding('跨域代收代寄', 'BIG5', 'UTF-8')."'
					ELSE '".mb_convert_encoding('手動', 'BIG5', 'UTF-8')."'
				END) AS SMS_TYPE,
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
	 * 感覺上是 住址隱匿/代收代寄/手動建檔 使用
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
				(CASE
					WHEN t.MA5_CONT LIKE '%".mb_convert_encoding('隱匿', 'BIG5', 'UTF-8')."%' THEN '".mb_convert_encoding('住址隱匿', 'BIG5', 'UTF-8')."'
					WHEN t.MA5_CONT LIKE '%".mb_convert_encoding('跨縣市', 'BIG5', 'UTF-8')."%' THEN '".mb_convert_encoding('跨域代收代寄', 'BIG5', 'UTF-8')."'
					ELSE '".mb_convert_encoding('手動', 'BIG5', 'UTF-8')."'
				END) AS SMS_TYPE,
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
				(CASE
					WHEN t.MA5_CONT LIKE '%".mb_convert_encoding('隱匿', 'BIG5', 'UTF-8')."%' THEN '".mb_convert_encoding('住址隱匿', 'BIG5', 'UTF-8')."'
					WHEN t.MA5_CONT LIKE '%".mb_convert_encoding('跨縣市', 'BIG5', 'UTF-8')."%' THEN '".mb_convert_encoding('跨域代收代寄', 'BIG5', 'UTF-8')."'
					ELSE '".mb_convert_encoding('手動', 'BIG5', 'UTF-8')."'
				END) AS SMS_TYPE,
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
	 * Find MOIADM.SMSLOG faulure records by date
	 */
	public function getMOIADMSMSLOGFailureRecordsByDate($tw_date) {
		if (!$this->db_wrapper->reachable()) {
			return array();
		}
		Logger::getInstance()->info(__METHOD__.': 取得 MOIADM SMSLOG 失敗資料 BY '.$tw_date.'。');
		$this->db_wrapper->getDB()->parse("
			SELECT *
				FROM MOIADM.SMSLOG A
		 WHERE MS07_1 = :bv_date
				AND MS_TYPE = 'M'
				AND MS31 = 'F'
				AND MS14 IS NOT NULL
				AND NOT EXISTS (SELECT 'X'
								FROM MOIADM.SMSLOG B
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
		$this->db_wrapper->getDB()->execute();
		$rows = $this->db_wrapper->getDB()->fetchAll();
		Logger::getInstance()->info(__METHOD__.': '.$tw_date.' 取得 '.count($rows).' 筆失敗資料 BY 。');
		return $rows;
	}
	/**
	 * Find failure data and insert into MOIADM.SMSWAIT table by date.
	 */
	public function resendMOIADMSMSFailureRecordsByDate($tw_date) {
		if (!$this->db_wrapper->reachable()) {
			return array();
		}
		Logger::getInstance()->info(__METHOD__.": 取得 $tw_date MOIADM.SMSLOG 裡失敗資料並插入等待佇列 MOIADM.SMSWAIT。");
		$this->db_wrapper->getDB()->parse("
			INSERT INTO MOIADM.SMSWAIT
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
				FROM MOIADM.SMSLOG A
			where ms07_1 = :bv_date
				and MS_TYPE = 'M'
				AND MS31 = 'F'
				AND MS14 IS NOT NULL
				AND NOT EXISTS (SELECT 'X'
								FROM MOIADM.SMSLOG B
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
		Logger::getInstance()->info(__METHOD__.": 插入等待佇列 ".($result ? "成功" : "失敗")."。");
		return $result;
	}
	/**
	 * insert into MOIADM.SMSWAIT table maunally
	 * expect param array
	 * array(
	 *   "MS30" => '0',
	 *   "MS_NOTE" => '',
	 *   "MS14" => '09XXXXXXXX',
	 *   "MS03" => '114',
	 *   "MS04_1" => 'HA81',
	 *   "MS04_2" => '000000'
	 * )
	 */
	public function resendMOIADMSMSRecord($record) {
		if (!$this->db_wrapper->reachable()) {
			return array();
		}
		Logger::getInstance()->info(__METHOD__.": 插入等待佇列 MOIADM.SMSWAIT 以利後續人工發送簡訊。");
		$this->db_wrapper->getDB()->parse("
			INSERT INTO MOIADM.SMSWAIT (MS03, MS04_1, MS04_2, MS07_1, MS07_2, MS30, MS_TYPE, MS14, MS_NOTE, MS32)
			VALUES (
					:bv_ms03,
					:bv_ms04_1,
					:bv_ms04_2,
					TO_CHAR(SYSDATE, 'YYYYMMDD') - 19110000,
					TO_CHAR(SYSDATE, 'HH24MISS'),
					:bv_ms30,
					'M',
					:bv_ms14,
					:bv_ms_note,
					1
			)
		");
		$this->db_wrapper->getDB()->bind(":bv_ms03", $record['MS03']);
		$this->db_wrapper->getDB()->bind(":bv_ms04_1", $record['MS04_1']);
		$this->db_wrapper->getDB()->bind(":bv_ms04_2", $record['MS04_2']);
		/**
		 * MS30:
		 * 0：待傳送
		 * I：補正
		 * D：駁回
		 * C：延期複丈
		 * F：結案
		 */	
		$this->db_wrapper->getDB()->bind(":bv_ms30", '0');
		$this->db_wrapper->getDB()->bind(":bv_ms14", $record['MS14']);
		$this->db_wrapper->getDB()->bind(":bv_ms_note", $record['MS_NOTE']);
		$result = $this->db_wrapper->getDB()->execute() === FALSE ? false : true;
		Logger::getInstance()->info(__METHOD__.": 插入等待佇列 ".($result ? "成功" : "失敗")."。");
		return $result;
	}
	/**
	 * 查詢最新 MA5_NO 序號
	 */
	public function getNextMA5_NO() {
		if (!$this->db_wrapper->reachable()) {
			return false;
		}
		global $today;
		$next_no = $today.'000001';
		$this->db_wrapper->getDB()->parse("
			SELECT (MAX(MA5_NO) + 1) AS NEXT_NO
			FROM MOICAS.SMS_MA05
			WHERE MA5_NO LIKE '".$today."%'
		");
		$this->db_wrapper->getDB()->execute();
		$row = $this->db_wrapper->getDB()->fetch();
		if (!empty($row) && !empty($row['NEXT_NO'])) {
			$next_no = $row['NEXT_NO'];
		}
		Logger::getInstance()->info(__METHOD__.": 下一個序號是 $next_no 。");
		return $next_no;
	}
	/**
	 * 使用 MOICAS.SMS_MA05 來傳送手動建檔訊息
	 * MA5_TYPE: 0 👉 immediately, 1 👉 reserved
	 * MA5_STATUS: 1 👉 READY, 2 👉 OK, 3 👉 RETRY LIMIT REACHED, 4 👉 RETRY
	 */
	public function manualSendSMS($cell, $cont, $name = __METHOD__) {
		if (empty($cont)) {
			Logger::getInstance()->warning(__METHOD__.": 無內容，無法傳送簡訊");
			return false;
		}

    // 移除所有非數字字元
    $cell = preg_replace('/[^0-9]/', '', $cell);
		if (strlen($cell) !== 10 || substr($cell, 0, 2) !== '09' || !ctype_digit(substr($cell, 2))) {
			Logger::getInstance()->warning(__METHOD__.": $cell 非正確手機號碼格式，無法傳送簡訊");
			return false;
		}

		if (!$this->db_wrapper->reachable()) {
			return false;
		}

		Logger::getInstance()->info(__METHOD__.": 插入 MOICAS.SMS_MA05 以利人工發送簡訊。");
		$next_no = $this->getNextMA5_NO();
		$this->db_wrapper->getDB()->parse("
			INSERT INTO MOICAS.SMS_MA05
				(MA5_NO,
				MA5_MID,
				MA5_CDATE,
				MA5_CTIME,
				MA5_NAME,
				MA5_MP,
				MA5_CONT,
				MA5_TYPE,
				MA5_STATUS,
				EDITID,
				EDITDATE,
				EDITTIME)
			VALUES (
			  '".$next_no."',
				'MOISMS-API',
				TO_CHAR(SYSDATE, 'YYYYMMDD') - 19110000,
				TO_CHAR(SYSDATE, 'HH24MISS'),
				:bv_ma5_name,
				:bv_ma5_mp,
				:bv_ma5_cont,
				'0',
				'1',
				'MOISMS-API',
				TO_CHAR(SYSDATE, 'YYYYMMDD') - 19110000,
				TO_CHAR(SYSDATE, 'HH24MISS')
			)
		");
		$this->db_wrapper->getDB()->bind(":bv_ma5_no", $next_no);
		$this->db_wrapper->getDB()->bind(":bv_ma5_name", mb_convert_encoding($name, 'BIG5', 'UTF-8'));
		$this->db_wrapper->getDB()->bind(":bv_ma5_mp", $cell);
		$this->db_wrapper->getDB()->bind(":bv_ma5_cont", mb_convert_encoding($cont, 'BIG5', 'UTF-8'));
		$result = $this->db_wrapper->getDB()->execute() === FALSE ? false : true;
		Logger::getInstance()->info(__METHOD__.": 即時簡訊插入 MOICAS.SMS_MA05 ($next_no) ".($result ? "成功" : "失敗")."。");
		return $result;
	}
	/**
	 * 使用 MOICAS.SMS_MA05 來傳送手動預約建檔訊息
	 * MA5_TYPE: 0 👉 immediately, 1 👉 reserved
	 * MA5_RDATE: MA5_TYPE = 1 時必填
	 * MA5_RTIME: MA5_TYPE = 1 時必填
	 * MA5_STATUS: 1 👉 READY, 2 👉 OK, 3 👉 RETRY LIMIT REACHED, 4 👉 RETRY
	 */
	public function manualSendBookingSMS($cell, $cont, $rdate, $rtime, $name = __METHOD__) {
		if(!isValidTaiwanDate($rdate) || !isValidTime($rtime)) {
			Logger::getInstance()->warning(__METHOD__.": 沒有正確的預約日期時間，改為直接發送簡訊。");
			return $this->manualSendSMS($cell, $cont, $name);
		}

		$tmp_date = timestampToDate(time(), 'TW');
		$parts = explode(' ', $tmp_date);
		$today = preg_replace('/[^0-9]/', '', $parts[0]);
		$now = preg_replace('/[^0-9]/', '', $parts[1]);
		if ($rdate < $today || ($rdate == $today && $rtime < $now)) {
			Logger::getInstance()->warning(__METHOD__.": 預約日期時間已過，改為直接發送簡訊。");
			return $this->manualSendSMS($cell, $cont, $name);
		}

		if (empty($cont)) {
			Logger::getInstance()->warning(__METHOD__.": 無內容，無法傳送簡訊");
			return false;
		}

    // 移除所有非數字字元
    $cell = preg_replace('/[^0-9]/', '', $cell);
		if (strlen($cell) !== 10 || substr($cell, 0, 2) !== '09' || !ctype_digit(substr($cell, 2))) {
			Logger::getInstance()->warning(__METHOD__.": $cell 非正確手機號碼格式，無法傳送簡訊");
			return false;
		}

		if (!$this->db_wrapper->reachable()) {
			return false;
		}

		Logger::getInstance()->info(__METHOD__.": 插入 MOICAS.SMS_MA05 以利人工發送預約簡訊 $rdate $rtime 。");
		$next_no = $this->getNextMA5_NO();
		$this->db_wrapper->getDB()->parse("
			INSERT INTO MOICAS.SMS_MA05
				(MA5_NO,
				MA5_MID,
				MA5_CDATE,
				MA5_CTIME,
				MA5_NAME,
				MA5_MP,
				MA5_CONT,
				MA5_TYPE,
				MA5_RDATE,
				MA5_RTIME,
				MA5_STATUS,
				EDITID,
				EDITDATE,
				EDITTIME)
			VALUES (
			  '".$next_no."',
				'MOISMS-API',
				TO_CHAR(SYSDATE, 'YYYYMMDD') - 19110000,
				TO_CHAR(SYSDATE, 'HH24MISS'),
				:bv_ma5_name,
				:bv_ma5_mp,
				:bv_ma5_cont,
				'1',
				:bv_ma5_rdate,
				:bv_ma5_rtime,
				'1',
				'MOISMS-API',
				TO_CHAR(SYSDATE, 'YYYYMMDD') - 19110000,
				TO_CHAR(SYSDATE, 'HH24MISS')
			)
		");
		// $this->db_wrapper->getDB()->bind(":bv_ma5_no", $next_no);
		$this->db_wrapper->getDB()->bind(":bv_ma5_name", mb_convert_encoding($name, 'BIG5', 'UTF-8'));
		$this->db_wrapper->getDB()->bind(":bv_ma5_mp", $cell);
		$this->db_wrapper->getDB()->bind(":bv_ma5_cont", mb_convert_encoding($cont, 'BIG5', 'UTF-8'));
		$this->db_wrapper->getDB()->bind(":bv_ma5_rdate", $rdate);
		$this->db_wrapper->getDB()->bind(":bv_ma5_rtime", $rtime);
		$result = $this->db_wrapper->getDB()->execute() === FALSE ? false : true;
		Logger::getInstance()->info(__METHOD__.": 預約簡訊 $rdate $rtime 插入 MOICAS.SMS_MA05 ($next_no) ".($result ? "成功" : "失敗")."。");
		return $result;
	}

}
