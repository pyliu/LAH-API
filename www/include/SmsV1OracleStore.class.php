<?php
require_once(__DIR__ . DIRECTORY_SEPARATOR . 'SmsV1Encoding.class.php');
require_once(__DIR__ . DIRECTORY_SEPARATOR . 'SmsV1StoreInterface.class.php');

/**
 * SmsV1OracleStore - Oracle 9i 資料存取實作（透過 OraDBWrapper 原生 OCI8 連線）
 */
class SmsV1OracleStore implements SmsV1StoreInterface {
    /** @var OraDBWrapper|null */
    private $wrapper;

    public function __construct(?OraDBWrapper $wrapper = null) {
        $this->wrapper = $wrapper;
    }

    /**
     * 取得站台與縣市基本資訊（查詢 SRKEYN 55/01 與 66/01）
     *
     * @return array{site_id: string, office_name: string, county_name: string}
     */
    public function getMetadata(): array {
        $rows = $this->queryRaw(
            "SELECT KCDE_1, KCDE_2, KCNT, KRMK
             FROM SRKEYN
             WHERE (KCDE_1 = '55' AND KCDE_2 = '01')
                OR (KCDE_1 = '66' AND KCDE_2 = '01')"
        );

        $siteId = '';
        $officeName = '';
        $countyName = '';

        foreach ($rows as $row) {
            $k1 = trim(SmsV1Encoding::fromOracleEncoding($row['KCDE_1'] ?? ''));
            $k2 = trim(SmsV1Encoding::fromOracleEncoding($row['KCDE_2'] ?? ''));
            if ($k1 === '55' && $k2 === '01') {
                $siteId = trim(SmsV1Encoding::fromOracleEncoding($row['KRMK'] ?? ''));
                $officeName = trim(SmsV1Encoding::fromOracleEncoding($row['KCNT'] ?? ''));
            } elseif ($k1 === '66' && $k2 === '01') {
                $countyName = trim(SmsV1Encoding::fromOracleEncoding($row['KCNT'] ?? ''));
            }
        }

        return array(
            'site_id' => $siteId,
            'office_name' => $officeName,
            'county_name' => $countyName,
        );
    }

    /**
     * 查詢三表佇列或歷史紀錄（Oracle 9i ROWNUM 子查詢分頁，回傳 UTF-8 欄位）
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
                $innerSql = "SELECT * FROM SSMSMAIN WHERE MS_TYPE = 'M' ORDER BY MS07_1 || MS07_2 DESC";
                break;
            case 'wait':
                $innerSql = "SELECT * FROM SSMSWAIT WHERE MS_TYPE IN ('M', 'W', 'O') ORDER BY MS07_1 || MS07_2 DESC";
                break;
            case 'log':
                $innerSql = "SELECT * FROM SSMSLOG WHERE MS_TYPE IN ('M', 'W', 'O') ORDER BY MS07_1 || MS07_2 DESC";
                break;
            default:
                throw new \InvalidArgumentException('Invalid table parameter. Allowed values: main, wait, log.');
        }

        $sql = "SELECT * FROM ({$innerSql}) WHERE ROWNUM <= :bv_limit";
        $rows = $this->queryRaw($sql, array(':bv_limit' => $limit));

        $normalized = array();
        foreach ($rows as $row) {
            $item = array();
            foreach ($row as $key => $value) {
                $lowerKey = strtolower(SmsV1Encoding::fromOracleEncoding((string)$key));
                $decoded = $value === null ? '' : SmsV1Encoding::fromOracleEncoding((string)$value);
                $item[$lowerKey] = rtrim($decoded);
            }
            $normalized[] = $item;
        }
        return $normalized;
    }

    /**
     * 執行單一查詢並回傳原始 Oracle 位元組資料列
     *
     * @param string $sql
     * @param array<string, mixed> $binds
     * @return array<int, array<string, mixed>>
     */
    public function queryRaw(string $sql, array $binds = array()): array {
        $db = $this->requireReachableDb();
        $db->parse($sql);
        foreach ($binds as $key => $value) {
            $db->bind($key, $value);
        }
        $db->execute();
        $rows = $db->fetchAll(true);
        return is_array($rows) ? $rows : array();
    }

    /**
     * 預留原子交易執行 helper：將多筆 DML 語句包入單一 PL/SQL BEGIN ... END; 區塊，
     * 確保在單一資料庫執行呼叫中原子提交或回滾。
     *
     * @param array<int, string> $statements
     * @param array<string, mixed> $binds
     */
    public function executePlSqlBlock(array $statements, array $binds = array()): void {
        $cleaned = array();
        foreach ($statements as $stmt) {
            $trimmed = rtrim(trim($stmt), "; \t\n\r\0\x0B");
            if ($trimmed !== '') {
                $cleaned[] = $trimmed . ';';
            }
        }
        if (empty($cleaned)) {
            return;
        }

        $plsql = "BEGIN\n" . implode("\n", $cleaned) . "\nEND;";
        $db = $this->requireReachableDb();
        $db->parse($plsql);
        foreach ($binds as $key => $value) {
            $db->bind($key, $value);
        }
        $db->execute();
    }

    private function getWrapper(): OraDBWrapper {
        if ($this->wrapper === null) {
            if (!class_exists('OraDBWrapper', false)) {
                require_once(__DIR__ . DIRECTORY_SEPARATOR . 'OraDBWrapper.class.php');
            }
            $this->wrapper = new OraDBWrapper();
        }
        return $this->wrapper;
    }

    /**
     * @return OraDB
     */
    private function requireReachableDb() {
        $wrapper = $this->getWrapper();
        if (!$wrapper->reachable()) {
            throw new \RuntimeException('Oracle database is not reachable.');
        }
        $db = $wrapper->getDB();
        if ($db === null) {
            throw new \RuntimeException('Oracle database connection is unavailable.');
        }
        return $db;
    }
}
