<?php
require_once("init.php");
require_once("OraDB.class.php");
require_once("System.class.php");
require_once("Cache.class.php");

class MOIEXP {

	private $db;
	private $db_ok = true;
	private $site = 'HA';
	private $site_code = 'A';
	private $site_number = 1;

    private function isDBReachable($txt = __METHOD__) {
        $this->db_ok = System::getInstance()->isDBReachable();
        if (!$this->db_ok) {
            Logger::getInstance()->error('資料庫無法連線，無法取得資料。['.$txt.']');
        }
        return $this->db_ok;
    }

    function __construct() {
		if ($this->isDBReachable()) {
			$type = OraDB::getPointDBTarget();
			$this->db = new OraDB($type);
		}
		$this->site = strtoupper(System::getInstance()->get('SITE')) ?? 'HA';
		if (!empty($this->site)) {
			$this->site_code = $this->site[1];
			$this->site_number = ord($this->site_code) - ord('A');
		}
    }

    function __destruct() {
		if ($this->db) {
			$this->db->close();
		}
        $this->db = null;
    }

	public function getCodeData($year) {
		if (!$this->db_ok) {
			return array();
		}
		$sql = "
			-- 案件(REG + SUR)數量統計 BY 年
			SELECT t.RM01 AS YEAR, t.RM02 AS CODE, q.KCNT AS CODE_NAME, COUNT(*) AS COUNT,
				(CASE
					--WHEN REGEXP_LIKE(t.RM02, '^".$this->site."[[:alpha:]]1$') THEN 'reg.HBX1'
					WHEN RM02 IN ('".$this->site."A1', '".$this->site."B1', '".$this->site."C1', '".$this->site."D1', '".$this->site."E1', '".$this->site."F1', '".$this->site."G1', '".$this->site."H1') THEN 'reg.HBX1'
					WHEN t.RM02 LIKE 'H".$this->site_number."%'  THEN 'reg.H2XX'
					WHEN t.RM02 LIKE '%".$this->site."'  THEN 'reg.XXHB'
					WHEN t.RM02 LIKE 'H%".$this->site_code."1' THEN 'reg.HXB1'
					WHEN t.RM02 LIKE '".$this->site."%' THEN 'reg.HB'
					ELSE '登記案件'
				END) AS CODE_TYPE FROM MOICAS.CRSMS t
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
		
		$this->db->parse($sql);
		$this->db->bind(":bv_year", $year);
        $this->db->execute();
        return $this->db->fetchAll();
	}

