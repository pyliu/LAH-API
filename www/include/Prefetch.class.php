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
        'TRUST_REBOW' => 'Prefetch::getTrustRebow',
        'TRUST_REBOW_EXCEPTION' => 'Prefetch::getTrustRebowException',
        'TRUST_RBLOW' => 'Prefetch::getTrustRblow',
        'TRUST_RBLOW_EXCEPTION' => 'Prefetch::getTrustRblowException',
        'NON_SCRIVENER' => 'Prefetch::getNonScrivenerCase',
        'NON_SCRIVENER_WEB' => 'Prefetch::getNonScrivenerWebCase',
        'FOREIGNER' => 'Prefetch::getForeignerCase'
    );
    private $ora_db = null;
    private $cache = null;
    private $config = null;
    
	private $site = 'HB';
	private $site_code = 'B';
	private $site_number = 2;

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
            // initialize site info
            $this->site = strtoupper($this->config->get('SITE')) ?? 'HB';
            if (!empty($this->site)) {
                $this->site_code = $this->site[1];
                $this->site_number = ord($this->site_code) - ord('A') + 1;
            }
        }
        return $this->config;
    }

    private function getRemainingCacheTimeByKey($key) {
        if ($this->getCache()->isExpired($key)) {
            return 0;
        }
        return $this->getCache()->getExpireTimestamp($key) - mktime();
    }

    function __construct() {
        // init system config first to assign site data code
        $this->getSystemConfig();
    }

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
                    v.KNAME AS RM11_CHT,
                    sa11.USER_NAME AS RM45_USERNAME,
                    sa12.USER_NAME AS RM30_1_USERNAME,
                    s.*
                FROM
                    MOICAS.CRSMS s
                    LEFT JOIN MOIADM.RKEYN Q ON s.RM09=Q.KCDE_2 AND Q.KCDE_1 = '06'
                    LEFT JOIN MOIADM.RKEYN_ALL v ON (v.KCDE_1 = '48' AND v.KCDE_2 = 'H' AND v.KCDE_3 = s.RM10 AND s.RM11 = v.KCDE_4),
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
                    -- NOT REGEXP_LIKE(RM02, '^".$this->site."[[:alpha:]]1$')
                    -- RM02 NOT LIKE '".$this->site."%1'		-- only search our own cases
                    RM02 NOT IN ('".$this->site."A1', '".$this->site."B1', '".$this->site."C1', '".$this->site."D1', '".$this->site."E1', '".$this->site."F1', '".$this->site."G1', '".$this->site."H1')
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
                    -- NOT REGEXP_LIKE(RM02, '^".$this->site."[[:alpha:]]1$')
                    -- RM02 NOT LIKE '".$this->site."%1'		-- only search our own cases
                    RM02 NOT IN ('".$this->site."A1', '".$this->site."B1', '".$this->site."C1', '".$this->site."D1', '".$this->site."E1', '".$this->site."F1', '".$this->site."G1', '".$this->site."H1')
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
    public function getAskCaseCacheRemainingTime($days = 92) {
        return $this->getRemainingCacheTimeByKey(self::KEYS['ASK']."_$days");
    }
    /**
     * 強制重新讀取取消請示案件
     */
    public function reloadAskCase($days = 92) {
        $this->getCache()->del(self::KEYS['ASK']."_$days");
        return $this->getAskCase($days);
    }
    /**
	 * 取得取消請示的案件
     * default cache time is 60 minutes * 60 seconds = 3600 seconds
	 */
	public function getAskCase($days = 92, $expire_duration = 3600) {
        if ($this->getCache()->isExpired(self::KEYS['ASK']."_$days")) {
            global $log;
            $log->info('['.self::KEYS['ASK']."_$days".'] 快取資料已失效，重新擷取 ... ');

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
            $date_a_year_before->modify("-$days days");
            $start = ltrim($date_a_year_before->format("Ymd"), "0");	// ex: 1090617

            $office = $this->getSystemConfig()->get('SITE');    // e.g. HB
            $db->bind(":bv_office_end", $office[1]);
            $db->bind(":bv_office", $office);
            $db->bind(":bv_today", $today);
            $db->bind(":bv_start", $start);
            $db->execute();
            $result = $db->fetchAll();
            $this->getCache()->set(self::KEYS['ASK']."_$days", $result, $expire_duration);

            $log->info("[".self::KEYS['ASK']."_$days"."] 快取資料已更新 ( ".count($result)." 筆，預計 ${expire_duration} 秒後到期)");

            return $result;
        }
        return $this->getCache()->get(self::KEYS['ASK']."_$days");
	}
    /**
     * 信託註記建物所有部資料快取剩餘時間
     */
    public function getTrustRebowCacheRemainingTime($year) {
        return $this->getRemainingCacheTimeByKey(self::KEYS['TRUST_REBOW'].$year);
    }
    /**
     * 強制重新讀取信託註記建物所有部資料
     */
    public function reloadTrustRebow($year) {
        $this->getCache()->del(self::KEYS['TRUST_REBOW'].$year);
        return $this->getTrustRebow($year);
    }
    /**
	 * 取得信託註記建物所有部資料
     * default cache time is 24 hours * 60 minutes * 60 seconds = 86400 seconds
	 */
	public function getTrustRebow($year, $expire_duration = 86400) {
        if ($this->getCache()->isExpired(self::KEYS['TRUST_REBOW'].$year)) {
            global $log;
            $log->info('['.self::KEYS['TRUST_REBOW'].$year.'] 快取資料已失效，重新擷取 ... ');

            $db = $this->getOraDB();
            $db->parse("
                SELECT is48,r1.kcnt as is48_cht,is49,is01,is09,isname,gg30_1,r3.kcnt as gg30_1_cht,gg30_2,ee15_1,ee15_3,ee15_2,is03,is04_1,is04_2,r2.kcnt as ee06_cht,is05,is_date
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
                ORDER BY is05 desc,is03 desc,is04_1,is04_2 desc
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
    /**
     * 信託註記建物所有部例外資料快取剩餘時間
     */
    public function getTrustRebowExceptionCacheRemainingTime($year) {
        return $this->getRemainingCacheTimeByKey(self::KEYS['TRUST_REBOW_EXCEPTION'].$year);
    }
    /**
     * 強制重新讀取信託註記建物所有部例外資料
     */
    public function reloadTrustRebowException($year) {
        $this->getCache()->del(self::KEYS['TRUST_REBOW_EXCEPTION'].$year);
        return $this->getTrustRebowException($year);
    }
    /**
	 * 取得信託註記建物所有部例外資料
     * default cache time is 24 hours * 60 minutes * 60 seconds = 86400 seconds
	 */
	public function getTrustRebowException($year, $expire_duration = 86400) {
        if ($this->getCache()->isExpired(self::KEYS['TRUST_REBOW_EXCEPTION'].$year)) {
            global $log;
            $log->info('['.self::KEYS['TRUST_REBOW_EXCEPTION'].$year.'] 快取資料已失效，重新擷取 ... ');

            $db = $this->getOraDB();
            $db->parse("
                SELECT is48,r1.kcnt as is48_cht,is49,is01,is09,isname,'','','','','','',is03,is04_1,is04_2,'',is05,is_date FROM moiadm.rkeyn r1, 
                    (SELECT *FROM (SELECT * FROM (SELECT * FROM moicad.rsindx  WHERE is00 IN ('E') AND IS_type IN ('M','D') AND IS06='CU') 
                WHERE NOT EXISTS 
                (SELECT gg48,gg49,gg01 FROM (SELECT * FROM moicad.rgall WHERE gg00='E' AND gg30_1 in ('GH','GJ'))  
                WHERE is00=gg00
                AND is48=gg48
                AND is49=gg49
                AND is01=gg01))) 
                WHERE r1.kcde_1='48' 
                AND r1.kcde_2=is48
                and    is03= :bv_year
                ORDER BY is05 desc,is03 desc,is48 desc,is49
            ");
            
            $db->bind(":bv_year", $year);
            $db->execute();
            $result = $db->fetchAll();
            $this->getCache()->set(self::KEYS['TRUST_REBOW_EXCEPTION'].$year, $result, $expire_duration);

            $log->info("[".self::KEYS['TRUST_REBOW_EXCEPTION'].$year."] 快取資料已更新 ( ".count($result)." 筆，預計 ${expire_duration} 秒後到期)");

            return $result;
        }
        return $this->getCache()->get(self::KEYS['TRUST_REBOW_EXCEPTION'].$year);
	}
    /**
     * 信託註記土地所有部資料快取剩餘時間
     */
    public function getTrustRblowCacheRemainingTime($year) {
        return $this->getRemainingCacheTimeByKey(self::KEYS['TRUST_RBLOW'].$year);
    }
    /**
     * 強制重新讀取信託註記土地所有部資料
     */
    public function reloadTrustRblow($year) {
        $this->getCache()->del(self::KEYS['TRUST_RBLOW'].$year);
        return $this->getTrustRblow($year);
    }
    /**
	 * 取得信託註記土地所有部資料
     * default cache time is 24 hours * 60 minutes * 60 seconds = 86400 seconds
	 */
	public function getTrustRblow($year, $expire_duration = 86400) {
        if ($this->getCache()->isExpired(self::KEYS['TRUST_RBLOW'].$year)) {
            global $log;
            $log->info('['.self::KEYS['TRUST_RBLOW'].$year.'] 快取資料已失效，重新擷取 ... ');

            $db = $this->getOraDB();
            $db->parse("
                SELECT is48,r1.kcnt as is48_cht,is49,is01,is09,isname,gg30_1,r3.kcnt as gg30_1_cht,gg30_2,bb15_1,bb15_3,bb15_2,is03,is04_1,is04_2,r2.kcnt as bb06_cht,is05,is_date 
                FROM moicad.rsindx,moicad.rgall,moicad.rblow,moiadm.rkeyn r1, moiadm.rkeyn r2 , moiadm.rkeyn r3
                where 1=1 
                and   is03= :bv_year
                and is00 IN ('B') 
                AND IS_type IN ('A','M','D') 
                AND gg00='B' 
                AND gg30_1 in ('GH','GJ') 
                AND gg48=is48 
                AND gg49=is49 
                AND gg01=is01 
                AND gg48=ba48 
                AND gg49=ba49 
                AND gg01=bb01 
                AND r1.kcde_1='48' 
                AND r1.kcde_2=is48 
                AND r2.kcde_1='06' 
                AND r2.kcde_2=bb06 
                AND r3.kcde_1='30'
                AND r3.kcde_2=gg30_1
                ORDER BY is05 desc,is03 desc,is04_1,is04_2 
            ");
            
            $db->bind(":bv_year", $year);
            $db->execute();
            $result = $db->fetchAll();
            $this->getCache()->set(self::KEYS['TRUST_RBLOW'].$year, $result, $expire_duration);

            $log->info("[".self::KEYS['TRUST_RBLOW'].$year."] 快取資料已更新 ( ".count($result)." 筆，預計 ${expire_duration} 秒後到期)");

            return $result;
        }
        return $this->getCache()->get(self::KEYS['TRUST_RBLOW'].$year);
	}
    /**
     * 信託註記土地所有部例外資料快取剩餘時間
     */
    public function getTrustRblowExceptionCacheRemainingTime($year) {
        return $this->getRemainingCacheTimeByKey(self::KEYS['TRUST_RBLOW_EXCEPTION'].$year);
    }
    /**
     * 強制重新讀取信託註記土地所有部例外資料
     */
    public function reloadTrustRblowException($year) {
        $this->getCache()->del(self::KEYS['TRUST_RBLOW_EXCEPTION'].$year);
        return $this->getTrustRblowException($year);
    }
    /**
	 * 取得信託註記土地所有部例外資料
     * default cache time is 24 hours * 60 minutes * 60 seconds = 86400 seconds
	 */
	public function getTrustRblowException($year, $expire_duration = 86400) {
        if ($this->getCache()->isExpired(self::KEYS['TRUST_RBLOW_EXCEPTION'].$year)) {
            global $log;
            $log->info('['.self::KEYS['TRUST_RBLOW_EXCEPTION'].$year.'] 快取資料已失效，重新擷取 ... ');

            $db = $this->getOraDB();
            $db->parse("
                SELECT is48,r1.kcnt as is48_cht,is49,is01,is09,isname,'','','','','','',is03,is04_1,is04_2,'',is05,is_date FROM moiadm.rkeyn r1, 
                    (SELECT *FROM (SELECT * FROM (SELECT * FROM moicad.rsindx  WHERE is00 IN ('B') AND IS_type IN ('M','D') AND IS06='CU') 
                WHERE NOT EXISTS 
                (SELECT gg48,gg49,gg01 FROM (SELECT * FROM moicad.rgall WHERE gg00='B' AND gg30_1 in ('GH','GJ'))  
                WHERE is00=gg00 AND is48=gg48 AND is49=gg49 AND is01=gg01))) 
                WHERE r1.kcde_1='48' 
                AND r1.kcde_2=is48
                and    is03= :bv_year
                ORDER BY is05 desc,is03 desc,is48 desc,is49
            ");
            
            $db->bind(":bv_year", $year);
            $db->execute();
            $result = $db->fetchAll();
            $this->getCache()->set(self::KEYS['TRUST_RBLOW_EXCEPTION'].$year, $result, $expire_duration);

            $log->info("[".self::KEYS['TRUST_RBLOW_EXCEPTION'].$year."] 快取資料已更新 ( ".count($result)." 筆，預計 ${expire_duration} 秒後到期)");

            return $result;
        }
        return $this->getCache()->get(self::KEYS['TRUST_RBLOW_EXCEPTION'].$year);
	}
    /**
     * 非專業代理人區間案件快取剩餘時間
     */
    public function getNonScrivenerCaseCacheRemainingTime($st, $ed) {
        return $this->getRemainingCacheTimeByKey(self::KEYS['NON_SCRIVENER'].$st.$ed);
    }
    /**
     * 強制重新讀取非專業代理人區間案件
     */
    public function reloadNonScrivenerCase($st, $ed) {
        $this->getCache()->del(self::KEYS['NON_SCRIVENER'].$st.$ed);
        return $this->getNonScrivenerCase($st, $ed);
    }
    /**
	 * 取得非專業代理人區間案件
     * default cache time is 24 hours * 60 minutes * 60 seconds = 86400 seconds
	 */
	public function getNonScrivenerCase($st, $ed, $expire_duration = 86400) {
        if ($this->getCache()->isExpired(self::KEYS['NON_SCRIVENER'].$st.$ed)) {
            global $log;
            $log->info('['.self::KEYS['NON_SCRIVENER'].$st.$ed.'] 快取資料已失效，重新擷取 ... ');

            $db = $this->getOraDB();
            $db->parse("
                SELECT
                    t.*,
                    u.KCNT AS RM09_CHT,
                    v.KNAME AS RM11_CHT,
                    r.ab02 AS RM24_NAME,
                    r.ab03 AS RM24_ADDR,
                    r.ab04_1 || r.ab04_2 AS RM24_TEL,
                    r.*
                FROM
                    MOICAS.CRSMS t
                    LEFT JOIN MOICAS.CABRP r ON t.RM24 = r.AB01
                    LEFT JOIN MOIADM.RKEYN u ON t.RM09 = u.KCDE_2 AND u.KCDE_1 = '06'
                    LEFT JOIN MOIADM.RKEYN_ALL v ON (v.KCDE_1 = '48' AND v.KCDE_2 = 'H' AND v.KCDE_3 = t.RM10 AND t.RM11 = v.KCDE_4)
                WHERE 1=1
                    AND RM07_1 BETWEEN :bv_st AND :bv_ed
                    AND t.RM24 IN (
                        SELECT DISTINCT RM24  from MOICAS.CRSMS t
                        WHERE RM07_1 BETWEEN :bv_st AND :bv_ed
                        AND RM24 IS NOT NULL
                        AND RM24 NOT IN (
                            SELECT DISTINCT s.AB01
                            FROM MOICAS.CABRP s
                            WHERE 1=1
                                AND AB09 = 'N'
                                AND AB10 = 'N'
                                AND AB11 = 'N'
                        )
                    )
                ORDER BY t.RM07_1     
            ");
            
            $db->bind(":bv_st", $st);
            $db->bind(":bv_ed", $ed);
            $db->execute();
            $result = $db->fetchAll();
            $this->getCache()->set(self::KEYS['NON_SCRIVENER'].$st.$ed, $result, $expire_duration);

            $log->info("[".self::KEYS['NON_SCRIVENER'].$st.$ed."] 快取資料已更新 ( ".count($result)." 筆，預計 ${expire_duration} 秒後到期)");

            return $result;
        }
        return $this->getCache()->get(self::KEYS['NON_SCRIVENER'].$st.$ed);
    }
    /**
     * 非專業代理人區間WEB案件快取剩餘時間
     */
    public function getNonScrivenerWebCaseCacheRemainingTime($st, $ed, $not_inc_ids = array()) {
        return $this->getRemainingCacheTimeByKey(self::KEYS['NON_SCRIVENER_WEB'].$st.$ed.implode('', $not_inc_ids));
    }
    /**
     * 強制重新讀取非專業代理人區間WEB案件
     */
    public function reloadNonScrivenerWebCase($st, $ed, $not_inc_ids = array()) {
        $this->getCache()->del(self::KEYS['NON_SCRIVENER_WEB'].$st.$ed.implode('', $not_inc_ids));
        return $this->getNonScrivenerWebCase($st, $ed);
    }
    /**
	 * 取得非專業代理人區間WEB案件
     * default cache time is 24 hours * 60 minutes * 60 seconds = 86400 seconds
	 */
	public function getNonScrivenerWebCase($st, $ed, $not_inc_ids = array(), $expire_duration = 86400) {
        if ($this->getCache()->isExpired(self::KEYS['NON_SCRIVENER_WEB'].$st.$ed.implode('', $not_inc_ids))) {
            global $log;
            $log->info('['.self::KEYS['NON_SCRIVENER_WEB'].$st.$ed.implode('', $not_inc_ids).'] 快取資料已失效，重新擷取 ... ');

            $db = $this->getOraDB();
            $IN_CONDITION = "";
            if (!empty($not_inc_ids)) {
                $IN_CONDITION = "AND AB01 NOT IN ('";
                $IN_CONDITION .= implode("','", $not_inc_ids);
                $IN_CONDITION .= "')";
            }
            $db->parse("
                -- 登記案件
                SELECT 
                    q.*,
                    AB04_1 || AB04_2 AS AB04_NON_SCRIVENER_TEL,
                    t.*,
                    SUBSTR(AB01, 1, 5) || LPAD('*', LENGTH(SUBSTR(AB01, 6)), '*') AS AB01_S,
                    (RM01 || '-' || RM02_C.KCNT || '-' || RM03) AS RM123,
                    (RM18_A.LADR) AS RM18_ADDR,
                    (RM21_A.LADR) AS RM21_ADDR,
                    (RM09_C.KCNT) AS RM09_C_KCNT,
                    (RM10_C.KCNT) AS RM10_C_KCNT,
                    (RM11_C.KNAME) AS RM11_C_KCNT,
                    (SUBSTR(RM12, 1, 4) || '-' || SUBSTR(RM12, 5, 4)) AS RM12_C,
                    (SUBSTR(RM15, 1, 5) || '-' || SUBSTR(RM15, 6, 3)) AS RM15_C
                FROM SCRSMS t
                LEFT OUTER JOIN SRLNID RM18_A
                    ON RM18_A.LIDN = RM18
                LEFT OUTER JOIN SRLNID RM21_A
                    ON RM21_A.LIDN = RM21
                LEFT OUTER JOIN SRKEYN RM02_C
                    ON RM02_C.KCDE_1 = '04'
                AND RM02_C.KCDE_2 = RM02
                LEFT OUTER JOIN SRKEYN RM09_C
                    ON RM09_C.KCDE_1 = '06'
                AND RM09_C.KCDE_2 = RM09
                LEFT OUTER JOIN SRKEYN RM10_C
                    ON RM10_C.KCDE_1 = '46'
                AND RM10_C.KCDE_2 = RM10
                LEFT OUTER JOIN MOIADM.RKEYN_ALL RM11_C
                    ON RM11_C.KCDE_1 = '48'
                    AND RM11_C.KCDE_2 = 'H'
                    AND RM11_C.KCDE_3 = RM10
                    AND RM11_C.KCDE_4 = RM11,
                SCABRP q
                WHERE 1 = 1
                AND RM07_1 BETWEEN :bv_st AND :bv_ed
                AND AB_FLAG = 'N'
                AND (AB13 > 2 OR AB23 > 5)
                AND (RM24 = AB01 OR RM24_OTHER = AB01)
                AND NVL(RM18, 'X') <> AB01
                AND NVL(RM21, 'X') <> AB01
                $IN_CONDITION
                
                ORDER BY AB01 DESC, RM07_1 DESC
            ");
            
            $db->bind(":bv_st", $st);
            $db->bind(":bv_ed", $ed);
            $db->execute();
            $result = $db->fetchAll();
            $this->getCache()->set(self::KEYS['NON_SCRIVENER_WEB'].$st.$ed.implode('', $not_inc_ids), $result, $expire_duration);

            $log->info("[".self::KEYS['NON_SCRIVENER_WEB'].$st.$ed.implode('', $not_inc_ids)."] 快取資料已更新 ( ".count($result)." 筆，預計 ${expire_duration} 秒後到期)");

            return $result;
        }
        return $this->getCache()->get(self::KEYS['NON_SCRIVENER_WEB'].$st.$ed.implode('', $not_inc_ids));
    }
    /**
     * 外國人案件快取剩餘時間
     */
    public function getForeignerCaseCacheRemainingTime($year_month) {
        return $this->getRemainingCacheTimeByKey(self::KEYS['FOREIGNER'].$year_month);
    }
    /**
     * 強制重新讀取外國人案件
     */
    public function reloadForeignerCase($year_month) {
        $this->getCache()->del(self::KEYS['FOREIGNER'].$year_month);
        return $this->getForeignerCase($year_month);
    }
    /**
	 * 取得外國人案件
     * default cache time is 24 hours * 60 minutes * 60 seconds = 86400 seconds
	 */
	public function getForeignerCase($year_month, $expire_duration = 86400) {
        if ($this->getCache()->isExpired(self::KEYS['FOREIGNER'].$year_month)) {
            global $log;
            $log->info('['.self::KEYS['FOREIGNER'].$year_month.'] 快取資料已失效，重新擷取 ... ');

            $db = $this->getOraDB();
            $db->parse("
                SELECT DISTINCT
                    t.RM01   AS \"收件年\",
                    t.RM02   AS \"收件字\",
                    t.RM03   AS \"收件號\",
                    t.RM09   AS \"登記原因代碼\",
                    r.KCNT    AS \"登記原因\",
                    t.RM07_1 AS \"收件日期\",
                    t.RM58_1 AS \"結案日期\",
                    t.RM18   AS \"權利人統一編號\",
                    t.RM19   AS \"權利人姓名\",
                    t.RM21   AS \"義務人統一編號\",
                    t.RM22   AS \"義務人姓名\",
                    (CASE
                        WHEN q.LCDE = '1' THEN '本國人'
                        WHEN q.LCDE = '2' THEN '外國人'
                        WHEN q.LCDE = '3' THEN '國有（中央機關）'
                        WHEN q.LCDE = '4' THEN '省市有（省市機關）'
                        WHEN q.LCDE = '5' THEN '縣市有（縣市機關）'
                        WHEN q.LCDE = '6' THEN '鄉鎮市有（鄉鎮市機關）'
                        WHEN q.LCDE = '7' THEN '本國私法人'
                        WHEN q.LCDE = '8' THEN '外國法人'
                        WHEN q.LCDE = '9' THEN '祭祀公業'
                        WHEN q.LCDE = 'A' THEN '其他'
                        WHEN q.LCDE = 'B' THEN '銀行法人'
                        WHEN q.LCDE = 'C' THEN '大陸地區自然人'
                        WHEN q.LCDE = 'D' THEN '大陸地區法人'
                        ELSE q.LCDE
                    END) AS \"外國人類別\",
                    (CASE
                        WHEN t.RM30 = 'A' THEN '初審'
                        WHEN t.RM30 = 'B' THEN '複審'
                        WHEN t.RM30 = 'H' THEN '公告'
                        WHEN t.RM30 = 'I' THEN '補正'
                        WHEN t.RM30 = 'R' THEN '登錄'
                        WHEN t.RM30 = 'C' THEN '校對'
                        WHEN t.RM30 = 'U' THEN '異動完成'
                        WHEN t.RM30 = 'F' THEN '結案'
                        WHEN t.RM30 = 'X' THEN '補正初核'
                        WHEN t.RM30 = 'Y' THEN '駁回初核'
                        WHEN t.RM30 = 'J' THEN '撤回初核'
                        WHEN t.RM30 = 'K' THEN '撤回'
                        WHEN t.RM30 = 'Z' THEN '歸檔'
                        WHEN t.RM30 = 'N' THEN '駁回'
                        WHEN t.RM30 = 'L' THEN '公告初核'
                        WHEN t.RM30 = 'E' THEN '請示'
                        WHEN t.RM30 = 'D' THEN '展期'
                        ELSE t.RM30
                    END) AS \"辦理情形\",
                    (CASE
                        WHEN t.RM31 = 'A' THEN '結案'
                        WHEN t.RM31 = 'B' THEN '撤回'
                        WHEN t.RM31 = 'C' THEN '併案'
                        WHEN t.RM31 = 'D' THEN '駁回'
                        WHEN t.RM31 = 'E' THEN '請示'
                        ELSE t.RM31
                    END) AS \"結案與否\"
                FROM
                    (select * from MOICAS.CRSMS where RM07_1 LIKE :bv_year || '%' AND RM56_1 LIKE :bv_year_month || '%') t,
                    (select * from MOICAD.RLNID p where p.LCDE in ('2', '8', 'C', 'D') ) q, -- 代碼檔 09
                    (select * from MOICAD.RKEYN k where k.KCDE_1 = '06') r
                WHERE
                    ( t.RM18 = q.LIDN OR t.RM21 = q.LIDN ) AND
                    r.KCDE_2 = t.RM09
            ");
            
            $db->bind(":bv_year", substr($year_month, 0, 3));
            $db->bind(":bv_year_month", $year_month);
            $db->execute();
            $result = $db->fetchAll();
            $this->getCache()->set(self::KEYS['FOREIGNER'].$year_month, $result, $expire_duration);

            $log->info("[".self::KEYS['FOREIGNER'].$year_month."] 快取資料已更新 ( ".count($result)." 筆，預計 ${expire_duration} 秒後到期)");

            return $result;
        }
        return $this->getCache()->get(self::KEYS['FOREIGNER'].$year_month);
	}
}
