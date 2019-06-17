<?php
require_once("init.php");
require_once("OraDB.class.php");
require_once("RegCaseData.class.php");

class Query {

    private $db;
    private $stid;

	private function checkPID($id) {
		if( !$id ) {
			return false;
		}
		$id = strtoupper(trim($id));
		// first char is Alphabet, second is [12ABCD], rest are digits
		$ereg_pattern = "^[A-Z]{1}[12ABCD]{1}[[:digit:]]{8}$";
		if(!ereg($ereg_pattern, $id)) {
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

	private function prepareXCaseDiffSetStatement($diff, $wanted_column = "") {
		$set_str = "";
		foreach ($diff as $col_name => $arr_vals) {
			if (!empty($wanted_column) && $col_name != $wanted_column) {
				continue;
			}
			$set_str .= $col_name." = '".$arr_vals["REMOTE"]."',";
		}
		return rtrim($set_str, ",");
	}

    function __construct() {
        $this->db = new OraDB();
    }

    function __destruct() {
        $this->db = null;
    }

	public function getSectionRALIDCount($cond = "") {
		$prefix = "
			-- 段小段面積計算 (RALID 登記－土地標示部)
			select AA48 as \"段代碼\",
				m.KCNT as \"段名稱\",
				SUM(AA10) as \"面積\",
				COUNT(AA10) as \"土地標示部筆數\"
			FROM MOICAD.RALID t
			LEFT JOIN MOIADM.RKEYN m on (m.KCDE_1 = '48' and m.KCDE_2 = t.AA48)
		";
		if (is_numeric($cond)) {
			$prefix .= "WHERE t.AA48 LIKE '%' || :bv_cond";
		} else if (!empty($cond)) {
			$prefix .= "WHERE m.KCNT LIKE '%' || :bv_cond || '%'";
		}
		$postfix = "
			GROUP BY t.AA48, m.KCNT
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
        return $this->db->fetch();
	}

	public function fixProblematicCrossCases($id) {
		if (empty($id) || !ereg("^[0-9A-Za-z]{13}$", $id)) {
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
	
	public function getMaxNumByYearWord($year, $word) {
		if (!filter_var($year, FILTER_SANITIZE_NUMBER_INT)) {
			return false;
		}
		$this->db->parse("
			SELECT * from MOICAS.CRSMS t
			WHERE RM01 = :bv_year AND RM02 = :bv_word AND rownum = 1
			ORDER BY RM01 DESC, RM03 DESC
		");
		
		$this->db->bind(":bv_year", $year);
		$this->db->bind(":bv_word", trim($word));
		$this->db->execute();
		$row = $this->db->fetch();
		return empty($row) ? "0" : ltrim($row["RM03"], "0");
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
        $this->db->parse("SELECT * FROM SCRSMS WHERE RM07_1 BETWEEN :bv_qday and :bv_qday ORDER BY RM07_1, RM07_2 DESC");
        $this->db->bind(":bv_qday", $qday);
		$this->db->execute();
		return $this->db->fetchAll();
    }

    public function getCaseDetail($id) {
        if (empty($id) || !ereg("^[0-9A-Za-z]{13}$", $id)) {
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
		return $this->db->fetch();
	}
	
	public function getXCaseDiff($id) {
        if (empty($id) || !ereg("^[0-9A-Za-z]{13}$", $id)) {
            return -1;
		}
		
		$diff_result = array();
		$code = substr($id, 3, 4);
		$db_user = "L1H".$code[1]."0H03";

		// connection switch to L1HWEB
		$this->db->connect(CONNECTION_TYPE::L1HWEB);
		$this->db->parse("
			SELECT *
			FROM $db_user.CRSMS t
			WHERE RM01 = :bv_rm01_year AND RM02 = :bv_rm02_code AND RM03 = :bv_rm03_number
		");
        $this->db->bind(":bv_rm01_year", substr($id, 0, 3));
        $this->db->bind(":bv_rm02_code", $code);
        $this->db->bind(":bv_rm03_number", substr($id, 7, 6));
		$this->db->execute();
		$remote_row = $this->db->fetch();

		// 遠端無此資料
		if (empty($remote_row)) {
			return -2;
		}

		// connection switch to MAIN
		$this->db->connect(CONNECTION_TYPE::MAIN);
		$this->db->parse("
			SELECT *
			FROM MOICAS.CRSMS t
			WHERE RM01 = :bv_rm01_year AND RM02 = :bv_rm02_code AND RM03 = :bv_rm03_number
		");
        $this->db->bind(":bv_rm01_year", substr($id, 0, 3));
        $this->db->bind(":bv_rm02_code", $code);
        $this->db->bind(":bv_rm03_number", substr($id, 7, 6));
		$this->db->execute();
		$local_row = $this->db->fetch();

		// 本地無此資料
		if (empty($local_row)) {
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
	
	public function instXCase($id) {
		if (empty($id) || !ereg("^[0-9A-Za-z]{13}$", $id)) {
            return -1;
		}
		
		$diff_result = array();
		$code = substr($id, 3, 4);
		$db_user = "L1H".$code[1]."0H03";

		// connection switch to L1HWEB
		$this->db->connect(CONNECTION_TYPE::L1HWEB);
		$this->db->parse("
			SELECT *
			FROM $db_user.CRSMS t
			WHERE RM01 = :bv_rm01_year AND RM02 = :bv_rm02_code AND RM03 = :bv_rm03_number
		");
        $this->db->bind(":bv_rm01_year", substr($id, 0, 3));
        $this->db->bind(":bv_rm02_code", $code);
        $this->db->bind(":bv_rm03_number", substr($id, 7, 6));
		$this->db->execute();
		$remote_row = $this->db->fetch();

		// 遠端無此資料
		if (empty($remote_row)) {
			return -2;
		}

		// connection switch to MAIN
		$this->db->connect(CONNECTION_TYPE::MAIN);
		$this->db->parse("
			SELECT *
			FROM MOICAS.CRSMS t
			WHERE RM01 = :bv_rm01_year AND RM02 = :bv_rm02_code AND RM03 = :bv_rm03_number
		");
        $this->db->bind(":bv_rm01_year", substr($id, 0, 3));
        $this->db->bind(":bv_rm02_code", $code);
        $this->db->bind(":bv_rm03_number", substr($id, 7, 6));
		$this->db->execute();
		$local_row = $this->db->fetch();

		// 本地無此資料才能新增
		if (empty($local_row)) {
			// 使用遠端資料新增本所資料
			$remote_row;
			$columns = "(";
			$values = "(";
			foreach ($remote_row as $key => $value) {
				$columns .= $key.",";
				$values .= "'".iconv("utf-8", "big5", $value)."',";
			}
			$columns = rtrim($columns, ",").")";
			$values = rtrim($values, ",").")";

			$this->db->parse("
				INSERT INTO MOICAS.CRSMS ".$columns." VALUES ".$values."
			");

			$this->db->execute();

			return true;
		}

		return false;
	}

	public function syncXCase($id) {
		return $this->syncXCaseColumn($id, "");
	}

	public function syncXCaseColumn($id, $column) {
		$diff = $this->getXCaseDiff($id);
		if (!empty($diff)) {
			$year = substr($id, 0, 3);
			$code = substr($id, 3, 4);
			$number = substr($id, 7, 6);

			$set_str = $this->prepareXCaseDiffSetStatement($diff, $column);

			$this->db->parse("
				UPDATE MOICAS.CRSMS SET ".$set_str." WHERE RM01 = :bv_rm01_year AND RM02 = :bv_rm02_code AND RM03 = :bv_rm03_number
			");

			$this->db->bind(":bv_rm01_year", $year);
			$this->db->bind(":bv_rm02_code", $code);
			$this->db->bind(":bv_rm03_number", $number);
			
			$this->db->execute();

			return true;
		}
		return false;
	}
	
	public function getSelectSQLData($sql) {
		// non-select statement will skip
		if (!eregi("^SELECT.+$", $sql)) {
			return false;
		}
		// second defense line
		if (eregi("^.*(INSERT|DELETE|UPDATE).*$", $sql)) {
			return false;
		}

		$this->db->parse(iconv("utf-8", "big5", rtrim($sql, ";")));
		$this->db->execute();
		return $this->db->fetchAll();
	}

    public function echoAllCasesHTML($qday) {
        $all = $this->queryAllCasesByDate($qday);

        // Fetch the results of the query
        $str = "<table id='case_results' class='table-hover text-center col-lg-12' border='1'>\n";
        $str .= "<thead id='case_table_header'><tr class='header'>".
            "<th id='fixed_th1' data-toggle='tooltip' title='依「收件字號」排序'>收件字號</th>\n".
            "<th id='fixed_th2' data-toggle='tooltip' title='依「收件時間」排序'>收件時間</th>\n".
            "<th id='fixed_th3' data-toggle='tooltip' title='依「限辦時限」排序'>限辦</th>\n".
            "<th id='fixed_th4' data-toggle='tooltip' title='依「辦理情形」排序'>情形</th>\n".
            "<th id='fixed_th5' data-toggle='tooltip' title='依「收件人員」排序'>收件人員</th>\n".
            "<th id='fixed_th6' data-toggle='tooltip' title='依「作業人員」排序'>作業人員</th>\n".
            "<th id='fixed_th7' data-toggle='tooltip' title='依「初審人員」排序'>初審人員</th>\n".
            "<th id='fixed_th8' data-toggle='tooltip' title='依「複審人員」排序'>複審人員</th>\n".
            "<th id='fixed_th9' data-toggle='tooltip' title='依「准登人員」排序'>准登人員</th>\n".
            "<th id='fixed_th10' data-toggle='tooltip' title='依「登錄人員」排序'>登錄人員</th>\n".
            "<th id='fixed_th11' data-toggle='tooltip' title='依「校對人員」排序'>校對人員</th>\n".
            "<th id='fixed_th12' data-toggle='tooltip' title='依「結案人員」排序'>結案人員</th>\n".
            "</tr></thead>\n";
        $str .= "<tbody>\n";
        $count = 0;
        foreach ($all as $row) {
            $count++;
            $data = new RegCaseData($row);
            $str .= "<tr class='".$data->getStatusCss()."'>\n";
            $str .= "<td class='text-right px-3'><a class='case ajax ".($data->isDanger() ? "text-danger" : "")."' href='#'>".$data->getReceiveSerial()."</a></td>\n".
                "<td data-toggle='tooltip' title='收件時間'>".$data->getReceiveTime()."</td>\n".
                "<td data-toggle='tooltip' data-placement='right' title='限辦期限：".$data->getDueDate()."'>".$data->getDueHrs()."</td>\n".
                "<td data-toggle='tooltip' title='辦理情形'>".$data->getStatus()."</td>\n".
                "<td ".$data->getReceptionistTooltipAttr().">".$data->getReceptionist()."</td>\n".
                "<td ".$data->getCurrentOperatorTooltipAttr().">".$data->getCurrentOperator()."</td>\n".
                "<td ".$data->getFirstReviewerTooltipAttr().">".$data->getFirstReviewer()."</td>\n".
                "<td ".$data->getSecondReviewerTooltipAttr().">".$data->getSecondReviewer()."</td>\n".
                "<td ".$data->getPreRegisterTooltipAttr().">".$data->getPreRegister()."</td>\n".
                "<td ".$data->getRegisterTooltipAttr().">".$data->getRegister()."</td>\n".
                "<td ".$data->getCheckerTooltipAttr().">".$data->getChecker()."</td>\n".
                "<td ".$data->getCloserTooltipAttr().">".$data->getCloser()."</td>\n";
            $str .= "</tr>\n";
        }
        $str .= "</tbody>\n";
        $str .= "</table>\n";

        $str = "<span>日期: ".$qday." 共 <span class='text-primary' id='record_count'>".$count."</span> 筆資料</span>\n" . $str;

        echo $str;
    }
}
?>
