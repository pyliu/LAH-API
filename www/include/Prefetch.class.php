<?php
require_once("init.php");
require_once("DynamicSQLite.class.php");
require_once("OraDB.class.php");
require_once("Cache.class.php");
require_once("System.class.php");

class Prefetch {
    private const PREFETCH_SQLITE_DB = ROOT_DIR.DIRECTORY_SEPARATOR."assets".DIRECTORY_SEPARATOR."db".DIRECTORY_SEPARATOR."prefetch.db";
    private const KEYS = array(
        'RM30H' => 'Prefetch::getRM30HCase',
        'OVERDUE' => 'Prefetch::getOverdueCaseIn15Days',
        'ALMOST_OVERDUE' => 'Prefetch::getAlmostOverdueCase',
        'ASK' => 'Prefetch::getAskCase',
        'TRUST_REBOW' => 'Prefetch::getTrustRebow'
    );
    private $ora_db = null;
    private $cache = null;
    private $config = null;

    private function getOraDB() {
        if ($this->ora_db === null) {
            $this->ora_db = new OraDB(CONNECTION_TYPE::MAIN);
        }
        return $this->ora_db;
    }
    
    private function getCache() {
        if ($this->cache === null) {
            $this->cache = new Cache(self::PREFETCH_SQLITE_DB);
        }
        return $this->cache;
    }

    private function getSystemConfig() {
        if ($this->config === null) {
            $this->config = new System();
        }
        return $this->config;
    }

    private function getRemainingCacheTimeByKey($key) {
        if ($this->getCache()->isExpired($key)) {
            return 0;
        }
        return $this->getCache()->getExpireTimestamp($key) - mktime();
    }

    function __construct() { }