	public function getEasycardPayment($qday = '') {
		if (!$this->db_ok) {
			return array();
		}
		// AA100 付款方式代碼，EXPK付款方式表格 (K02 => 中文)
		$this->db->parse("
			SELECT t.*, s.K02
			FROM MOIEXP.EXPAA t
			LEFT JOIN MOIEXP.EXPK s ON t.AA100 = s.K01
			WHERE AA01 >= :bv_qday AND AA106 <> '1' AND s.K02 = '悠遊卡'
		");
		if (empty($qday)) {
			global $week_ago;
			// fetch all data wthin a week back
			$this->db->bind(":bv_qday", $week_ago);
		} else {
			if (!filter_var($qday, FILTER_SANITIZE_NUMBER_INT)) {
            	return false;
			}
			$this->db->bind(":bv_qday", $qday);
		}
		$this->db->execute();
		return $this->db->fetchAll();
	}

	public function fixEasycardPayment($qday, $pc_num) {
		if (!$this->db_ok) {
			return false;
		}

		// ex: UPDATE MOIEXP.EXPAA SET AA106 = '1' WHERE AA01 = '1080321' AND AA106 <> '1' AND AA04 = '0015746';
		$this->db->parse("
			UPDATE MOIEXP.EXPAA SET AA106 = '1' WHERE AA01 = :bv_qday AND AA106 <> '1' AND AA04 = :bv_pc_num
		");
		$this->db->bind(":bv_qday", $qday);
		$this->db->bind(":bv_pc_num", $pc_num);
		// UPDATE/INSERT can not use fetch after execute ... 
		$this->db->execute();
		return true;
	}

	public function getExpkItems() {
		if (!$this->db_ok) {
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
		$this->db->parse("SELECT * FROM MOIEXP.EXPK t ORDER BY K01");
		$this->db->execute();
		return $this->db->fetchAll();
	}

	public function getExpacItems($year, $num) {
		if (!$this->db_ok) {
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
		if (!$this->db_ok) {
			return false;
		}

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
		if (!$this->db_ok) {
			return array();
		}

		if (empty($num)) {
			$this->db->parse("
				SELECT t.*, s.K02 AS AA100_CHT
				FROM MOIEXP.EXPAA t
				LEFT JOIN MOIEXP.EXPK s ON t.AA100 = s.K01
				WHERE t.AA01 = :bv_qday
			");
		} else {
			$this->db->parse("
				SELECT t.*, s.K02 AS AA100_CHT
				FROM MOIEXP.EXPAA t
				LEFT JOIN MOIEXP.EXPK s ON t.AA100 = s.K01
				WHERE t.AA04 = :bv_num AND t.AA01 = :bv_qday
			");
			$this->db->bind(":bv_num", $num);
		}
		$this->db->bind(":bv_qday", $qday);
		$this->db->execute();
		return $this->db->fetchAll();
	}

	public function getBakedExpaaData($rows) {
		$mapping = array();
		$cache = Cache::getInstance();
		// AA39 is 承辦人員, AA89 is 修改人員代碼
		$users = $cache->getUserNames();
		foreach ($rows[0] as $key => $value) {
			if (is_null($value)) {
				continue;
			}
			$col_mapping = include(INC_DIR."/config/Config.ColsNameMapping.EXPAA.php");
			if (empty($col_mapping[$key])) {
				$mapping[$key] = $value;
			} else {
				$mapping[$col_mapping[$key]] = ($key == "AA39" || $key == "AA89") ? $users[$value]."【${value}】" : $value;
			}
		}
		return $mapping;
	}

	public function getExpaaDataByPc($year, $keyword) {
		if (!$this->db_ok) {
			return array();
		}
		$this->db->parse("
			SELECT t.*, s.K02 AS AA100_CHT
			FROM MOIEXP.EXPAA t
			LEFT JOIN MOIEXP.EXPK s ON t.AA100 = s.K01
			WHERE t.AA04 = :bv_pcnum AND t.AA01 LIKE :bv_year
		");
		$this->db->bind(":bv_pcnum", $keyword);
		$this->db->bind(":bv_year", "${year}%");
		$this->db->execute();
		return $this->db->fetchAll();
	}

	public function getExpaaMaxPc($year) {
		if (!$this->db_ok) {
			return '0';
		}

		if (!filter_var($year, FILTER_SANITIZE_NUMBER_INT)) {
			return '0';
		}

		$this->db->parse("
			SELECT t.*, s.K02 AS AA100_CHT
			FROM MOIEXP.EXPAA t
			LEFT JOIN MOIEXP.EXPK s ON t.AA100 = s.K01
			WHERE t.AA01 LIKE :bv_year AND rownum = 1
			ORDER BY t.AA04 DESC
		");
		$this->db->bind(":bv_year", "${year}%");
		$this->db->execute();
		$row = $this->db->fetch();
		return empty($row) ? "0" : ltrim($row['AA04'], "0");
	}

	public function getExpaaDataByAa($keyword) {
		if (!$this->db_ok) {
			return array();
		}
		$this->db->parse("
			SELECT t.*, s.K02 AS AA100_CHT
			FROM MOIEXP.EXPAA t
			LEFT JOIN MOIEXP.EXPK s ON t.AA100 = s.K01
			WHERE t.AA05 = :bv_aa
		");
		$this->db->bind(":bv_aa", $keyword);
		$this->db->execute();
		return $this->db->fetchAll();
	}

	public function getExpaaLatestAa($year) {
		if (!$this->db_ok) {
			return '';
		}

		if (!filter_var($year, FILTER_SANITIZE_NUMBER_INT)) {
			return '';
		}

		$this->db->parse("
			SELECT t.*, s.K02 AS AA100_CHT
			FROM MOIEXP.EXPAA t
			LEFT JOIN MOIEXP.EXPK s ON t.AA100 = s.K01
			WHERE t.AA01 LIKE :bv_year AND rownum = 1
			ORDER BY t.AA04 DESC
		");
		$this->db->bind(":bv_year", "${year}%");
		$this->db->execute();
		$row = $this->db->fetch();
		return empty($row) ? "" : ltrim($row['AA05'], "");
	}

	public function updateExpaaData($column, $date, $number, $update_val) {
		if (!$this->db_ok) {
			return false;
		}

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
		if (!$this->db_ok) {
			return array();
		}

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
		if (!$this->db_ok) {
			return false;
		}

		
		if (empty($date) || empty($pc_num) || empty($operator) || empty($fee_number) || empty($reason)) {
			Logger::getInstance()->error(__METHOD__.": One of the parameters is empty. The system can not add obsolete fee expaa data.");
			Logger::getInstance()->warning(__METHOD__.": The input params: ${date}, ${pc_num}, ${operator}, ${fee_number}, ${reason}.");
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

		Logger::getInstance()->info(__METHOD__.": 插入 SQL \"$sql\"");

		$this->db->execute();
		return true;
	}
	
	public function getExpeItems() {
		if (!$this->db_ok) {
			return array();
		}
		$this->db->parse("select * from MOIEXP.EXPE t");
		$this->db->execute();
		return $this->db->fetchAll();
	}
}
