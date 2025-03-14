<?php
require_once("init.php");
require_once("OraDBWrapper.class.php");
require_once("RegCaseData.class.php");
require_once("System.class.php");

class Query {
	private $db_wrapper = null;

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
	}

	function __destruct() {
		$this->db_wrapper = null;
	}

	public function getCodeData($year) {
		if (!$this->db_wrapper->reachable()) {
			return array();
		}
		$sql = "
			-- 案件(REG + SUR)數量統計 BY 年
			SELECT
				t.RM01 AS YEAR,
				t.RM02 AS CODE,
				q.KCNT AS CODE_NAME,
				COUNT(*) AS COUNT,
				(CASE
					--WHEN REGEXP_LIKE(t.RM02, '^".$this->db_wrapper->getSite()."[[:alpha:]]1$') THEN 'reg.HBX1'
					WHEN RM02 IN ('".$this->db_wrapper->getSite()."A1', '".$this->db_wrapper->getSite()."B1', '".$this->db_wrapper->getSite()."C1', '".$this->db_wrapper->getSite()."D1', '".$this->db_wrapper->getSite()."E1', '".$this->db_wrapper->getSite()."F1', '".$this->db_wrapper->getSite()."G1', '".$this->db_wrapper->getSite()."H1') THEN 'reg.HBX1'
					WHEN t.RM02 LIKE 'H".$this->db_wrapper->getSiteNumber()."%' THEN 'reg.H2XX'
					WHEN t.RM02 LIKE '%".$this->db_wrapper->getSite()."' THEN 'reg.XXHB'
					WHEN t.RM02 LIKE 'H%".$this->db_wrapper->getSiteCode()."1' THEN 'reg.HXB1'
					WHEN t.RM02 LIKE '".$this->db_wrapper->getSite()."%' THEN 'reg.HB'
					ELSE '".mb_convert_encoding('登記案件', 'UTF-8', 'BIG5')."'
				END) AS CODE_TYPE
			FROM MOICAS.CRSMS t
			LEFT JOIN MOIADM.RKEYN q ON q.kcde_1 = '04' AND q.kcde_2 = t.rm02
			WHERE RM01 = :bv_year
			GROUP BY t.RM01, t.RM02, q.KCNT
			UNION
			SELECT t.MM01 AS YEAR, t.MM02 AS CODE, q.KCNT AS CODE_NAME, COUNT(*) AS COUNT, 'sur.HB'  AS CODE_TYPE FROM MOICAS.CMSMS t
			LEFT JOIN MOIADM.RKEYN q ON q.kcde_1 = '04' AND q.kcde_2 = t.mm02
			WHERE MM01 = :bv_year
			GROUP BY t.MM01, t.MM02, q.KCNT
			ORDER BY YEAR, CODE
		";
		
		$this->db_wrapper->getDB()->parse($sql);
		$this->db_wrapper->getDB()->bind(":bv_year", $year);
		$this->db_wrapper->getDB()->execute();
		return $this->db_wrapper->getDB()->fetchAll();
	}
	
	public function getSectionRALIDCount($cond = "") {
		if (!$this->db_wrapper->reachable()) {
			return array();
		}

		$prefix = "
			SELECT m.KCDE_2 AS \"段代碼\",
				m.KCNT AS \"段名稱\",
				t.AA46 AS \"區代碼\",
				q.KNAME AS \"區名稱\",
				SUM(t.AA10) AS \"面積\",
				COUNT(t.AA10) AS \"土地標示部筆數\"
			FROM MOIADM.RKEYN m
			LEFT JOIN MOICAD.RALID t ON m.KCDE_2 = t.AA48 -- 段小段面積計算 (RALID 登記－土地標示部)
			LEFT JOIN MOIADM.RKEYN_ALL q ON q.KCDE_1 = '46' AND q.KCDE_2 = 'H' AND t.AA46 = q.KCDE_3
			WHERE m.KCDE_1 = '48' AND m.KCDE_2 NOT LIKE '/*%' AND t.AA46 IS NOT NULL
		";
		
		if (is_numeric($cond)) {
			$prefix .= "AND m.KCDE_2 LIKE '%' || :bv_cond";
		} else if (!empty($cond)) {
			$prefix .= "AND m.KCNT LIKE '%' || :bv_cond || '%'";
		}

		$postfix = "
			GROUP BY m.KCDE_2, m.KCNT, t.AA46, q.KNAME
		";

		$this->db_wrapper->getDB()->parse($prefix.$postfix);
		if (!empty($cond)) {
			$this->db_wrapper->getDB()->bind(":bv_cond", iconv("utf-8", "big5", ltrim($cond, '0')));
		}

		$this->db_wrapper->getDB()->execute();
		
		return $this->db_wrapper->getDB()->fetchAll();
	}

	public function getCertLog($section_code, $numbers) {
		if (!$this->db_wrapper->reachable()) {
			return array();
		}

		
		if (empty($section_code) || empty($numbers)) {
			Logger::getInstance()->warning(__METHOD__.": 輸入參數為空，無法查詢謄本記錄檔。");
			return false;
		}
		$sql = "
			-- 可至地政WEB版 => 單一窗口系統/整合謄本/謄本LOG查詢-地所(桃園版) 查詢
			-- 法院謄本申請LOG檔查詢 BY 段、地建號
			SELECT t.MD01          AS \"收件年\",
				t.MD02             AS \"收件字\",
				t.MD03             AS \"收件號\",
				t.MD06             AS \"段小段\",
				t.MD08             AS \"地建號\",
				t.MD07              AS \"地建別\",
				r.SR_TYPE          AS \"申請類別\",
				r.SR09             AS \"申請人統編\",
				r.SR10             AS \"申請人姓名\",
				r.SR_AGENT_ID      AS \"代理人統編\",
				r.SR_AGENT_NAME    AS \"代理人姓名\",
				r.SR_SUBAGENT_ID   AS \"複代理人統編\",
				r.SR_SUBAGENT_NAME AS \"複代理人姓名\",
				r.SR08             AS \"LOG時間\",
				r.SR_METHOD        AS \"申請方式\",
				(CASE
					WHEN t.MD04 = 'A' THEN '".iconv("utf-8", "big5", "登記電子資料謄本")."'
					WHEN t.MD04 = 'B' THEN '".iconv("utf-8", "big5", "地價電子資料謄本")."'
					WHEN t.MD04 = 'C' THEN '".iconv("utf-8", "big5", "地籍圖謄本")."'
					WHEN t.MD04 = 'D' THEN '".iconv("utf-8", "big5", "建物平面圖謄本")."'
					WHEN t.MD04 = 'E' THEN '".iconv("utf-8", "big5", "人工登記簿謄本")."'
					WHEN t.MD04 = 'F' THEN '".iconv("utf-8", "big5", "閱覽")."'
					WHEN t.MD04 = 'G' THEN '".iconv("utf-8", "big5", "列印電子資料")."'
					WHEN t.MD04 = 'H' THEN '".iconv("utf-8", "big5", "申請")."'
					WHEN t.MD04 = 'I' THEN '".iconv("utf-8", "big5", "測量")."'
					WHEN t.MD04 = 'J' THEN '".iconv("utf-8", "big5", "異動索引")."'
					WHEN t.MD04 = 'K' THEN '".iconv("utf-8", "big5", "土地建物異動清冊")."'
					WHEN t.MD04 = 'L' THEN '".iconv("utf-8", "big5", "代理人申請登記案件明細表")."'
					WHEN t.MD04 = 'M' THEN '".iconv("utf-8", "big5", "土地參考資訊")."'
					ELSE t.MD04
       			END) AS \"謄本種類\",
       			(CASE
					WHEN t.MD05 = 'A' THEN '".iconv("utf-8", "big5", "全部")."'
					WHEN t.MD05 = 'B' THEN '".iconv("utf-8", "big5", "所有權個人全部")."'
					WHEN t.MD05 = 'C' THEN '".iconv("utf-8", "big5", "標示部")."'
					WHEN t.MD05 = 'D' THEN '".iconv("utf-8", "big5", "所有權部")."'
					WHEN t.MD05 = 'E' THEN '".iconv("utf-8", "big5", "他項權利部")."'
					WHEN t.MD05 = 'F' THEN '".iconv("utf-8", "big5", "標示部及所有權部")."'
					WHEN t.MD05 = 'G' THEN '".iconv("utf-8", "big5", "標示部及他項權利部")."'
					WHEN t.MD05 = 'H' THEN '".iconv("utf-8", "big5", "他項權利部之個人")."'
					WHEN t.MD05 = 'I' THEN '".iconv("utf-8", "big5", "他項權利個人全部")."'
					WHEN t.MD05 = 'J' THEN '".iconv("utf-8", "big5", "標示部")."'
					WHEN t.MD05 = 'K' THEN '".iconv("utf-8", "big5", "所有權部")."'
					WHEN t.MD05 = 'KA' THEN '".iconv("utf-8", "big5", "收件年期字號")."'
					WHEN t.MD05 = 'KB' THEN '".iconv("utf-8", "big5", "收件年字號+登序")."'
					WHEN t.MD05 = 'KC' THEN '".iconv("utf-8", "big5", "收件年字號+統編")."'
					WHEN t.MD05 = 'KD' THEN '".iconv("utf-8", "big5", "收件年字號+姓名")."'
					WHEN t.MD05 = 'L' THEN '".iconv("utf-8", "big5", "標示部及所有權部")."'
					WHEN t.MD05 = 'LA' THEN '".iconv("utf-8", "big5", "統計起始日期+統計終止日期+代理人統一編號")."'
					WHEN t.MD05 = 'LB' THEN '".iconv("utf-8", "big5", "統計起始日期+統計終止日期")."'
					WHEN t.MD05 = 'LC' THEN '".iconv("utf-8", "big5", "代理人統一編號")."'
					WHEN t.MD05 = 'M' THEN '".iconv("utf-8", "big5", "標示部(標示部不存在)")."'
					WHEN t.MD05 = 'N' THEN '".iconv("utf-8", "big5", "所有權部(標示部不存在)")."'
					WHEN t.MD05 = 'O' THEN '".iconv("utf-8", "big5", "標示部及所有權部(標示部不存在)")."'
					WHEN t.MD05 = 'P' THEN '".iconv("utf-8", "big5", "資料庫不存在")."'
					WHEN t.MD05 = 'Q' THEN '".iconv("utf-8", "big5", "地籍圖謄本")."'
					WHEN t.MD05 = 'R' THEN '".iconv("utf-8", "big5", "建物平面圖謄本")."'
					WHEN t.MD05 = 'S' THEN '".iconv("utf-8", "big5", "登記簿謄本")."'
					WHEN t.MD05 = 'T' THEN '".iconv("utf-8", "big5", "閱覽")."'
					WHEN t.MD05 = 'U' THEN '".iconv("utf-8", "big5", "列印電子資料")."'
					WHEN t.MD05 = 'V' THEN '".iconv("utf-8", "big5", "申請")."'
					WHEN t.MD05 = 'W' THEN '".iconv("utf-8", "big5", "測量")."'
					WHEN t.MD05 = 'X' THEN '".iconv("utf-8", "big5", "異動索引")."'
					WHEN t.MD05 = 'XA' THEN '".iconv("utf-8", "big5", "收件年期字號")."'
					WHEN t.MD05 = 'XB' THEN '".iconv("utf-8", "big5", "地建號")."'
					WHEN t.MD05 = 'XC' THEN '".iconv("utf-8", "big5", "地建號+登序")."'
					WHEN t.MD05 = 'XD' THEN '".iconv("utf-8", "big5", "地建號+部別")."'
					WHEN t.MD05 = 'XE' THEN '".iconv("utf-8", "big5", "地建號+統編")."'
					ELSE t.MD05
       			END) AS \"謄本項目\"
			FROM MOICAS.CUSMD2 t
			INNER JOIN MOICAS.RSCNRL r
				ON (t.MD03 = r.SR03)
			AND (t.MD02 = r.SR02)
			AND (t.MD01 = r.SR01)
			--WHERE r.SR09 ='A123456789' OR r.SR_AGENT_ID = 'A123456789' OR r.SR_SUBAGENT_ID = 'A123456789';
			--WHERE r.SR10 LIKE '%聯邦%' AND t.MD06 = '0222'
			WHERE t.MD06 = :bv_section_code AND t.MD08 in ('".implode("','", $numbers)."')
		";
		//$sql = mb_convert_encoding($sql, "big5", "utf-8");
		// $sql = iconv("utf-8", "big5", $sql);
		$this->db_wrapper->getDB()->parse($sql);
		$this->db_wrapper->getDB()->bind(":bv_section_code", $section_code);
		$this->db_wrapper->getDB()->execute();
		return $this->db_wrapper->getDB()->fetchAll();
	}
	
	public function getMaxNumByYearWord($year, $code) {
		if (!$this->db_wrapper->reachable()) {
			return '0';
		}

		if (!filter_var($year, FILTER_SANITIZE_NUMBER_INT)) {
			return false;
		}

		$num_key = "RM03";
		if (array_key_exists($code, SUR_WORD)) {
			$this->db_wrapper->getDB()->parse("
				SELECT * FROM (
					SELECT * from MOICAS.CMSMS t
					WHERE MM01 = :bv_year AND MM02 = :bv_code AND rownum = 1
					ORDER BY MM03 DESC
				) WHERE ROWNUM = 1
			");
			$num_key = "MM03";
		} else if (array_key_exists($code, PRC_WORD)) {
			$this->db_wrapper->getDB()->parse("
				SELECT * FROM (
					SELECT * from MOIPRC.PSCRN t
					WHERE SS03 = :bv_year AND SS04_1 = :bv_code
					ORDER BY SS04_1 DESC
				) WHERE ROWNUM = 1
			");
			$num_key = "SS04_2";
		} else  {
			$this->db_wrapper->getDB()->parse("
				SELECT * FROM (
					SELECT * from MOICAS.CRSMS t
					WHERE RM01 = :bv_year AND RM02 = :bv_code
					ORDER BY RM03 DESC
				) WHERE ROWNUM = 1
			");
		}
		
		$this->db_wrapper->getDB()->bind(":bv_year", $year);
		$this->db_wrapper->getDB()->bind(":bv_code", trim($code));
		$this->db_wrapper->getDB()->execute();
		$row = $this->db_wrapper->getDB()->fetch();

		return empty($row) ? "0" : ltrim($row[$num_key], "0");
	}

	public function getCRSMSCasesByPID($id) {
		if (!$this->db_wrapper->reachable()) {
			return array();
		}

		$id = strtoupper($id);
        if (!$this->checkPID($id)) {
            return false;
		}
		$this->db_wrapper->getDB()->parse("
			SELECT t.*, w.KCNT AS \"RM09_CHT\"
			FROM MOICAS.CRSMS t
			LEFT JOIN MOIADM.RKEYN w ON t.RM09 = w.KCDE_2 AND w.KCDE_1 = '06'   -- 登記原因
			WHERE t.RM18 = :bv_id
			OR t.RM21 = :bv_id
			OR t.RM24 = :bv_id
			OR t.RM25 = :bv_id
			ORDER BY RM07_1 DESC
        ");
		$this->db_wrapper->getDB()->bind(":bv_id", $id);
		$this->db_wrapper->getDB()->execute();
		return $this->db_wrapper->getDB()->fetchAll();
	}

	public function getCMSMSCasesByPID($id) {
		if (!$this->db_wrapper->reachable()) {
			return array();
		}

		$id = strtoupper($id);
		if (!$this->checkPID($id)) {
            return false;
		}
		$this->db_wrapper->getDB()->parse("
			SELECT t.*, w.KCNT AS \"MM06_CHT\"
			FROM MOICAS.CMSMS t
			LEFT JOIN MOIADM.RKEYN w ON t.MM06 = w.KCDE_2 AND w.KCDE_1 = 'M3'   -- 登記原因
			WHERE t.MM13 = :bv_id
			OR t.MM17_1 = :bv_id
			OR t.MM17_2 = :bv_id
			ORDER BY MM04_1 DESC
        ");
		$this->db_wrapper->getDB()->bind(":bv_id", $id);
		$this->db_wrapper->getDB()->execute();
		return $this->db_wrapper->getDB()->fetchAll();
	}

	public function getEasycardPayment($qday = '') {
		if (!$this->db_wrapper->reachable()) {
			return array();
		}
		// AA100 付款方式代碼，EXPK付款方式表格 (K02 => 中文)
		$this->db_wrapper->getDB()->parse("
			SELECT t.*, s.K02
			FROM MOIEXP.EXPAA t
			LEFT JOIN MOIEXP.EXPK s ON t.AA100 = s.K01
			WHERE AA01 >= :bv_qday AND AA106 <> '1' AND s.K02 = '".iconv("utf-8", "big5", '悠遊卡')."'
		");
		if (empty($qday)) {
			$tw_date = new Datetime("now");
			$tw_date->modify("-1911 year");
			$tw_date->modify("-1 week");
			$week_ago = ltrim($tw_date->format("Ymd"), "0");	// ex: 1080318
			// fetch all data wthin a week back
			$this->db_wrapper->getDB()->bind(":bv_qday", $week_ago);
		} else {
			if (!filter_var($qday, FILTER_SANITIZE_NUMBER_INT)) {
            	return false;
			}
			$this->db_wrapper->getDB()->bind(":bv_qday", $qday);
		}
		$this->db_wrapper->getDB()->execute();
		return $this->db_wrapper->getDB()->fetchAll();
	}

	public function fixEasycardPayment($qday, $pc_num) {
		if (!$this->db_wrapper->reachable()) {
			return false;
		}

		// ex: UPDATE MOIEXP.EXPAA SET AA106 = '1' WHERE AA01 = '1080321' AND AA106 <> '1' AND AA04 = '0015746';
		$this->db_wrapper->getDB()->parse("
			UPDATE MOIEXP.EXPAA SET AA106 = '1' WHERE AA01 = :bv_qday AND AA106 <> '1' AND AA04 = :bv_pc_num
		");
		$this->db_wrapper->getDB()->bind(":bv_qday", $qday);
		$this->db_wrapper->getDB()->bind(":bv_pc_num", $pc_num);
		// UPDATE/INSERT can not use fetch after execute ... 
		$this->db_wrapper->getDB()->execute();
		return true;
	}

	public function getExpkItems() {
		if (!$this->db_wrapper->reachable()) {
			return array();
		}
		/*
			e.g. HA
			K01 K02 K03 K04
			01	現金
			02	支票
			03	匯票
			04	iBon
			06	悠遊卡
			07	其他匯款
			08	晶片金融卡(網路ATM)
			09	信用卡
			10	APPLE PAY
			11	安卓 PAY
			12	三星 PAY
			16	信用卡(罰鍰)
			14	內政部線上申辦
			15	桃園e指通線上申辦
		 */
		$this->db_wrapper->getDB()->parse("SELECT * FROM MOIEXP.EXPK t ORDER BY K01");
		$this->db_wrapper->getDB()->execute();
		return $this->db_wrapper->getDB()->fetchAll();
	}

	public function getExpacItems($year, $num) {
		if (!$this->db_wrapper->reachable()) {
			return array();
		}

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
		$this->db_wrapper->getDB()->parse("
			SELECT *
			FROM MOIEXP.EXPAC t
			LEFT JOIN MOIEXP.EXPE p
				ON p.E20 = t.AC20
			WHERE t.AC04 = :bv_num AND t.AC25 = :bv_year
        ");
		$this->db_wrapper->getDB()->bind(":bv_year", $year);
		$this->db_wrapper->getDB()->bind(":bv_num", $num);
		$this->db_wrapper->getDB()->execute();
		return $this->db_wrapper->getDB()->fetchAll();
	}

	public function modifyExpacItem($year, $num, $code, $amount) {
		if (!$this->db_wrapper->reachable()) {
			return false;
		}

		// ex: UPDATE MOIEXP.EXPAC SET AC20 = '35' WHERE AC04 = '0021131' AND AC25 = '108' AND AC30 = '280';
		$this->db_wrapper->getDB()->parse("
			UPDATE MOIEXP.EXPAC SET AC20 = :bv_code WHERE AC04 = :bv_pc_num AND AC25 = :bv_year AND AC30 = :bv_amount
		");
		$this->db_wrapper->getDB()->bind(":bv_year", $year);
		$this->db_wrapper->getDB()->bind(":bv_pc_num", $num);
		$this->db_wrapper->getDB()->bind(":bv_code", $code);
		$this->db_wrapper->getDB()->bind(":bv_amount", $amount);
		// UPDATE/INSERT can not use fetch after execute ... 
		$this->db_wrapper->getDB()->execute();
		return true;
	}

	public function getExpaaData($qday, $num) {
		if (!$this->db_wrapper->reachable()) {
			return array();
		}

		if (empty($num)) {
			$this->db_wrapper->getDB()->parse("
				SELECT t.*, s.K02 AS AA100_CHT
				FROM MOIEXP.EXPAA t
				LEFT JOIN MOIEXP.EXPK s ON t.AA100 = s.K01
				WHERE t.AA01 = :bv_qday
			");
		} else {
			$this->db_wrapper->getDB()->parse("
				SELECT t.*, s.K02 AS AA100_CHT
				FROM MOIEXP.EXPAA t
				LEFT JOIN MOIEXP.EXPK s ON t.AA100 = s.K01
				WHERE t.AA04 = :bv_num AND t.AA01 = :bv_qday
			");
			$this->db_wrapper->getDB()->bind(":bv_num", $num);
		}
		$this->db_wrapper->getDB()->bind(":bv_qday", $qday);
		$this->db_wrapper->getDB()->execute();
		return $this->db_wrapper->getDB()->fetchAll();
	}

	public function updateExpaaData($column, $date, $number, $update_val) {
		if (!$this->db_wrapper->reachable()) {
			return false;
		}

		if (strlen($date) != 7 || strlen($number) != 7) {
			return false;
		}

		$this->db_wrapper->getDB()->parse("
			UPDATE MOIEXP.EXPAA SET ${column} = :bv_update_value WHERE AA01 = :bv_aa01 AND AA04 = :bv_aa04
		");

		$this->db_wrapper->getDB()->bind(":bv_update_value", $update_val);
		$this->db_wrapper->getDB()->bind(":bv_aa01", $date);
		$this->db_wrapper->getDB()->bind(":bv_aa04", $number);
		
		$this->db_wrapper->getDB()->execute();

		return true;
	}

	public function getDummyObFees() {
		if (!$this->db_wrapper->reachable()) {
			return array();
		}

		$tw_date = new Datetime("now");
		$tw_date->modify("-1911 year");
		$this_year = ltrim($tw_date->format("Y"), "0");	// ex: 109

		// use '9' + year(3 digits) + '000' to stand for the obsolete fee application
		$this->db_wrapper->getDB()->parse("
			select * from MOIEXP.EXPAA t
			where aa04 like '9' || :bv_year || '%'
			order by AA01 desc, AA04 desc
		");
		$this->db_wrapper->getDB()->bind(":bv_year", $this_year);
		$this->db_wrapper->getDB()->execute();
		return $this->db_wrapper->getDB()->fetchAll();
	}

	public function addDummyObFees($date, $pc_num, $operator, $fee_number, $reason) {
		if (!$this->db_wrapper->reachable()) {
			return false;
		}

		
		if (empty($date) || empty($pc_num) || empty($operator) || empty($fee_number) || empty($reason)) {
			Logger::getInstance()->error(__METHOD__.": One of the parameters is empty. The system can not add obsolete fee expaa data.");
			Logger::getInstance()->warning(__METHOD__.": The input params: ${date}, ${pc_num}, ${operator}, ${fee_number}, ${reason}.");
			return false;
		}

		$sql = "INSERT INTO MOIEXP.EXPAA (AA01,AA04,AA05,AA06,AA07,AA08,AA09,AA02,AA24,AA25,AA39,AA104) VALUES (:bv_date, :bv_pc_num, :bv_fee_num, '1', '0', '0', '1', :bv_date, :bv_date, :bv_year, :bv_operator, :bv_reason)";
		$this->db_wrapper->getDB()->parse($sql);
		$this->db_wrapper->getDB()->bind(":bv_date", $date);
		$this->db_wrapper->getDB()->bind(":bv_year", substr($date, 0, 3));
		$this->db_wrapper->getDB()->bind(":bv_pc_num", $pc_num);
		$this->db_wrapper->getDB()->bind(":bv_fee_num", $fee_number);
		$this->db_wrapper->getDB()->bind(":bv_operator", $operator);
		$this->db_wrapper->getDB()->bind(":bv_reason", iconv("utf-8", "big5", $reason));

		Logger::getInstance()->info(__METHOD__.": 插入 SQL \"$sql\"");

		$this->db_wrapper->getDB()->execute();
		return true;
	}

	public function getForeignCasesByYearMonth($year_month) {
		if (!$this->db_wrapper->reachable()) {
			return array();
		}

		if (!filter_var($year_month, FILTER_SANITIZE_NUMBER_INT)) {
            return false;
        }
		$this->db_wrapper->getDB()->parse("
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
				SQ.RM31   AS \"結案代碼\"
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
		$this->db_wrapper->getDB()->bind(":bv_year_month", $year_month);
		$this->db_wrapper->getDB()->execute();
		return $this->db_wrapper->getDB()->fetchAll();
	}

	public function getChargeItems() {
		if (!$this->db_wrapper->reachable()) {
			return array();
		}
		$this->db_wrapper->getDB()->parse("select * from MOIEXP.EXPE t");
		$this->db_wrapper->getDB()->execute();
		return $this->db_wrapper->getDB()->fetchAll();
	}

    public function queryAllCasesNotDone() {
		if (!$this->db_wrapper->reachable()) {
			return array();
		}
		$site = System::getInstance()->getSiteCode();
        $this->db_wrapper->getDB()->parse("
			SELECT *
			FROM SCRSMS 
			LEFT JOIN SRKEYN ON KCDE_1 = '06' AND RM09 = KCDE_2
			WHERE RM31 IS NULL AND (RM99 IS NULL OR (RM99 = 'Y' AND RM101 = :bv_site))
			ORDER BY RM07_1, RM07_2 DESC
		");
		$this->db_wrapper->getDB()->bind(":bv_site", $site);
		$this->db_wrapper->getDB()->execute();
		return $this->db_wrapper->getDB()->fetchAll();
    }

    // template method for query all cases by date
    public function queryAllCasesByDate($qday) {
		if (!$this->db_wrapper->reachable()) {
			return array();
		}

        // only allow int number for $qday
        if (!filter_var($qday, FILTER_SANITIZE_NUMBER_INT)) {
            return false;
        }
		
		$site = System::getInstance()->getSiteCode();
        $this->db_wrapper->getDB()->parse("
			SELECT *
			FROM SCRSMS
			LEFT JOIN SRKEYN ON KCDE_1 = '06' AND RM09 = KCDE_2
			WHERE (RM07_1 BETWEEN :bv_qday AND :bv_qday) AND (RM99 IS NULL OR (RM99 = 'Y' AND RM101 = :bv_site))
			ORDER BY RM07_1, RM07_2 DESC
		");
        $this->db_wrapper->getDB()->bind(":bv_qday", $qday);
		$this->db_wrapper->getDB()->bind(":bv_site", $site);
		$this->db_wrapper->getDB()->execute();
		return $this->db_wrapper->getDB()->fetchAll();
    }

	public function queryReasonCasesByMonth($reason_code, $query_month) {
		if (!$this->db_wrapper->reachable()) {
			return array();
		}

		// only allow int number for $query_month
        if (!filter_var($query_month, FILTER_SANITIZE_NUMBER_INT)) {
            return false;
        }
        $this->db_wrapper->getDB()->parse("SELECT * FROM SCRSMS LEFT JOIN SRKEYN ON KCDE_1 = '06' AND RM09 = KCDE_2 WHERE RM07_1 LIKE :bv_qmonth || '%' AND RM09 = :bv_rcode ORDER BY RM07_1, RM07_2 DESC");
		$this->db_wrapper->getDB()->bind(":bv_qmonth", $query_month);
		$this->db_wrapper->getDB()->bind(":bv_rcode", $reason_code);
		$this->db_wrapper->getDB()->execute();
		return $this->db_wrapper->getDB()->fetchAll();
	}

	public function queryCourtCasesByMonth($query_month) {
		if (!$this->db_wrapper->reachable()) {
			return array();
		}

		// only allow int number for $query_month
        if (!filter_var($query_month, FILTER_SANITIZE_NUMBER_INT)) {
            return false;
        }
        $this->db_wrapper->getDB()->parse("
			SELECT *
			FROM SCRSMS
			LEFT JOIN SRKEYN ON KCDE_1 = '06' AND RM09 = KCDE_2
			WHERE
				RM07_1 LIKE :bv_qmonth || '%' AND
				RM09 in ('33', '34', '49', '54', '50', '55', '52', '57', '51', '56', 'FB', 'FC', 'AU')
			ORDER BY RM07_1, RM07_2 DESC
		");
		$this->db_wrapper->getDB()->bind(":bv_qmonth", $query_month);
		$this->db_wrapper->getDB()->execute();
		return $this->db_wrapper->getDB()->fetchAll();
	}

	public function queryRegRemoteCasesByMonth($query_month) {
		if (!$this->db_wrapper->reachable()) {
			return array();
		}

		// only allow int number for $query_month
        if (!filter_var($query_month, FILTER_SANITIZE_NUMBER_INT)) {
            return false;
		}
		$this->db_wrapper->getDB()->parse("
			SELECT
				t.RM01 AS \"收件年\",
				t.RM02 AS \"收件字\",
				t.RM03 AS \"收件號\",
				t.RM09 AS \"登記原因代碼\",
				w.KCNT AS \"登記原因\",
				t.RM07_1 AS \"收件日期\",
				u.LIDN AS \"權利人統編\",
				u.LNAM AS \"權利人名稱\",
				u.LADR AS \"權利人地址\",
				v.AB01 AS \"代理人統編\",
				v.AB02 AS \"代理人名稱\",
				v.AB03 AS \"代理人地址\",
				t.RM13 AS \"筆數\",
				t.RM16 AS \"棟數\"
			FROM MOICAS.CRSMS t
				LEFT JOIN MOICAD.RLNID u ON t.RM18 = u.LIDN  -- 權利人
				LEFT JOIN MOICAS.CABRP v ON t.RM24 = v.AB01  -- 代理人
				LEFT JOIN MOIADM.RKEYN w ON t.RM09 = w.KCDE_2 AND w.KCDE_1 = '06'   -- 登記原因
			WHERE 
				t.RM07_1 LIKE :bv_qmonth || '%' AND 
				(u.LADR NOT LIKE '%' || :bv_city || '%' AND u.LADR NOT LIKE '%' || :bv_county || '%') AND 
                (v.AB03 NOT LIKE '%' || :bv_city || '%' AND v.AB03 NOT LIKE '%' || :bv_county || '%')
		");
		$this->db_wrapper->getDB()->bind(":bv_qmonth", $query_month);
		$this->db_wrapper->getDB()->bind(":bv_city", mb_convert_encoding('桃園市', "big5"));
    $this->db_wrapper->getDB()->bind(":bv_county", mb_convert_encoding('桃園縣', "big5"));
		$this->db_wrapper->getDB()->execute();
		return $this->db_wrapper->getDB()->fetchAll();
	}

	public function queryRegSubCasesByMonth($query_month) {
		if (!$this->db_wrapper->reachable()) {
			return array();
		}

		// only allow int number for $query_month
        if (!filter_var($query_month, FILTER_SANITIZE_NUMBER_INT)) {
            return false;
		}
		$this->db_wrapper->getDB()->parse("
			-- 本所處理跨所子號案件
			SELECT SQ.RM01   AS \"收件年\",
				SQ.RM02   AS \"收件字\",
				SQ.RM03   AS \"收件號\",
				SQ.RM09   AS \"登記原因代碼\",
				k.KCNT    AS \"登記原因\",
				SQ.RM07_1 AS \"收件日期\",
				SQ.RM58_1 AS \"結案日期\",
				SQ.RM18   AS \"權利人統一編號\",
				SQ.RM19   AS \"權利人姓名\",
				SQ.RM21   AS \"義務人統一編號\",
				SQ.RM22   AS \"義務人姓名\",
				SQ.RM30   AS \"辦理情形\",
				SQ.RM31   AS \"結案已否\"
			FROM (SELECT *
					FROM MOICAS.CRSMS tt
					WHERE tt.rm07_1 LIKE :bv_qmonth || '%'
					AND tt.RM02 LIKE 'H%".$this->db_wrapper->getSiteCode()."1' -- 本所處理跨所案件
					AND tt.RM03 NOT LIKE '%0' -- 子號案件
					) SQ
			LEFT JOIN MOICAD.RKEYN k
				ON k.KCDE_2 = SQ.RM09
			WHERE k.KCDE_1 = '06'
		");
		$this->db_wrapper->getDB()->bind(":bv_qmonth", $query_month);
		$this->db_wrapper->getDB()->execute();
		return $this->db_wrapper->getDB()->fetchAll();
	}

	public function queryRegfCasesByMonth($query_month) {
		if (!$this->db_wrapper->reachable()) {
			return array();
		}

		// only allow int number for $query_month
        if (!filter_var($query_month, FILTER_SANITIZE_NUMBER_INT)) {
            return false;
		}
		$this->db_wrapper->getDB()->parse("
			-- REGF 登記主檔－外國人地權登記統計檔 (SREGF)
			SELECT
				MOICAD.REGF.RF03 AS \"收件年\",
				MOICAD.REGF.RF04_1 AS \"收件字\",
				MOICAD.REGF.RF04_2 AS \"收件號\",
				MOICAD.REGF.RF40 AS \"註記日期\",
				MOICAD.REGF.ITEM AS \"項目代碼\",
				MOICAD.REGF.RECA AS \"土地筆數\",
				MOICAD.REGF.RF10 AS \"土地面積\",
				MOICAD.REGF.RECD AS \"建物筆數\",
				MOICAD.REGF.RF08 AS \"建物面積\",
				MOICAD.REGF.RE46 AS \"鄉鎮市區代碼\"
			FROM MOICAD.REGF
			WHERE MOICAD.REGF.RF40 LIKE :bv_qmonth || '%'
		");
		$this->db_wrapper->getDB()->bind(":bv_qmonth", $query_month);
		$this->db_wrapper->getDB()->execute();
		return $this->db_wrapper->getDB()->fetchAll();
	}

	public function queryFixCasesByMonth($query_month) {
		if (!$this->db_wrapper->reachable()) {
			return array();
		}

		// only allow int number for $query_month
        if (!filter_var($query_month, FILTER_SANITIZE_NUMBER_INT)) {
            return false;
		}
		$this->db_wrapper->getDB()->parse("
			SELECT DISTINCT t.*, s.KCNT AS \"RM09_CHT\"
			FROM MOICAS.CRSMS t
			LEFT JOIN SRKEYN s ON s.KCDE_1 = '06' AND t.RM09 = s.KCDE_2
			WHERE t.RM07_1 LIKE :bv_qmonth || '%'
				AND t.RM51 Is Not Null
				AND t.RM52 Is Not Null
		");
		$this->db_wrapper->getDB()->bind(":bv_qmonth", $query_month);
		$this->db_wrapper->getDB()->execute();
		return $this->db_wrapper->getDB()->fetchAll();
	}

	public function queryRejectCasesByMonth($query_month) {
		if (!$this->db_wrapper->reachable()) {
			return array();
		}

		// only allow int number for $query_month
        if (!filter_var($query_month, FILTER_SANITIZE_NUMBER_INT)) {
            return false;
		}
		$this->db_wrapper->getDB()->parse("
			SELECT DISTINCT t.*, s.KCNT AS \"RM09_CHT\"
			FROM MOICAS.CRSMS t
			LEFT JOIN SRKEYN s ON s.KCDE_1 = '06' AND t.RM09 = s.KCDE_2
			WHERE t.RM07_1 LIKE :bv_qmonth || '%'
				AND t.RM48_1 Is Not Null
				AND t.RM48_2 Is Not Null
		");
		$this->db_wrapper->getDB()->bind(":bv_qmonth", $query_month);
		$this->db_wrapper->getDB()->execute();
		return $this->db_wrapper->getDB()->fetchAll();
	}

	public function queryEXPBARefundCasesByMonth($query_month) {
		if (!$this->db_wrapper->reachable()) {
			return array();
		}

		// only allow int number for $query_month
        if (!filter_var($query_month, FILTER_SANITIZE_NUMBER_INT)) {
            return false;
		}
		$this->db_wrapper->getDB()->parse("
			-- 主動申請退費
			select
				BA32  AS  \"退費日期\",
				BA33  AS  \"退費年\",
				BA35  AS  \"收入退還書編號\",
				BA36  AS  \"申請書文號\",
				BA37  AS  \"退還書狀況\",
				BA25  AS  \"結帳年\",
				BA04  AS  \"收費電腦給號\",
				BA38  AS  \"公庫付款日期\",
				BA39  AS  \"承辦員代碼\",
				BA40  AS  \"退費總金額\"
			from MOIEXP.EXPBA t
			where ba32 LIKE :bv_qmonth || '%' and BA42 = '01'  --溢繳規費
		");
		$this->db_wrapper->getDB()->bind(":bv_qmonth", $query_month);
		$this->db_wrapper->getDB()->execute();
		return $this->db_wrapper->getDB()->fetchAll();
	}

	public function querySurRainCasesByMonth($query_month) {
		if (!$this->db_wrapper->reachable()) {
			return array();
		}

		// only allow int number for $query_month
        if (!filter_var($query_month, FILTER_SANITIZE_NUMBER_INT)) {
            return false;
		}
		$this->db_wrapper->getDB()->parse("
			select
				t.MM01   AS \"收件年\",
				t.MM02   AS \"收件字\",
				t.MM03   AS \"收件號\",
				t.MM04_1 AS \"收件日期\",
				--t.MM04_2 AS \"收件時間\",
				t.MM05   AS \"收件類別\",
				t.MM06   AS \"申請事由\",
				--t.MM07   AS \"鄉鎮市區\",
				--t.MM08   AS \"段小段\",
				--t.MM09   AS \"地號\",
				--t.MM09_1 AS \"地目\",
				--t.MM10   AS \"建號\",
				--t.MM11   AS \"筆 / 棟數\",
				--t.MM12   AS \"面積\",
				--t.MM13   AS \"申請人統一編號\",
				--t.MM14   AS \"申請人姓名\",
				--t.MM15   AS \"申請人電話\",
				--t.MM16   AS \"申請人住址\",
				--t.MM17_1 AS \"代理人統一編號\",
				--t.MM17_2 AS \"複代理人統一編號\",
				--t.MM18   AS \"案件點數\",
				--t.MM19   AS \"辦理期限\",
				t.MM20   AS \"案件天數\",
				--t.MM21_1 AS \"逾期日期\",
				--t.MM21_2 AS \"逾期時間\",
				t.MM22   AS \"辦理情形\",
				t.MM23   AS \"結案已否\",
				--t.MM24   AS \"件數\",
				--t.MM13_1 AS \"共(人)\",
				--t.MM15_1 AS \"申請人電話分機號碼\",
				--t.MM25   AS \"管轄所代碼\",
				--t.MM20_1 AS \"實際辦理天數\",
				--t.MM16_1 AS \"電子信箱\",
				--t.MM17_3 AS \"登記助理員統編\",
				--t.MM15_2 AS \"手機號碼\",
				--t.MM26   AS \"區域號碼\",
				--t.MM27   AS \"分機號碼\",
				t.MM31   AS \"收件人\"
				--t.MM32   AS \"是否為跨所案件\",
				--t.MM33   AS \"跨所-收件所所別\",
				--t.MM34   AS \"跨所-收件所縣市別\"
			from SCMSMS t
			left join SCMSDS q
				on t.MM01 = q.MD01
				and t.MM02 = q.MD02
				and t.MM03 = q.MD03
			where t.MM04_1 LIKE :bv_qmonth || '%'
				and q.MD12 = '1' -- 因雨延期代碼
		");
		$this->db_wrapper->getDB()->bind(":bv_qmonth", $query_month);
		$this->db_wrapper->getDB()->execute();
		return $this->db_wrapper->getDB()->fetchAll();
	}

	public function queryExpiredAnnouncementCases($reviewer_id = "") {
		if (!$this->db_wrapper->reachable()) {
			return array();
		}

		if (empty($reviewer_id)) {
			$this->db_wrapper->getDB()->parse("
				SELECT *
				FROM SCRSMS
				LEFT JOIN SRKEYN ON KCDE_1 = '06' AND RM09 = KCDE_2
				WHERE 1=1
				AND (RM99 IS NULL OR RM101 = 'HA')
				--AND RM03 LIKE '%0'-- without sub-case
				AND RM31 IS NULL	-- not closed case
				AND RM30 = 'H'		-- announcement case
				AND RM50 < :bv_now
				ORDER BY RM29_1 DESC, RM29_2 DESC
			");
		} else {
			$this->db_wrapper->getDB()->parse("
				SELECT *
				FROM SCRSMS
				LEFT JOIN SRKEYN ON KCDE_1 = '06' AND RM09 = KCDE_2
				WHERE 1=1
				AND (RM99 IS NULL OR RM101 = 'HA')
				--AND RM03 LIKE '%0'-- without sub-case
				AND RM31 IS NULL	-- not closed case
				AND RM30 = 'H'		-- announcement case
				AND RM50 < :bv_now
				AND RM45 = :bv_reviewer_id
				ORDER BY RM29_1 DESC, RM29_2 DESC
			");
			$this->db_wrapper->getDB()->bind(":bv_reviewer_id", $reviewer_id);	// HB1184
		}

		// bind today
		$tw_date = new Datetime("now");
		$tw_date->modify("-1911 year");
		$now = ltrim($tw_date->format("Ymd"), "0");	// ex: 1111121
		$this->db_wrapper->getDB()->bind(":bv_now", $now);

		Logger::getInstance()->info(__METHOD__.": Find expired announcement cases.");

		$this->db_wrapper->getDB()->execute();
		return $this->db_wrapper->getDB()->fetchAll();
	}

	// 找近15天逾期的案件
	public function queryOverdueCasesIn15Days($reviewer_id = "") {
		if (!$this->db_wrapper->reachable()) {
			return array();
		}

		if (empty($reviewer_id)) {
			$this->db_wrapper->getDB()->parse("
				SELECT *
				FROM SCRSMS
				LEFT JOIN SRKEYN ON KCDE_1 = '06' AND RM09 = KCDE_2
				WHERE 1=1
					AND (RM99 IS NULL OR RM101 = '".$this->db_wrapper->getSite()."')
					AND RM03 LIKE '%0' 			-- without sub-case
					AND RM31 IS NULL			-- not closed case
					AND RM29_1 || RM29_2 < :bv_now
					AND RM29_1 || RM29_2 > :bv_start
				ORDER BY RM29_1 DESC, RM29_2 DESC
			");
		} else {
			$this->db_wrapper->getDB()->parse("
				SELECT *
				FROM SCRSMS
				LEFT JOIN SRKEYN ON KCDE_1 = '06' AND RM09 = KCDE_2
				WHERE 1=1
					AND (RM99 IS NULL OR RM101 = '".$this->db_wrapper->getSite()."')
					AND RM03 LIKE '%0' 			-- without sub-case
					AND RM31 IS NULL			-- not closed case
					AND RM29_1 || RM29_2 < :bv_now
					AND RM29_1 || RM29_2 > :bv_start
					AND RM45 = :bv_reviewer_id
				ORDER BY RM29_1 DESC, RM29_2 DESC
			");
			$this->db_wrapper->getDB()->bind(":bv_reviewer_id", $reviewer_id);	// HBxxxx
		}

		$tw_date = new Datetime("now");
		$tw_date->modify("-1911 year");
		$now = ltrim($tw_date->format("YmdHis"), "0");	// ex: 1080325152111

		$date_15days_before = new Datetime("now");
		$date_15days_before->modify("-1911 year");
		$date_15days_before->modify("-15 days");
		$start = ltrim($date_15days_before->format("YmdHis"), "0");	// ex: 1090107081410
		
		
		Logger::getInstance()->info(__METHOD__.": Find overdue date between $start and $now cases.");

		$this->db_wrapper->getDB()->bind(":bv_now", $now);
		$this->db_wrapper->getDB()->bind(":bv_start", $start);
		$this->db_wrapper->getDB()->execute();
		return $this->db_wrapper->getDB()->fetchAll();
	}

	// 找快逾期的案件
	public function queryAlmostOverdueCases($reviewer_id = "") {
		if (!$this->db_wrapper->reachable()) {
			return array();
		}

		$query_str = "
			SELECT *
			FROM SCRSMS
			LEFT JOIN SRKEYN ON KCDE_1 = '06' AND RM09 = KCDE_2
			WHERE 1=1
				AND (RM99 IS NULL OR RM101 = '".$this->db_wrapper->getSite()."')
				AND RM03 LIKE '%0' 			-- without sub-case
				AND RM31 IS NULL			-- not closed case
				AND RM29_1 || RM29_2 < :bv_now_plus_4hrs
				AND RM29_1 || RM29_2 > :bv_now
		";
		if (empty($reviewer_id)) {
			$this->db_wrapper->getDB()->parse("
				${query_str}
				ORDER BY RM29_1 DESC, RM29_2 DESC
			");
		} else {
			$this->db_wrapper->getDB()->parse("
				${query_str}
					AND RM45 = :bv_reviewer_id
				ORDER BY RM29_1 DESC, RM29_2 DESC
			");
			$this->db_wrapper->getDB()->bind(":bv_reviewer_id", $reviewer_id);	// HBxxxx
		}

		

		$tw_date = new Datetime("now");
		$tw_date->modify("-1911 year");
		$now = ltrim($tw_date->format("YmdHis"), "0");	// ex: 1080325152111

		$date_4hrs_later = new Datetime("now");
		$date_4hrs_later->modify("-1911 year");
		$date_4hrs_later->modify("+4 hours");
		if ($date_4hrs_later->format("H") > 17) {
			Logger::getInstance()->info(__METHOD__.": ".$date_4hrs_later->format("YmdHis")." is over 17:00:00, so add 16 hrs ... ");
			$date_4hrs_later->modify("+16 hours");
		}
		$now_plus_4hrs = ltrim($date_4hrs_later->format("YmdHis"), "0");	// ex: 1090107081410
		
		Logger::getInstance()->info(__METHOD__.": Find overdue date between $now and $now_plus_4hrs cases.");

		$this->db_wrapper->getDB()->bind(":bv_now", $now);
		$this->db_wrapper->getDB()->bind(":bv_now_plus_4hrs", $now_plus_4hrs);
		$this->db_wrapper->getDB()->execute();
		return $this->db_wrapper->getDB()->fetchAll();
	}

	// 查詢指定收件日期之案件
    public function queryOverdueCasesByDate($qday) {
		if (!$this->db_wrapper->reachable()) {
			return array();
		}

        // only allow int number for $qday
        if (!filter_var($qday, FILTER_SANITIZE_NUMBER_INT)) {
            return false;
        }
		$this->db_wrapper->getDB()->parse("
			SELECT *
			FROM SCRSMS
			LEFT JOIN SRKEYN ON KCDE_1 = '06' AND RM09 = KCDE_2
			WHERE
				RM07_1 = :bv_qday
				AND (RM99 IS NULL OR RM101 = '".$this->db_wrapper->getSite()."')	-- 找本所案件
				AND RM03 LIKE '%0' 		-- without sub-case
				AND RM31 IS NULL
				AND RM29_1 || RM29_2 < :bv_qdatetime
			ORDER BY RM07_1, RM07_2 DESC
		");
		$this->db_wrapper->getDB()->bind(":bv_qday", $qday);
        $this->db_wrapper->getDB()->bind(":bv_qdatetime", $qday.date("His"));
		$this->db_wrapper->getDB()->execute();
		return $this->db_wrapper->getDB()->fetchAll();
	}
	
	// 找接近逾期案件
	public function queryNearOverdueCases() {
		if (!$this->db_wrapper->reachable()) {
			return array();
		}

		$this->db_wrapper->getDB()->parse("
			SELECT *
			FROM SCRSMS
			LEFT JOIN SRKEYN ON KCDE_1 = '06' AND RM09 = KCDE_2
			WHERE 1=1
				AND (RM99 IS NULL OR RM101 = '".$this->db_wrapper->getSite()."') -- 找本所案件
				AND RM31 IS NULL
				AND (RM29_1 || RM29_2 < :bv_4hours_later AND RM29_1 || RM29_2 > :bv_now)
			ORDER BY RM07_1, RM07_2 DESC
		");
		$tw_date = new Datetime("now");
		$tw_date->modify("-1911 year");
		$today = ltrim($tw_date->format("Ymd"), "0");	// ex: 1080325152111
		$this->db_wrapper->getDB()->bind(":bv_4hours_later", $today.date("His", strtotime("+4 hours")));
		$this->db_wrapper->getDB()->bind(":bv_now", $today.date("His"));
		$this->db_wrapper->getDB()->execute();
		return $this->db_wrapper->getDB()->fetchAll();
	}

	// 查詢指定收件日期之案件
	public function queryNearOverdueCasesByDate($qday) {
		if (!$this->db_wrapper->reachable()) {
			return array();
		}

        // only allow int number for $qday
        if (!filter_var($qday, FILTER_SANITIZE_NUMBER_INT)) {
            return false;
        }
		$this->db_wrapper->getDB()->parse("
			SELECT *
			FROM SCRSMS
			LEFT JOIN SRKEYN ON KCDE_1 = '06' AND RM09 = KCDE_2
			WHERE
				RM07_1 = :bv_qday
				AND (RM99 IS NULL OR RM101 = '".$this->db_wrapper->getSite()."')	-- 本所案件
				AND RM31 IS NULL
				AND (RM29_1 || RM29_2 < :bv_4hours_later AND RM29_1 || RM29_2 > :bv_now)
			ORDER BY RM07_1, RM07_2 DESC
		");
		$this->db_wrapper->getDB()->bind(":bv_qday", $qday);
		$this->db_wrapper->getDB()->bind(":bv_4hours_later", $qday.date("His", strtotime("+4 hours")));
		$this->db_wrapper->getDB()->bind(":bv_now", $qday.date("His"));
		$this->db_wrapper->getDB()->execute();
		return $this->db_wrapper->getDB()->fetchAll();
	}

	public function getRegCaseStatsMonthly($year_month) {
		if (!$this->db_wrapper->reachable()) {
			return array();
		}

		
        // only allow int number for $qday
        if (!filter_var($year_month, FILTER_SANITIZE_NUMBER_INT)) {
			Logger::getInstance()->info(__METHOD__.": 不允許非數字參數【${year_month}】");
            return false;
        }
		if (strlen($year_month) != 5) {
			Logger::getInstance()->info(__METHOD__.": 參數須為5碼【${year_month}】");
			return false;
		}
		
		$this->db_wrapper->getDB()->parse(
			"SELECT s.kcnt AS \"reason\", COUNT(*) AS \"count\"
			FROM SCRSMS t
			LEFT JOIN SRKEYN s
			  ON t.rm09 = s.kcde_2
			WHERE s.kcde_1 = '06'
			  AND RM07_1 LIKE :bv_year_month || '%'
			GROUP BY s.kcnt"
        );
        
		$this->db_wrapper->getDB()->bind(":bv_year_month", $year_month);
		$this->db_wrapper->getDB()->execute();
		return $this->db_wrapper->getDB()->fetchAll();
	}

    public function getRegCaseDetail($id) {
		if (!$this->db_wrapper->reachable()) {
			return array();
		}

        if (!$this->checkCaseID($id)) {
            return array();
		}
		
		$this->db_wrapper->getDB()->parse("
			SELECT s.*, v.KNAME AS RM11_CHT
				FROM (SELECT r.*, q.AB02
					FROM (
						SELECT t.*, m.KCNT 
						FROM MOICAS.CRSMS t
						LEFT JOIN MOIADM.RKEYN m ON (m.KCDE_1 = '06' AND t.RM09 = m.KCDE_2)
						WHERE t.RM01 = :bv_rm01_year and t.RM02 = :bv_rm02_code and t.RM03 = :bv_rm03_number
					) r
					LEFT JOIN MOICAS.CABRP q ON r.RM24 = q.AB01) s
				LEFT JOIN MOIADM.RKEYN_ALL v ON (v.KCDE_1 = '48' AND v.KCDE_2 = 'H' AND v.KCDE_3 = s.RM10 AND s.RM11 = v.KCDE_4)
		");
		
        $this->db_wrapper->getDB()->bind(":bv_rm01_year", substr($id, 0, 3));
        $this->db_wrapper->getDB()->bind(":bv_rm02_code", substr($id, 3, 4));
        $this->db_wrapper->getDB()->bind(":bv_rm03_number", substr($id, 7, 6));

		$this->db_wrapper->getDB()->execute();
		// true -> raw data with converting to utf-8
		return $this->db_wrapper->getDB()->fetch();
	}

    public function getSurCaseDetail($id) {
		if (!$this->db_wrapper->reachable()) {
			return array();
		}

        if (!$this->checkCaseID($id)) {
            return "";
		}
		
		$this->db_wrapper->getDB()->parse(
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
        
        $this->db_wrapper->getDB()->bind(":bv_year", substr($id, 0, 3));
        $this->db_wrapper->getDB()->bind(":bv_code", substr($id, 3, 4));
        $this->db_wrapper->getDB()->bind(":bv_number", substr($id, 7, 6));

		$this->db_wrapper->getDB()->execute();
		// true -> raw data with converting to utf-8
		return $this->db_wrapper->getDB()->fetch();
	}
	
	// 取得已結案卻延期複丈之案件
	public function getSurProblematicCases() {
		if (!$this->db_wrapper->reachable()) {
			return array();
		}

		$this->db_wrapper->getDB()->parse("
			select t.*, s.*, u.KCNT
			from MOICAS.CMSMS t
			left join MOICAS.CMSDS s
			on t.mm01 = s.md01
			and t.mm02 = s.md02
			and t.mm03 = s.md03
			left join MOIADM.RKEYN u
			on t.mm06 = u.kcde_2
			and u.kcde_1 = 'M3'
			where
			t.mm22 = 'C' 
			and t.mm23 in ('A', 'B', 'C', 'F')
			order by t.mm01, t.mm02, t.mm03
		");
		$this->db_wrapper->getDB()->execute();
		return $this->db_wrapper->getDB()->fetchAll();
	}

	public function fixSurDelayCase($id, $upd_mm22, $clr_delay, $fix_case_count = 'false') {
		if (!$this->db_wrapper->reachable()) {
			return false;
		}

		if (!$this->checkCaseID($id)) {
            return false;
		}

		$year = substr($id, 0, 3);
		$code = substr($id, 3, 4);
		$number = str_pad(substr($id, 7, 6), 6, "0", STR_PAD_LEFT);
		
		if ($upd_mm22 == "true") {
			$this->db_wrapper->getDB()->parse("
				UPDATE MOICAS.CMSMS SET MM22 = 'D'
				WHERE MM01 = :bv_year AND MM02 = :bv_code AND MM03 = :bv_number
			");
			$this->db_wrapper->getDB()->bind(":bv_year", $year);
			$this->db_wrapper->getDB()->bind(":bv_code", $code);
			$this->db_wrapper->getDB()->bind(":bv_number", $number);
			// UPDATE/INSERT can not use fetch after execute ... 
			$this->db_wrapper->getDB()->execute();
		}

		if ($clr_delay == "true") {
			$this->db_wrapper->getDB()->parse("
				UPDATE MOICAS.CMSDS SET MD12 = '', MD13_1 = '', MD13_2 = ''
				WHERE MD01 = :bv_year AND MD02 = :bv_code AND MD03 = :bv_number
			");
			$this->db_wrapper->getDB()->bind(":bv_year", $year);
			$this->db_wrapper->getDB()->bind(":bv_code", $code);
			$this->db_wrapper->getDB()->bind(":bv_number", $number);
			// UPDATE/INSERT can not use fetch after execute ... 
			$this->db_wrapper->getDB()->execute();
		}

		if ($fix_case_count == "true") {
			$this->db_wrapper->getDB()->parse("
				UPDATE MOICAS.CMSMS SET MM24 = :bv_count
				WHERE MM01 = :bv_year AND MM02 = :bv_code AND MM03 = :bv_number
			");
			$this->db_wrapper->getDB()->bind(":bv_year", $year);
			$this->db_wrapper->getDB()->bind(":bv_code", $code);
			$this->db_wrapper->getDB()->bind(":bv_number", $number);
			$this->db_wrapper->getDB()->bind(":bv_count", 1);
			// UPDATE/INSERT can not use fetch after execute ... 
			$this->db_wrapper->getDB()->execute();
		}

		return true;
	}

	public function getPrcCaseAll($id) {
		if (!$this->db_wrapper->reachable()) {
			return array();
		}

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
		$this->db_wrapper->getDB()->parse(iconv("utf-8", "big5", $sql));
		
        $this->db_wrapper->getDB()->bind(":bv_ss03_year", substr($id, 0, 3));
        $this->db_wrapper->getDB()->bind(":bv_ss04_1_code", substr($id, 3, 4));
        $this->db_wrapper->getDB()->bind(":bv_ss04_2_number", substr($id, 7, 6));

		$this->db_wrapper->getDB()->execute();
		return $this->db_wrapper->getDB()->fetchAll();
	}

	public function getSelectSQLData($sql, $raw = false) {
		if (!$this->db_wrapper->reachable()) {
			return array();
		}

		
		// non-select statement will skip
		if (!preg_match("/^SELECT/i", $sql)) {
			Logger::getInstance()->error(__METHOD__.": 非SELECT起始之SQL!");
			Logger::getInstance()->error(__METHOD__.": $sql");
			return false;
		}
		// second defense line
		if (preg_match("/^.*(INSERT|DELETE|UPDATE)/i", $sql)) {
			Logger::getInstance()->error(__METHOD__.": 不能使用非SELECT起始之SQL!");
			Logger::getInstance()->error(__METHOD__.": $sql");
			return false;
		}

		$this->db_wrapper->getDB()->parse(mb_convert_encoding(rtrim($sql, ";"), "big5", "utf-8"));
		$this->db_wrapper->getDB()->execute();
		return $this->db_wrapper->getDB()->fetchAll($raw);
	}
	
	public function getAnnouncementData() {
		if (!$this->db_wrapper->reachable()) {
			return array();
		}

		$this->db_wrapper->getDB()->parse("
			SELECT t.RA01, m.kcnt, t.RA02, t.RA03
			FROM MOICAS.CRACD t
			LEFT JOIN MOIADM.RKEYN m
			ON t.RA01 = m.kcde_2
			WHERE m.kcde_1 = '06'
			ORDER BY RA01
		");
		$this->db_wrapper->getDB()->execute();
		return $this->db_wrapper->getDB()->fetchAll();
	}

	public function updateAnnouncementData($code, $day, $flag) {
		if (!$this->db_wrapper->reachable()) {
			return false;
		}

		$this->db_wrapper->getDB()->parse("
			UPDATE MOICAS.CRACD SET RA02 = :bv_ra02_day, RA03 = :bv_ra03_flag WHERE RA01 = :bv_ra01_code
		");

		$this->db_wrapper->getDB()->bind(":bv_ra01_code", $code);
		$this->db_wrapper->getDB()->bind(":bv_ra02_day", $day);
		$this->db_wrapper->getDB()->bind(":bv_ra03_flag", $flag == "Y" ? $flag : "N");
		
		$this->db_wrapper->getDB()->execute();
		return true;
	}

	public function clearAnnouncementFlag() {
		if (!$this->db_wrapper->reachable()) {
			return false;
		}

		$this->db_wrapper->getDB()->parse("
			UPDATE MOICAS.CRACD SET RA03 = 'N' WHERE RA03 <> 'N'
		");
		$this->db_wrapper->getDB()->execute();
		return true;
	}

	public function getCaseTemp($year, $code, $number) {
		if (!$this->db_wrapper->reachable()) {
			return array();
		}

		$result = array();
		if (empty($year) || empty($code) || empty($number)) {
			return $result;
		}

		// prevent length not enough issue
		$number = str_pad($number, 6, "0", STR_PAD_LEFT);

		

		// an array to express temp tables and key field names that need to be checked.
		$temp_tables = include("config/Config.TempTables.php");
		//foreach ($temp_tables as $tmp_tbl_name => $key_fields) {
		foreach ($temp_tables as $tmp_tbl_name => $key) {
			/*
			Logger::getInstance()->info(__METHOD__.": 查詢 $tmp_tbl_name 的暫存資料 ... 【".$key_fields[0].", ".$key_fields[1].", ".$key_fields[2]."】");
			$this->db_wrapper->getDB()->parse("
				SELECT * FROM ".$tmp_tbl_name." WHERE ".$key_fields[0]." = :bv_year AND ".$key_fields[1]." = :bv_code AND ".$key_fields[2]." = :bv_number
			");
			*/
			// Logger::getInstance()->info(__METHOD__.": 查詢 $tmp_tbl_name 的暫存資料 ... 【".$key."03, ".$key."04_1, ".$key."04_2】");
			$this->db_wrapper->getDB()->parse("
				SELECT * FROM ".$tmp_tbl_name." WHERE ".$key."03 = :bv_year AND ".$key."04_1 = :bv_code AND ".$key."04_2 = :bv_number
			");

			$this->db_wrapper->getDB()->bind(":bv_year", $year);
			$this->db_wrapper->getDB()->bind(":bv_code", $code);
			$this->db_wrapper->getDB()->bind(":bv_number", $number);
			
			$this->db_wrapper->getDB()->execute();
			// for FE, 0 -> table name, 1 -> data, 2 -> SQL statement
			//$result[] = array($tmp_tbl_name, $this->db_wrapper->getDB()->fetchAll(), "SELECT * FROM ".$tmp_tbl_name." WHERE ".$key_fields[0]." = '$year' AND ".$key_fields[1]." = '$code' AND ".$key_fields[2]." = '$number'");
			$result[] = array($tmp_tbl_name, $this->db_wrapper->getDB()->fetchAll(), "SELECT * FROM ".$tmp_tbl_name." WHERE ".$key."03 = '$year' AND ".$key."04_1 = '$code' AND ".$key."04_2 = '$number'");
		}
		return $result;
	}

	public function clearCaseTemp($year, $code, $number, $table = "") {
		if (!$this->db_wrapper->reachable()) {
			return false;
		}

		

		if (empty($year) || empty($code) || empty($number)) {
			Logger::getInstance()->error(__METHOD__."：輸入案件參數不能為空白【${year}, ${code}, ${number}】");
			return false;
		}

		// prevent length not enough issue
		$number = str_pad($number, 6, "0", STR_PAD_LEFT);
		
		// an array to express temp tables and key field names that need to be checked.
		$temp_tables = include("config/Config.TempTables.php");
		//foreach ($temp_tables as $tmp_tbl_name => $key_fields) {
		foreach ($temp_tables as $tmp_tbl_name => $key) {
			if (!empty($table) && $tmp_tbl_name != $table) {
				Logger::getInstance()->info(__METHOD__."：指定刪除 $table 故跳過 $tmp_tbl_name 。");
				continue;
			}
			if ($tmp_tbl_name == "MOICAT.RINDX") {
				Logger::getInstance()->warning(__METHOD__."：無法刪除 MOICAT.RINDX 跳過！");
				continue;
			}
			if ($tmp_tbl_name == "MOIPRT.PHIND") {
				Logger::getInstance()->warning(__METHOD__."：無法刪除 MOIPRT.PHIND 跳過！");
				continue;
			}
			Logger::getInstance()->info(__METHOD__."：刪除 $tmp_tbl_name 資料 ... ");
			$this->db_wrapper->getDB()->parse("
				DELETE FROM ".$tmp_tbl_name." WHERE ".$key."03 = :bv_year AND ".$key."04_1 = :bv_code AND ".$key."04_2 = :bv_number
			");

			$this->db_wrapper->getDB()->bind(":bv_year", $year);
			$this->db_wrapper->getDB()->bind(":bv_code", $code);
			$this->db_wrapper->getDB()->bind(":bv_number", $number);
			
			$this->db_wrapper->getDB()->execute();
			
		}
		return true;
	}

	public function updateCaseColumnData($id, $table, $column, $val) {
		if (!$this->db_wrapper->reachable()) {
			return false;
		}

		if (empty($id) || empty($table) || empty($column) || (empty($val) && $val !== '0' && $val !=='')) {
			Logger::getInstance()->error(__METHOD__."：輸入參數不能為空白【 $id, $table, $column, $val 】");
			return false;
		}

		if (!$this->checkCaseID($id)) {
			Logger::getInstance()->error(__METHOD__."：ID格式不正確【應為13碼，目前： $id 】");
			return false;
		}

		$year_col = strpos($table, "CRSMS") ? "RM01" : "MM01";
		$code_col = strpos($table, "CRSMS") ? "RM02" : "MM02";
		$num_col = strpos($table, "CRSMS") ? "RM03" : "MM03";

		$year = substr($id, 0, 3);
		$code = substr($id, 3, 4);
		$num = substr($id, 7, 6);

		$sql = "UPDATE $table SET $column = :bv_val WHERE $year_col = :bv_year AND $code_col = :bv_code AND $num_col = :bv_number";
		
		Logger::getInstance()->info(__METHOD__."：預備執行 $sql");

		try {
			$this->db_wrapper->getDB()->parse($sql);
			$this->db_wrapper->getDB()->bind(":bv_year", $year);
			$this->db_wrapper->getDB()->bind(":bv_code", $code);
			$this->db_wrapper->getDB()->bind(":bv_number", $num);
			$this->db_wrapper->getDB()->bind(":bv_val", mb_convert_encoding($val, "big5"));
			$this->db_wrapper->getDB()->execute();

			$note = "year: $year, code: $code, number: $num, $column: $val\n\n$sql";
			SQLiteAdminActionLog::getInstance()->add(ADMIN_ACTION_TYPE::UPDATE_CRSMS_COLUMN, '/api/query_json_api/reg_upd_col', $note);
			
			return true;
		} catch (Exception $ex) {
			Logger::getInstance()->error(__CLASS__.'::'.__METHOD__.': '.$ex->getMessage());
		}
		return false;
	}

	// 取得權利人資料
	public function getRLNIDByID($id) {
		if (!$this->db_wrapper->reachable()) {
			return array();
		}

		$this->db_wrapper->getDB()->parse("
			select * from MOICAD.RLNID t
			where lidn like :bv_id
		");
		$this->db_wrapper->getDB()->bind(":bv_id", '%'.$id.'%');
		$this->db_wrapper->getDB()->execute();
		return $this->db_wrapper->getDB()->fetchAll();
	}

	/**
	 * 取得目前為公告狀態案件
	 */
	public function getRM30HCase() {
		if (!$this->db_wrapper->reachable()) {
			return array();
		}

		$this->db_wrapper->getDB()->parse("
			-- RM49 公告日期, RM50 公告到期日
			SELECT
				Q.KCNT AS RM09_CHT,
				sa11.USER_NAME AS RM45_USERNAME,
				sa12.USER_NAME AS RM30_1_USERNAME,
				s.*
			FROM
				MOICAS.CRSMS s LEFT JOIN MOIADM.RKEYN Q ON s.RM09=Q.KCDE_2 AND Q.KCDE_1 = '06',
				MOIADM.SYSAUTH1 sa11,
				MOIADM.SYSAUTH1 sa12
			WHERE s.RM30 = 'H'
				-- RM45 初審人員
				AND s.RM45 = sa11.USER_ID
				-- RM30_1 作業人員
				AND s.RM30_1 = sa12.USER_ID
				-- RM49 公告日期, RM50 公告到期日
			ORDER BY s.RM50, sa11.USER_NAME
		");
		$this->db_wrapper->getDB()->execute();
		return $this->db_wrapper->getDB()->fetchAll();
	}
	/**
	 * 取得目前為請示狀態案件
	 */
	public function getRM30ECase() {
		if (!$this->db_wrapper->reachable()) {
			return array();
		}

		$this->db_wrapper->getDB()->parse("
			SELECT * FROM MOICAS.CRSMS t
			LEFT JOIN MOIADM.RKEYN q ON t.RM09=q.KCDE_2 AND q.KCDE_1 = '06'
			WHERE RM80 IS NOT NULL AND RM31 NOT IN ('A', 'B', 'C', 'D')
			ORDER BY t.RM29_1, t.RM80
		");
		$this->db_wrapper->getDB()->execute();
		return $this->db_wrapper->getDB()->fetchAll();
	}
	/**
	 * 取得曾經取消請示案件
	 */
	public function getCancelRM30ECase() {
		if (!$this->db_wrapper->reachable()) {
			return array();
		}

		$this->db_wrapper->getDB()->parse("
			SELECT * FROM MOICAS.CRSMS t
			LEFT JOIN MOIADM.RKEYN q ON t.RM09=q.KCDE_2 AND q.KCDE_1 = '06'
			WHERE RM83 IS NOT NULL AND RM29_1 < RM58_1
			ORDER BY t.RM29_1, t.RM58_1
		");
		$this->db_wrapper->getDB()->execute();
		return $this->db_wrapper->getDB()->fetchAll();
	}
}