    function __destruct() { }
    /**
     * 目前為公告狀態案件快取剩餘時間
     */
    public function getRM30HCaseCacheRemainingTime() {
        return $this->getRemainingCacheTimeByKey(self::KEYS['RM30H']);
    }
    /**
     * 強制重新讀取目前為公告狀態案件
     */
    public function reloadRM30HCase() {
        $this->getCache()->del(self::KEYS['RM30H']);
        return $this->getRM30HCase();
    }
    /**
	 * 取得目前為公告狀態案件
     * default cache time is 60 minutes * 60 seconds = 3600 seconds
	 */
	public function getRM30HCase($expire_duration = 3600) {
        if ($this->getCache()->isExpired(self::KEYS['RM30H'])) {
            global $log;
            $log->info('['.self::KEYS['RM30H'].'] 快取資料已失效，重新擷取 ... ');

            $db = $this->getOraDB();
            $db->parse("
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
            $db->execute();
            $result = $db->fetchAll();
            $this->getCache()->set(self::KEYS['RM30H'], $result, $expire_duration);

            $log->info("[".self::KEYS['RM30H']."] 快取資料已更新 ( ".count($result)." 筆，預計 ${expire_duration} 秒後到期)");

            return $result;
        }
        return $this->getCache()->get(self::KEYS['RM30H']);
    }
    /**
     * 15天內逾期案件快取剩餘時間
     */
    public function getOverdueCaseCacheRemainingTime() {
        return $this->getRemainingCacheTimeByKey(self::KEYS['OVERDUE']);
    }
    /**
     * 強制重新讀取15天內逾期案件
     */
    public function reloadOverdueCaseIn15Days() {
        $this->getCache()->del(self::KEYS['OVERDUE']);
        return $this->getOverdueCaseIn15Days();
    }
    /**
	 * 取得15天內逾期案件
     * default cache time is 15 minutes * 60 seconds = 900 seconds
	 */
	public function getOverdueCaseIn15Days($expire_duration = 900) {
        if ($this->getCache()->isExpired(self::KEYS['OVERDUE'])) {
            global $log;
            $log->info('['.self::KEYS['OVERDUE'].'] 快取資料已失效，重新擷取 ... ');

            $db = $this->getOraDB();
            $db->parse("
                SELECT *
                FROM SCRSMS
                LEFT JOIN SRKEYN ON KCDE_1 = '06' AND RM09 = KCDE_2
                WHERE
                    -- RM07_1 > :bv_start
                    RM02 NOT LIKE 'HB%1'		-- only search our own cases
                    AND RM03 LIKE '%0' 			-- without sub-case
                    AND RM31 IS NULL			-- not closed case
                    AND RM29_1 || RM29_2 < :bv_now
                    AND RM29_1 || RM29_2 > :bv_start
                ORDER BY RM29_1 DESC, RM29_2 DESC
            ");

            $tw_date = new Datetime("now");
            $tw_date->modify("-1911 year");
            $now = ltrim($tw_date->format("YmdHis"), "0");	// ex: 1080325152111

            $date_15days_before = new Datetime("now");
            $date_15days_before->modify("-1911 year");
            $date_15days_before->modify("-15 days");
            $start = ltrim($date_15days_before->format("YmdHis"), "0");	// ex: 1090107081410
            
            global $log;
            $log->info(__METHOD__.": Find overdue date between $start and $now cases.");

            $db->bind(":bv_now", $now);
            $db->bind(":bv_start", $start);
            $db->execute();
            $result = $db->fetchAll();
            $this->getCache()->set(self::KEYS['OVERDUE'], $result, $expire_duration);

            $log->info("[".self::KEYS['OVERDUE']."] 快取資料已更新 ( ".count($result)." 筆，預計 ${expire_duration} 秒後到期)");

            return $result;
        }
        return $this->getCache()->get(self::KEYS['OVERDUE']);
	}
    /**
     * 快逾期案件快取剩餘時間
     */
    public function getAlmostOverdueCaseCacheRemainingTime() {
        return $this->getRemainingCacheTimeByKey(self::KEYS['ALMOST_OVERDUE']);
    }
    /**
     * 強制重新讀取快逾期案件
     */
    public function reloadAlmostOverdueCase() {
        $this->getCache()->del(self::KEYS['ALMOST_OVERDUE']);
        return $this->getAlmostOverdueCase();
    }
    /**
	 * 取得快逾期的案件
     * default cache time is 15 minutes * 60 seconds = 900 seconds
	 */
	public function getAlmostOverdueCase($expire_duration = 900) {
        if ($this->getCache()->isExpired(self::KEYS['ALMOST_OVERDUE'])) {
            global $log;
            $log->info('['.self::KEYS['ALMOST_OVERDUE'].'] 快取資料已失效，重新擷取 ... ');

            $db = $this->getOraDB();
            $db->parse("
                SELECT *
                FROM SCRSMS
                LEFT JOIN SRKEYN ON KCDE_1 = '06' AND RM09 = KCDE_2
                WHERE
                    RM02 NOT LIKE 'HB%1'		-- only search our own cases
                    AND RM03 LIKE '%0' 			-- without sub-case
                    AND RM31 IS NULL			-- not closed case
                    AND RM29_1 || RM29_2 < :bv_now_plus_4hrs
                    AND RM29_1 || RM29_2 > :bv_now
                ORDER BY RM29_1 DESC, RM29_2 DESC
            ");

            $tw_date = new Datetime("now");
            $tw_date->modify("-1911 year");
            $now = ltrim($tw_date->format("YmdHis"), "0");	// ex: 1080325152111

            $date_4hrs_later = new Datetime("now");
            $date_4hrs_later->modify("-1911 year");
            $date_4hrs_later->modify("+4 hours");
            if ($date_4hrs_later->format("H") > 17) {
                $log->info(__METHOD__.": ".$date_4hrs_later->format("YmdHis")." is over 17:00:00, so add 16 hrs ... ");
                $date_4hrs_later->modify("+16 hours");
            }
            $now_plus_4hrs = ltrim($date_4hrs_later->format("YmdHis"), "0");	// ex: 1090107081410
            
            $log->info(__METHOD__.": Find almost overdue date between $now and $now_plus_4hrs cases.");

            $db->bind(":bv_now", $now);
            $db->bind(":bv_now_plus_4hrs", $now_plus_4hrs);
            $db->execute();
            $result = $db->fetchAll();
            $this->getCache()->set(self::KEYS['ALMOST_OVERDUE'], $result, $expire_duration);

            $log->info("[".self::KEYS['ALMOST_OVERDUE']."] 快取資料已更新 ( ".count($result)." 筆，預計 ${expire_duration} 秒後到期)");

            return $result;
        }
        return $this->getCache()->get(self::KEYS['ALMOST_OVERDUE']);
    }
    /**
     * 取消請示案件快取剩餘時間
     */
    public function getAskCaseCacheRemainingTime() {
        return $this->getRemainingCacheTimeByKey(self::KEYS['ASK']);
    }
    /**
     * 強制重新讀取取消請示案件
     */
    public function reloadAskCase() {
        $this->getCache()->del(self::KEYS['ASK']);
        return $this->getAskCase();
    }
    /**
	 * 取得取消請示的案件
     * default cache time is 60 minutes * 60 seconds = 3600 seconds
	 */
	public function getAskCase($expire_duration = 3600) {
        if ($this->getCache()->isExpired(self::KEYS['ASK'])) {
            global $log;
            $log->info('['.self::KEYS['ASK'].'] 快取資料已失效，重新擷取 ... ');

            $db = $this->getOraDB();
            $db->parse("
                SELECT * FROM MOICAS.CRSMS t
                LEFT JOIN MOIADM.RKEYN q ON t.RM09=q.KCDE_2 AND q.KCDE_1 = '06'
                WHERE
                    (RM02 LIKE :bv_office || '%' AND RM02 NOT LIKE 'H' || :bv_office_end || '%1') AND
                    RM83 IS NOT NULL AND
                    -- RM31 in ('A', 'B', 'C', 'D') AND
                    -- RM29_1 < RM58_1 AND
                    RM07_1 BETWEEN :bv_start AND :bv_today
                ORDER BY t.RM29_1 DESC, t.RM58_1 DESC
            ");
            
            $tw_date = new Datetime("now");
            $tw_date->modify("-1911 year");
            $today = ltrim($tw_date->format("Ymd"), "0");	// ex: 1091217

            $date_a_year_before = new Datetime("now");
            $date_a_year_before->modify("-1911 year");
            $date_a_year_before->modify("-92 days");
            $start = ltrim($date_a_year_before->format("Ymd"), "0");	// ex: 1090617

            $office = $this->getSystemConfig()->get('SITE');    // e.g. HB
            $db->bind(":bv_office_end", $office[1]);
            $db->bind(":bv_office", $office);
            $db->bind(":bv_today", $today);
            $db->bind(":bv_start", $start);
            $db->execute();
            $result = $db->fetchAll();
            $this->getCache()->set(self::KEYS['ASK'], $result, $expire_duration);

            $log->info("[".self::KEYS['ASK']."] 快取資料已更新 ( ".count($result)." 筆，預計 ${expire_duration} 秒後到期)");

            return $result;
        }
        return $this->getCache()->get(self::KEYS['ASK']);
	}
    /**
     * 信託註記建物標示部資料快取剩餘時間
     */
    public function getRebowCacheRemainingTime($year) {
        return $this->getRemainingCacheTimeByKey(self::KEYS['TRUST_REBOW'].$year);
    }
    /**
     * 強制重新讀取信託註記建物標示部資料
     */
    public function reloadRebow($year) {
        $this->getCache()->del(self::KEYS['TRUST_REBOW'].$year);
        return $this->getTrustRebow($year);
    }
    /**
	 * 取得信託註記建物標示部資料
     * default cache time is 8 hours * 60 minutes * 60 seconds = 28800 seconds
	 */
	public function getTrustRebow($year, $expire_duration = 28800) {
        if ($this->getCache()->isExpired(self::KEYS['TRUST_REBOW'].$year)) {
            global $log;
            $log->info('['.self::KEYS['TRUST_REBOW'].$year.'] 快取資料已失效，重新擷取 ... ');

            $db = $this->getOraDB();
            $db->parse("
                SELECT is48,r1.kcnt,is49,is01,is09,isname,gg30_1,r3.kcnt as gg30_1_cht,gg30_2,ee15_1,ee15_3,ee15_2,is03,is04_1,is04_2,r2.kcnt,is05,is_date
                FROM moicad.rsindx,moicad.rgall,moicad.rebow,moiadm.rkeyn r1,moiadm.rkeyn r2,moiadm.rkeyn r3
                where 1=1
                and   is03 = :bv_year
                and   is00 IN ('E')
                AND IS_type IN ('A','M','D') 
                AND gg00='E' 
                AND gg30_1 in ('GH','GJ') 
                AND gg48=is48 
                AND gg49=is49 
                AND gg01=is01 
                AND gg48=ed48 
                AND gg49=ed49 
                AND gg01=ee01 
                AND r1.kcde_1='48' 
                AND r1.kcde_2=is48 
                AND r2.kcde_1='06' 
                AND r2.kcde_2=ee06
                AND r3.kcde_1='30'
                AND r3.kcde_2=gg30_1
                ORDER BY is03 desc,is04_1,is04_2 
            ");
            
            $db->bind(":bv_year", $year);
            $db->execute();
            $result = $db->fetchAll();
            $this->getCache()->set(self::KEYS['TRUST_REBOW'].$year, $result, $expire_duration);

            $log->info("[".self::KEYS['TRUST_REBOW'].$year."] 快取資料已更新 ( ".count($result)." 筆，預計 ${expire_duration} 秒後到期)");

            return $result;
        }
        return $this->getCache()->get(self::KEYS['TRUST_REBOW'].$year);
	}
}
