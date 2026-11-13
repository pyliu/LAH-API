<?php
if (!defined('DB_DIR') && !defined('ROOT_DIR')) {
    require_once(__DIR__ . DIRECTORY_SEPARATOR . 'GlobalConstants.inc.php');
}
require_once(__DIR__ . DIRECTORY_SEPARATOR . 'SmsV1StoreInterface.class.php');

/**
 * SmsV1MockStore - MOCK 模式專用 SQLite 測試接縫（sms_mock.db）
 */
class SmsV1MockStore implements SmsV1StoreInterface {
    /** @var string */
    private $dbPath;

    /** @var \PDO|null */
    private $pdo = null;

    /**
     * @param string|null $dbPath 自訂 SQLite 路徑（預設使用 DB_DIR . '/sms_mock.db'）
     */
    public function __construct(?string $dbPath = null) {
        if ($dbPath !== null && $dbPath !== '') {
            $this->dbPath = $dbPath;
        } else {
            $dbDir = defined('DB_DIR') ? DB_DIR : (ROOT_DIR . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'db');
            $this->dbPath = $dbDir . DIRECTORY_SEPARATOR . 'sms_mock.db';
        }
        $this->initialize();
    }

    public function getDbPath(): string {
        return $this->dbPath;
    }

    public function getPdo(): \PDO {
        if ($this->pdo === null) {
            $this->initialize();
        }
        return $this->pdo;
    }

    /**
     * 取得站台與縣市基本資訊（查詢 SRKEYN 55/01 與 66/01）
     *
     * @return array{site_id: string, office_name: string, county_name: string}
     */
    public function getMetadata(): array {
        $pdo = $this->getPdo();
        $stmt = $pdo->query(
            "SELECT KCDE_1, KCDE_2, KCNT, KRMK
             FROM SRKEYN
             WHERE (KCDE_1 = '55' AND KCDE_2 = '01')
                OR (KCDE_1 = '66' AND KCDE_2 = '01')"
        );
        $rows = $stmt ? $stmt->fetchAll(\PDO::FETCH_ASSOC) : array();

        $siteId = '';
        $officeName = '';
        $countyName = '';

        foreach ($rows as $row) {
            $k1 = trim((string)($row['KCDE_1'] ?? ''));
            $k2 = trim((string)($row['KCDE_2'] ?? ''));
            if ($k1 === '55' && $k2 === '01') {
                $siteId = trim((string)($row['KRMK'] ?? ''));
                $officeName = trim((string)($row['KCNT'] ?? ''));
            } elseif ($k1 === '66' && $k2 === '01') {
                $countyName = trim((string)($row['KCNT'] ?? ''));
            }
        }

        return array(
            'site_id' => $siteId,
            'office_name' => $officeName,
            'county_name' => $countyName,
        );
    }

    /**
     * 查詢三表佇列或歷史紀錄（依 MS07_1 || MS07_2 DESC 排序並限制筆數）
     *
     * @param string $table main|wait|log
     * @param int $limit
     * @return array<int, array<string, string>>
     */
    public function getRecords(string $table, int $limit = 200): array {
        if ($limit < 1) {
            throw new \InvalidArgumentException('Limit must be a positive integer.');
        }

        switch ($table) {
            case 'main':
                $sql = "SELECT * FROM SSMSMAIN WHERE MS_TYPE = 'M' ORDER BY COALESCE(MS07_1, '') || COALESCE(MS07_2, '') DESC LIMIT :limit";
                break;
            case 'wait':
                $sql = "SELECT * FROM SSMSWAIT WHERE MS_TYPE IN ('M', 'W', 'O') ORDER BY COALESCE(MS07_1, '') || COALESCE(MS07_2, '') DESC LIMIT :limit";
                break;
            case 'log':
                $sql = "SELECT * FROM SSMSLOG WHERE MS_TYPE IN ('M', 'W', 'O') ORDER BY COALESCE(MS07_1, '') || COALESCE(MS07_2, '') DESC LIMIT :limit";
                break;
            default:
                throw new \InvalidArgumentException('Invalid table parameter. Allowed values: main, wait, log.');
        }

        $pdo = $this->getPdo();
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        if (!is_array($rows)) {
            return array();
        }

        $normalized = array();
        foreach ($rows as $row) {
            $item = array();
            foreach ($row as $key => $value) {
                $lowerKey = strtolower((string)$key);
                $item[$lowerKey] = $value === null ? '' : rtrim((string)$value);
            }
            $normalized[] = $item;
        }
        return $normalized;
    }

