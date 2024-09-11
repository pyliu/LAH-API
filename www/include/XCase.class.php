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

	private function getXCaseCRCLD($id) {
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
	
	private function getLocalCRCLD($id) {
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
	
	private function insertLocalCRCLD($l3_crcld) {
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
			$this->db_wrapper->getDB()->execute();
			return true;
		}
		return false;
	}

	private function updateLocalCRCLD($l3_crcld) {
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
			$this->db_wrapper->getDB()->execute();
			return true;
		}
		return false;
	}

	private function getXCaseCRCRD($crcld) {
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
	
	private function getLocalCRCRD($crcld) {
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
				return true;
			}
			Logger::getInstance()->warning(__METHOD__.": 同步遠端案件 $rc01-$rc04-$rc06 補正資料錯誤(回傳值：$l3_crcrd)");
			return false;
		}
		Logger::getInstance()->warning(__METHOD__.": 同步遠端案件 $id 補正連結資料錯誤(回傳值：$l3_crcld)");
		return false;
	}
}
