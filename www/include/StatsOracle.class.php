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

    public function getSurRainCount($year_month) {
		if (!$this->checkYearMonth($year_month)) {
            return false;
		}
        $this->db->parse("
            -- 測量因雨延期
            select COUNT(*) AS \"count\" from SCMSMS t
            left join SCMSDS q on MM01 = MD01 and MM02 = MD02 and MM03 = MD03
            where t.MM04_1 LIKE :bv_cond || '%'
            and MD12 = '1'
		");
		$this->db->bind(":bv_cond", $year_month);
		$this->db->execute();
		return $this->db->fetchAll();
    }

    public function getRegReasonCount($year_month) {
		if (!$this->checkYearMonth($year_month)) {
            return false;
		}
        $this->db->parse("
            -- 合併 11、分割  06、第一次登記 02、滅失 21、逕為分割 07、遺漏更正 CN、判決共有物分割  35、和解共有物分割 36、調解共有物分割 37
            -- 住址變更 48、拍賣 67、清償 AF、徵收  70、管理機關變更  46
            SELECT
                (CASE
                    WHEN t.RM09 = '11' THEN '合併'
                    WHEN t.RM09 = '06' THEN '分割'
                    WHEN t.RM09 = '02' THEN '第一次登記'
                    WHEN t.RM09 = '21' THEN '滅失'
                    WHEN t.RM09 = '07' THEN '逕為分割'
                    WHEN t.RM09 = 'CN' THEN '遺漏更正'
                    WHEN t.RM09 = '35' THEN '判決共有物分割'
                    WHEN t.RM09 = '36' THEN '和解共有物分割'
                    WHEN t.RM09 = '37' THEN '調解共有物分割'
                    WHEN t.RM09 = '48' THEN '住址變更'
                    WHEN t.RM09 = '67' THEN '拍賣'
                    WHEN t.RM09 = 'AF' THEN '清償'
                    WHEN t.RM09 = '70' THEN '徵收'
                    WHEN t.RM09 = '46' THEN '管理者變更'    
                    ELSE t.RM09
                END) AS \"text\",
                COUNT(*) AS \"count\"
            FROM MOICAS.CRSMS t
            WHERE t.RM09 in ('11', '06', '02', '21', '07', 'CN', '35', '36', '37', '48', '67', 'AF', '70', '46')
            AND t.RM07_1 LIKE :bv_cond || '%'
            GROUP BY t.RM09
		");
		$this->db->bind(":bv_cond", $year_month);
		$this->db->execute();
		return $this->db->fetchAll();
    }
}
?>
