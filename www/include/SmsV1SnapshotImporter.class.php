<?php
require_once(__DIR__ . DIRECTORY_SEPARATOR . 'SmsV1MockStore.class.php');

/**
 * SmsV1SnapshotImporter - 唯讀 Oracle SQL 驗證與 sms_mock.db 快照匯入核心（PHP 7.4 / 8.5 雙軌相容）
 */
class SmsV1SnapshotImporter {
    /**
     * 允許匯入的 20 張資料表與欄位白名單（須與 SmsV1MockStore::createTables 保持一致）
     *
     * @var array<string, array<int, string>>
     */
    public const TABLE_COLUMNS = array(
        'SRKEYN' => array('KCDE_1', 'KCDE_2', 'KCNT', 'KRMK'),
        'SSMSMAIN' => array(
            'MS03', 'MS04_1', 'MS04_2', 'MS07_1', 'MS07_2', 'MS14', 'MS30',
            'MS_TYPE', 'MS_NOTE', 'MS_MAIL', 'APP_SEQ', 'APP_ID', 'GOV_ID',
            'CC00', 'CC48', 'CC49', 'GOV_OID',
        ),
        'SSMSWAIT' => array(
            'MS03', 'MS04_1', 'MS04_2', 'MS07_1', 'MS07_2', 'MS14', 'MS30', 'MS32',
            'MS_TYPE', 'MS_MAIL', 'MS_NOTE', 'APP_SEQ', 'APP_ID', 'GOV_ID',
            'CC00', 'CC48', 'CC49', 'GOV_OID',
        ),
        'SSMSLOG' => array(
            'MS03', 'MS04_1', 'MS04_2', 'MS07_1', 'MS07_2', 'MS14', 'MS30', 'MS31', 'MS33',
            'MS_TYPE', 'MS_MAIL', 'MS_NOTE', 'APP_SEQ', 'APP_ID', 'GOV_ID',
            'CC00', 'CC48', 'CC49', 'GOV_OID',
        ),
        'SCRSMS' => array(
            'RM01', 'RM02', 'RM03', 'RM07_1', 'RM07_2', 'RM09',
            'RM30', 'RM31', 'RM32', 'RM56_1', 'RM56_2', 'RM99', 'RM100', 'RM101',
        ),
        'SCMSMS' => array('MM01', 'MM02', 'MM03', 'MM22', 'MM23'),
        'SCRSMS_RM1821' => array('RM01', 'RM02', 'RM03', 'RM_TYPE', 'RM_ID'),
        'SCRSMS_RM1215' => array('RM01', 'RM02', 'RM03', 'RM00', 'RM11', 'RM1215'),
        'TRINDX' => array('II03', 'II04_1', 'II04_2', 'II00', 'II_DATA', 'IIKEY'),
        'SRSINDX' => array('IS03', 'IS04_1', 'IS04_2', 'IS00', 'IS01', 'IS09', 'IS48', 'IS49'),
        'SRBLOW' => array('BA48', 'BA49', 'BB01', 'BB09'),
        'SREBOW' => array('ED48', 'ED49', 'EE01', 'EE09'),
        'SSMS_CODE04' => array('COD04'),
        'SSMS_CODE06' => array('COD06', 'ITEMS'),
        'SSMS_USERINFO_M' => array(
            'LIDN', 'LNAM', 'REPLY_MOBILE', 'MOBILE', 'MOBILE2',
            'REPLY_EMAIL', 'EMAIL', 'EMAIL2', 'SMS_FLAG',
        ),
        'SSMS_GOVINFO_D' => array('APP_SEQ', 'GOV_ID', 'CC00', 'CC48', 'CC49'),
        'SMS_GOVINFO_M' => array('APP_SEQ', 'APP_ID', 'GOV_NAME', 'EMAIL', 'APP_STOP', 'GOV_OID', 'EMAIL_SUB'),
        'SSMS_GOVINFO_M' => array('APP_SEQ', 'EMAIL_SUB'),
        'SCMSDS' => array('MD01', 'MD02', 'MD03', 'MD04', 'MD13_1', 'MD13_2'),
        'SSYSAUTH1' => array('USER_ID', 'USER_NAME'),
    );

    /**
     * 取得受支援的資料表欄位定義
     *
     * @return array<string, array<int, string>>
     */
    public static function getTableColumns(): array {
        return self::TABLE_COLUMNS;
    }

