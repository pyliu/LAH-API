<?php
require_once('init.php');
require_once("OraDB.class.php");

class StatsOracle {
    private $db;

    private function checkYearMonth($year_month) {
        global $log;
		if (empty($year_month) || strlen($year_month) != 5) {
            $log->error(__METHOD__.": $year_month foramt is not correct.");
            return false;
        }
        return true;
    }

    function __construct() {
        $this->db = new OraDB();
    }

    function __destruct() { }

    public function getRefundCount($year_month) {
		if (!$this->checkYearMonth($year_month)) {
            return false;
		}
		$this->db->parse("
            -- 主動申請退費
            SELECT COUNT(*) AS \"count\" FROM MOIEXP.EXPBA t
            WHERE t.BA32 LIKE :bv_cond || '%' and t.BA42 = '01'  --溢繳規費
		");
		$this->db->bind(":bv_cond", $year_month);
		$this->db->execute();
		return $this->db->fetchAll();
    }

    public function getCourtCaseCount($year_month) {
		if (!$this->checkYearMonth($year_month)) {
            return false;
		}
        $this->db->parse("
            -- 法院囑託案件，登記原因為查封(33)、塗銷查封(34)
            SELECT COUNT(*) AS \"count\"
            FROM MOICAS.CRSMS t
            WHERE t.RM07_1 LIKE :bv_cond || '%'
            AND t.RM09 in ('33', '34')
		");
		$this->db->bind(":bv_cond", $year_month);
		$this->db->execute();
		return $this->db->fetchAll();
    }
}
?>
