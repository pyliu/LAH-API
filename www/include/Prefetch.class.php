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
        'NON_SCRIVENER_REG' => 'Prefetch::getNonScrivenerRegCase',
        'NON_SCRIVENER_SUR' => 'Prefetch::getNonScrivenerSurCase',
        'FOREIGNER' => 'Prefetch::getForeignerCase',
        'TRUST_REG_QUERY' => 'Prefetch::getTrustRegQuery',
        'TRUST_OBLITERATE_LAND' => 'Prefetch::getTrustObliterateLand',
        'TRUST_OBLITERATE_BUILD' => 'Prefetch::getTrustObliterateBuilding',
        '375_LAND_CHANGE' => 'Prefetch::getLand375Change',
        '375_OWNER_CHANGE' => 'Prefetch::getOwner375Change',
        'NOT_DONE_CHANGE' => 'Prefetch::getNotDoneChange',
        'LAND_REF_CHANGE' => 'Prefetch::getLandRefChange',
        'REG_FIX_CASE' => 'Prefetch::getRegFixCase',
        'REG_NOT_DONE_CASE' => 'Prefetch::getRegNotDoneCase',
        'REG_UNTAKEN_CASE' => 'Prefetch::getRegUntakenCase'
    );
    private $ora_db = null;
    private $cache = null;
    private $config = null;
    
	private $site = 'HA';
	private $site_code = 'A';
	private $site_number = 1;

    private function getOraDB() {
        if ($this->ora_db === null) {
            $type = OraDB::getPointDBTarget();
            $this->ora_db = new OraDB($type);
        }
        return $this->ora_db;
    }
    
    private function getCache() {
        if ($this->cache === null) {
            $this->cache = Cache::getInstance(self::PREFETCH_SQLITE_DB);
        }
        return $this->cache;
    }

    private function getSystemConfig() {
        if ($this->config === null) {
            $this->config = System::getInstance();
            // initialize site info
            $this->site = strtoupper($this->config->get('SITE')) ?? 'HA';
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
        return $this->getCache()->getExpireTimestamp($key) - time();
    }

    private function isDBReachable($key_txt) {
        $flag = System::getInstance()->isDBReachable();
        if (!$flag) {
            Logger::getInstance()->error('資料庫無法連線，無法取得更新資料。['.$key_txt.']');
        }
        return $flag;
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
     * default cache time is 12 hours * 60 minutes * 60 seconds = 43200 seconds
	 */
	public function getRM30HCase($expire_duration = 43200) {
        if ($this->getCache()->isExpired(self::KEYS['RM30H'])) {
            Logger::getInstance()->info('['.self::KEYS['RM30H'].'] 快取資料已失效，重新擷取 ... ');
            if ($this->isDBReachable(self::KEYS['RM30H'])) {
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
                    ORDER BY s.RM50, sa11.USER_NAME, s.RM01, s.RM02, s.RM03
                ");
                $db->execute();
                $result = $db->fetchAll();
                $this->getCache()->set(self::KEYS['RM30H'], $result, $expire_duration);
                Logger::getInstance()->info("[".self::KEYS['RM30H']."] 快取資料已更新 ( ".count($result)." 筆，預計 ${expire_duration} 秒後到期)");
                return $result;
            } else {
                return array();
            }
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
            Logger::getInstance()->info('['.self::KEYS['OVERDUE'].'] 快取資料已失效，重新擷取 ... ');
            if ($this->isDBReachable(self::KEYS['OVERDUE'])) {
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
                
                Logger::getInstance()->info(__METHOD__.": Find overdue date between $start and $now cases.");

                $db->bind(":bv_now", $now);
                $db->bind(":bv_start", $start);
                $db->execute();
                $result = $db->fetchAll();
                $this->getCache()->set(self::KEYS['OVERDUE'], $result, $expire_duration);

                Logger::getInstance()->info("[".self::KEYS['OVERDUE']."] 快取資料已更新 ( ".count($result)." 筆，預計 ${expire_duration} 秒後到期)");

                return $result;
            } else {
                return array();
            }
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
            Logger::getInstance()->info('['.self::KEYS['ALMOST_OVERDUE'].'] 快取資料已失效，重新擷取 ... ');
            if ($this->isDBReachable(self::KEYS['ALMOST_OVERDUE'])) {
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
                    Logger::getInstance()->info(__METHOD__.": ".$date_4hrs_later->format("YmdHis")." is over 17:00:00, so add 16 hrs ... ");
                    $date_4hrs_later->modify("+16 hours");
                }
                $now_plus_4hrs = ltrim($date_4hrs_later->format("YmdHis"), "0");	// ex: 1090107081410
                
                Logger::getInstance()->info(__METHOD__.": Find almost overdue date between $now and $now_plus_4hrs cases.");

                $db->bind(":bv_now", $now);
                $db->bind(":bv_now_plus_4hrs", $now_plus_4hrs);
                $db->execute();
                $result = $db->fetchAll();
                $this->getCache()->set(self::KEYS['ALMOST_OVERDUE'], $result, $expire_duration);

                Logger::getInstance()->info("[".self::KEYS['ALMOST_OVERDUE']."] 快取資料已更新 ( ".count($result)." 筆，預計 ${expire_duration} 秒後到期)");

                return $result;
            } else {
                return array();
            }
        }
        return $this->getCache()->get(self::KEYS['ALMOST_OVERDUE']);
    }
    /**
     * 取消請示案件快取剩餘時間
     */
    public function getAskCaseCacheRemainingTime($begin, $end) {
        return $this->getRemainingCacheTimeByKey(self::KEYS['ASK']."_${begin}_${end}");
    }
    /**
     * 強制重新讀取取消請示案件
     */
    public function reloadAskCase($begin, $end) {
        $this->getCache()->del(self::KEYS['ASK']."_${begin}_${end}");
        return $this->getAskCase($begin, $end);
    }
    /**
	 * 取得取消請示的案件
     * default cache time is 60 minutes * 60 seconds = 3600 seconds
	 */
	public function getAskCase($begin, $end, $expire_duration = 3600) {
        if ($this->getCache()->isExpired(self::KEYS['ASK']."_${begin}_${end}")) {
            Logger::getInstance()->info('['.self::KEYS['ASK']."_${begin}_${end}".'] 快取資料已失效，重新擷取 ... ');
            if ($this->isDBReachable(self::KEYS['ASK']."_${begin}_${end}")) {
                $db = $this->getOraDB();
                $db->parse("
                    SELECT * FROM MOICAS.CRSMS t
                    LEFT JOIN MOIADM.RKEYN q ON t.RM09=q.KCDE_2 AND q.KCDE_1 = '06'
                    WHERE
                        (RM02 LIKE :bv_office || '%' AND RM02 NOT LIKE 'H' || :bv_office_end || '%1') AND
                        RM83 IS NOT NULL AND
                        -- RM31 in ('A', 'B', 'C', 'D') AND
                        -- RM29_1 < RM58_1 AND
                        RM07_1 BETWEEN :bv_begin AND :bv_end
                    --ORDER BY t.RM29_1 DESC, t.RM58_1 DESC
                    ORDER BY t.RM01, t.RM02, t.RM03, t.RM83, t.RM07_1
                ");
                
                $office = $this->getSystemConfig()->get('SITE');    // e.g. HB
                $db->bind(":bv_office_end", $office[1]);
                $db->bind(":bv_office", $office);
                $db->bind(":bv_begin", $begin);
                $db->bind(":bv_end", $end);
                $db->execute();
                $result = $db->fetchAll();
                $this->getCache()->set(self::KEYS['ASK']."_${begin}_${end}", $result, $expire_duration);

                Logger::getInstance()->info("[".self::KEYS['ASK']."_${begin}_${end}"."] 快取資料已更新 ( ".count($result)." 筆，預計 ${expire_duration} 秒後到期)");

                return $result;
            } else {
                return array();
            }
        }
        return $this->getCache()->get(self::KEYS['ASK']."_${begin}_${end}");
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
            Logger::getInstance()->info('['.self::KEYS['TRUST_REBOW'].$year.'] 快取資料已失效，重新擷取 ... ');
            if ($this->isDBReachable(self::KEYS['TRUST_REBOW'])) {
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

                Logger::getInstance()->info("[".self::KEYS['TRUST_REBOW'].$year."] 快取資料已更新 ( ".count($result)." 筆，預計 ${expire_duration} 秒後到期)");

                return $result;
            } else {
                return array();
            }
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
            Logger::getInstance()->info('['.self::KEYS['TRUST_REBOW_EXCEPTION'].$year.'] 快取資料已失效，重新擷取 ... ');
            if ($this->isDBReachable(self::KEYS['TRUST_REBOW_EXCEPTION'])) {
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

                Logger::getInstance()->info("[".self::KEYS['TRUST_REBOW_EXCEPTION'].$year."] 快取資料已更新 ( ".count($result)." 筆，預計 ${expire_duration} 秒後到期)");

                return $result;
            } else {
                return array();
            }
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
            Logger::getInstance()->info('['.self::KEYS['TRUST_RBLOW'].$year.'] 快取資料已失效，重新擷取 ... ');
            if ($this->isDBReachable(self::KEYS['TRUST_RBLOW'])) {
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

                Logger::getInstance()->info("[".self::KEYS['TRUST_RBLOW'].$year."] 快取資料已更新 ( ".count($result)." 筆，預計 ${expire_duration} 秒後到期)");

                return $result;
            } else {
                return array();
            }
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
            Logger::getInstance()->info('['.self::KEYS['TRUST_RBLOW_EXCEPTION'].$year.'] 快取資料已失效，重新擷取 ... ');
            if ($this->isDBReachable(self::KEYS['TRUST_RBLOW_EXCEPTION'])) {
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

                Logger::getInstance()->info("[".self::KEYS['TRUST_RBLOW_EXCEPTION'].$year."] 快取資料已更新 ( ".count($result)." 筆，預計 ${expire_duration} 秒後到期)");

                return $result;
            } else {
                return array();
            }
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
            Logger::getInstance()->info('['.self::KEYS['NON_SCRIVENER'].$st.$ed.'] 快取資料已失效，重新擷取 ... ');
            if ($this->isDBReachable(self::KEYS['NON_SCRIVENER'])) {
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

                Logger::getInstance()->info("[".self::KEYS['NON_SCRIVENER'].$st.$ed."] 快取資料已更新 ( ".count($result)." 筆，預計 ${expire_duration} 秒後到期)");

                return $result;
            } else {
                return array();
            }
        }
        return $this->getCache()->get(self::KEYS['NON_SCRIVENER'].$st.$ed);
    }
    /**
     * 非專業代理人區間登記案件快取剩餘時間
     */
    public function getNonScrivenerRegCaseCacheRemainingTime($st, $ed, $not_inc_ids = array()) {
        return $this->getRemainingCacheTimeByKey(self::KEYS['NON_SCRIVENER_REG'].md5($st.$ed.implode('', $not_inc_ids)));
    }
    /**
     * 強制重新讀取非專業代理人區間登記案件
     */
    public function reloadNonScrivenerRegCase($st, $ed, $not_inc_ids = array()) {
        $this->getCache()->del(self::KEYS['NON_SCRIVENER_REG'].md5($st.$ed.implode('', $not_inc_ids)));
        return $this->getNonScrivenerRegCase($st, $ed, $not_inc_ids);
    }
    /**
	 * 取得非專業代理人區間登記案件
     * default cache time is 24 hours * 60 minutes * 60 seconds = 86400 seconds
	 */
	public function getNonScrivenerRegCase($st, $ed, $not_inc_ids = array(), $expire_duration = 86400) {
        $md5_hash = md5($st.$ed.implode('', $not_inc_ids));
        if ($this->getCache()->isExpired(self::KEYS['NON_SCRIVENER_REG'].$md5_hash)) {
            Logger::getInstance()->info('['.self::KEYS['NON_SCRIVENER_REG'].$md5_hash.'] 快取資料已失效，重新擷取 ... ');
            if ($this->isDBReachable(self::KEYS['NON_SCRIVENER_REG'])) {
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
                $this->getCache()->set(self::KEYS['NON_SCRIVENER_REG'].$md5_hash, $result, $expire_duration);

                Logger::getInstance()->info("[".self::KEYS['NON_SCRIVENER_REG'].$md5_hash."] 快取資料已更新 ( ".count($result)." 筆，預計 ${expire_duration} 秒後到期)");

                return $result;
            } else {
                return array();
            }
        }
        return $this->getCache()->get(self::KEYS['NON_SCRIVENER_REG'].$md5_hash);
    }
    /**
     * 非專業代理人區間測量案件快取剩餘時間
     */
    public function getNonScrivenerSurCaseCacheRemainingTime($st, $ed, $not_inc_ids = array()) {
        return $this->getRemainingCacheTimeByKey(self::KEYS['NON_SCRIVENER_SUR'].md5($st.$ed.implode('', $not_inc_ids)));
    }
    /**
     * 強制重新讀取非專業代理人區間測量案件
     */
    public function reloadNonScrivenerSurCase($st, $ed, $not_inc_ids = array()) {
        $this->getCache()->del(self::KEYS['NON_SCRIVENER_SUR'].md5($st.$ed.implode('', $not_inc_ids)));
        return $this->getNonScrivenerSurCase($st, $ed, $not_inc_ids);
    }
    /**
	 * 取得非專業代理人區間測量案件
     * default cache time is 24 hours * 60 minutes * 60 seconds = 86400 seconds
	 */
	public function getNonScrivenerSurCase($st, $ed, $not_inc_ids = array(), $expire_duration = 86400) {
        $md5_hash = md5($st.$ed.implode('', $not_inc_ids));
        if ($this->getCache()->isExpired(self::KEYS['NON_SCRIVENER_SUR'].$md5_hash)) {
            Logger::getInstance()->info('['.self::KEYS['NON_SCRIVENER_SUR'].$md5_hash.'] 快取資料已失效，重新擷取 ... ');
            if ($this->isDBReachable(self::KEYS['NON_SCRIVENER_SUR'])) {
                $db = $this->getOraDB();
                $IN_CONDITION = "";
                if (!empty($not_inc_ids)) {
                    $IN_CONDITION = "AND AB01 NOT IN ('";
                    $IN_CONDITION .= implode("','", $not_inc_ids);
                    $IN_CONDITION .= "')";
                }
                $db->parse("
                    -- 測量案件
                    SELECT
                        c.*,    -- CABRP
                        m.*,    -- CMSMS
                        c.AB04_1 || c.AB04_2 AS AB04_NON_SCRIVENER_TEL,
                        -- SUBSTR(c.AB01, 1, 5) || LPAD('*', LENGTH(SUBSTR(c.AB01, 6)), '*') AS AB01_S,
                        (m.MM01 || '-' || m.MM02_C.KCNT || '(' || m.MM02 || ')-' || m.MM03) AS MM123,
                        (MM13_A.LADR) AS MM13_ADDR,
                        (MM06_C.KCNT) AS RM09_C_KCNT,    -- 事由中文
                        (MM07_C.KCNT) AS RM10_C_KCNT,
                        (MM08_C.KNAME) AS RM11_C_KCNT,
                        (SUBSTR(MM09, 1, 4) || '-' || SUBSTR(MM09, 5, 4)) AS RM12_C,
                        (SUBSTR(MM10, 1, 5) || '-' || SUBSTR(MM10, 6, 3)) AS RM15_C
                    FROM SCMSMS m
                    LEFT OUTER JOIN SRLNID MM13_A
                        ON MM13_A.LIDN = MM13
                    LEFT OUTER JOIN SRKEYN MM02_C
                        ON MM02_C.KCDE_1 = '04'
                    AND MM02_C.KCDE_2 = MM02
                    LEFT OUTER JOIN SRKEYN MM06_C
                        ON MM06_C.KCDE_1 = 'M3'
                    AND MM06_C.KCDE_2 = MM06
                    LEFT OUTER JOIN SRKEYN MM07_C
                        ON MM07_C.KCDE_1 = '46'
                    AND MM07_C.KCDE_2 = MM07
                    LEFT OUTER JOIN MOIADM.RKEYN_ALL MM08_C
                        ON MM08_C.KCDE_1 = '48'
                        AND MM08_C.KCDE_2 = 'H'
                        AND MM08_C.KCDE_3 = MM07
                        AND MM08_C.KCDE_4 = MM08,
                    SCABRP c
                    WHERE 1 = 1
                    AND MM04_1 BETWEEN :bv_st AND :bv_ed
                    AND AB_FLAG = 'N'
                    AND (AB13 > 2 OR AB23 > 5)
                    AND (MM17_1 = AB01 OR MM17_2 = AB01)
                    AND NVL(MM13, 'X') <> AB01
                    AND NVL(MM13, 'X') <> AB01
                    
                    $IN_CONDITION
                    
                    ORDER BY AB01 DESC, MM01 || MM02 || MM03 DESC
                ");
                
                $db->bind(":bv_st", $st);
                $db->bind(":bv_ed", $ed);
                $db->execute();
                $result = $db->fetchAll();
                $this->getCache()->set(self::KEYS['NON_SCRIVENER_SUR'].$md5_hash, $result, $expire_duration);

                Logger::getInstance()->info("[".self::KEYS['NON_SCRIVENER_SUR'].$md5_hash."] 快取資料已更新 ( ".count($result)." 筆，預計 ${expire_duration} 秒後到期)");

                return $result;
            } else {
                return array();
            }
        }
        return $this->getCache()->get(self::KEYS['NON_SCRIVENER_SUR'].$md5_hash);
    }
    /**
     * 外國人案件快取剩餘時間
     */
    public function getForeignerCaseCacheRemainingTime($st, $ed) {
        return $this->getRemainingCacheTimeByKey(self::KEYS['FOREIGNER']."_${st}_${ed}");
    }
    /**
     * 強制重新讀取外國人案件
     */
    public function reloadForeignerCase($st, $ed) {
        $this->getCache()->del(self::KEYS['FOREIGNER']."_${st}_${ed}");
        return $this->getForeignerCase($st, $ed);
    }
    /**
	 * 取得外國人案件
     * default cache time is 24 hours * 60 minutes * 60 seconds = 86400 seconds
	 */
	public function getForeignerCase($st, $ed, $expire_duration = 86400) {
        if ($this->getCache()->isExpired(self::KEYS['FOREIGNER']."_${st}_${ed}")) {
            Logger::getInstance()->info('['.self::KEYS['FOREIGNER']."_${st}_${ed}".'] 快取資料已失效，重新擷取 ... ');
            if ($this->isDBReachable(self::KEYS['FOREIGNER']."_${st}_${ed}")) {
                $db = $this->getOraDB();
                $db->parse("
                    SELECT DISTINCT
                        t.*,
                        r.KCNT AS RM09_CHT,
                        t.RM01   AS \"收件年\",
                        t.RM02   AS \"收件字\",
                        t.RM03   AS \"收件號\",
                        t.RM01 || '-' || t.RM02 || '-' || t.RM03 AS \"收件字號\",
                        t.RM09   AS \"登記原因代碼\",
                        r.KCNT    AS \"登記原因\",
                        t.RM07_1 AS \"收件日期\",
                        t.RM58_1 AS \"結案日期\",
                        t.RM18   AS \"權利人統一編號\",
                        t.RM19   AS \"權利人姓名\",
                        t.RM21   AS \"義務人統一編號\",
                        t.RM22   AS \"義務人姓名\",
                        (CASE
                            WHEN q.LCDE = '1' THEN '".mb_convert_encoding('本國人', 'BIG5', 'UTF-8')."'
                            WHEN q.LCDE = '2' THEN '".mb_convert_encoding('外國人', 'BIG5', 'UTF-8')."'
                            WHEN q.LCDE = '3' THEN '".mb_convert_encoding('國有（中央機關）', 'BIG5', 'UTF-8')."'
                            WHEN q.LCDE = '4' THEN '".mb_convert_encoding('省市有（省市機關）', 'BIG5', 'UTF-8')."'
                            WHEN q.LCDE = '5' THEN '".mb_convert_encoding('縣市有（縣市機關）', 'BIG5', 'UTF-8')."'
                            WHEN q.LCDE = '6' THEN '".mb_convert_encoding('鄉鎮市有（鄉鎮市機關）', 'BIG5', 'UTF-8')."'
                            WHEN q.LCDE = '7' THEN '".mb_convert_encoding('本國私法人', 'BIG5', 'UTF-8')."'
                            WHEN q.LCDE = '8' THEN '".mb_convert_encoding('外國法人', 'BIG5', 'UTF-8')."'
                            WHEN q.LCDE = '9' THEN '".mb_convert_encoding('祭祀公業', 'BIG5', 'UTF-8')."'
                            WHEN q.LCDE = 'A' THEN '".mb_convert_encoding('其他', 'BIG5', 'UTF-8')."'
                            WHEN q.LCDE = 'B' THEN '".mb_convert_encoding('銀行法人', 'BIG5', 'UTF-8')."'
                            WHEN q.LCDE = 'C' THEN '".mb_convert_encoding('大陸地區自然人', 'BIG5', 'UTF-8')."'
                            WHEN q.LCDE = 'D' THEN '".mb_convert_encoding('大陸地區法人', 'BIG5', 'UTF-8')."'
                            ELSE q.LCDE
                        END) AS \"外國人類別\",
                        (CASE
                            WHEN t.RM30 = 'A' THEN '".mb_convert_encoding('初審', 'BIG5', 'UTF-8')."'
                            WHEN t.RM30 = 'B' THEN '".mb_convert_encoding('複審', 'BIG5', 'UTF-8')."'
                            WHEN t.RM30 = 'H' THEN '".mb_convert_encoding('公告', 'BIG5', 'UTF-8')."'
                            WHEN t.RM30 = 'I' THEN '".mb_convert_encoding('補正', 'BIG5', 'UTF-8')."'
                            WHEN t.RM30 = 'R' THEN '".mb_convert_encoding('登錄', 'BIG5', 'UTF-8')."'
                            WHEN t.RM30 = 'C' THEN '".mb_convert_encoding('校對', 'BIG5', 'UTF-8')."'
                            WHEN t.RM30 = 'U' THEN '".mb_convert_encoding('異動完成', 'BIG5', 'UTF-8')."'
                            WHEN t.RM30 = 'F' THEN '".mb_convert_encoding('結案', 'BIG5', 'UTF-8')."'
                            WHEN t.RM30 = 'X' THEN '".mb_convert_encoding('補正初核', 'BIG5', 'UTF-8')."'
                            WHEN t.RM30 = 'Y' THEN '".mb_convert_encoding('駁回初核', 'BIG5', 'UTF-8')."'
                            WHEN t.RM30 = 'J' THEN '".mb_convert_encoding('撤回初核', 'BIG5', 'UTF-8')."'
                            WHEN t.RM30 = 'K' THEN '".mb_convert_encoding('撤回', 'BIG5', 'UTF-8')."'
                            WHEN t.RM30 = 'Z' THEN '".mb_convert_encoding('歸檔', 'BIG5', 'UTF-8')."'
                            WHEN t.RM30 = 'N' THEN '".mb_convert_encoding('駁回', 'BIG5', 'UTF-8')."'
                            WHEN t.RM30 = 'L' THEN '".mb_convert_encoding('公告初核', 'BIG5', 'UTF-8')."'
                            WHEN t.RM30 = 'E' THEN '".mb_convert_encoding('請示', 'BIG5', 'UTF-8')."'
                            WHEN t.RM30 = 'D' THEN '".mb_convert_encoding('展期', 'BIG5', 'UTF-8')."'
                            ELSE t.RM30
                        END) AS \"辦理情形\",
                        (CASE
                            WHEN t.RM31 = 'A' THEN '".mb_convert_encoding('結案', 'BIG5', 'UTF-8')."'
                            WHEN t.RM31 = 'B' THEN '".mb_convert_encoding('撤回', 'BIG5', 'UTF-8')."'
                            WHEN t.RM31 = 'C' THEN '".mb_convert_encoding('併案', 'BIG5', 'UTF-8')."'
                            WHEN t.RM31 = 'D' THEN '".mb_convert_encoding('駁回', 'BIG5', 'UTF-8')."'
                            WHEN t.RM31 = 'E' THEN '".mb_convert_encoding('請示', 'BIG5', 'UTF-8')."'
                            ELSE t.RM31
                        END) AS \"結案與否\"
                    FROM
                        (select * from MOICAS.CRSMS where RM56_1 BETWEEN :bv_begin AND :bv_end) t,  -- RM56_1 校對日期
                        (select * from MOICAD.RLNID p where p.LCDE in ('2', '8', 'C', 'D') ) q, -- 代碼檔 09
                        (select * from MOICAD.RKEYN k where k.KCDE_1 = '06') r
                    WHERE
                        ( t.RM18 = q.LIDN OR t.RM21 = q.LIDN ) AND
                        r.KCDE_2 = t.RM09
                ");
                
                $db->bind(":bv_begin", $st);
                $db->bind(":bv_end", $ed);
                $db->execute();
                $result = $db->fetchAll();
                $this->getCache()->set(self::KEYS['FOREIGNER']."_${st}_${ed}", $result, $expire_duration);

                Logger::getInstance()->info("[".self::KEYS['FOREIGNER']."_${st}_${ed}"."] 快取資料已更新 ( ".count($result)." 筆，預計 ${expire_duration} 秒後到期)");

                return $result;
            } else {
                return array();
            }
        }
        return $this->getCache()->get(self::KEYS['FOREIGNER']."_${st}_${ed}");
	}
    /**
     * 信託資料查詢快取剩餘時間
     */
    public function getTrustRegQueryCacheRemainingTime($st, $ed) {
        return $this->getRemainingCacheTimeByKey(self::KEYS['TRUST_REG_QUERY'].$st.$ed);
    }
    /**
     * 強制重新讀取信託資料查詢
     */
    public function reloadTrustQuery($st, $ed) {
        $this->getCache()->del(self::KEYS['TRUST_REG_QUERY'].$st.$ed);
        return $this->getTrustRegQuery($st, $ed);
    }
    /**
	 * 取得信託區間資料查詢
     * default cache time is 24 hours * 60 minutes * 60 seconds = 86400 seconds
	 */
	public function getTrustRegQuery($st, $ed, $expire_duration = 86400) {
        if ($this->getCache()->isExpired(self::KEYS['TRUST_REG_QUERY'].$st.$ed)) {
            Logger::getInstance()->info('['.self::KEYS['TRUST_REG_QUERY'].$st.$ed.'] 快取資料已失效，重新擷取 ... ');
            if ($this->isDBReachable(self::KEYS['TRUST_REG_QUERY'])) {
                $db = $this->getOraDB();
                $db->parse("
                    SELECT
                        t.*,
                        u.KCNT AS RM09_CHT,
                        v.KNAME AS RM11_CHT
                    FROM
                        MOICAS.CRSMS t
                        LEFT JOIN MOIADM.RKEYN u ON t.RM09 = u.KCDE_2 AND u.KCDE_1 = '06'
                        LEFT JOIN MOIADM.RKEYN_ALL v ON (v.KCDE_1 = '48' AND v.KCDE_2 = 'H' AND v.KCDE_3 = t.RM10 AND t.RM11 = v.KCDE_4)
                    WHERE 1=1
                        AND RM07_1 BETWEEN :bv_st AND :bv_ed
                        AND t.RM09 IN ('CU', 'CX', 'CV', 'CW')
                    ORDER BY t.RM07_1     
                ");
                
                $db->bind(":bv_st", $st);
                $db->bind(":bv_ed", $ed);
                $db->execute();
                $result = $db->fetchAll();
                $this->getCache()->set(self::KEYS['TRUST_REG_QUERY'].$st.$ed, $result, $expire_duration);

                Logger::getInstance()->info("[".self::KEYS['TRUST_REG_QUERY'].$st.$ed."] 快取資料已更新 ( ".count($result)." 筆，預計 ${expire_duration} 秒後到期)");

                return $result;
            } else {
                return array();
            }
        }
        return $this->getCache()->get(self::KEYS['TRUST_REG_QUERY'].$st.$ed);
    }
    /**
     * 土地註記塗銷查詢快取剩餘時間
     */
    public function getTrustObliterateLandCacheRemainingTime($st, $ed) {
        return $this->getRemainingCacheTimeByKey(self::KEYS['TRUST_OBLITERATE_LAND'].$st.$ed);
    }
    /**
     * 強制重新讀取土地註記塗銷資料查詢
     */
    public function reloadTrustObliterateLand($st, $ed) {
        $this->getCache()->del(self::KEYS['TRUST_OBLITERATE_LAND'].$st.$ed);
        return $this->getTrustObliterateLand($st, $ed);
    }
    /**
	 * 取得土地註記塗銷資料查詢
     * default cache time is 24 hours * 60 minutes * 60 seconds = 86400 seconds
	 */
	public function getTrustObliterateLand($st, $ed, $expire_duration = 86400) {
        if ($this->getCache()->isExpired(self::KEYS['TRUST_OBLITERATE_LAND'].$st.$ed)) {
            Logger::getInstance()->info('['.self::KEYS['TRUST_OBLITERATE_LAND'].$st.$ed.'] 快取資料已失效，重新擷取 ... ');
            if ($this->isDBReachable(self::KEYS['TRUST_OBLITERATE_LAND'])) {
                $db = $this->getOraDB();
                $db->parse("
                    SELECT DISTINCT
                        GG48,
                        ra.KNAME AS GG48_CHT,
                        GG49,
                        GG01,
                        BB09,
                        LNAM,
                        GS_TYPE,
                        s.RM01 || '-' || s.RM02 || '-' || s.RM03 AS RM123,
                        s.RM09,
                        r.KCNT AS RM09_CHT,
                        s.RM33,
                        GG30_1,
                        GG30_2
                    FROM WRGALL, SCRSMS s, WRBLOW, SRLNID, MOIADM.RKEYN r, MOIADM.RKEYN_ALL ra
                    WHERE 1=1
                        AND ra.KCDE_1 = '48'
                        AND ra.KCDE_2 = 'H'
                        AND GG48 = ra.KCDE_4(+) 
                        AND r.KCDE_1 = '06'
                        AND s.RM09 = r.KCDE_2(+)
                        AND RM54_1 BETWEEN :bv_st AND :bv_ed
                        AND GG00 = 'B'
                        AND GG30_1 IN ('GH', 'GJ')
                        AND GS_TYPE IN ('D', 'M')
                        AND s.RM01 = GS03
                        AND s.RM02 = GS04_1
                        AND s.RM03 = GS04_2
                        AND NOT EXISTS (
                            SELECT *
                            FROM MOICAD.RBLOW
                            WHERE 1=1
                                AND GG48 = BA48
                                AND GG49 = BA49
                                AND GG01 = BB01
                                AND GS03 = s.RM01
                                AND GS04_1 = s.RM02
                                AND GS04_2 = s.RM03
                        )
                        AND GG48 = BA48
                        AND GG49 = BA49
                        AND GG01 = BB01
                        AND BB09 = LIDN
                    ORDER BY RM123, GG48, GG49, GG01
                ");
                
                $db->bind(":bv_st", $st);
                $db->bind(":bv_ed", $ed);
                $db->execute();
                $result = $db->fetchAll();
                $this->getCache()->set(self::KEYS['TRUST_OBLITERATE_LAND'].$st.$ed, $result, $expire_duration);

                Logger::getInstance()->info("[".self::KEYS['TRUST_OBLITERATE_LAND'].$st.$ed."] 快取資料已更新 ( ".count($result)." 筆，預計 ${expire_duration} 秒後到期)");

                return $result;
            } else {
                return array();
            }
        }
        return $this->getCache()->get(self::KEYS['TRUST_OBLITERATE_LAND'].$st.$ed);
    }
    /**
     * 建物註記塗銷查詢快取剩餘時間
     */
    public function getTrustObliterateBuildingCacheRemainingTime($st, $ed) {
        return $this->getRemainingCacheTimeByKey(self::KEYS['TRUST_OBLITERATE_BUILD'].$st.$ed);
    }
    /**
     * 強制重新讀取建物註記塗銷資料查詢
     */
    public function reloadTrustObliterateBuilding($st, $ed) {
        $this->getCache()->del(self::KEYS['TRUST_OBLITERATE_BUILD'].$st.$ed);
        return $this->getTrustObliterateBuilding($st, $ed);
    }
    /**
	 * 取得建物註記塗銷資料查詢
     * default cache time is 24 hours * 60 minutes * 60 seconds = 86400 seconds
	 */
	public function getTrustObliterateBuilding($st, $ed, $expire_duration = 86400) {
        if ($this->getCache()->isExpired(self::KEYS['TRUST_OBLITERATE_BUILD'].$st.$ed)) {
            Logger::getInstance()->info('['.self::KEYS['TRUST_OBLITERATE_BUILD'].$st.$ed.'] 快取資料已失效，重新擷取 ... ');
            if ($this->isDBReachable(self::KEYS['TRUST_OBLITERATE_BUILD'])) {
                $db = $this->getOraDB();
                $db->parse("
                    SELECT DISTINCT
                        GG48,
                        ra.KNAME AS GG48_CHT,
                        GG49,
                        GG01,
                        EE09 AS BB09, -- rename to BB09 to align with land query
                        LNAM,
                        GS_TYPE,
                        s.RM01 || '-' || s.RM02 || '-' || s.RM03 AS RM123,
                        s.RM09,
                        r.KCNT AS RM09_CHT,
                        s.RM33,
                        GG30_1,
                        GG30_2
                    FROM WRGALL, SCRSMS s, WREBOW, SRLNID, MOIADM.RKEYN r, MOIADM.RKEYN_ALL ra
                    WHERE 1=1
                        AND ra.KCDE_1 = '48'
                        AND ra.KCDE_2 = 'H'
                        AND GG48 = ra.KCDE_4(+) 
                        AND r.KCDE_1 = '06'
                        AND s.RM09 = r.KCDE_2(+)
                        AND RM54_1 BETWEEN '1100101' AND '1100228'
                        AND GG00 = 'E'
                        AND GG30_1 in ('GH', 'GJ')
                        AND GS_TYPE in ('D', 'M')
                        AND s.RM01 = GS03
                        AND s.RM02 = GS04_1
                        AND s.RM03 = GS04_2
                        AND NOT EXISTS (
                            SELECT *
                            FROM MOICAD.REBOW
                            WHERE GG48 = ED48
                                AND GG49 = ED49
                                AND GG01 = EE01
                                AND GS03 = s.RM01
                                AND GS04_1 = s.RM02
                                AND GS04_2 = s.RM03
                        )
                        AND GG48 = ED48
                        AND GG49 = ED49
                        AND GG01 = EE01
                        AND EE09 = LIDN
                    ORDER BY RM123, GG48, GG49, GG01     
                ");
                
                $db->bind(":bv_st", $st);
                $db->bind(":bv_ed", $ed);
                $db->execute();
                $result = $db->fetchAll();
                $this->getCache()->set(self::KEYS['TRUST_OBLITERATE_BUILD'].$st.$ed, $result, $expire_duration);

                Logger::getInstance()->info("[".self::KEYS['TRUST_OBLITERATE_BUILD'].$st.$ed."] 快取資料已更新 ( ".count($result)." 筆，預計 ${expire_duration} 秒後到期)");

                return $result;
            } else {
                return array();
            }
        }
        return $this->getCache()->get(self::KEYS['TRUST_OBLITERATE_BUILD'].$st.$ed);
    }
    /**
     * 375租約異動[土地標示部]查詢快取剩餘時間
     */
    public function getLand375ChangeCacheRemainingTime($st, $ed) {
        return $this->getRemainingCacheTimeByKey(self::KEYS['375_LAND_CHANGE'].$st.$ed);
    }
    /**
     * 強制重新讀取375租約異動[土地標示部]查詢
     */
    public function reloadLand375Change($st, $ed) {
        $this->getCache()->del(self::KEYS['375_LAND_CHANGE'].$st.$ed);
        return $this->getLand375Change($st, $ed);
    }
    /**
	 * 取得375租約異動[土地標示部]查詢
     * default cache time is 24 hours * 60 minutes * 60 seconds = 86400 seconds
	 */
	public function getLand375Change($st, $ed, $expire_duration = 86400) {
        if ($this->getCache()->isExpired(self::KEYS['375_LAND_CHANGE'].$st.$ed)) {
            Logger::getInstance()->info('['.self::KEYS['375_LAND_CHANGE'].$st.$ed.'] 快取資料已失效，重新擷取 ... ');
            if ($this->isDBReachable(self::KEYS['375_LAND_CHANGE'])) {
                $db = $this->getOraDB();
                $db->parse("
                    --375租約異動 MOICAW.RGALL土地標示部
                    SELECT DISTINCT
                        t.*,
                        t.RM01 || '-' || t.RM02 || '-' || t.RM03 AS \"RM123\",
                        rkeyn.KCNT AS RM09_CHT,
                        rkeyn_all.KNAME AS RM11_CHT,
                        rkeyn_all.KNAME AS GG48_CHT,
                        (CASE
                            WHEN t.GS_TYPE = 'A' THEN '".mb_convert_encoding('新增', 'BIG5', 'UTF-8')."'
                            WHEN t.GS_TYPE = 'D' THEN '".mb_convert_encoding('刪除', 'BIG5', 'UTF-8')."'
                            WHEN t.GS_TYPE = 'N' THEN '".mb_convert_encoding('更新後', 'BIG5', 'UTF-8')."'
                            WHEN t.GS_TYPE = 'M' THEN '".mb_convert_encoding('更新前', 'BIG5', 'UTF-8')."'
                            ELSE t.GS_TYPE
                        END) AS GS_TYPE_CHT
                    FROM 
                        (SELECT * FROM MOICAW.RGALL rgall LEFT JOIN MOICAS.CRSMS crsms ON rgall.GS03 = crsms.RM01 AND rgall.GS04_1 = crsms.RM02 AND rgall.GS04_2 = crsms.RM03) t
                        LEFT JOIN MOIADM.RKEYN rkeyn ON t.RM09 = rkeyn.KCDE_2 AND rkeyn.KCDE_1 = '06'
                        LEFT JOIN MOIADM.RKEYN_ALL rkeyn_all ON (rkeyn_all.KCDE_1 = '48' AND rkeyn_all.KCDE_2 = 'H' AND rkeyn_all.KCDE_3 = t.RM10 AND t.RM11 = rkeyn_all.KCDE_4)
                    WHERE
                        t.RM56_1 BETWEEN :bv_st AND :bv_ed
                        AND t.GG30_1 = 'A6'
                    ORDER BY t.GS03,
                        t.GS04_1,
                        t.GS04_2,
                        t.GG48,
                        t.GG49  
                ");
                
                $db->bind(":bv_st", $st);
                $db->bind(":bv_ed", $ed);
                $db->execute();
                $result = $db->fetchAll();
                $this->getCache()->set(self::KEYS['375_LAND_CHANGE'].$st.$ed, $result, $expire_duration);

                Logger::getInstance()->info("[".self::KEYS['375_LAND_CHANGE'].$st.$ed."] 快取資料已更新 ( ".count($result)." 筆，預計 ${expire_duration} 秒後到期)");

                return $result;
            } else {
                return array();
            }
        }
        return $this->getCache()->get(self::KEYS['375_LAND_CHANGE'].$st.$ed);
    }
    /**
     * 375租約異動[土地所有權部]查詢快取剩餘時間
     */
    public function getOwner375ChangeCacheRemainingTime($st, $ed) {
        return $this->getRemainingCacheTimeByKey(self::KEYS['375_OWNER_CHANGE'].$st.$ed);
    }
    /**
     * 強制重新讀取375租約異動[土地所有權部]查詢
     */
    public function reloadOwner375Change($st, $ed) {
        $this->getCache()->del(self::KEYS['375_OWNER_CHANGE'].$st.$ed);
        return $this->getOwner375Change($st, $ed);
    }
    /**
	 * 取得375租約異動[土地所有權部]查詢
     * default cache time is 24 hours * 60 minutes * 60 seconds = 86400 seconds
	 */
	public function getOwner375Change($st, $ed, $expire_duration = 86400) {
        if ($this->getCache()->isExpired(self::KEYS['375_OWNER_CHANGE'].$st.$ed)) {
            Logger::getInstance()->info('['.self::KEYS['375_OWNER_CHANGE'].$st.$ed.'] 快取資料已失效，重新擷取 ... ');
            if ($this->isDBReachable(self::KEYS['375_OWNER_CHANGE'])) {
                $db = $this->getOraDB();
                $db->parse("
                    SELECT DISTINCT
                        r.*,
                        t.*,
                        u.KCNT AS RM09_CHT,
                        w.KNAME    AS BA48_CHT,
                        (CASE
                            WHEN r.BS_TYPE = 'A' THEN '".mb_convert_encoding('新增', 'BIG5', 'UTF-8')."'
                            WHEN r.BS_TYPE = 'D' THEN '".mb_convert_encoding('刪除', 'BIG5', 'UTF-8')."'
                            WHEN r.BS_TYPE = 'N' THEN '".mb_convert_encoding('更新後', 'BIG5', 'UTF-8')."'
                            WHEN r.BS_TYPE = 'M' THEN '".mb_convert_encoding('更新前', 'BIG5', 'UTF-8')."'
                            ELSE r.BS_TYPE
                        END) AS BS_TYPE_CHT,
                        v.LNAM AS BB09_NAME,
                        v.LADR AS BB09_ADDR
                    FROM
                        MOICAW.RBLOW r
                    LEFT JOIN MOICAD.RGALL s ON r.BA49 = s.GG49 AND r.BA48 = s.GG48
                    LEFT JOIN MOICAS.CRSMS t ON r.BS04_2 = t.RM03 AND r.BS04_1 = t.RM02 AND r.BS03 = t.RM01
                    LEFT JOIN MOIADM.RKEYN u ON u.KCDE_1 = '06' AND t.RM09 = u.KCDE_2
                    LEFT JOIN MOICAD.RLNID v ON r.BB09 = v.LIDN
                    LEFT JOIN MOIADM.RKEYN_ALL w ON (w.KCDE_1 = '48' AND w.KCDE_2 = 'H' AND w.KCDE_3 = t.RM10 AND t.RM11 = w.KCDE_4)
                    WHERE 1 = 1
                        -- AND r.BB05 BETWEEN :bv_st AND :bv_ed    -- BY 登記日期
                        AND t.RM56_1 BETWEEN :bv_st AND :bv_ed  -- BY 校對日期
                        AND s.GG30_1 = 'A6' -- 其他登記事項代碼 A6 => ３７５
                ");
                
                $db->bind(":bv_st", $st);
                $db->bind(":bv_ed", $ed);
                $db->execute();
                $result = $db->fetchAll();
                $this->getCache()->set(self::KEYS['375_OWNER_CHANGE'].$st.$ed, $result, $expire_duration);

                Logger::getInstance()->info("[".self::KEYS['375_OWNER_CHANGE'].$st.$ed."] 快取資料已更新 ( ".count($result)." 筆，預計 ${expire_duration} 秒後到期)");

                return $result;
            } else {
                return array();
            }
        }
        return $this->getCache()->get(self::KEYS['375_OWNER_CHANGE'].$st.$ed);
    }

    
    /**
     * 未辦標的註記異動查詢快取剩餘時間
     */
    public function getNotDoneChangeCacheRemainingTime($st, $ed) {
        return $this->getRemainingCacheTimeByKey(self::KEYS['NOT_DONE_CHANGE'].$st.$ed);
    }
    /**
     * 強制重新讀取未辦標的註記異動查詢
     */
    public function reloadNotDoneChange($st, $ed) {
        $this->getCache()->del(self::KEYS['NOT_DONE_CHANGE'].$st.$ed);
        return $this->getNotDoneChange($st, $ed);
    }
    /**
	 * 取得未辦標的註記異動查詢
     * default cache time is 24 hours * 60 minutes * 60 seconds = 86400 seconds
	 */
	public function getNotDoneChange($st, $ed, $expire_duration = 86400) {
        if ($this->getCache()->isExpired(self::KEYS['NOT_DONE_CHANGE'].$st.$ed)) {
            Logger::getInstance()->info('['.self::KEYS['NOT_DONE_CHANGE'].$st.$ed.'] 快取資料已失效，重新擷取 ... ');
            if ($this->isDBReachable(self::KEYS['NOT_DONE_CHANGE'])) {
                $db = $this->getOraDB();
                $db->parse("
                    SELECT DISTINCT
                        u.GG00,                 -- 部別代碼
                        (CASE
                            WHEN u.GG00 = 'B' THEN '".mb_convert_encoding('土地', 'BIG5', 'UTF-8')."'
                            WHEN u.GG00 = 'E' THEN '".mb_convert_encoding('建物', 'BIG5', 'UTF-8')."'
                            ELSE u.GG00
                        END) AS GG00_CHT,       -- 部別
                        u.GS03,                 -- 收件年
                        u.GS04_1,               -- 收件字
                        u.GS04_2,               -- 收件號
                        u.RM09,                 -- 登記原因代碼
                        v.KCNT AS RM09_CHT,     -- 登記原因
                        u.RM56_1,               -- 校對日期
                        u.GS_TYPE,              -- 異動別代碼
                        (CASE
                            WHEN u.GS_TYPE = 'N' THEN '".mb_convert_encoding('更新後', 'BIG5', 'UTF-8')."'
                            WHEN u.GS_TYPE = 'M' THEN '".mb_convert_encoding('更新前', 'BIG5', 'UTF-8')."'
                            WHEN u.GS_TYPE = 'A' THEN '".mb_convert_encoding('新增', 'BIG5', 'UTF-8')."'
                            WHEN u.GS_TYPE = 'D' THEN '".mb_convert_encoding('刪除', 'BIG5', 'UTF-8')."'
                            ELSE u.GS_TYPE
                        END) AS GS_TYPE_CHT,    -- 異動狀態
                        u.GG48,                 -- 段代碼,
                        u.GG49,                 -- 地/建號
                        u.GG01,                 -- 登序
                        w.IS09,                 -- 編統
                        w.ISNAME,               -- 權利人
                        u.GG30_2                -- 內容
                    FROM
                        (SELECT * FROM MOICAW.RGALL t,  MOICAS.CRSMS s WHERE  t.GS04_2 = s.RM03 AND t.GS04_1 = s.RM02 AND t.GS03 = s.RM01 AND t.GG30_1 = '9H' AND s.RM56_1 BETWEEN :bv_st AND :bv_ed) u
                        LEFT JOIN MOIADM.RKEYN v ON u.RM09 = v.kcde_2 AND v.kcde_1 = '06'
                        LEFT JOIN MOICAD.RSINDX w ON u.GG01 = w.IS01 AND u.GG49 = w.IS49 AND u.GG48 = w.IS48 AND u.GS04_2 = w.IS04_2 AND u.GS04_1 = w.IS04_1 AND u.GS03 = w.IS03
                    ORDER BY GG00_CHT,
                        u.GS03,
                        u.GS04_1,
                        u.GS04_2,
                        u.GG48,
                        u.GG49,
                        u.GG01
                ");
                
                $db->bind(":bv_st", $st);
                $db->bind(":bv_ed", $ed);
                $db->execute();
                $result = $db->fetchAll();
                $this->getCache()->set(self::KEYS['NOT_DONE_CHANGE'].$st.$ed, $result, $expire_duration);

                Logger::getInstance()->info("[".self::KEYS['NOT_DONE_CHANGE'].$st.$ed."] 快取資料已更新 ( ".count($result)." 筆，預計 ${expire_duration} 秒後到期)");

                return $result;
            } else {
                return array();
            }
        }
        return $this->getCache()->get(self::KEYS['NOT_DONE_CHANGE'].$st.$ed);
    }

    /**
     * 土地參考異動查詢快取剩餘時間
     */
    public function getLandRefChangeCacheRemainingTime($st, $ed) {
        return $this->getRemainingCacheTimeByKey(self::KEYS['LAND_REF_CHANGE'].$st.$ed);
    }
    /**
     * 強制重新讀取土地參考異動查詢
     */
    public function reloadLandRefChange($st, $ed) {
        $this->getCache()->del(self::KEYS['LAND_REF_CHANGE'].$st.$ed);
        return $this->getLandRefChange($st, $ed);
    }
    /**
	 * 取得土地參考異動查詢
     * default cache time is 24 hours * 60 minutes * 60 seconds = 86400 seconds
	 */
	public function getLandRefChange($st, $ed, $expire_duration = 86400) {
        if ($this->getCache()->isExpired(self::KEYS['LAND_REF_CHANGE'].$st.$ed)) {
            Logger::getInstance()->info('['.self::KEYS['LAND_REF_CHANGE'].$st.$ed.'] 快取資料已失效，重新擷取 ... ');
            if ($this->isDBReachable(self::KEYS['LAND_REF_CHANGE'])) {
                $db = $this->getOraDB();
                $db->parse("
                    select distinct
                        t.as_type,  -- 異動類別
                        (CASE
                            WHEN t.as_type = 'A' THEN '".mb_convert_encoding('新增', 'BIG5', 'UTF-8')."'
                            WHEN t.as_type = 'D' THEN '".mb_convert_encoding('刪除', 'BIG5', 'UTF-8')."'
                            WHEN t.as_type = 'N' THEN '".mb_convert_encoding('更新後', 'BIG5', 'UTF-8')."'
                            WHEN t.as_type = 'M' THEN '".mb_convert_encoding('更新前', 'BIG5', 'UTF-8')."'
                            ELSE t.as_type
                        END) AS as_type_cht,
                        t.aa48,     -- 段代碼
                        x.kname as aa48_cht,    -- 段名稱
                        t.aa49,     -- 地號
                        v.gg30_2,   -- 其他登記事項內容
                        r.af08,     -- 土參類別代碼
                        u.kcnt as af08_cht, -- 土參類別內容
                        r.af09,     -- 土參資訊內容
                        -- s.*         -- 案件資料
                        s.rm01,
                        s.rm02,
                        s.rm03,
                        s.rm09,
                        w.kcnt as rm09_cht, -- 登記原因
                        s.rm56_1    -- 校對日期
                    from MOICAW.RALID t
                    left join MOICAD.AFLBF r
                        on t.aa48 = r.af03 and t.aa49 = r.af04 || r.af05
                    left join RKEYN u
                        on r.af08 = u.kcde_2 and u.kcde_1 = '91'
                    left join MOICAS.CRSMS s
                        on t.as03 = s.rm01 and t.as04_1 = s.rm02 and t.as04_2 = s.rm03
                    left join MOICAW.RGALL v
                        on t.as03 = v.gs03 and t.as04_1 = v.gs04_1 and t.as04_2 = v.gs04_2
                    left join MOIADM.RKEYN w
                        on s.rm09 = w.kcde_2 AND w.kcde_1 = '06'
                    left join MOIADM.RKEYN_ALL x
                        on x.kcde_2 = 'H' and x.kcde_1 = '48' and x.kcde_4 = t.aa48
                    where 1 = 1
                        and (t.aa05 between :bv_st and :bv_ed)
                        and r.af09 IS NOT NULL
                ");
                
                $db->bind(":bv_st", $st);
                $db->bind(":bv_ed", $ed);
                $db->execute();
                $result = $db->fetchAll();
                $this->getCache()->set(self::KEYS['LAND_REF_CHANGE'].$st.$ed, $result, $expire_duration);

                Logger::getInstance()->info("[".self::KEYS['LAND_REF_CHANGE'].$st.$ed."] 快取資料已更新 ( ".count($result)." 筆，預計 ${expire_duration} 秒後到期)");

                return $result;
            } else {
                return array();
            }
        }
        return $this->getCache()->get(self::KEYS['LAND_REF_CHANGE'].$st.$ed);
    }
    
    /**
     * 補正案件查詢快取剩餘時間
     */
    public function getRegFixCaseCacheRemainingTime() {
        return $this->getRemainingCacheTimeByKey(self::KEYS['REG_FIX_CASE']);
    }
    /**
     * 強制重新讀取補正案件查詢
     */
    public function reloadRegFixCase() {
        $this->getCache()->del(self::KEYS['REG_FIX_CASE']);
        return $this->getRegFixCase();
    }
    /**
	 * 取得補正案件查詢
     * default cache time is 24 hours * 60 minutes * 60 seconds = 86400 seconds
	 */
	public function getRegFixCase($expire_duration = 86400) {
        global $site_code; // should from GlobalConstants.inc.php
        if ($this->getCache()->isExpired(self::KEYS['REG_FIX_CASE'])) {
            Logger::getInstance()->info('['.self::KEYS['REG_FIX_CASE'].'] 快取資料已失效，重新擷取 ... ');
            if ($this->isDBReachable(self::KEYS['REG_FIX_CASE'])) {
                $db = $this->getOraDB();
                $db->parse("
                    select distinct 
                        S.*,
                        Ar.kcnt      AS RM09_CHT,
                        Au.USER_NAME AS RM45_CHT,
                        v.KNAME      AS RM11_CHT
                    from MOICAS.CRSMS     S
                    left join MOIADM.RKEYN Ar on Ar.kcde_1 = '06' and S.rm09 = Ar.kcde_2
                    left join MOIADM.SYSAUTH1  Au on S.RM45 = Au.USER_ID
                    left join MOIADM.SYSAUTH1  Au2 on S.RM30_1 = Au2.USER_ID
                    left join MOIADM.RKEYN_ALL v on (v.KCDE_1 = '48' AND v.KCDE_2 = 'H' AND v.KCDE_3 = S.RM10 AND S.RM11 = v.KCDE_4)
                    where 1 = 1
                        and S.rm30 in ('I', 'X') -- 補正、補正初核
                        and (S.rm99 IS NULL or (S.rm99 = 'Y' and S.rm101 = '${site_code}'))  -- 本所案件
                    order by S.RM50, Au.USER_NAME
                ");
                
                $db->execute();
                $result = $db->fetchAll();
                $this->getCache()->set(self::KEYS['REG_FIX_CASE'], $result, $expire_duration);

                Logger::getInstance()->info("[".self::KEYS['REG_FIX_CASE']."] 快取資料已更新 ( ".count($result)." 筆，預計 ${expire_duration} 秒後到期)");

                return $result;
            } else {
                return array();
            }
        }
        return $this->getCache()->get(self::KEYS['REG_FIX_CASE']);
    }

    /**
     * 未結案登記案件查詢快取剩餘時間
     */
    public function getRegNotDoneCaseCacheRemainingTime() {
        return $this->getRemainingCacheTimeByKey(self::KEYS['REG_NOT_DONE_CASE']);
    }
    /**
     * 強制重新讀取未結案登記案件查詢
     */
    public function reloadRegNotDoneCase() {
        $this->getCache()->del(self::KEYS['REG_NOT_DONE_CASE']);
        return $this->getRegNotDoneCase();
    }
    /**
	 * 取得未結案登記案件查詢
     * default cache time is 24 hours * 60 minutes * 60 seconds = 86400 seconds
	 */
	public function getRegNotDoneCase($expire_duration = 86400) {
        global $site_code; // should from GlobalConstants.inc.php
        if ($this->getCache()->isExpired(self::KEYS['REG_NOT_DONE_CASE'])) {
            Logger::getInstance()->info('['.self::KEYS['REG_NOT_DONE_CASE'].'] 快取資料已失效，重新擷取 ... ');
            if ($this->isDBReachable(self::KEYS['REG_NOT_DONE_CASE'])) {
                $db = $this->getOraDB();
                $db->parse("
                    SELECT *
                    FROM SCRSMS 
                    LEFT JOIN SRKEYN ON KCDE_1 = '06' AND RM09 = KCDE_2
                    WHERE RM31 IS NULL AND (RM99 IS NULL OR (RM99 = 'Y' AND RM101 = :bv_site))
                    ORDER BY RM07_1, RM07_2 DESC
                ");
                $db->bind(":bv_site", $this->site);
                $db->execute();
                $result = $db->fetchAll();
                $this->getCache()->set(self::KEYS['REG_NOT_DONE_CASE'], $result, $expire_duration);
                Logger::getInstance()->info("[".self::KEYS['REG_NOT_DONE_CASE']."] 快取資料已更新 ( ".count($result)." 筆，預計 ${expire_duration} 秒後到期)");
                return $result;
            } else {
                return array();
            }
        }
        return $this->getCache()->get(self::KEYS['REG_NOT_DONE_CASE']);
    }
    
    /**
     * 結案未歸檔登記案件查詢快取剩餘時間
     */
    public function getRegUntakenCaseCacheRemainingTime($st, $ed) {
        return $this->getRemainingCacheTimeByKey(self::KEYS['REG_UNTAKEN_CASE'].$st.$ed);
    }
    /**
     * 強制重新讀取結案未歸檔登記案件查詢
     */
    public function reloadRegUntakenCase($st, $ed) {
        $this->getCache()->del(self::KEYS['REG_UNTAKEN_CASE'].$st.$ed);
        return $this->getRegUntakenCase($st, $ed);
    }
    /**
	 * 取得結案未歸檔登記案件查詢
     * default cache time is 24 hours * 60 minutes * 60 seconds = 86400 seconds
	 */
	public function getRegUntakenCase($st, $ed, $expire_duration = 3600) {
        global $site_code; // should from GlobalConstants.inc.php
        if ($this->getCache()->isExpired(self::KEYS['REG_UNTAKEN_CASE'].$st.$ed)) {
            Logger::getInstance()->info('['.self::KEYS['REG_UNTAKEN_CASE'].'] 快取資料已失效，重新擷取 ... ');
            if ($this->isDBReachable(self::KEYS['REG_UNTAKEN_CASE'])) {
                $db = $this->getOraDB();
                $db->parse("
                    select * from MOICAS.CRSMS t
                    left join MOIADM.RKEYN r ON r.KCDE_1 = '06' AND t.RM09 = r.KCDE_2
                    where RM07_1 between :bv_st and :bv_ed
                    and (rm99 is null or rm101 = :bv_site)
                    and rm31 = 'A'
                    and rm30 <> 'Z'
                    order by rm07_1, rm07_2 desc
                ");
                $db->bind(":bv_site", $this->site);
                $db->bind(":bv_st", $st);
                $db->bind(":bv_ed", $ed);
                $db->execute();
                $result = $db->fetchAll();
                $this->getCache()->set(self::KEYS['REG_UNTAKEN_CASE'], $result, $expire_duration);
                Logger::getInstance()->info("[".self::KEYS['REG_UNTAKEN_CASE']."] 快取資料已更新 ( ".count($result)." 筆，預計 ${expire_duration} 秒後到期)");
                return $result;
            } else {
                return array();
            }
        }
        return $this->getCache()->get(self::KEYS['REG_UNTAKEN_CASE'].$st.$ed);
    }

}
