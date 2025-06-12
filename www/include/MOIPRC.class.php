<?php
require_once("init.php");

class MOIPRC {
	private $db_wrapper = null;
	function __construct() {
		$this->db_wrapper = new OraDBWrapper();
	}

	function __destruct() {
		$this->db_wrapper = null;
	}

	public function getRealPriceMap($st, $ed) {
		if (!$this->db_wrapper->reachable() || empty($st) || empty($ed)) {
			return array();
		}

		$this->db_wrapper->getDB()->parse("
			SELECT DISTINCT s.rm01 || '-' || s.rm02 || '-' || s.rm03 as \"RM123\",
				r.KCNT as \"RM09_CHT\",
				u.KNAME as \"RM11_CHT\",
				v.*,
				t.*,
				s.*
			FROM MOICAS.CRSMS s
			LEFT JOIN MOIPRC.PSCRN v
				ON s.RM01 = v.SS03
				AND s.RM02 = v.SS04_1
				AND s.RM03 = v.SS04_2
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
			ORDER BY s.RM07_1 DESC
		");
		$this->db_wrapper->getDB()->bind(":bv_st", $st);
		$this->db_wrapper->getDB()->bind(":bv_ed", $ed);
		$this->db_wrapper->getDB()->execute();
		$records = $this->db_wrapper->getDB()->fetchAll();

		// set other site's case no data
		$l3hweb = new LXHWEB();
		$map = $l3hweb->getRealpriceCaseNoMap($st, $ed);
		// assign other site's case no into records
		foreach ($records as $idx => $record) {
			if (empty(trim($record['P1MP_CASENO'])) || is_null($record['P1MP_CASENO'])) {
				$key = $record['RM01'].$record['RM02'].$record['RM03'];
				$records[$idx]['P1MP_CASENO'] = $map[$key];
			}
		}
		
		return $records;
	}

}
