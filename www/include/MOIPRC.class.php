<?php
require_once("init.php");
require_once("OraDB.class.php");
require_once("System.class.php");
require_once("Cache.class.php");

class MOIPRC {

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

	public function getRealPriceMap($st, $ed) {
		if (!$this->db_ok || empty($st) || empty($ed)) {
			return array();
		}

		$this->db->parse("
			SELECT s.rm01 || '-' || s.rm02 || '-' || s.rm03 as \"RM123\",
				r.KCNT as \"RM09_CHT\",
				u.KNAME as \"RM11_CHT\",
				t.*,
				s.*
			FROM MOICAS.CRSMS s
			LEFT JOIN MOIPRC.REALPRICE1_MAP t
				ON s.RM01 = t.P1MP_RM01
				AND s.RM02 = t.P1MP_RM02
				AND s.RM03 = t.P1MP_RM03
			LEFT JOIN MOIADM.RKEYN r
				ON r.KCDE_1 = '06'
				AND r.KCDE_2 = s.RM09
			LEFT JOIN MOIADM.RKEYN_ALL u
				ON u.KCDE_1 = '48'
				AND u.KCDE_2 = 'H'
				AND u.KCDE_4 = s.RM11
			WHERE 1 = 1
				AND s.RM07_1 BETWEEN :bv_st AND :bv_ed
				AND s.RM09 = '64'
		");
		$this->db->bind(":bv_st", $st);
		$this->db->bind(":bv_ed", $ed);
		$this->db->execute();
		return $this->db->fetchAll();
	}

}