    /**
     * 強制驗證 SQL 必須為單一唯讀 SELECT 語句（禁止分號與任何寫入/DDL 關鍵字）
     *
     * @param string $sql
     * @return void
     * @throws \InvalidArgumentException
     */
    public static function assertReadOnlySelectSql(string $sql): void {
        $trimmed = trim($sql);
        if ($trimmed === '') {
            throw new \InvalidArgumentException('唯讀 SQL 檢核失敗：SQL 不可為空');
        }

        // 移除 SQL 單引號字串常值（含跳脫 ''）與註解後再驗證關鍵字
        $stripped = preg_replace("/'(?:''|[^'])*'/s", "''", $trimmed);
        $stripped = is_string($stripped) ? $stripped : $trimmed;
        $stripped = preg_replace('/\/\*.*?\*\//s', ' ', $stripped);
        $stripped = is_string($stripped) ? $stripped : $trimmed;
        $stripped = preg_replace('/--[^\r\n]*/', ' ', $stripped);
        $stripped = is_string($stripped) ? trim($stripped) : $trimmed;

        if (strpos($stripped, ';') !== false) {
            throw new \InvalidArgumentException('唯讀 SQL 檢核失敗：禁止包含分號或多重敘述');
        }

        if (!preg_match('/^\(?\s*SELECT\b/i', $stripped)) {
            throw new \InvalidArgumentException('唯讀 SQL 檢核失敗：僅允許 SELECT 查詢');
        }

        if (preg_match('/\b(INSERT|UPDATE|DELETE|DROP|ALTER|TRUNCATE|CREATE|MERGE|GRANT|REVOKE|CALL|BEGIN|COMMIT|ROLLBACK|EXECUTE|LOCK)\b/i', $stripped, $matches)) {
            throw new \InvalidArgumentException('唯讀 SQL 檢核失敗：偵測到禁止關鍵字 ' . strtoupper((string)$matches[1]));
        }
    }

    /**
     * 若目標 SQLite 檔案已存在且非空，先執行 WAL checkpoint 並備份為 .bak
     *
     * @param string $dbPath
     * @return string|null
     */
    public static function backupDatabase(string $dbPath): ?string {
        if ($dbPath === ':memory:' || !is_file($dbPath) || filesize($dbPath) === 0) {
            return null;
        }

        $bakPath = $dbPath . '.bak';
        try {
            $tmpPdo = new \PDO('sqlite:' . $dbPath);
            $tmpPdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
            $tmpPdo->exec('PRAGMA busy_timeout = 5000;');
            $tmpPdo->exec('PRAGMA wal_checkpoint(TRUNCATE);');
            $tmpPdo = null;
        } catch (\Throwable $e) {
            // 若檔案暫無 WAL 或鎖定中仍直接嘗試複製主檔
        }

        if (!@copy($dbPath, $bakPath)) {
            throw new \RuntimeException('無法備份既有 SQLite 資料庫至：' . $bakPath);
        }

        return $bakPath;
    }

    /**
     * 正規化欄位值：保留 null，將標量轉字串並去除 Oracle CHAR 欄位尾端補白
     *
     * @param mixed $value
     * @return string|null
     */
    public static function normalizeValue($value): ?string {
        if ($value === null) {
            return null;
        }
        return rtrim((string)$value);
    }

    /**
     * 清空重建並寫入快照資料至指定 sms_mock.db
     *
     * @param string $dbPath
     * @param array<string, array<int, array<string, mixed>>> $dataset
     * @param bool $backupExisting
     * @return array{db_path: string, backup_path: ?string, counts: array<string, int>, total_rows: int}
     * @throws \InvalidArgumentException
     */
    public static function importSnapshot(string $dbPath, array $dataset, bool $backupExisting = true): array {
        foreach (array_keys($dataset) as $tableName) {
            if (!array_key_exists($tableName, self::TABLE_COLUMNS)) {
                throw new \InvalidArgumentException('不支援的快照資料表：' . (string)$tableName);
            }
        }

        $backupPath = $backupExisting ? self::backupDatabase($dbPath) : null;

        $store = new SmsV1MockStore($dbPath);
        $pdo = $store->getPdo();
        $counts = array();
        $totalRows = 0;
        $inTx = false;

        try {
            $pdo->exec('BEGIN IMMEDIATE');
            $inTx = true;

            foreach (array_keys(self::TABLE_COLUMNS) as $tableName) {
                $pdo->exec('DELETE FROM ' . $tableName);
                $counts[$tableName] = 0;
            }

            foreach (self::TABLE_COLUMNS as $tableName => $columns) {
                $rows = isset($dataset[$tableName]) && is_array($dataset[$tableName]) ? $dataset[$tableName] : array();
                if (empty($rows)) {
                    continue;
                }

                $colList = implode(', ', $columns);
                $placeholders = array();
                foreach ($columns as $col) {
                    $placeholders[] = ':' . $col;
                }
                $verb = ($tableName === 'SRKEYN') ? 'INSERT OR REPLACE INTO' : 'INSERT INTO';
                $sql = $verb . ' ' . $tableName . ' (' . $colList . ') VALUES (' . implode(', ', $placeholders) . ')';
                $stmt = $pdo->prepare($sql);

                $rowCount = 0;
                foreach ($rows as $row) {
                    if (!is_array($row)) {
                        continue;
                    }
                    $binds = array();
                    foreach ($columns as $col) {
                        $binds[':' . $col] = array_key_exists($col, $row)
                            ? self::normalizeValue($row[$col])
                            : null;
                    }
                    $stmt->execute($binds);
                    $rowCount++;
                }
                $counts[$tableName] = $rowCount;
                $totalRows += $rowCount;
            }

            $pdo->exec('COMMIT');
            $inTx = false;

            if ($dbPath !== ':memory:') {
                @$pdo->exec('PRAGMA wal_checkpoint(TRUNCATE);');
            }
        } catch (\Throwable $e) {
            if ($inTx) {
                @$pdo->exec('ROLLBACK');
            }
            throw $e;
        } finally {
            $pdo = null;
            $store = null;
        }

        return array(
            'db_path' => $dbPath,
            'backup_path' => $backupPath,
            'counts' => $counts,
            'total_rows' => $totalRows,
        );
    }
}
