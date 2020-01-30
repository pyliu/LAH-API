<?php
require_once("init.php");
require_once("OraDB.class.php");
require_once("RegCaseData.class.php");

class Query {

    private $db;

	private function checkPID($id) {
		if( !$id ) {
			return false;
		}
		$id = strtoupper(trim($id));
		// first char is Alphabet, second is [12ABCD], rest are digits
		if(!preg_match("/^[A-Z]{1}[12ABCD]{1}[[:digit:]]{8}$/i", $id)) {
			return false;
		}
		// key str
		$wd_str = "BAKJHGFEDCNMLVUTSRQPZWYX0000OI";
		$d1 = strpos($wd_str, $id[0]) % 10;
		$sum = 0;
		if($id[1] >= 'A') {
			// second char is not digit
			$id[1] = chr($id[1])-65;
		}
		for($ii = 1; $ii < 9; $ii++) {
			$sum += (int)$id[$ii] * (9-$ii);
		}
		$sum += $d1 + (int)$id[9];
		if($sum%10 != 0) {
			return false;
		}
		return true;
	}

	private function checkCaseID($id) {
		if (empty($id) || !preg_match("/^[0-9A-Za-z]{13}$/i", $id)) {
            return false;
		}
		return true;
	}

    function __construct() {
        $this->db = new OraDB();
    }

    function __destruct() {
        $this->db = null;
    }

	public function getSectionRALIDCount($cond = "") {
		$prefix = "
			select m.KCDE_2 as \"段代碼\",
				m.KCNT as \"段名稱\",
				SUM(t.AA10) as \"面積\",
				COUNT(t.AA10) as \"土地標示部筆數\"
			FROM MOIADM.RKEYN m
			LEFT JOIN MOICAD.RALID t on m.KCDE_2 = t.AA48 -- 段小段面積計算 (RALID 登記－土地標示部)
			WHERE m.KCDE_1 = '48'
		";
		if (is_numeric($cond)) {
			$prefix .= "AND m.KCDE_2 LIKE '_' || :bv_cond";
		} else if (!empty($cond)) {
			$prefix .= "AND m.KCNT LIKE '%' || :bv_cond || '%'";
		}
		$postfix = "
			GROUP BY m.KCDE_2, m.KCNT
		";
		$this->db->parse($prefix.$postfix);
		if (!empty($cond)) {
			$this->db->bind(":bv_cond", iconv("utf-8", "big5", $cond));
		}
		$this->db->execute();
		return $this->db->fetchAll();
	}
	
	public function getProblematicCrossCases() {
		global $week_ago;
		$this->db->parse("SELECT * FROM SCRSMS WHERE RM07_1 >= :bv_week_ago AND RM02 LIKE 'H%1' AND (RM99 is NULL OR RM100 is NULL OR RM100_1 is NULL OR RM101 is NULL OR RM101_1 is NULL)");
		$this->db->bind(":bv_week_ago", $week_ago);
        $this->db->execute();
        return $this->db->fetchAll();
	}

