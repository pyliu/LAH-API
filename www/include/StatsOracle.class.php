<?php
require_once('init.php');
require_once("OraDBWrapper.class.php");
require_once("MOICAS.class.php");
require_once("System.class.php");

class StatsOracle {
	private $db_wrapper = null;

    private function checkYearMonth($year_month) {
        if (empty($year_month) || strlen($year_month) != 5) {
            Logger::getInstance()->error(__METHOD__.": $year_month foramt is not correct. (ex. 11203)");
            return false;
        }
        return true;
    }

    private function checkYearMonthDay($year_month_day) {
        if (empty($year_month_day) || strlen($year_month_day) != 7) {
            Logger::getInstance()->error(__METHOD__.": $year_month_day foramt is not correct. (ex. 1120321)");
            return false;
        }
        return true;
    }

	function __construct() {
		$this->db_wrapper = new OraDBWrapper();
	}

	function __destruct() {
		$this->db_wrapper = null;
	}

    public function getRefundCount($year_month) {
        if (!$this->checkYearMonth($year_month)) {
            return false;
        }
        $this->db_wrapper->getDB()->parse("
            -- 主動申請退費
            SELECT '主動申請退費' AS \"text\", COUNT(*) AS \"count\" FROM MOIEXP.EXPBA t
            WHERE t.BA32 LIKE :bv_cond || '%' and t.BA42 = '01'  --溢繳規費
        ");
        $this->db_wrapper->getDB()->bind(":bv_cond", $year_month);
        $this->db_wrapper->getDB()->execute();
        return $this->db_wrapper->getDB()->fetchAll(true);   // true => fetch raw data instead of converting to UTF-8
    }

    public function getCourtCaseCount($year_month) {
        if (!$this->checkYearMonth($year_month)) {
            return false;
        }
        $this->db_wrapper->getDB()->parse("
            -- 法院囑託案件
            -- 登記原因為查封(33)、塗銷查封(34)、假扣押(49)、塗銷假扣押(54)、假處分(50)、塗銷假處分(55)
            -- 禁止處分(52)、塗銷禁止處分(57)、破產登記(51)、塗銷破產登記(56)、暫時處分(FB)、塗銷暫時處分(FC)、未登記建物查封(AU)
            SELECT '法院囑託案件' AS \"text\", COUNT(*) AS \"count\"
            FROM MOICAS.CRSMS t
            WHERE t.RM07_1 LIKE :bv_cond || '%'
            AND t.RM09 in ('33', '34', '49', '54', '50', '55', '52', '57', '51', '56', 'FB', 'FC', 'AU')
		");
        $this->db_wrapper->getDB()->bind(":bv_cond", $year_month);
        $this->db_wrapper->getDB()->execute();
        return $this->db_wrapper->getDB()->fetchAll(true);   // true => fetch raw data instead of converting to UTF-8
    }

    public function getSurRainCount($year_month) {
        if (!$this->checkYearMonth($year_month)) {
            return false;
        }
        $this->db_wrapper->getDB()->parse("
            -- 測量因雨延期
            select '測量因雨延期案件' AS \"text\", COUNT(*) AS \"count\" from SCMSMS t
            left join SCMSDS q on MM01 = MD01 and MM02 = MD02 and MM03 = MD03
            where t.MM04_1 LIKE :bv_cond || '%'
            and MD12 = '1'
		");
        $this->db_wrapper->getDB()->bind(":bv_cond", $year_month);
        $this->db_wrapper->getDB()->execute();
        return $this->db_wrapper->getDB()->fetchAll(true);   // true => fetch raw data instead of converting to UTF-8
    }

    public function getRegFixCount($year_month) {
        if (!$this->checkYearMonth($year_month)) {
            return false;
        }
        $this->db_wrapper->getDB()->parse("
            -- 補正統計
            SELECT DISTINCT COUNT(*) AS \"count\", '登記補正案件' AS \"text\"
            FROM MOICAS.CRSMS
            WHERE MOICAS.CRSMS.RM07_1 LIKE :bv_cond || '%'
            AND MOICAS.CRSMS.RM51 Is Not Null
            AND MOICAS.CRSMS.RM52 Is Not Null
        ");
        $this->db_wrapper->getDB()->bind(":bv_cond", $year_month);
        $this->db_wrapper->getDB()->execute();
        return $this->db_wrapper->getDB()->fetchAll(true);   // true => fetch raw data instead of converting to UTF-8
    }

    public function getRegRejectCount($year_month) {
        if (!$this->checkYearMonth($year_month)) {
            return false;
        }
        $this->db_wrapper->getDB()->parse("
            -- 駁回統計
            SELECT DISTINCT COUNT(*) AS \"count\", '登記駁回案件' AS \"text\"
            FROM MOICAS.CRSMS
            WHERE MOICAS.CRSMS.RM07_1 LIKE :bv_cond || '%'
            AND MOICAS.CRSMS.RM48_1 Is Not Null
            AND MOICAS.CRSMS.RM48_2 Is Not Null
        ");
        $this->db_wrapper->getDB()->bind(":bv_cond", $year_month);
        $this->db_wrapper->getDB()->execute();
        return $this->db_wrapper->getDB()->fetchAll(true);   // true => fetch raw data instead of converting to UTF-8
    }

    public function getRegReasonCount($year_month) {
        if (!$this->checkYearMonth($year_month)) {
            return false;
        }
        $this->db_wrapper->getDB()->parse("
            -- 合併 11、分割  06、第一次登記 02、滅失 21、逕為分割 07、遺漏更正 CN、判決共有物分割  35、和解共有物分割 36、調解共有物分割 37
            -- 住址變更 48、拍賣 67、清償 AF、徵收  70、管理機關變更  46
            SELECT t.RM09 AS \"id\", q.kcnt AS \"text\", COUNT(*) AS \"count\"
            FROM MOICAS.CRSMS t
            LEFT JOIN MOICAD.RKEYN q ON q.kcde_1 = '06' AND t.rm09 = q.kcde_2
            WHERE
              t.RM09 in ('11', '06', '02', '21', '07', 'CN', '35', '36', '37', '48', '67', 'AF', '70', '46')
              AND t.RM07_1 LIKE :bv_cond || '%'
            GROUP BY t.RM09, q.kcnt
        ");
        $this->db_wrapper->getDB()->bind(":bv_cond", $year_month);
        $this->db_wrapper->getDB()->execute();
        return $this->db_wrapper->getDB()->fetchAll();   // true => fetch raw data instead of converting to UTF-8
    }

    public function getRegCaseCount($year_month) {
        if (!$this->checkYearMonth($year_month)) {
            return false;
        }
        $this->db_wrapper->getDB()->parse("
            SELECT t.RM09 AS \"id\", q.kcnt AS \"text\", COUNT(*) AS \"count\"
            FROM MOICAS.CRSMS t
            LEFT JOIN MOICAD.RKEYN q
            ON q.kcde_1 = '06'
            AND t.rm09 = q.kcde_2
            WHERE t.RM07_1 LIKE :bv_cond || '%'
            GROUP BY t.RM09, q.kcnt
        ");
        $this->db_wrapper->getDB()->bind(":bv_cond", $year_month);
        $this->db_wrapper->getDB()->execute();
        return $this->db_wrapper->getDB()->fetchAll();   // true => fetch raw data instead of converting to UTF-8
    }

    public function getRegRemoteCount($year_month) {
        if (!$this->checkYearMonth($year_month)) {
            return false;
        }
        $this->db_wrapper->getDB()->parse("
            SELECT
                '遠途先審案件' AS \"text\", COUNT(*) AS \"count\"
            FROM MOICAS.CRSMS t
                LEFT JOIN MOICAD.RLNID u ON t.RM18 = u.LIDN  -- 權利人
                LEFT JOIN MOICAS.CABRP v ON t.RM24 = v.AB01  -- 代理人
                LEFT JOIN MOIADM.RKEYN w ON t.RM09 = w.KCDE_2 AND w.KCDE_1 = '06'   -- 登記原因
            WHERE 
                --t.RM02 = 'HB06' AND 
                t.RM07_1 LIKE :bv_cond || '%' AND 
                (u.LADR NOT LIKE '%' || :bv_city || '%' AND u.LADR NOT LIKE '%' || :bv_county || '%') AND 
                (v.AB03 NOT LIKE '%' || :bv_city || '%' AND v.AB03 NOT LIKE '%' || :bv_county || '%')
        ");
        $this->db_wrapper->getDB()->bind(":bv_cond", $year_month);
        $this->db_wrapper->getDB()->bind(":bv_city", mb_convert_encoding('桃園市', "big5"));
        $this->db_wrapper->getDB()->bind(":bv_county", mb_convert_encoding('桃園縣', "big5"));
        $this->db_wrapper->getDB()->execute();
        return $this->db_wrapper->getDB()->fetchAll(true);   // true => fetch raw data instead of converting to UTF-8
    }

    public function getRegSubCaseCount($year_month) {
        if (!$this->checkYearMonth($year_month)) {
            return false;
        }

		$site = strtoupper(System::getInstance()->get('SITE')) ?? 'HA';
        $site_code = 'A';
		if (!empty($site)) {
			$site_code = $site[1];
			$site_number = ord($site_code) - ord('A');
		}

        $this->db_wrapper->getDB()->parse("
            SELECT '本所處理跨所子號案件' AS \"text\", COUNT(*) AS \"count\"
            FROM MOICAS.CRSMS tt
            WHERE tt.rm07_1 LIKE :bv_cond || '%'
                AND tt.rm02 LIKE 'H%".$site_code."1' -- 本所處理跨所案件
                AND tt.RM03 NOT LIKE '%0' -- 子號案件
        ");
        $this->db_wrapper->getDB()->bind(":bv_cond", $year_month);
        $this->db_wrapper->getDB()->execute();
        return $this->db_wrapper->getDB()->fetchAll(true);   // true => fetch raw data instead of converting to UTF-8
    }

    public function getRegfCount($year_month) {
        if (!$this->checkYearMonth($year_month)) {
            return false;
        }
        $this->db_wrapper->getDB()->parse("
            SELECT '外國人地權登記統計' AS \"text\", COUNT(*) AS \"count\"
            FROM MOICAD.REGF
            WHERE MOICAD.REGF.RF40 LIKE :bv_cond || '%'
        ");
        $this->db_wrapper->getDB()->bind(":bv_cond", $year_month);
        $this->db_wrapper->getDB()->execute();
        return $this->db_wrapper->getDB()->fetchAll(true);   // true => fetch raw data instead of converting to UTF-8
    }

    public function getRegFirstCount($st, $ed) {
        $moicas = new MOICAS();
        return $moicas->getCRSMSFirstRegCase($st, $ed);
    }

    public function getRegFirstSubCount($st, $ed) {
        $moicas = new MOICAS();
        return $moicas->getCRSMSFirstRegSubCase($st, $ed);
    }

    public function getRegRM02Count($rm02, $st, $ed) {
        $moicas = new MOICAS();
        return $moicas->getCRSMSRegRM02Case($rm02, $st, $ed);
    }

    public function getRegRM02SubCount($rm02, $st, $ed) {
        $moicas = new MOICAS();
        return $moicas->getCRSMSRegRM02SubCase($rm02, $st, $ed);
    }
    /**
     * the stats data will be collected every night (22:00) on cross site AP
     */
    public function getRegaCount($day) {
        if (!$this->db_wrapper->reachable() || !$this->checkYearMonthDay($day)) {
            return false;
        }
        $this->db_wrapper->getDB()->parse("
            select * from MOICAD.REGA t
            where ra40 like :bv_cond
        ");
        $this->db_wrapper->getDB()->bind(":bv_cond", $day);
        $this->db_wrapper->getDB()->execute();
        return $this->db_wrapper->getDB()->fetchAll(true);   // true 
        
    }
    /**
     * collect REG case count data by the hour period between dates
     */
    public function getRegPeriodCount($st, $ed) {
        if (!$this->db_wrapper->reachable() || !$this->checkYearMonthDay($st) || !$this->checkYearMonthDay($ed)) {
            return false;
        }
        $this->db_wrapper->getDB()->parse("
            SELECT
                COUNT(CASE WHEN t.RM07_2 LIKE '08%' THEN 1 ELSE NULL END) AS \"08\",
                COUNT(CASE WHEN t.RM07_2 LIKE '09%' THEN 1 ELSE NULL END) AS \"09\",
                COUNT(CASE WHEN t.RM07_2 LIKE '10%' THEN 1 ELSE NULL END) AS \"10\",
                COUNT(CASE WHEN t.RM07_2 LIKE '11%' THEN 1 ELSE NULL END) AS \"11\",
                COUNT(CASE WHEN t.RM07_2 LIKE '12%' THEN 1 ELSE NULL END) AS \"12\",
                COUNT(CASE WHEN t.RM07_2 LIKE '13%' THEN 1 ELSE NULL END) AS \"13\",
                COUNT(CASE WHEN t.RM07_2 LIKE '14%' THEN 1 ELSE NULL END) AS \"14\",
                COUNT(CASE WHEN t.RM07_2 LIKE '15%' THEN 1 ELSE NULL END) AS \"15\",
                COUNT(CASE WHEN t.RM07_2 LIKE '16%' THEN 1 ELSE NULL END) AS \"16\",
                COUNT(CASE WHEN t.RM07_2 LIKE '17%' THEN 1 ELSE NULL END) AS \"17\"
                FROM MOICAS.CRSMS t
            WHERE 1=1
                AND (t.RM07_1 BETWEEN :bv_st And :bv_ed)
                AND (t.RM101 = 'HA' OR t.RM101 IS NULL)
        ");
        $this->db_wrapper->getDB()->bind(":bv_st", $st);
        $this->db_wrapper->getDB()->bind(":bv_ed", $ed);
        $this->db_wrapper->getDB()->execute();
        return $this->db_wrapper->getDB()->fetch(true);  // true => fetch raw data instead converting to UTF-8
    }
    /**
     * collect SUR case count data by the hour period between dates
     */
    public function getSurPeriodCount($st, $ed) {
        if (!$this->db_wrapper->reachable() || !$this->checkYearMonthDay($st) || !$this->checkYearMonthDay($ed)) {
            return false;
        }
        $this->db_wrapper->getDB()->parse("
            SELECT
                COUNT(CASE WHEN t.MM04_2 LIKE '08%' THEN 1 ELSE NULL END) AS \"08\",
                COUNT(CASE WHEN t.MM04_2 LIKE '09%' THEN 1 ELSE NULL END) AS \"09\",
                COUNT(CASE WHEN t.MM04_2 LIKE '10%' THEN 1 ELSE NULL END) AS \"10\",
                COUNT(CASE WHEN t.MM04_2 LIKE '11%' THEN 1 ELSE NULL END) AS \"11\",
                COUNT(CASE WHEN t.MM04_2 LIKE '12%' THEN 1 ELSE NULL END) AS \"12\",
                COUNT(CASE WHEN t.MM04_2 LIKE '13%' THEN 1 ELSE NULL END) AS \"13\",
                COUNT(CASE WHEN t.MM04_2 LIKE '14%' THEN 1 ELSE NULL END) AS \"14\",
                COUNT(CASE WHEN t.MM04_2 LIKE '15%' THEN 1 ELSE NULL END) AS \"15\",
                COUNT(CASE WHEN t.MM04_2 LIKE '16%' THEN 1 ELSE NULL END) AS \"16\",
                COUNT(CASE WHEN t.MM04_2 LIKE '17%' THEN 1 ELSE NULL END) AS \"17\"
                FROM MOICAS.CMSMS t
            WHERE 1=1
                AND (t.MM04_1 BETWEEN :bv_st And :bv_ed)
        ");
        $this->db_wrapper->getDB()->bind(":bv_st", $st);
        $this->db_wrapper->getDB()->bind(":bv_ed", $ed);
        $this->db_wrapper->getDB()->execute();
        return $this->db_wrapper->getDB()->fetch(true);  // true => fetch raw data instead converting to UTF-8
    }

    public function getRegCertCase($st, $ed) {
        if (!$this->db_wrapper->reachable() || !$this->checkYearMonthDay($st) || !$this->checkYearMonthDay($ed)) {
            return false;
        }
        $this->db_wrapper->getDB()->parse("
            -- 謄本核發資料(未加外網電子謄本數量) by period
            SELECT
                *
            FROM MOICAS.CUSMM
            INNER JOIN MOICAS.RSCNRL
                ON (MOICAS.CUSMM.MU01 = MOICAS.RSCNRL.SR01)
            AND (MOICAS.CUSMM.MU02 = MOICAS.RSCNRL.SR02)
            AND (MOICAS.CUSMM.MU03 = MOICAS.RSCNRL.SR03)
            WHERE (((MOICAS.CUSMM.MU12) Between :bv_st And :bv_ed) AND
                ((MOICAS.RSCNRL.SR06) = :bv_site))
        ");
        $site = strtoupper(System::getInstance()->get('SITE')) ?? 'HA';
        $this->db_wrapper->getDB()->bind(":bv_st", $st);
        $this->db_wrapper->getDB()->bind(":bv_ed", $ed);
        $this->db_wrapper->getDB()->bind(":bv_site", $site);
        $this->db_wrapper->getDB()->execute();
        return $this->db_wrapper->getDB()->fetchAll();
    }
    /**
     * 初審案件統計
     */
    public function getInitialReviewCaseStats($st, $ed) {
        if (!$this->db_wrapper->reachable()) {
            return false;
        }
        $site = strtoupper(System::getInstance()->get('SITE')) ?? 'HA';
        $this->db_wrapper->getDB()->parse("
            -- 初審年度案件統計
            select :bv_site as \"office_name\", \"initial_id\", \"initial_name\", \"normal_case_count\", \"easy_case_count\", \"total_case_count\" from (
                SELECT 
                    ssc.rm45 AS \"initial_id\",
                    ssa.user_name AS \"initial_name\",
                    SUM(CASE WHEN ssc.rm08 != '9' THEN 1 ELSE 0 END) AS \"normal_case_count\",
                    SUM(CASE WHEN ssc.rm08 = '9' THEN 1 ELSE 0 END) AS \"easy_case_count\",
                    COUNT(*) AS \"total_case_count\"
                FROM 
                    moicas.crsms ssc
                JOIN 
                    moiadm.sysauth1 ssa ON ssc.rm45 = ssa.user_id
                WHERE 
                    ssc.rm03 LIKE '%0' 
                    AND ssc.rm44_1 BETWEEN :bv_st AND :bv_ed
                    AND ((ssc.rm99 IS NULL) OR (ssc.rm99 IS NOT NULL AND ssc.rm101 = :bv_site))
                GROUP BY 
                    ssc.rm45, ssa.user_name
                ORDER BY 
                    \"total_case_count\" DESC
            )
        ");
        $this->db_wrapper->getDB()->bind(":bv_st", $st);
        $this->db_wrapper->getDB()->bind(":bv_ed", $ed);
        $this->db_wrapper->getDB()->bind(":bv_site", $site);
        $this->db_wrapper->getDB()->execute();
        return $this->db_wrapper->getDB()->fetchAll();
    }
    /**
     * 複審案件統計
     */
    public function getFinalReviewCaseStats($st, $ed) {
        if (!$this->db_wrapper->reachable()) {
            return false;
        }
        $site = strtoupper(System::getInstance()->get('SITE')) ?? 'HA';
        $this->db_wrapper->getDB()->parse("
            -- 複審年度案件統計
            select :bv_site as \"office_name\", \"final_id\", \"final_name\", \"case_count\" from (
                SELECT 
                    ssc.rm47 AS \"final_id\",
                    ssa.user_name AS \"final_name\",
                    COUNT(*) AS \"case_count\"
                FROM 
                    moicas.crsms ssc
                JOIN 
                    moiadm.sysauth1 ssa ON ssc.rm47 = ssa.user_id
                WHERE 
                    ssc.rm47 IS NOT NULL 
                    AND ssc.rm03 LIKE '%0' 
                    AND ssc.rm46_1 BETWEEN :bv_st AND :bv_ed
                    AND ((ssc.rm99 IS NULL) OR (ssc.rm99 IS NOT NULL AND ssc.rm101 = :bv_site))
                GROUP BY 
                    ssc.rm47, ssa.user_name
                ORDER BY 
                    \"case_count\" DESC
            )
        ");
        $this->db_wrapper->getDB()->bind(":bv_st", $st);
        $this->db_wrapper->getDB()->bind(":bv_ed", $ed);
        $this->db_wrapper->getDB()->bind(":bv_site", $site);
        $this->db_wrapper->getDB()->execute();
        return $this->db_wrapper->getDB()->fetchAll();
    }
    /**
     * 課長案件統計
     */
    public function getChiefReviewCaseStats($st, $ed) {
        if (!$this->db_wrapper->reachable()) {
            return false;
        }
        $site = strtoupper(System::getInstance()->get('SITE')) ?? 'HA';
        $this->db_wrapper->getDB()->parse("
            -- 課長年度案件統計
            select :bv_site as \"office_name\", \"chief_id\", \"chief_name\", \"case_count\" from (
                SELECT 
                    ssc.rm106 AS \"chief_id\",
                    ssa.user_name AS \"chief_name\",
                    COUNT(*) AS \"case_count\"
                FROM 
                    moicas.crsms ssc
                JOIN 
                    moiadm.sysauth1 ssa ON ssc.rm106 = ssa.user_id
                WHERE 
                    ssc.rm106 IS NOT NULL 
                    AND ssc.rm03 LIKE '%0' 
                    AND ssc.rm106_1 BETWEEN :bv_st AND :bv_ed
                    AND (ssc.rm99 IS NULL OR ssc.rm101 = :bv_site)
                GROUP BY 
                    ssc.rm106, ssa.user_name
                ORDER BY 
                    \"case_count\" DESC
            )
        ");
        $this->db_wrapper->getDB()->bind(":bv_st", $st);
        $this->db_wrapper->getDB()->bind(":bv_ed", $ed);
        $this->db_wrapper->getDB()->bind(":bv_site", $site);
        $this->db_wrapper->getDB()->execute();
        return $this->db_wrapper->getDB()->fetchAll();
    }
    /**
     * 百歲人瑞所有權人統計
     */
    public function getHundredYearsOwnerData($birth_year, $type) {
        if (!$this->db_wrapper->reachable()) {
            return false;
        }
        if (!in_array($type, ['land', 'building'])) {
            Logger::getInstance()->error(__METHOD__.": type $type 不正確。 [僅支援 land(土地)、building(建物)]");
            return false;
        }
        $birth_year_padded = str_pad($birth_year, 3, "0", STR_PAD_LEFT);
        if ($type === 'land') {
            $this->db_wrapper->getDB()->parse("
                -- 民國15年出生之土地所有權人資料(百歲人瑞資料查詢)
                select
                r.GG00 AS \"資料集代號\",
                t.BA48 AS \"段號\",
                u.KNAME AS \"段名\",
                t.BA49 AS \"地號\",
                t.BB01 AS \"登次\",
                t.BB15_1 AS \"權利範圍類別\",
                t.BB15_2 AS \"分母\",
                t.BB15_3 AS \"分子\",
                t.BB09 AS \"所有權人統編\",
                s.LNAM AS \"所有權人姓名\",
                t.BB05 AS \"登記日期\",
                t.BB07 AS \"登記原因發生日期\",
                s.ladr AS \"住址\",
                r.GG30_1 AS \"其他登記事項代碼\",
                r.GG30_2 AS \"其他登記事項內容\",
                s.Lbir_2 AS \"出生日期\"
                --SUBSTR(s.Lbir_2, 1, 3) AS \"出生年\"
                from MOICAD.RBLOW t
                left join MOICAD.RLNID s ON t.BB09 = s.lidn
                left join MOIADM.RKEYN_ALL u ON u.kcde_1 = '48' AND t.BA48 = u.kcde_4 AND  u.Kcde_2 = 'H'
                left join MOICAD.RGALL r ON r.GG01 = t.BB01 AND r.GG48 = t.BA48 AND r.GG49 = t.BA49
                where BB09 NOT LIKE '*%' AND s.lbir_2 IS NOT NULL AND s.LBIR_2 BETWEEN :bv_by || '0101' and :bv_by || '1231'
                ORDER BY t.BA48, t.BA49, s.Lbir_2
            ");
        } else {
            $this->db_wrapper->getDB()->parse("
                -- 民國15年出生之建物所有權人資料(百歲人瑞資料查詢)
                select
                r.GG00 AS \"資料集代號\",
                t.ED48 AS \"段號\",
                u.KNAME AS \"段名\",
                t.ED49 AS \"建號\",
                t.EE01 AS \"登次\",
                t.EE15_1 AS \"權利範圍類別\",
                t.EE15_2 AS \"分母\",
                t.EE15_3 AS \"分子\",
                t.EE09 AS \"所有權人統編\",
                s.LNAM AS \"所有權人姓名\",
                t.EE05 AS \"登記日期\",
                t.EE07 AS \"登記原因發生日期\",
                r.GG30_1 AS \"其他登記事項代碼\",
                r.GG30_2 AS \"其他登記事項內容\",
                s.Lbir_2 AS \"出生日期\"
                --SUBSTR(s.Lbir_2, 1, 3) AS \"出生年\"
                from MOICAD.REBOW t
                left join MOICAD.RLNID s ON t.EE09 = s.lidn
                left join MOIADM.RKEYN_ALL u ON u.kcde_1 = '48' AND t.ED48 = u.kcde_4 AND  u.Kcde_2 = 'H'
                left join MOICAD.RGALL r ON r.GG01 = t.EE01 AND r.GG48 = t.ED48 AND r.GG49 = t.ED49
                where t.EE09 NOT LIKE '*%' AND s.lbir_2 IS NOT NULL AND s.LBIR_2 BETWEEN :bv_by || '0101' and :bv_by || '1231'
                ORDER BY t.ED48, t.ED49, s.Lbir_2
            ");
        }
        $this->db_wrapper->getDB()->bind(":bv_by", $birth_year_padded);
        $this->db_wrapper->getDB()->execute();
        return $this->db_wrapper->getDB()->fetchAll();
    }
}
