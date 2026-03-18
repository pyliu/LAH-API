<?php
require_once("init.php");
require_once("OraDBWrapper.class.php");
/**
 * 跨所相關操作專用類別
 */
class XCase {
	private $db_wrapper = null;

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

	private function insertLocalCRCLD($l3_crcld) {
		$result = false;
		if (is_array($l3_crcld)) {
			$year = $l3_crcld['CL01'];
			$code = $l3_crcld['CL02'];
			$num = $l3_crcld['CL03'];
			$rc04 = $l3_crcld['CL04'];	// 序號
			$rc01 = $l3_crcld['CL05'];	// 年
			$rc06 = $l3_crcld['CL06'];	// 所別
			// connection switch to MAIN
			$this->db_wrapper->getDB()->setConnType(CONNECTION_TYPE::MAIN);
			$this->db_wrapper->getDB()->parse("
				INSERT INTO MOICAS.CRCLD (CL01,CL02,CL03,CL04,CL05,CL06) VALUES (
					:bv_year,
					:bv_code,
					:bv_num,
					:bv_rc04,
					:bv_rc01,
					:bv_rc06
				)
			");
			$this->db_wrapper->getDB()->bind(":bv_year", $year);
			$this->db_wrapper->getDB()->bind(":bv_code", $code);
			$this->db_wrapper->getDB()->bind(":bv_number", $num);
			$this->db_wrapper->getDB()->bind(":bv_rc04", $rc04);
			$this->db_wrapper->getDB()->bind(":bv_rc01", $rc01);
			$this->db_wrapper->getDB()->bind(":bv_rc06", $rc06);
			$result = $this->db_wrapper->getDB()->execute() === FALSE ? false : true;
		}
		return $result;
	}

	private function updateLocalCRCLD($l3_crcld) {
		$result = false;
		if (is_array($l3_crcld)) {
			$year = $l3_crcld['CL01'];
			$code = $l3_crcld['CL02'];
			$num = $l3_crcld['CL03'];
			$rc04 = $l3_crcld['CL04'];	// 序號
			$rc01 = $l3_crcld['CL05'];	// 年
			$rc06 = $l3_crcld['CL06'];	// 所別
			// connection switch to MAIN
			$this->db_wrapper->getDB()->setConnType(CONNECTION_TYPE::MAIN);
			$this->db_wrapper->getDB()->parse("
				UPDATE MOICAS.CRCLD SET 
					CL04 = :bv_rc04,
					CL05 = :bv_rc01,
					CL06 = :bv_rc06
				WHERE
					CL01 = :bv_year
					and CL02 = :bv_code
					and CL03 = :bv_number
			");
			$this->db_wrapper->getDB()->bind(":bv_year", $year);
			$this->db_wrapper->getDB()->bind(":bv_code", $code);
			$this->db_wrapper->getDB()->bind(":bv_number", $num);
			$this->db_wrapper->getDB()->bind(":bv_rc04", $rc04);
			$this->db_wrapper->getDB()->bind(":bv_rc01", $rc01);
			$this->db_wrapper->getDB()->bind(":bv_rc06", $rc06);
			$result = $this->db_wrapper->getDB()->execute() === FALSE ? false : true;
		}
		return $result;
	}

	private function insertLocalCRCRD($l3_crcrd) {
		if (is_array($l3_crcrd)) {
			$content = $l3_crcrd['RC05'];	// 補正資料內容
			$rcsel1 = $l3_crcrd['RCSEL1'];
			$rc04 = $l3_crcrd['RC04'];	// 序號
			$rc01 = $l3_crcrd['RC01'];	// 年
			$rc06 = $l3_crcrd['RC06'];	// 所別
			// connection switch to MAIN
			$this->db_wrapper->getDB()->setConnType(CONNECTION_TYPE::MAIN);
			$this->db_wrapper->getDB()->parse("
				INSERT INTO MOICAS.CRCRD (RC01,RC04,RC05,RC06,RCSEL1) VALUES (
					:bv_rc01,
					:bv_rc04,
					:bv_rc05,
					:bv_rc06,
					:bv_rcsel1
				)
			");
			$this->db_wrapper->getDB()->bind(":bv_rc01", $rc01);
			$this->db_wrapper->getDB()->bind(":bv_rc04", $rc04);
			$this->db_wrapper->getDB()->bind(":bv_rc05", $content);
			$this->db_wrapper->getDB()->bind(":bv_rc06", $rc06);
			$this->db_wrapper->getDB()->bind(":bv_rcsel1", $rcsel1);
			$this->db_wrapper->getDB()->execute();
			return true;
		}
		Logger::getInstance()->warning(__METHOD__.": 插入本地 MOICAS.CRCRD 補正資料失敗");
		return false;
	}

	private function updateLocalCRCRD($l3_crcrd) {
		if (is_array($l3_crcrd)) {
			$content = $l3_crcrd['RC05'];	// 補正資料內容
			$rcsel1 = $l3_crcrd['RCSEL1'];
			$rc04 = $l3_crcrd['RC04'];	// 序號
			$rc01 = $l3_crcrd['RC01'];	// 年
			$rc06 = $l3_crcrd['RC06'];	// 所別
			// connection switch to MAIN
			$this->db_wrapper->getDB()->setConnType(CONNECTION_TYPE::MAIN);
			$this->db_wrapper->getDB()->parse("
				UPDATE MOICAS.CRCRD SET 
					RC05 = :bv_rc05,
					RCSEL1 = :bv_rcsel1
				WHERE
					RC01 = :bv_rc01
					AND RC04 = :bv_rc04
					AND RC06 = :bv_rc06
			");
			$this->db_wrapper->getDB()->bind(":bv_rc01", $rc01);
			$this->db_wrapper->getDB()->bind(":bv_rc04", $rc04);
			$this->db_wrapper->getDB()->bind(":bv_rc05", $content);
			$this->db_wrapper->getDB()->bind(":bv_rc06", $rc06);
			$this->db_wrapper->getDB()->bind(":bv_rcsel1", $rcsel1);
			$this->db_wrapper->getDB()->execute();
			return true;
		}
		Logger::getInstance()->warning(__METHOD__.": 更新本地 MOICAS.CRCRD 補正資料失敗");
		return false;
	}

	function __construct() {
		$this->db_wrapper = new OraDBWrapper();
	}

	function __destruct() {
		$this->db_wrapper = null;
	}

	public function getLocalDBMaxNumByWord($code, $year = '') {
		if (!$this->db_wrapper->reachable()) {
			return false;
		}
		if (empty($year)) {
			global $this_year;
			$year = $this_year;
		}
		if (!filter_var($year, FILTER_SANITIZE_NUMBER_INT) || strlen($year) !== 3) {
			Logger::getInstance()->warning(__METHOD__.": \$year 不符合規範。($year)");
			return false;
		}
		// connection switch to MAIN
		$this->db_wrapper->getDB()->setConnType(CONNECTION_TYPE::MAIN);
		$num_key = "RM03";
		$this->db_wrapper->getDB()->parse("
			SELECT * FROM (
				SELECT * from MOICAS.CRSMS t
				WHERE RM01 = :bv_year AND RM02 = :bv_code
				ORDER BY RM03 DESC
			) WHERE ROWNUM = 1
		");
		$this->db_wrapper->getDB()->bind(":bv_year", $year);
		$this->db_wrapper->getDB()->bind(":bv_code", trim($code));
		$this->db_wrapper->getDB()->execute();
		$row = $this->db_wrapper->getDB()->fetch();

		return empty($row) ? "0" : ltrim($row[$num_key], "0");
	}

	public function getXCaseCRCLD($id) {
		if (!$this->db_wrapper->reachable()) {
			return -1;
		}

    if (!$this->checkCaseID($id)) {
      return -2;
		}
		
		$year = substr($id, 0, 3);
		$code = substr($id, 3, 4);
		$num = substr($id, 7, 6);
		$db_user = "L1H".$code[1]."0H03";

		Logger::getInstance()->info(__METHOD__.": 找遠端 $db_user.CRCLD 的案件連結資料【$year, $code, $num"."】");

		// connection switch to L3HWEB
		$this->db_wrapper->getDB()->setConnType(CONNECTION_TYPE::L3HWEB);
		$this->db_wrapper->getDB()->parse("
			SELECT * FROM $db_user.CRCLD t
			WHERE 1=1
				AND cl01 = :bv_year
				AND cl02 = :bv_code
				AND cl03 = :bv_number
		");
		$this->db_wrapper->getDB()->bind(":bv_year", $year);
		$this->db_wrapper->getDB()->bind(":bv_code", $code);
		$this->db_wrapper->getDB()->bind(":bv_number", $num);
		$this->db_wrapper->getDB()->execute();
		$remote_row = $this->db_wrapper->getDB()->fetch(true);

		// 遠端無連結資料
		if (empty($remote_row)) {
			Logger::getInstance()->warning(__METHOD__.": 遠端 $db_user.CRCLD 查無 $year-$code-$num 案件連結資料");
			return -3;
		}

		return $remote_row;
	}
	
	public function getLocalCRCLD($id) {
		if (!$this->db_wrapper->reachable()) {
			return -1;
		}

    if (!$this->checkCaseID($id)) {
      return -1;
		}
		
		$year = substr($id, 0, 3);
		$code = substr($id, 3, 4);
		$num = substr($id, 7, 6);
		$db_user = "MOICAS";

		Logger::getInstance()->info(__METHOD__.": 找本地 $db_user.CRCLD 的案件連結資料【$year, $code, $num"."】");

		// connection switch to MAIN
		$this->db_wrapper->getDB()->setConnType(CONNECTION_TYPE::MAIN);
		$this->db_wrapper->getDB()->parse("
			select * from $db_user.CRCLD t
			where 1=1
				and cl01 = :bv_year
				and cl02 = :bv_code
				and cl03 = :bv_number
		");
		$this->db_wrapper->getDB()->bind(":bv_year", $year);
		$this->db_wrapper->getDB()->bind(":bv_code", $code);
		$this->db_wrapper->getDB()->bind(":bv_number", $num);
		$this->db_wrapper->getDB()->execute();
		$row = $this->db_wrapper->getDB()->fetch(true);

		// 無連結資料
		if (empty($row)) {
			Logger::getInstance()->warning(__METHOD__.": 本地 $db_user.CRCLD 查無 $year-$code-$num 案件連結資料");
			return -2;
		}

		return $row;
	}
	
	public function getXCaseCRCRD($crcld) {
		if (!$this->db_wrapper->reachable()) {
			return -1;
		}

    if (!is_array($crcld)) {
      return -2;
		}
		
		// $year = $crcld['CL01'];
		// $code = $crcld['CL02'];
		// $num = $crcld['CL03'];
		$rc04 = $crcld['CL04'];	// 序號
		$rc01 = $crcld['CL05'];	// 年
		$rc06 = $crcld['CL06'];	// 所別

		$db_user = "L1H".$rc06[1]."0H03";

		Logger::getInstance()->info(__METHOD__.": 找遠端 $db_user.CRCRD 的案件補正資料【$rc04, $rc01, $rc06"."】");

		// connection switch to L3HWEB
		$this->db_wrapper->getDB()->setConnType(CONNECTION_TYPE::L3HWEB);
		$this->db_wrapper->getDB()->parse("
			SELECT * FROM $db_user.CRCRD t
			WHERE 1=1
				and RC01 = :bv_rc01
				and RC04 = :bv_rc04
				and RC06 = :bv_rc06
		");
		$this->db_wrapper->getDB()->bind(":bv_rc01", $rc01);
		$this->db_wrapper->getDB()->bind(":bv_rc04", $rc04);
		$this->db_wrapper->getDB()->bind(":bv_rc06", $rc06);
		$this->db_wrapper->getDB()->execute();
		$remote_row = $this->db_wrapper->getDB()->fetch(true);

		// 遠端無補正資料
		if (empty($remote_row)) {
			Logger::getInstance()->warning(__METHOD__.": 遠端 $db_user.CRCRD 查無 $rc04-$rc01-$rc06 案件補正資料");
			return -2;
		}

		return $remote_row;
	}
	
	public function getLocalCRCRD($crcld) {
		if (!$this->db_wrapper->reachable()) {
			return -1;
		}

    if (!is_array($crcld)) {
      return -2;
		}
		
		$rc04 = $crcld['CL04'];	// 序號
		$rc01 = $crcld['CL05'];	// 年
		$rc06 = $crcld['CL06'];	// 所別
		$db_user = "MOICAS";

		Logger::getInstance()->info(__METHOD__.": 找本地 $db_user.CRCRD 的案件補正資料【$rc04-$rc01-$rc06"."】");

		// connection switch to MAIN
		$this->db_wrapper->getDB()->setConnType(CONNECTION_TYPE::MAIN);
		$this->db_wrapper->getDB()->parse("
			SELECT * FROM $db_user.CRCRD t
			WHERE 1=1
				and RC01 = :bv_rc01
				and RC04 = :bv_rc04
				and RC06 = :bv_rc06
		");
		$this->db_wrapper->getDB()->bind(":bv_rc01", $rc01);
		$this->db_wrapper->getDB()->bind(":bv_rc04", $rc04);
		$this->db_wrapper->getDB()->bind(":bv_rc06", $rc06);
		$this->db_wrapper->getDB()->execute();
		$row = $this->db_wrapper->getDB()->fetch(true);

		// 無補正資料
		if (empty($row)) {
			Logger::getInstance()->warning(__METHOD__.": 本地 $db_user.CRCRD 查無 $rc04-$rc01-$rc06 案件補正資料");
			return -3;
		}

		return $row;
	}
	/**
	 * Public interface for 同步跨所登記補正資料
	 */
	public function syncXCaseFixData($id) {
		// L1HX0H03 has link data of the case (先取得 L3 連結資料)
		$l3_crcld = $this->getXCaseCRCLD($id);
		if (is_array($l3_crcld)) {
			// 檢查本地 CRCLD 並更新資料
			$local_crcld = $this->getLocalCRCLD($id);
			if ($local_crcld === -1) {
				Logger::getInstance()->warning(__METHOD__.": 取得本地案件 $id 補正連結資料資料庫無法連線(回傳值：$local_crcld)");
				return false;
			} else if ($local_crcld === -2) {
				Logger::getInstance()->info(__METHOD__.": 本地端無CRCLD連結資料，需進行新增動作");
				$this->insertLocalCRCLD($l3_crcld);
			} else if (is_array($local_crcld)) {
				Logger::getInstance()->info(__METHOD__.": 本地端已有CRCLD連結資料，需進行更新動作");
				$this->updateLocalCRCLD($l3_crcld);
			}
			// 檢查 CRCRD 並更新資料
			$rc04 = $l3_crcld['CL04'];	// 序號
			$rc01 = $l3_crcld['CL05'];	// 年
			$rc06 = $l3_crcld['CL06'];	// 所別
			$office_code = $l3_crcld['CL02'][1];
			$l3_crcrd = $this->getXCaseCRCRD($l3_crcld, $office_code);
			if (is_array($l3_crcrd)) {
				// 檢查本地 CRCRD 並更新資料
				$local_crcrd = $this->getLocalCRCRD($l3_crcld);
				if ($local_crcrd === -1) {
					Logger::getInstance()->warning(__METHOD__.": 取得本地案件 $rc01-$rc04-$rc06 補正資料資料庫無法連線(回傳值：$local_crcrd)");
					return false;
				} else if ($local_crcrd === -2) {
					Logger::getInstance()->warning(__METHOD__.": 傳入之CRCLD資料有誤資料");
					Logger::getInstance()->warning(__METHOD__.": \$l3_crcld 👉 ".print_r($l3_crcld, true));
					return false;
				} else if ($local_crcrd === -3) {
					Logger::getInstance()->info(__METHOD__.": 本地端無CRCRD補正資料，需進行新增動作");
					$this->insertLocalCRCRD($l3_crcrd);
				} else if (is_array($local_crcrd)) {
					Logger::getInstance()->info(__METHOD__.": 本地端已有CRCRD補正資料，需進行更新動作");
					$this->updateLocalCRCRD($l3_crcrd);
				}
				Logger::getInstance()->info(__METHOD__.": 同步遠端案件 $rc01-$rc04-$rc06 補正資料成功");
				return $l3_crcrd['RC05'];
			}
			Logger::getInstance()->warning(__METHOD__.": 同步遠端案件 $rc01-$rc04-$rc06 補正資料錯誤(回傳值：$l3_crcrd)");
			return false;
		}
		Logger::getInstance()->warning(__METHOD__.": 取得遠端案件 $id 補正連結資料錯誤(回傳值：$l3_crcld)");
		return false;
	}
	/**
	 * Public interface for 取得跨所登記補正資料
	 */
	public function getXCaseFixData($id) {
		// L1HX0H03 has link data of the case (先取得 L3 連結資料)
		$l3_crcld = $this->getXCaseCRCLD($id);
		if (is_array($l3_crcld)) {
			// 檢查 CRCRD 並更新資料
			$rc04 = $l3_crcld['CL04'];	// 序號
			$rc01 = $l3_crcld['CL05'];	// 年
			$rc06 = $l3_crcld['CL06'];	// 所別
			$office_code = $l3_crcld['CL02'][1];
			$l3_crcrd = $this->getXCaseCRCRD($l3_crcld, $office_code);
			if (is_array($l3_crcrd)) {
				Logger::getInstance()->warning(__METHOD__.": 取得遠端案件 $rc01-$rc04-$rc06 補正資料成功");
				return $l3_crcrd;
			}
			Logger::getInstance()->warning(__METHOD__.": 取得遠端案件 $rc01-$rc04-$rc06 補正資料錯誤(回傳值：$l3_crcrd)");
			return false;
		}
		Logger::getInstance()->warning(__METHOD__.": 取得遠端案件 $id 補正連結資料錯誤(回傳值：$l3_crcld)");
		return false;
	}
	/**
	 * Public interface for 找出跨所回寫出問題案件
	 */
	public function findFailureXCases() {
		$codes = array_keys(REG_CODE["本所收件"]);
		$site = System::getInstance()->getSiteCode();
		// 過濾掉本所收本所的跨所收件碼
		$filtered_codes = array_filter($codes, function($code) use ($site) {
				// 檢查 $code 字串的開頭是否為 $site
				// strpos() 的回傳值若為 0，代表 $site 就在字串的開頭
				// 我們要保留的是「開頭不是 $site」的元素，所以條件是 !== 0
				return strpos($code, $site) !== 0;
		});
		global $this_year;
		$info = array();
		foreach ($filtered_codes as $code) {
				$latestNum = $this->getLocalDBMaxNumByWord($code);
				$info[$code] = array(
					"localMax" => $latestNum,
					"foundIds" => []
				);
				if ($latestNum > 0) {
						$result = false;
						$step = 10;
						do {
								$nextNum = str_pad($latestNum + $step, 6, '0', STR_PAD_LEFT);
								// e.g. 114HAB1017600
								$nextCaseID = $this_year.$code.$nextNum;
								Logger::getInstance()->info(__METHOD__.": 檢查 $nextCaseID 在本地資料庫的狀態。");
								$result = $this->getXCaseDiff($nextCaseID);
								// -3 means local db has no such case
								if ($result === -3) {
										$info[$code]['foundIds'][] = "$this_year-$code-$nextNum";
										Logger::getInstance()->info(__METHOD__.": 找到 $nextCaseID 未存在於本地資料庫。");
								}
								$step += 10;
								// -1 means db not reachable or case id format is not correct
								// -2 means remote db has no such case
								// -3 means local db has no such case
								// otherwise returns case data array
						} while($result === -3);
				}
		}
		// returns info array
		return $info;
	}

	public function getXCaseDiff($id, $raw = false) {
		if (!$this->db_wrapper->reachable()) {
			return -1;
		}

    if (!$this->checkCaseID($id)) {
      return -1;
		}
		
		$diff_result = array();
		$year = substr($id, 0, 3);
		$code = substr($id, 3, 4);
		$num = substr($id, 7, 6);
		$db_user = "L1H".$code[1]."0H03";

		
		Logger::getInstance()->info(__METHOD__.": 找遠端 ${db_user}.CRSMS 的案件資料【${year}, ${code}, ${num}】");

		// connection switch to L3HWEB
		$this->db_wrapper->getDB()->setConnType(CONNECTION_TYPE::L3HWEB);
		$this->db_wrapper->getDB()->parse("
			SELECT *
			FROM $db_user.CRSMS t
			WHERE RM01 = :bv_rm01_year AND RM02 = :bv_rm02_code AND RM03 = :bv_rm03_number
		");
		$this->db_wrapper->getDB()->bind(":bv_rm01_year", $year);
		$this->db_wrapper->getDB()->bind(":bv_rm02_code", $code);
		$this->db_wrapper->getDB()->bind(":bv_rm03_number", $num);
		$this->db_wrapper->getDB()->execute();
		$remote_row = $this->db_wrapper->getDB()->fetch($raw);

		// 遠端無此資料
		if (empty($remote_row)) {
			Logger::getInstance()->warning(__METHOD__.": 遠端 ${db_user}.CRSMS 查無 ${year}-${code}-${num} 案件資料");
			return -2;
		}

		Logger::getInstance()->info(__METHOD__.": 找本地 MOICAS.CRSMS 的案件資料【${year}, ${code}, ${num}】");

		// connection switch to MAIN
		$this->db_wrapper->getDB()->setConnType(CONNECTION_TYPE::MAIN);
		$this->db_wrapper->getDB()->parse("
			SELECT *
			FROM MOICAS.CRSMS t
			WHERE RM01 = :bv_rm01_year AND RM02 = :bv_rm02_code AND RM03 = :bv_rm03_number
		");
        $this->db_wrapper->getDB()->bind(":bv_rm01_year", $year);
        $this->db_wrapper->getDB()->bind(":bv_rm02_code", $code);
        $this->db_wrapper->getDB()->bind(":bv_rm03_number", $num);
		$this->db_wrapper->getDB()->execute();
		$local_row = $this->db_wrapper->getDB()->fetch($raw);

		// 本地無此資料
		if (empty($local_row)) {
			Logger::getInstance()->warning(__METHOD__.": 本地 MOICAS.CRSMS 查無 ${year}-${code}-${num} 案件資料");
			return -3;
		}

		$colsNameMapping = include("config/Config.ColsNameMapping.CRSMS.php");
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
		if (!$this->db_wrapper->reachable()) {
			return false;
		}

		if (!$this->checkCaseID($id)) {
      return -1;
		}

		$year = substr($id, 0, 3);
		$code = substr($id, 3, 4);
		$num = substr($id, 7, 6);
		$db_user = "L1H".$code[1]."0H03";

		// connection switch to L3HWEB
		$this->db_wrapper->getDB()->setConnType(CONNECTION_TYPE::L3HWEB);
		$this->db_wrapper->getDB()->parse("
			SELECT *
			FROM $db_user.CRSMS t
			WHERE RM01 = :bv_rm01_year AND RM02 = :bv_rm02_code AND RM03 = :bv_rm03_number
		");
		$this->db_wrapper->getDB()->bind(":bv_rm01_year", $year);
		$this->db_wrapper->getDB()->bind(":bv_rm02_code", $code);
		$this->db_wrapper->getDB()->bind(":bv_rm03_number", $num);
		$this->db_wrapper->getDB()->execute();
		$remote_row = $this->db_wrapper->getDB()->fetch($raw);

		// 遠端無此資料
		if (empty($remote_row)) {
			Logger::getInstance()->warning(__METHOD__.": 遠端 $db_user.CRSMS 查無 $year-$code-$num 案件資料");
			return -2;
		}

		// connection switch to MAIN
		$this->db_wrapper->getDB()->setConnType(CONNECTION_TYPE::MAIN);
		$this->db_wrapper->getDB()->parse("
			SELECT *
			FROM MOICAS.CRSMS t
			WHERE RM01 = :bv_rm01_year AND RM02 = :bv_rm02_code AND RM03 = :bv_rm03_number
		");
		$this->db_wrapper->getDB()->bind(":bv_rm01_year", $year);
		$this->db_wrapper->getDB()->bind(":bv_rm02_code", $code);
		$this->db_wrapper->getDB()->bind(":bv_rm03_number", $num);
		$this->db_wrapper->getDB()->execute();
		$local_row = $this->db_wrapper->getDB()->fetch($raw);

		// 本地無此資料才能新增
		if (empty($local_row)) {
			// 使用遠端資料新增本所資料
			$remote_row;
			$columns = "(";
			$values = "(";
			foreach ($remote_row as $key => $value) {
				$columns .= $key.",";
				$values .= "'".($raw ? $value : iconv("utf-8", ORACLE_ENCODING, $value))."',";
			}
			$columns = rtrim($columns, ",").")";
			$values = rtrim($values, ",").")";

			$this->db_wrapper->getDB()->parse("
				INSERT INTO MOICAS.CRSMS ".$columns." VALUES ".$values."
			");

			Logger::getInstance()->info(__METHOD__.": 插入 SQL \"INSERT INTO MOICAS.CRSMS ".$columns." VALUES ".$values."\"");
			$this->db_wrapper->getDB()->execute();

			return true;
		}
		Logger::getInstance()->error(__METHOD__.": 本地 MOICAS.CRSMS 已有 $year-$code-$num 案件資料");
		return false;
	}

	public function syncXCase($id) {
		return $this->syncXCaseColumn($id, "");
	}

	public function syncXCaseColumn($id, $wanted_column) {
		if (!$this->db_wrapper->reachable()) {
			return false;
		}

		$diff = $this->getXCaseDiff($id, true);	// true -> use raw data to update
		if (!empty($diff)) {
			
			$year = substr($id, 0, 3);
			$code = substr($id, 3, 4);
			$number = str_pad(substr($id, 7, 6), 6, "0", STR_PAD_LEFT);

			$set_str = "";
			foreach ($diff as $col_name => $arr_vals) {
				if (!empty($wanted_column) && $col_name != $wanted_column) {
					continue;
				}
				$set_str .= $col_name." = '".$arr_vals["REMOTE"]."',";
			}
			$set_str = rtrim($set_str, ",");

			$this->db_wrapper->getDB()->parse("
				UPDATE MOICAS.CRSMS SET ".$set_str." WHERE RM01 = :bv_rm01_year AND RM02 = :bv_rm02_code AND RM03 = :bv_rm03_number
			");

			$this->db_wrapper->getDB()->bind(":bv_rm01_year", $year);
			$this->db_wrapper->getDB()->bind(":bv_rm02_code", $code);
			$this->db_wrapper->getDB()->bind(":bv_rm03_number", $number);

			Logger::getInstance()->info(__METHOD__.": 更新 SQL \"UPDATE MOICAS.CRSMS SET ".$set_str." WHERE RM01 = '$year' AND RM02 = '$code' AND RM03 = '$number'\"");

			$this->db_wrapper->getDB()->execute();

			return true;
		}
		return false;
	}

	public function getProblematicXCases() {
		if (!$this->db_wrapper->reachable()) {
			return array();
		}

		$site_code = $this->db_wrapper->getSiteCode();
		$this->db_wrapper->getDB()->parse("
			SELECT *
				FROM MOICAS.CRSMS
			WHERE (
				    -- 本所跨他所
						RM02 LIKE 'H%".$site_code."1'
						-- 他所跨本所
						OR RM02 LIKE 'H".$site_code."A1'
						OR RM02 LIKE 'H".$site_code."B1'
						OR RM02 LIKE 'H".$site_code."B1'
						OR RM02 LIKE 'H".$site_code."C1'
						OR RM02 LIKE 'H".$site_code."D1'
						OR RM02 LIKE 'H".$site_code."E1'
						OR RM02 LIKE 'H".$site_code."F1'
						OR RM02 LIKE 'H".$site_code."G1'
						OR RM02 LIKE 'H".$site_code."H1'
						-- 他所跨(縣市)本所
						OR RM02 LIKE '%H".$site_code."'
				) 
				AND RM03 LIKE '%0'
				AND (RM99 is NULL OR RM100 is NULL OR RM100_1 is NULL OR RM101 is NULL OR
						RM101_1 is NULL)
		");
		$this->db_wrapper->getDB()->execute();
		return $this->db_wrapper->getDB()->fetchAll();
	}

	public function fixProblematicXCases($id) {
		if (!$this->db_wrapper->reachable()) {
			return false;
		}

		if (!$this->checkCaseID($id)) {
            return false;
		}
		
		$this->db_wrapper->getDB()->parse("
			UPDATE MOICAS.CRSMS SET 
				RM99 = 'Y',
				RM100 = :bv_hold_code,
				RM100_1 = :bv_hold_county_code,
				RM101 = :bv_receive_code,
				RM101_1 = :bv_receive_county_code
			WHERE
				RM01 = :bv_rm01_year
				AND RM02 = :bv_rm02_code
				AND RM03 = :bv_rm03_number
		");

		$code = substr($id, 3, 4);
		$this->db_wrapper->getDB()->bind(":bv_rm01_year", substr($id, 0, 3));
    $this->db_wrapper->getDB()->bind(":bv_rm02_code", $code);
		$this->db_wrapper->getDB()->bind(":bv_rm03_number", substr($id, 7, 6));
		
		$ty_hold_site_code = "H".$this->db_wrapper->getSiteCode();
		$receive_office_code = $code[0].$code[2];
		if (endsWith($code, $ty_hold_site_code)) {
			$map = getCrossCountyCodeMap();
			$receive_office_code = $map[$code[0].$code[1]][0];
			// 跨縣市
			$this->db_wrapper->getDB()->bind(":bv_hold_county_code", 'H');
			$this->db_wrapper->getDB()->bind(":bv_hold_code", $ty_hold_site_code);
		} else {
			// 桃園市內跨所
			$this->db_wrapper->getDB()->bind(":bv_hold_county_code", $code[0]);
			$this->db_wrapper->getDB()->bind(":bv_hold_code", $code[0].$code[1]);
		}
		$this->db_wrapper->getDB()->bind(":bv_receive_code", $receive_office_code);
		$this->db_wrapper->getDB()->bind(":bv_receive_county_code", $code[0]);
		// UPDATE/INSERT can not use fetch after execute ... 
		$this->db_wrapper->getDB()->execute();
		return true;
	}

	public function getPSCRNProblematicXCases() {
		if (!$this->db_wrapper->reachable()) {
			return array();
		}

		// global $week_ago;
		$this->db_wrapper->getDB()->parse("
			SELECT * FROM MOIPRC.PSCRN
			WHERE SS04_1 IN (
				'H".$this->db_wrapper->getSiteCode()."A1',
				'H".$this->db_wrapper->getSiteCode()."B1',
				'H".$this->db_wrapper->getSiteCode()."C1',
				'H".$this->db_wrapper->getSiteCode()."D1',
				'H".$this->db_wrapper->getSiteCode()."E1',
				'H".$this->db_wrapper->getSiteCode()."F1',
				'H".$this->db_wrapper->getSiteCode()."G1',
				'H".$this->db_wrapper->getSiteCode()."H1'
			) AND (
				SS99 is NULL
				OR SS100 is NULL
				OR SS100_1 is NULL
				OR SS101 is NULL
				OR SS101_1 is NULL
			)");
		// $this->db_wrapper->getDB()->bind(":bv_week_ago", $amonth);
        $this->db_wrapper->getDB()->execute();
        return $this->db_wrapper->getDB()->fetchAll();
	}

	public function fixPSCRNProblematicXCases($id) {
		if (!$this->db_wrapper->reachable()) {
			return false;
		}

		if (!$this->checkCaseID($id)) {
            return false;
		}
		
		$this->db_wrapper->getDB()->parse("
			UPDATE MOIPRC.PSCRN SET SS99 = 'Y', SS100 = :bv_hold_code, SS100_1 = :bv_county_code, SS101 = :bv_receive_code, SS101_1 = :bv_county_code
			WHERE SS03 = :bv_ss03_year AND SS04_1 = :bv_ss04_1_code AND SS04_2 = :bv_ss04_2_number
		");

		$code = substr($id, 3, 4);
		$this->db_wrapper->getDB()->bind(":bv_ss03_year", substr($id, 0, 3));
        $this->db_wrapper->getDB()->bind(":bv_ss04_1_code", $code);
		$this->db_wrapper->getDB()->bind(":bv_ss04_2_number", substr($id, 7, 6));
		$this->db_wrapper->getDB()->bind(":bv_county_code", $code[0]);
		$this->db_wrapper->getDB()->bind(":bv_hold_code", $code[0].$code[1]);
		$this->db_wrapper->getDB()->bind(":bv_receive_code", $code[0].$code[2]);
		// UPDATE/INSERT can not use fetch after execute ... 
		$this->db_wrapper->getDB()->execute();
		return true;
	}
}