	public function fixProblematicCrossCases($id) {
		if (!$this->checkCaseID($id)) {
            return false;
		}
		
		$this->db->parse("
			UPDATE MOICAS.CRSMS SET RM99 = 'Y', RM100 = :bv_hold_code, RM100_1 = :bv_county_code, RM101 = :bv_receive_code, RM101_1 = :bv_county_code
			WHERE RM01 = :bv_rm01_year AND RM02 = :bv_rm02_code AND RM03 = :bv_rm03_number
		");

		$code = substr($id, 3, 4);
		$this->db->bind(":bv_rm01_year", substr($id, 0, 3));
        $this->db->bind(":bv_rm02_code", $code);
		$this->db->bind(":bv_rm03_number", substr($id, 7, 6));
		$this->db->bind(":bv_county_code", $code[0]);
		$this->db->bind(":bv_hold_code", $code[0].$code[1]);
		$this->db->bind(":bv_receive_code", $code[0].$code[2]);
		// UPDATE/INSERT can not use fetch after execute ... 
		$this->db->execute();
		return true;
	}
	
	public function getMaxNumByYearWord($year, $code) {
		if (!filter_var($year, FILTER_SANITIZE_NUMBER_INT)) {
			return false;
		}

		if (array_key_exists($code, SUR_WORD)) {
			$this->db->parse("
				SELECT * from MOICAS.CMSMS t
				WHERE MM01 = :bv_year AND MM02 = :bv_code AND rownum = 1
				ORDER BY MM01 DESC, MM03 DESC
			");
		} else {
			$this->db->parse("
				SELECT * from MOICAS.CRSMS t
				WHERE RM01 = :bv_year AND RM02 = :bv_code AND rownum = 1
				ORDER BY RM01 DESC, RM03 DESC
			");
		}
		$this->db->bind(":bv_year", $year);
		$this->db->bind(":bv_code", trim($code));
		$this->db->execute();
		$row = $this->db->fetch();
		return empty($row) ? "0" : ltrim(array_key_exists($code, SUR_WORD) ? $row["MM03"] : $row["RM03"], "0");
	}

	public function getCRSMSCasesByID($id) {
		$id = strtoupper($id);
        if (!$this->checkPID($id)) {
            return false;
		}
		$this->db->parse("
			SELECT t.*
			FROM MOICAS.CRSMS t
			WHERE t.RM18 = :bv_id
			OR t.RM21 = :bv_id
			OR t.RM24 = :bv_id
			OR t.RM25 = :bv_id
        ");
		$this->db->bind(":bv_id", $id);
		$this->db->execute();
		return $this->db->fetchAll();
	}

	public function getCMSMSCasesByID($id) {
		$id = strtoupper($id);
		if (!$this->checkPID($id)) {
            return false;
		}
		$this->db->parse("
			SELECT t.*
			FROM MOICAS.CMSMS t
			WHERE t.MM13 = :bv_id
			OR t.MM17_1 = :bv_id
			OR t.MM17_2 = :bv_id
        ");
		$this->db->bind(":bv_id", $id);
		$this->db->execute();
		return $this->db->fetchAll();
	}

	public function getEasycardPayment($qday) {
		/*
			"K01","K02","K03","K04"
			"01","現金","N","N"
			"02","支票","N","N"
			"03","匯票","Y","N"
			"04","iBon","Y","Y"
			"05","ATM","N","N"
			"06","悠遊卡","N","N"
			"07","其他匯款","N","N"
			"08","信用卡","N","N"
			"09","行動支付","N","N"
		*/
		if (empty($qday)) {
			global $week_ago;
			// fetch all data wthin a week back
			$this->db->parse("
				SELECT * FROM MOIEXP.EXPAA WHERE AA01 >= :bv_qday AND AA106 <> '1' AND AA100 = '06'
			");
			$this->db->bind(":bv_qday", $week_ago);
		} else {
			if (!filter_var($qday, FILTER_SANITIZE_NUMBER_INT)) {
            	return false;
			}
			$this->db->parse("
				SELECT * FROM MOIEXP.EXPAA WHERE AA01 = :bv_qday AND AA106 <> '1' AND AA100 = '06'
			");
			$this->db->bind(":bv_qday", $qday);
		}
		$this->db->execute();
		return $this->db->fetchAll();
	}

	public function fixEasycardPayment($qday, $pc_num) {
		// ex: UPDATE MOIEXP.EXPAA SET AA106 = '1' WHERE AA01 = '1080321' AND AA106 <> '1' AND AA100 = '06' AND AA04 = '0015746';
		$this->db->parse("
			UPDATE MOIEXP.EXPAA SET AA106 = '1' WHERE AA01 = :bv_qday AND AA106 <> '1' AND AA100 = '06' AND AA04 = :bv_pc_num
		");
		$this->db->bind(":bv_qday", $qday);
		$this->db->bind(":bv_pc_num", $pc_num);
		// UPDATE/INSERT can not use fetch after execute ... 
		$this->db->execute();
		return true;
	}

	public function getExpacItems($year, $num) {
		/*
			01	土地法65條登記費
			02	土地法76條登記費
			03	土地法67條書狀費
			04	地籍謄本工本費
			06	檔案閱覽抄錄複製費
			07	閱覽費
			08	門牌查詢費
			09	複丈費及建物測量費
			10	地目變更勘查費
			14	電子謄本列印
			18	塑膠樁土地界標
			19	鋼釘土地界標(大)
			30	104年度登記罰鍰
			31	100年度登記罰鍰
			32	101年度登記罰鍰
			33	102年度登記罰鍰
			34	103年度登記罰鍰
			35	其他
			36	鋼釘土地界標(小)
			37	105年度登記罰鍰
			38	106年度登記罰鍰
			39	塑膠樁土地界標(大)
			40	107年度登記罰鍰
			41	108年度登記罰鍰
		 */
		$this->db->parse("
			SELECT *
			FROM MOIEXP.EXPAC t
			LEFT JOIN MOIEXP.EXPE p
				ON p.E20 = t.AC20
			WHERE t.AC04 = :bv_num AND t.AC25 = :bv_year
        ");
		$this->db->bind(":bv_year", $year);
		$this->db->bind(":bv_num", $num);
		$this->db->execute();
		return $this->db->fetchAll();
	}

	public function modifyExpacItem($year, $num, $code, $amount) {
		// ex: UPDATE MOIEXP.EXPAC SET AC20 = '35' WHERE AC04 = '0021131' AND AC25 = '108' AND AC30 = '280';
		$this->db->parse("
			UPDATE MOIEXP.EXPAC SET AC20 = :bv_code WHERE AC04 = :bv_pc_num AND AC25 = :bv_year AND AC30 = :bv_amount
		");
		$this->db->bind(":bv_year", $year);
		$this->db->bind(":bv_pc_num", $num);
		$this->db->bind(":bv_code", $code);
		$this->db->bind(":bv_amount", $amount);
		// UPDATE/INSERT can not use fetch after execute ... 
		$this->db->execute();
		return true;
	}

	public function getExpaaData($qday, $num) {
		if (empty($num)) {
			$this->db->parse("
				SELECT *
				FROM MOIEXP.EXPAA t
				WHERE t.AA01 = :bv_qday
			");
		} else {
			$this->db->parse("
				SELECT *
				FROM MOIEXP.EXPAA t
				WHERE t.AA04 = :bv_num AND t.AA01 = :bv_qday
			");
			$this->db->bind(":bv_num", $num);
		}
		$this->db->bind(":bv_qday", $qday);
		$this->db->execute();
		return $this->db->fetchAll();
	}

	public function updateExpaaData($column, $date, $number, $update_val) {
		if (strlen($date) != 7 || strlen($number) != 7) {
			return false;
		}

		$this->db->parse("
			UPDATE MOIEXP.EXPAA SET ${column} = :bv_update_value WHERE AA01 = :bv_aa01 AND AA04 = :bv_aa04
		");

		$this->db->bind(":bv_update_value", $update_val);
		$this->db->bind(":bv_aa01", $date);
		$this->db->bind(":bv_aa04", $number);
		
		$this->db->execute();

		return true;
	}

	public function getDummyObFees() {
		$tw_date = new Datetime("now");
		$tw_date->modify("-1911 year");
		$this_year = ltrim($tw_date->format("Y"), "0");	// ex: 109

		// use '9' + year(3 digits) + '000' to stand for the obsolete fee application
		$this->db->parse("
			select * from MOIEXP.EXPAA t
			where aa04 like '9' || :bv_year || '%'
			order by AA01 desc, AA04 desc
		");
		$this->db->bind(":bv_year", $this_year);
		$this->db->execute();
		return $this->db->fetchAll();
	}

	public function addDummyObFees($date, $pc_num, $operator, $fee_number, $reason) {
		global $log;
		if (empty($date) || empty($pc_num) || empty($operator) || empty($fee_number) || empty($reason)) {
			$log->error(__METHOD__.": One of the parameters is empty. The system can not add obsolete fee expaa data.");
			$log->warning(__METHOD__.": The input params: ${date}, ${pc_num}, ${operator}, ${fee_number}, ${reason}.");
			return false;
		}

		$sql = "INSERT INTO MOIEXP.EXPAA (AA01,AA04,AA05,AA06,AA07,AA08,AA09,AA02,AA24,AA25,AA39,AA104) VALUES (:bv_date, :bv_pc_num, :bv_fee_num, '1', '0', '0', '1', :bv_date, :bv_date, :bv_year, :bv_operator, :bv_reason)";
		$this->db->parse($sql);
		$this->db->bind(":bv_date", $date);
		$this->db->bind(":bv_year", substr($date, 0, 3));
		$this->db->bind(":bv_pc_num", $pc_num);
		$this->db->bind(":bv_fee_num", $fee_number);
		$this->db->bind(":bv_operator", $operator);
		$this->db->bind(":bv_reason", iconv("utf-8", "big5", $reason));

		$log->info(__METHOD__.": 插入 SQL \"$sql\"");

		$this->db->execute();
		return true;
	}

	public function getForeignCasesByYearMonth($year_month) {
		if (!filter_var($year_month, FILTER_SANITIZE_NUMBER_INT)) {
            return false;
        }
		$this->db->parse("
			-- 每月權利人&義務人為外國人案件
			SELECT 
				SQ.RM01 AS \"收件年\",
				SQ.RM02 AS \"收件字\",
				SQ.RM03 AS \"收件號\",
				SQ.RM09 AS \"登記原因代碼\",
				k.KCNT AS \"登記原因\",
				SQ.RM07_1 AS \"收件日期\",
				SQ.RM58_1 AS \"結案日期\",
				SQ.RM18   AS \"權利人統一編號\",
				SQ.RM19   AS \"權利人姓名\",
				SQ.RM21   AS \"義務人統一編號\",
				SQ.RM22   AS \"義務人姓名\",
				SQ.RM30   AS \"辦理情形\",
				SQ.RM31   AS \"結案已否\"
			FROM (
				SELECT *
				FROM MOICAS.CRSMS c
				INNER JOIN MOICAD.RLNID r
				ON (c.RM18 = r.LIDN OR c.RM21 = r.LIDN)
				WHERE (
					-- RM58_1 結案時間
					c.RM58_1 LIKE :bv_year_month || '%'
					AND r.LCDE in ('2', '8')
			)) SQ LEFT JOIN MOICAD.RKEYN k on k.KCDE_2 = SQ.RM09
			WHERE k.KCDE_1 = '06'
		");
		$this->db->bind(":bv_year_month", $year_month);
		$this->db->execute();
		return $this->db->fetchAll();
	}

	public function getChargeItems() {
		$this->db->parse("select * from MOIEXP.EXPE t");
		$this->db->execute();
		$all = $this->db->fetchAll();
		$return_arr = array();
		foreach ($all as $row) {
			$return_arr[$row["E20"]] = iconv("big5", "utf-8", $row["E21"]);
		}
		return $return_arr;
	}

    // template method for query all cases by date
    public function queryAllCasesByDate($qday) {
        // only allow int number for $qday
        if (!filter_var($qday, FILTER_SANITIZE_NUMBER_INT)) {
            return false;
        }
        $this->db->parse("SELECT * FROM SCRSMS LEFT JOIN SRKEYN ON KCDE_1 = '06' AND RM09 = KCDE_2 WHERE RM07_1 BETWEEN :bv_qday and :bv_qday ORDER BY RM07_1, RM07_2 DESC");
        $this->db->bind(":bv_qday", $qday);
		$this->db->execute();
		return $this->db->fetchAll();
    }

	// 找15天之內的案件
	public function queryOverdueCasesIn15Days() {
		$this->db->parse("
			SELECT *
			FROM SCRSMS
			LEFT JOIN SRKEYN ON KCDE_1 = '06' AND RM09 = KCDE_2
			WHERE
				RM07_1 > :bv_start
				AND RM02 NOT LIKE 'HB%1'	-- only search our own cases
				AND RM03 LIKE '%0' 			-- without sub-case
				AND RM31 IS NULL			-- not closed case
				AND RM29_1 || RM29_2 < :bv_now
			ORDER BY RM07_1 DESC, RM07_2 DESC
		");

		$tw_date = new Datetime("now");
		$tw_date->modify("-1911 year");
		$now = ltrim($tw_date->format("YmdHis"), "0");	// ex: 1080325152111

		$date_15days_before = new Datetime("now");
		$date_15days_before->modify("-1911 year");
		$date_15days_before->modify("-15 days");
		$start = ltrim($date_15days_before->format("Ymd"), "0");	// ex: 1090107
		
		$this->db->bind(":bv_now", $now);
		$this->db->bind(":bv_start", $start);
		$this->db->execute();
		return $this->db->fetchAll();
	}

	// 查詢指定收件日期之案件
    public function queryOverdueCasesByDate($qday) {
        // only allow int number for $qday
        if (!filter_var($qday, FILTER_SANITIZE_NUMBER_INT)) {
            return false;
        }
		$this->db->parse("
			SELECT *
			FROM SCRSMS
			LEFT JOIN SRKEYN ON KCDE_1 = '06' AND RM09 = KCDE_2
			WHERE
				RM07_1 = :bv_qday AND 
				RM02 NOT LIKE 'HB%1'	-- only search our own cases
				AND RM03 LIKE '%0' 		-- without sub-case
				AND RM31 IS NULL
				AND RM29_1 || RM29_2 < :bv_qdatetime
			ORDER BY RM07_1, RM07_2 DESC
		");
		$this->db->bind(":bv_qday", $qday);
        $this->db->bind(":bv_qdatetime", $qday.date("His"));
		$this->db->execute();
		return $this->db->fetchAll();
	}
	
	// 找全部案件
	public function queryNearOverdueCases() {
		$this->db->parse("
			SELECT *
			FROM SCRSMS
			LEFT JOIN SRKEYN ON KCDE_1 = '06' AND RM09 = KCDE_2
			WHERE
				RM02 NOT LIKE 'HB%1'	-- only search our own cases
				AND RM31 IS NULL
				AND (RM29_1 || RM29_2 < :bv_4hours_later AND RM29_1 || RM29_2 > :bv_now)
			ORDER BY RM07_1, RM07_2 DESC
		");
		$tw_date = new Datetime("now");
		$tw_date->modify("-1911 year");
		$today = ltrim($tw_date->format("Ymd"), "0");	// ex: 1080325152111
		$this->db->bind(":bv_4hours_later", $today.date("His", strtotime("+4 hours")));
		$this->db->bind(":bv_now", $today.date("His"));
		$this->db->execute();
		return $this->db->fetchAll();
	}

	// 查詢指定收件日期之案件
	public function queryNearOverdueCasesByDate($qday) {
        // only allow int number for $qday
        if (!filter_var($qday, FILTER_SANITIZE_NUMBER_INT)) {
            return false;
        }
		$this->db->parse("
			SELECT *
			FROM SCRSMS
			LEFT JOIN SRKEYN ON KCDE_1 = '06' AND RM09 = KCDE_2
			WHERE
				RM07_1 = :bv_qday AND 
				RM02 NOT LIKE 'HB%1'
				AND RM31 IS NULL
				AND (RM29_1 || RM29_2 < :bv_4hours_later AND RM29_1 || RM29_2 > :bv_now)
			ORDER BY RM07_1, RM07_2 DESC
		");
		$this->db->bind(":bv_qday", $qday);
		$this->db->bind(":bv_4hours_later", $qday.date("His", strtotime("+4 hours")));
		$this->db->bind(":bv_now", $qday.date("His"));
		$this->db->execute();
		return $this->db->fetchAll();
	}

	public function getRegCaseStatsMonthly($year_month) {
		global $log;
        // only allow int number for $qday
        if (!filter_var($year_month, FILTER_SANITIZE_NUMBER_INT)) {
			$log->info(__METHOD__.": 不允許非數字參數【${year_month}】");
            return false;
        }
		if (strlen($year_month) != 5) {
			$log->info(__METHOD__.": 參數須為5碼【${year_month}】");
			return false;
		}
		
		$this->db->parse(
			"SELECT s.kcnt AS \"reason\", COUNT(*) AS \"count\"
			FROM SCRSMS t
			LEFT JOIN SRKEYN s
			  ON t.rm09 = s.kcde_2
			WHERE s.kcde_1 = '06'
			  AND RM07_1 LIKE :bv_year_month || '%'
			GROUP BY s.kcnt"
        );
        
		$this->db->bind(":bv_year_month", $year_month);
		$this->db->execute();
		return $this->db->fetchAll();
	}

    public function getRegCaseDetail($id) {
        if (!$this->checkCaseID($id)) {
            return "";
		}
		
		$this->db->parse(
			"SELECT s.*, u.KCNT AS RM11_CNT
			FROM (SELECT r.*, q.AB02
				FROM (
					SELECT t.*, m.KCNT 
					FROM MOICAS.CRSMS t
					LEFT JOIN MOIADM.RKEYN m ON (m.KCDE_1 = '06' AND t.RM09 = m.KCDE_2)
					WHERE t.RM01 = :bv_rm01_year and t.RM02 = :bv_rm02_code and t.RM03 = :bv_rm03_number
				) r
				LEFT JOIN MOICAS.CABRP q ON r.RM24 = q.AB01) s
			LEFT JOIN MOIADM.RKEYN u ON (u.KCDE_1 = '48' AND s.RM11 = u.KCDE_2)"
        );
        
        $this->db->bind(":bv_rm01_year", substr($id, 0, 3));
        $this->db->bind(":bv_rm02_code", substr($id, 3, 4));
        $this->db->bind(":bv_rm03_number", substr($id, 7, 6));

		$this->db->execute();
		// true -> raw data with converting to utf-8
		return $this->db->fetch();
	}

    public function getSurCaseDetail($id) {
        if (!$this->checkCaseID($id)) {
            return "";
		}
		
		$this->db->parse(
			"select t.*, s.*, u.KCNT
			from MOICAS.CMSMS t
			left join MOICAS.CMSDS s
			  on t.mm01 = s.md01
			 and t.mm02 = s.md02
			 and t.mm03 = s.md03
			left join MOIADM.RKEYN u
			  on t.mm06 = u.kcde_2
			 and u.kcde_1 = 'M3'
			where
			 t.mm01 = :bv_year
			 and t.mm02 = :bv_code
			 and t.mm03 = :bv_number"
        );
        
        $this->db->bind(":bv_year", substr($id, 0, 3));
        $this->db->bind(":bv_code", substr($id, 3, 4));
        $this->db->bind(":bv_number", substr($id, 7, 6));

		$this->db->execute();
		// true -> raw data with converting to utf-8
		return $this->db->fetch();
	}

	public function fixSurDelayCase($id, $upd_mm22, $clr_delay) {
		if (!$this->checkCaseID($id)) {
            return false;
		}

		$year = substr($id, 0, 3);
		$code = substr($id, 3, 4);
		$number = substr($id, 7, 6);
		
		if ($upd_mm22 == "true") {
			$this->db->parse("
				UPDATE MOICAS.CMSMS SET MM22 = 'D'
				WHERE MM01 = :bv_year AND MM02 = :bv_code AND MM03 = :bv_number
			");
			$this->db->bind(":bv_year", $year);
			$this->db->bind(":bv_code", $code);
			$this->db->bind(":bv_number", $number);
			// UPDATE/INSERT can not use fetch after execute ... 
			$this->db->execute();
		}

		if ($clr_delay == "true") {
			$this->db->parse("
				UPDATE MOICAS.CMSDS SET MD12 = '', MD13_1 = '', MD13_2 = ''
				WHERE MD01 = :bv_year AND MD02 = :bv_code AND MD03 = :bv_number
			");
			$this->db->bind(":bv_year", $year);
			$this->db->bind(":bv_code", $code);
			$this->db->bind(":bv_number", $number);
			// UPDATE/INSERT can not use fetch after execute ... 
			$this->db->execute();
		}

		return true;
	}

	public function getPrcCaseAll($id) {
        if (!$this->checkCaseID($id)) {
            return "";
		}

		$sql = "
			SELECT
				t.ss06 || '：' || q.kcnt AS \"SS06_M\",
				(CASE
					WHEN t.SP_CODE = 'B' THEN 'B：登記中'
					WHEN t.SP_CODE = 'R' THEN 'R：登錄完成'
					WHEN t.SP_CODE = 'D' THEN 'D：校對中'
					WHEN t.SP_CODE = 'C' THEN 'C：校對正確'
					WHEN t.SP_CODE = 'E' THEN 'E：校對有誤'
					WHEN t.SP_CODE = 'S' THEN 'S：異動開始'
					WHEN t.SP_CODE = 'G' THEN 'G：異動有誤'
					WHEN t.SP_CODE = 'F' THEN 'F：異動完成'
					ELSE t.SP_CODE
				END) AS \"SP_CODE_M\",
				t.sp_date || ' ' || t.sp_time AS \"SP_DATE_M\",
				(CASE
					WHEN t.SS100 = 'HA' THEN '桃園' 
					WHEN t.SS100 = 'HB' THEN '中壢' 
					WHEN t.SS100 = 'HC' THEN '大溪' 
					WHEN t.SS100 = 'HD' THEN '楊梅' 
					WHEN t.SS100 = 'HE' THEN '蘆竹' 
					WHEN t.SS100 = 'HF' THEN '八德' 
					WHEN t.SS100 = 'HG' THEN '平鎮' 
					WHEN t.SS100 = 'HH' THEN '龜山' 
					ELSE t.SS100
				END) AS \"SS100_M\",
				(CASE
					WHEN t.SS101 = 'HA' THEN '桃園' 
					WHEN t.SS101 = 'HB' THEN '中壢' 
					WHEN t.SS101 = 'HC' THEN '大溪' 
					WHEN t.SS101 = 'HD' THEN '楊梅' 
					WHEN t.SS101 = 'HE' THEN '蘆竹' 
					WHEN t.SS101 = 'HF' THEN '八德' 
					WHEN t.SS101 = 'HG' THEN '平鎮' 
					WHEN t.SS101 = 'HH' THEN '龜山' 
					ELSE t.SS101
				END) AS \"SS101_M\",
				t.*
			FROM MOIPRC.PSCRN t
			LEFT JOIN SRKEYN q ON t.SS06  = q.kcde_2 AND q.kcde_1 = '06'
			WHERE
				SS03 = :bv_ss03_year AND
				SS04_1 = :bv_ss04_1_code AND
				SS04_2 = :bv_ss04_2_number
		";
		$this->db->parse(iconv("utf-8", "big5", $sql));
		
        $this->db->bind(":bv_ss03_year", substr($id, 0, 3));
        $this->db->bind(":bv_ss04_1_code", substr($id, 3, 4));
        $this->db->bind(":bv_ss04_2_number", substr($id, 7, 6));

		$this->db->execute();
		return $this->db->fetchAll();
	}
	
	public function getXCaseDiff($id, $raw = false) {
        if (!$this->checkCaseID($id)) {
            return -1;
		}
		
		$diff_result = array();
		$year = substr($id, 0, 3);
		$code = substr($id, 3, 4);
		$num = substr($id, 7, 6);
		$db_user = "L1H".$code[1]."0H03";

		global $log;
		$log->info(__METHOD__.": 找遠端 ${db_user}.CRSMS 的案件資料【${year}, ${code}, ${num}】");

		// connection switch to L1HWEB
		$this->db->connect(CONNECTION_TYPE::L1HWEB);
		$this->db->parse("
			SELECT *
			FROM $db_user.CRSMS t
			WHERE RM01 = :bv_rm01_year AND RM02 = :bv_rm02_code AND RM03 = :bv_rm03_number
		");
        $this->db->bind(":bv_rm01_year", $year);
        $this->db->bind(":bv_rm02_code", $code);
        $this->db->bind(":bv_rm03_number", $num);
		$this->db->execute();
		$remote_row = $this->db->fetch($raw);

		// 遠端無此資料
		if (empty($remote_row)) {
			$log->warning(__METHOD__.": 遠端 ${db_user}.CRSMS 查無 ${year}-${code}-${num} 案件資料");
			return -2;
		}

		$log->info(__METHOD__.": 找本地 MOICAS.CRSMS 的案件資料【${year}, ${code}, ${num}】");

		// connection switch to MAIN
		$this->db->connect(CONNECTION_TYPE::MAIN);
		$this->db->parse("
			SELECT *
			FROM MOICAS.CRSMS t
			WHERE RM01 = :bv_rm01_year AND RM02 = :bv_rm02_code AND RM03 = :bv_rm03_number
		");
        $this->db->bind(":bv_rm01_year", $year);
        $this->db->bind(":bv_rm02_code", $code);
        $this->db->bind(":bv_rm03_number", $num);
		$this->db->execute();
		$local_row = $this->db->fetch($raw);

		// 本地無此資料
		if (empty($local_row)) {
			$log->warning(__METHOD__.": 本地 MOICAS.CRSMS 查無 ${year}-${code}-${num} 案件資料");
			return -3;
		}

		$colsNameMapping = include("Config.ColsNameMapping.CRSMS.php");
		// compare each column base on remote data
		foreach ($remote_row as $key => $value) {
			if ($value != $local_row[$key]) { // use == to get every column for testing
				$diff_result[$key] = array(
					"REMOTE" => $value,
					"LOCAL" => $local_row[$key],
					"TEXT" => $colsNameMapping[$key],
					"COLUMN" => $key
				);
			}
		}

		return $diff_result;
	}
	
	public function instXCase($id, $raw = true) {
		if (!$this->checkCaseID($id)) {
            return -1;
		}

		global $log;
		$year = substr($id, 0, 3);
		$code = substr($id, 3, 4);
		$num = substr($id, 7, 6);
		$db_user = "L1H".$code[1]."0H03";

		// connection switch to L1HWEB
		$this->db->connect(CONNECTION_TYPE::L1HWEB);
		$this->db->parse("
			SELECT *
			FROM $db_user.CRSMS t
			WHERE RM01 = :bv_rm01_year AND RM02 = :bv_rm02_code AND RM03 = :bv_rm03_number
		");
        $this->db->bind(":bv_rm01_year", $year);
        $this->db->bind(":bv_rm02_code", $code);
        $this->db->bind(":bv_rm03_number", $num);
		$this->db->execute();
		$remote_row = $this->db->fetch($raw);

		// 遠端無此資料
		if (empty($remote_row)) {
			$log->warning(__METHOD__.": 遠端 ${db_user}.CRSMS 查無 ${year}-${code}-${num} 案件資料");
			return -2;
		}

		// connection switch to MAIN
		$this->db->connect(CONNECTION_TYPE::MAIN);
		$this->db->parse("
			SELECT *
			FROM MOICAS.CRSMS t
			WHERE RM01 = :bv_rm01_year AND RM02 = :bv_rm02_code AND RM03 = :bv_rm03_number
		");
        $this->db->bind(":bv_rm01_year", $year);
        $this->db->bind(":bv_rm02_code", $code);
        $this->db->bind(":bv_rm03_number", $num);
		$this->db->execute();
		$local_row = $this->db->fetch($raw);

		// 本地無此資料才能新增
		if (empty($local_row)) {
			// 使用遠端資料新增本所資料
			$remote_row;
			$columns = "(";
			$values = "(";
			foreach ($remote_row as $key => $value) {
				$columns .= $key.",";
				$values .= "'".($raw ? $value : iconv("utf-8", "big5", $value))."',";
			}
			$columns = rtrim($columns, ",").")";
			$values = rtrim($values, ",").")";

			$this->db->parse("
				INSERT INTO MOICAS.CRSMS ".$columns." VALUES ".$values."
			");

			$log->info(__METHOD__.": 插入 SQL \"INSERT INTO MOICAS.CRSMS ".$columns." VALUES ".$values."\"");
			$this->db->execute();

			return true;
		}
		$log->error(__METHOD__.": 本地 MOICAS.CRSMS 已有 ${year}-${code}-${num} 案件資料");
		return false;
	}

	public function syncXCase($id) {
		return $this->syncXCaseColumn($id, "");
	}

	public function syncXCaseColumn($id, $wanted_column) {
		$diff = $this->getXCaseDiff($id, true);	// true -> use raw data to update
		if (!empty($diff)) {
			global $log;
			$year = substr($id, 0, 3);
			$code = substr($id, 3, 4);
			$number = substr($id, 7, 6);

			$set_str = "";
			foreach ($diff as $col_name => $arr_vals) {
				if (!empty($wanted_column) && $col_name != $wanted_column) {
					continue;
				}
				$set_str .= $col_name." = '".$arr_vals["REMOTE"]."',";
			}
			$set_str = rtrim($set_str, ",");

			$this->db->parse("
				UPDATE MOICAS.CRSMS SET ".$set_str." WHERE RM01 = :bv_rm01_year AND RM02 = :bv_rm02_code AND RM03 = :bv_rm03_number
			");

			$this->db->bind(":bv_rm01_year", $year);
			$this->db->bind(":bv_rm02_code", $code);
			$this->db->bind(":bv_rm03_number", $number);

			$log->info(__METHOD__.": 更新 SQL \"UPDATE MOICAS.CRSMS SET ".$set_str." WHERE RM01 = '$year' AND RM02 = '$code' AND RM03 = '$number'\"");

			$this->db->execute();

			return true;
		}
		return false;
	}
	
	public function getSelectSQLData($sql, $raw = false) {
		global $log;
		// non-select statement will skip
		if (!preg_match("/^SELECT/i", $sql)) {
			$log->error(__METHOD__.": 非SELECT起始之SQL!");
			$log->error(__METHOD__.": $sql");
			return false;
		}
		// second defense line
		if (preg_match("/^.*(INSERT|DELETE|UPDATE)/i", $sql)) {
			$log->error(__METHOD__.": 不能使用非SELECT起始之SQL!");
			$log->error(__METHOD__.": $sql");
			return false;
		}

		$this->db->parse(mb_convert_encoding(rtrim($sql, ";"), "big5", "utf-8"));
		$this->db->execute();
		return $this->db->fetchAll($raw);
	}
	
	public function getAnnouncementData() {
		$this->db->parse("
			SELECT t.RA01, m.kcnt, t.RA02, t.RA03
			FROM MOICAS.CRACD t
			LEFT JOIN MOIADM.RKEYN m
			ON t.RA01 = m.kcde_2
			WHERE m.kcde_1 = '06'
			ORDER BY RA01
		");
		$this->db->execute();
		return $this->db->fetchAll();
	}

	public function updateAnnouncementData($code, $day, $flag) {
		$this->db->parse("
			UPDATE MOICAS.CRACD SET RA02 = :bv_ra02_day, RA03 = :bv_ra03_flag WHERE RA01 = :bv_ra01_code
		");

		$this->db->bind(":bv_ra01_code", $code);
		$this->db->bind(":bv_ra02_day", $day);
		$this->db->bind(":bv_ra03_flag", $flag == "Y" ? $flag : "N");
		
		$this->db->execute();
		return true;
	}

	public function clearAnnouncementFlag() {
		$this->db->parse("
			UPDATE MOICAS.CRACD SET RA03 = 'N' WHERE 1 = 1
		");
		$this->db->execute();
		return true;
	}

	public function getCaseTemp($year, $code, $number) {
		$result = array();
		if (empty($year) || empty($code) || empty($number)) {
			return $result;
		}

		global $log;

		// an array to express temp tables and key field names that need to be checked.
		$temp_tables = include("Config.TempTables.php");
		//foreach ($temp_tables as $tmp_tbl_name => $key_fields) {
		foreach ($temp_tables as $tmp_tbl_name => $key) {
			/*
			$log->info(__METHOD__.": 查詢 $tmp_tbl_name 的暫存資料 ... 【".$key_fields[0].", ".$key_fields[1].", ".$key_fields[2]."】");
			$this->db->parse("
				SELECT * FROM ".$tmp_tbl_name." WHERE ".$key_fields[0]." = :bv_year AND ".$key_fields[1]." = :bv_code AND ".$key_fields[2]." = :bv_number
			");
			*/
			$log->info(__METHOD__.": 查詢 $tmp_tbl_name 的暫存資料 ... 【".$key."03, ".$key."04_1, ".$key."04_2】");
			$this->db->parse("
				SELECT * FROM ".$tmp_tbl_name." WHERE ".$key."03 = :bv_year AND ".$key."04_1 = :bv_code AND ".$key."04_2 = :bv_number
			");

			$this->db->bind(":bv_year", $year);
			$this->db->bind(":bv_code", $code);
			$this->db->bind(":bv_number", $number);
			
			$this->db->execute();
			// for FE, 0 -> table name, 1 -> data, 2 -> SQL statement
			//$result[] = array($tmp_tbl_name, $this->db->fetchAll(), "SELECT * FROM ".$tmp_tbl_name." WHERE ".$key_fields[0]." = '$year' AND ".$key_fields[1]." = '$code' AND ".$key_fields[2]." = '$number'");
			$result[] = array($tmp_tbl_name, $this->db->fetchAll(), "SELECT * FROM ".$tmp_tbl_name." WHERE ".$key."03 = '$year' AND ".$key."04_1 = '$code' AND ".$key."04_2 = '$number'");
		}
		return $result;
	}

	public function clearCaseTemp($year, $code, $number, $table = "") {
		global $log;

		if (empty($year) || empty($code) || empty($number)) {
			$log->error(__METHOD__."：輸入案件參數不能為空白【${year}, ${code}, ${number}】");
			return false;
		}

		// an array to express temp tables and key field names that need to be checked.
		$temp_tables = include("Config.TempTables.php");
		//foreach ($temp_tables as $tmp_tbl_name => $key_fields) {
		foreach ($temp_tables as $tmp_tbl_name => $key) {
			if (!empty($table) && $tmp_tbl_name != $table) {
				$log->info(__METHOD__."：指定刪除 $table 故跳過 $tmp_tbl_name 。");
				continue;
			}
			if ($tmp_tbl_name == "MOICAT.RINDX") {
				$log->warning(__METHOD__."：無法刪除 MOICAT.RINDX 跳過！");
				continue;
			}
			$log->info(__METHOD__."：刪除 $tmp_tbl_name 資料 ... ");
			$this->db->parse("
				DELETE FROM ".$tmp_tbl_name." WHERE ".$key."03 = :bv_year AND ".$key."04_1 = :bv_code AND ".$key."04_2 = :bv_number
			");

			$this->db->bind(":bv_year", $year);
			$this->db->bind(":bv_code", $code);
			$this->db->bind(":bv_number", $number);
			
			$this->db->execute();
			
		}
		return true;
	}

	public function updateCaseColumnData($id, $table, $column, $val) {
		global $log;

		if (empty($id) || empty($table) || empty($column) || (empty($val) && $val !== '0' && $val !=='')) {
			$log->error(__METHOD__."：輸入參數不能為空白【${id}, ${table}, ${column}, ${val}】");
			return false;
		}

		if (!$this->checkCaseID($id)) {
			$log->error(__METHOD__."：ID格式不正確【應為13碼，目前：${id}】");
			return false;
		}

		$year_col = strpos($table, "CRSMS") ? "RM01" : "MM01";
		$code_col = strpos($table, "CRSMS") ? "RM02" : "MM02";
		$num_col = strpos($table, "CRSMS") ? "RM03" : "MM03";

		$year = substr($id, 0, 3);
		$code = substr($id, 3, 4);
		$num = substr($id, 7, 6);

		$sql = "UPDATE ${table} SET ${column} = :bv_val WHERE ${year_col} = :bv_year AND ${code_col} = :bv_code AND ${num_col} = :bv_number";
		
		$log->info(__METHOD__."：預備執行 $sql");

		$this->db->parse($sql);

		$this->db->bind(":bv_year", $year);
		$this->db->bind(":bv_code", $code);
		$this->db->bind(":bv_number", $num);
		$this->db->bind(":bv_val", $val);
		
		$this->db->execute();

		return true;
	}
}
?>
