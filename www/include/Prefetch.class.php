<?php
require_once("init.php");
require_once("DynamicSQLite.class.php");
require_once("OraDB.class.php");
require_once("Cache.class.php");

class Prefetch {
    private const PREFETCH_SQLITE_DB = ROOT_DIR.DIRECTORY_SEPARATOR."assets".DIRECTORY_SEPARATOR."db".DIRECTORY_SEPARATOR."prefetch.db";
    private $ora_db = null;
    private $cache = null;

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

    function __construct() { }

    function __destruct() { }
    /**
     * 目前為公告狀態案件快取剩餘時間
     */
    public function getRM30HCaseCacheRemainingTime() {
        if ($this->getCache()->isExpired('Prefetch::getRM30HCase')) {
            return 0;
        }
        return $this->getCache()->getExpireTimestamp('Prefetch::getRM30HCase') - mktime();
    }
    /**
     * 強制重新讀取目前為公告狀態案件
     */
    public function reloadRM30HCase() {
        $this->getCache()->del('Prefetch::getRM30HCase');
        return $this->getRM30HCase();
    }
    /**
	 * 取得目前為公告狀態案件
     * default cache time is 60 minutes * 60 seconds = 3600 seconds
	 */
	public function getRM30HCase($expire_duration = 3600) {
        if ($this->getCache()->isExpired('Prefetch::getRM30HCase')) {
            global $log;
            $log->info('[Prefetch::getRM30HCase] 快取資料已失效，重新擷取 ... ');

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
            $this->getCache()->set('Prefetch::getRM30HCase', $result, $expire_duration);

            $log->info("[Prefetch::getRM30HCase] 快取資料已更新 ( ".count($result)." 筆，預計 ${expire_duration} 秒後到期)");

            return $result;
        }
        return $this->getCache()->get('Prefetch::getRM30HCase');
	}
}