    private function initialize(): void {
        if ($this->dbPath !== ':memory:') {
            $dir = dirname($this->dbPath);
            if (!is_dir($dir)) {
                @mkdir($dir, 0777, true);
            }
        }

        $this->pdo = new \PDO('sqlite:' . $this->dbPath);
        $this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $this->pdo->setAttribute(\PDO::ATTR_TIMEOUT, 10);
        $this->pdo->exec('PRAGMA busy_timeout = 10000;');
        if ($this->dbPath !== ':memory:') {
            $this->pdo->exec('PRAGMA journal_mode = WAL;');
        }

        $this->createTables();
        $this->seedDefaultSrkeynFixtures();
    }

    private function createTables(): void {
        $ddlStatements = array(
            "CREATE TABLE IF NOT EXISTS SRKEYN (
                KCDE_1 TEXT NOT NULL,
                KCDE_2 TEXT NOT NULL,
                KCNT TEXT,
                KRMK TEXT,
                PRIMARY KEY (KCDE_1, KCDE_2)
            )",
            "CREATE TABLE IF NOT EXISTS SSMSMAIN (
                MS03 TEXT,
                MS04_1 TEXT,
                MS04_2 TEXT,
                MS07_1 TEXT,
                MS07_2 TEXT,
                MS14 TEXT,
                MS30 TEXT,
                MS_TYPE TEXT,
                MS_NOTE TEXT,
                MS_MAIL TEXT,
                APP_SEQ TEXT,
                APP_ID TEXT,
                GOV_ID TEXT,
                CC00 TEXT,
                CC48 TEXT,
                CC49 TEXT,
                GOV_OID TEXT
            )",
            "CREATE TABLE IF NOT EXISTS SSMSWAIT (
                MS03 TEXT,
                MS04_1 TEXT,
                MS04_2 TEXT,
                MS07_1 TEXT,
                MS07_2 TEXT,
                MS14 TEXT,
                MS30 TEXT,
                MS32 TEXT DEFAULT '0',
                MS_TYPE TEXT,
                MS_MAIL TEXT,
                MS_NOTE TEXT,
                APP_SEQ TEXT,
                APP_ID TEXT,
                GOV_ID TEXT,
                CC00 TEXT,
                CC48 TEXT,
                CC49 TEXT,
                GOV_OID TEXT
            )",
            "CREATE TABLE IF NOT EXISTS SSMSLOG (
                MS03 TEXT,
                MS04_1 TEXT,
                MS04_2 TEXT,
                MS07_1 TEXT,
                MS07_2 TEXT,
                MS14 TEXT,
                MS30 TEXT,
                MS31 TEXT,
                MS33 TEXT,
                MS_TYPE TEXT,
                MS_MAIL TEXT,
                MS_NOTE TEXT,
                APP_SEQ TEXT,
                APP_ID TEXT,
                GOV_ID TEXT,
                CC00 TEXT,
                CC48 TEXT,
                CC49 TEXT,
                GOV_OID TEXT
            )",
            "CREATE TABLE IF NOT EXISTS SCRSMS (
                RM01 TEXT,
                RM02 TEXT,
                RM03 TEXT,
                RM07_1 TEXT,
                RM07_2 TEXT,
                RM09 TEXT,
                RM30 TEXT,
                RM31 TEXT,
                RM32 TEXT,
                RM56_1 TEXT,
                RM56_2 TEXT,
                RM99 TEXT,
                RM100 TEXT,
                RM101 TEXT
            )",
            "CREATE TABLE IF NOT EXISTS SCRSMS_RM1821 (
                RM01 TEXT,
                RM02 TEXT,
                RM03 TEXT,
                RM_TYPE TEXT,
                RM_ID TEXT
            )",
            "CREATE TABLE IF NOT EXISTS SCRSMS_RM1215 (
                RM01 TEXT,
                RM02 TEXT,
                RM03 TEXT,
                RM00 TEXT,
                RM11 TEXT,
                RM1215 TEXT
            )",
            "CREATE TABLE IF NOT EXISTS TRINDX (
                II03 TEXT,
                II04_1 TEXT,
                II04_2 TEXT,
                II00 TEXT,
                II_DATA TEXT,
                IIKEY TEXT
            )",
            "CREATE TABLE IF NOT EXISTS SRSINDX (
                IS03 TEXT,
                IS04_1 TEXT,
                IS04_2 TEXT,
                IS00 TEXT,
                IS01 TEXT,
                IS09 TEXT,
                IS48 TEXT,
                IS49 TEXT
            )",
            "CREATE TABLE IF NOT EXISTS SRBLOW (
                BA48 TEXT,
                BA49 TEXT,
                BB01 TEXT,
                BB09 TEXT
            )",
            "CREATE TABLE IF NOT EXISTS SREBOW (
                ED48 TEXT,
                ED49 TEXT,
                EE01 TEXT,
                EE09 TEXT
            )",
            "CREATE TABLE IF NOT EXISTS SSMS_CODE04 (
                COD04 TEXT
            )",
            "CREATE TABLE IF NOT EXISTS SSMS_CODE06 (
                COD06 TEXT,
                ITEMS TEXT
            )",
            "CREATE TABLE IF NOT EXISTS SSMS_USERINFO_M (
                LIDN TEXT,
                LNAM TEXT,
                REPLY_MOBILE TEXT,
                MOBILE TEXT,
                MOBILE2 TEXT,
                REPLY_EMAIL TEXT,
                EMAIL TEXT,
                EMAIL2 TEXT,
                SMS_FLAG TEXT
            )",
            "CREATE TABLE IF NOT EXISTS SSMS_GOVINFO_D (
                APP_SEQ TEXT,
                GOV_ID TEXT,
                CC00 TEXT,
                CC48 TEXT,
                CC49 TEXT
            )",
            "CREATE TABLE IF NOT EXISTS SMS_GOVINFO_M (
                APP_SEQ TEXT,
                APP_ID TEXT,
                GOV_NAME TEXT,
                EMAIL TEXT,
                APP_STOP TEXT,
                GOV_OID TEXT
            )",
            "CREATE TABLE IF NOT EXISTS SSMS_GOVINFO_M (
                APP_SEQ TEXT,
                EMAIL_SUB TEXT
            )"
        );

        foreach ($ddlStatements as $ddl) {
            $this->pdo->exec($ddl);
        }
    }

    private function seedDefaultSrkeynFixtures(): void {
        $fixtures = array(
            array('55', '01', '桃園地政事務所', 'HA'),
            array('66', '01', '桃園市', ''),
            array('55', '02', '桃園', ''),
            array('SMS', '01', '', ''),
        );

        $stmt = $this->pdo->prepare(
            'INSERT OR IGNORE INTO SRKEYN (KCDE_1, KCDE_2, KCNT, KRMK) VALUES (:k1, :k2, :kcnt, :krmk)'
        );
        foreach ($fixtures as $row) {
            $stmt->execute(array(
                ':k1' => $row[0],
                ':k2' => $row[1],
                ':kcnt' => $row[2],
                ':krmk' => $row[3],
            ));
        }
    }
}
