<?php
/**
 * HXWEB 主資料庫唯讀（SELECT-only）快照擷取工具 -> sms_mock.db
 *
 * 使用範例：
 *   d:\DEV\PHP7\php.exe d:\DEV\Land-Affairs-Helper\www\task\capture_sms_mock_snapshot.php --allow-live-readonly
 *
 * 選用參數：
 *   --days=7              近 N 日案件視窗（預設 7）
 *   --case-limit=100      SCRSMS 最近案件抓取上限（預設 100）
 *   --queue-limit=200     SSMSMAIN / SSMSWAIT / SSMSLOG 各表上限（預設 200）
 *   --applicant-limit=50  SMS_FLAG='Y' 申請人與關聯案件最低補齊筆數（預設 50）
 *   --db-path=<path>      輸出 SQLite 路徑（預設 www/assets/db/sms_mock.db）
 *   --no-backup           重建時不產生 .bak 備份檔
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    echo "Forbidden: CLI only.\n";
    exit(1);
}

$options = getopt('h', array(
    'help',
    'allow-live-readonly',
    'days:',
    'case-limit:',
    'queue-limit:',
    'applicant-limit:',
    'db-path:',
    'no-backup',
));

$printUsage = function (): void {
    echo "=== HXWEB 唯讀快照擷取工具（sms_mock.db）===\n";
    echo "安全護欄：本工具僅執行 SELECT 唯讀查詢，且不會修改 www/.env (MOCK=true)。\n";
    echo "因涉及連線主資料庫 HXWEB 並寫入真實資料至本地 SQLite，必須明確加上 --allow-live-readonly。\n\n";
    echo "執行方式：\n";
    echo "  d:\\DEV\\PHP7\\php.exe " . __FILE__ . " --allow-live-readonly\n\n";
    echo "參數說明：\n";
    echo "  --allow-live-readonly  允許以唯讀模式連線 HXWEB 執行 SELECT 快照\n";
    echo "  --days=7               最近幾日（民國日期）案件範圍（預設 7）\n";
    echo "  --case-limit=100       SCRSMS 最近案件抓取上限（預設 100）\n";
    echo "  --queue-limit=200      SSMSMAIN / SSMSWAIT / SSMSLOG 各表上限（預設 200）\n";
    echo "  --applicant-limit=50   SMS_FLAG='Y' 申請人保底筆數（預設 50）\n";
    echo "  --db-path=<path>       目標 SQLite 路徑（預設 www/assets/db/sms_mock.db）\n";
    echo "  --no-backup            覆寫前不備份既有 sms_mock.db.bak\n";
};

if (isset($options['h']) || isset($options['help'])) {
    $printUsage();
    exit(0);
}

if (!isset($options['allow-live-readonly'])) {
    fwrite(STDERR, "[BLOCKED] 缺少必要安全旗標 --allow-live-readonly，已中止操作（未連線 HXWEB，亦未異動 sms_mock.db）。\n\n");
    $printUsage();
    exit(1);
}

$days = isset($options['days']) ? max(1, (int)$options['days']) : 7;
$caseLimit = isset($options['case-limit']) ? max(1, (int)$options['case-limit']) : 100;
$queueLimit = isset($options['queue-limit']) ? max(1, (int)$options['queue-limit']) : 200;
$applicantLimit = isset($options['applicant-limit']) ? max(0, (int)$options['applicant-limit']) : 50;
$noBackup = isset($options['no-backup']);

$tmpDir = sys_get_temp_dir();
if (!is_dir($tmpDir)) {
    @mkdir($tmpDir, 0777, true);
}
session_save_path($tmpDir);

require_once(dirname(__DIR__) . DIRECTORY_SEPARATOR . 'include' . DIRECTORY_SEPARATOR . 'init.php');
set_error_handler(function ($errno, $errstr, $errfile, $errline) {
    if ((error_reporting() & $errno) === 0) {
        return false;
    }
    throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
});
set_exception_handler(function (Throwable $e) {
    fwrite(STDERR, "[ERROR] " . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n");
    exit(1);
});
require_once(INC_DIR . DIRECTORY_SEPARATOR . 'OraDB.class.php');
require_once(INC_DIR . DIRECTORY_SEPARATOR . 'SmsV1Encoding.class.php');
require_once(INC_DIR . DIRECTORY_SEPARATOR . 'SmsV1SnapshotImporter.class.php');

$defaultDbDir = defined('DB_DIR') ? DB_DIR : (ROOT_DIR . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'db');
$dbPath = isset($options['db-path']) && trim((string)$options['db-path']) !== ''
    ? trim((string)$options['db-path'])
    : ($defaultDbDir . DIRECTORY_SEPARATOR . 'sms_mock.db');

/**
 * 執行經嚴格檢核的唯讀 SELECT 查詢並將 CP950 欄位轉換為 UTF-8
 *
 * @param OraDB $db
 * @param string $sql
 * @param array<string, mixed> $binds
 * @return array<int, array<string, ?string>>
 */
function fetchReadOnlyUtf8(OraDB $db, string $sql, array $binds = array()): array {
    SmsV1SnapshotImporter::assertReadOnlySelectSql($sql);
    $db->parse($sql);
    $bindValues = array();
    foreach ($binds as $key => $val) {
        $bindValues[$key] = $val;
        $db->bind($key, $bindValues[$key]);
    }
    $db->execute();
    $rawRows = $db->fetchAll(true);
    if (!is_array($rawRows)) {
        return array();
    }

    $utf8Rows = array();
    foreach ($rawRows as $row) {
        $converted = array();
        foreach ($row as $col => $val) {
            if ($val === null) {
                $converted[$col] = null;
            } else {
                $converted[$col] = SmsV1Encoding::fromOracleEncoding((string)$val);
            }
        }
        $utf8Rows[] = $converted;
    }
    return $utf8Rows;
}

/**
 * 依案件主鍵三元組 (RM01, RM02, RM03) 分批執行唯讀查詢
 *
 * @param OraDB $db
 * @param string $table
 * @param array<int, string> $selectColumns
 * @param array<int, string> $keyCols
 * @param array<string, array{0: string, 1: string, 2: string}> $caseKeys
 * @return array<int, array<string, ?string>>
 */
function fetchByCaseTuples(OraDB $db, string $table, array $selectColumns, array $keyCols, array $caseKeys): array {
    if (empty($caseKeys)) {
        return array();
    }
    $colSql = implode(', ', $selectColumns);
    $tupleList = array_values($caseKeys);
    $allRows = array();

    foreach (array_chunk($tupleList, 100) as $chunk) {
        $placeholders = array();
        $binds = array();
        foreach ($chunk as $idx => $tuple) {
            $p0 = ':k0_' . $idx;
            $p1 = ':k1_' . $idx;
            $p2 = ':k2_' . $idx;
            $placeholders[] = "({$p0}, {$p1}, {$p2})";
            $binds[$p0] = $tuple[0];
            $binds[$p1] = $tuple[1];
            $binds[$p2] = $tuple[2];
        }
        $sql = "SELECT {$colSql} FROM {$table} WHERE ({$keyCols[0]}, {$keyCols[1]}, {$keyCols[2]}) IN (" . implode(', ', $placeholders) . ")";
        foreach (fetchReadOnlyUtf8($db, $sql, $binds) as $r) {
            $allRows[] = $r;
        }
    }

    return $allRows;
}

/**
 * 依單一欄位值陣列分批查詢
 *
 * @param OraDB $db
 * @param string $table
 * @param array<int, string> $selectColumns
 * @param string $keyCol
 * @param array<int, string> $values
 * @return array<int, array<string, ?string>>
 */
function fetchBySingleKeyList(OraDB $db, string $table, array $selectColumns, string $keyCol, array $values): array {
    if (empty($values)) {
        return array();
    }
    $colSql = implode(', ', $selectColumns);
    $uniqueValues = array_values(array_unique(array_filter(array_map('trim', $values), function ($v) {
        return $v !== '';
    })));
    if (empty($uniqueValues)) {
        return array();
    }

    $allRows = array();
    foreach (array_chunk($uniqueValues, 300) as $chunk) {
        $placeholders = array();
        $binds = array();
        foreach ($chunk as $idx => $val) {
            $p = ':id_' . $idx;
            $placeholders[] = $p;
            $binds[$p] = $val;
        }
        $sql = "SELECT {$colSql} FROM {$table} WHERE {$keyCol} IN (" . implode(', ', $placeholders) . ")";
        foreach (fetchReadOnlyUtf8($db, $sql, $binds) as $r) {
            $allRows[] = $r;
        }
    }

    return $allRows;
}

/**
 * @param array<string, array{0: string, 1: string, 2: string}> $caseKeys
 * @param string|null $k1
 * @param string|null $k2
 * @param string|null $k3
 */
function addCaseKey(array &$caseKeys, ?string $k1, ?string $k2, ?string $k3): void {
    $v1 = trim((string)$k1);
    $v2 = trim((string)$k2);
    $v3 = trim((string)$k3);
    if ($v1 === '' || $v2 === '' || $v3 === '') {
        return;
    }
    $caseKeys[$v1 . '|' . $v2 . '|' . $v3] = array($v1, $v2, $v3);
}

$startTime = microtime(true);
echo "[1/5] 連線 HXWEB 主資料庫（唯讀 SELECT 模式，www/.env MOCK=" . (getenv('MOCK') ?: 'true') . " 維持不變）...\n";

$oraDb = new OraDB(CONNECTION_TYPE::MAIN);
$schema = SmsV1SnapshotImporter::getTableColumns();
$dataset = array();

echo "[2/5] 擷取基礎代碼與機關／使用者主檔...\n";
$dataset['SRKEYN'] = fetchReadOnlyUtf8(
    $oraDb,
    "SELECT " . implode(', ', $schema['SRKEYN']) . " FROM SRKEYN WHERE KCDE_1 IN ('04','06','48','55','66','SMS')"
);
$dataset['SSMS_CODE04'] = fetchReadOnlyUtf8($oraDb, "SELECT " . implode(', ', $schema['SSMS_CODE04']) . " FROM SSMS_CODE04");
$dataset['SSMS_CODE06'] = fetchReadOnlyUtf8($oraDb, "SELECT " . implode(', ', $schema['SSMS_CODE06']) . " FROM SSMS_CODE06");
$dataset['SSMS_GOVINFO_D'] = fetchReadOnlyUtf8($oraDb, "SELECT " . implode(', ', $schema['SSMS_GOVINFO_D']) . " FROM SSMS_GOVINFO_D");
$dataset['SMS_GOVINFO_M'] = fetchReadOnlyUtf8($oraDb, "SELECT " . implode(', ', $schema['SMS_GOVINFO_M']) . " FROM SMS_GOVINFO_M");
$dataset['SSMS_GOVINFO_M'] = fetchReadOnlyUtf8($oraDb, "SELECT " . implode(', ', $schema['SSMS_GOVINFO_M']) . " FROM SSMS_GOVINFO_M");
$dataset['SSYSAUTH1'] = fetchReadOnlyUtf8($oraDb, "SELECT " . implode(', ', $schema['SSYSAUTH1']) . " FROM SSYSAUTH1");

echo "[3/5] 擷取佇列與紀錄表（各上限 {$queueLimit} 筆）...\n";
$dataset['SSMSMAIN'] = fetchReadOnlyUtf8(
    $oraDb,
    "SELECT " . implode(', ', $schema['SSMSMAIN']) . " FROM SSMSMAIN WHERE ROWNUM <= :lim",
    array(':lim' => $queueLimit)
);
$dataset['SSMSWAIT'] = fetchReadOnlyUtf8(
    $oraDb,
    "SELECT " . implode(', ', $schema['SSMSWAIT']) . " FROM SSMSWAIT WHERE ROWNUM <= :lim",
    array(':lim' => $queueLimit)
);
$dataset['SSMSLOG'] = fetchReadOnlyUtf8(
    $oraDb,
    "SELECT * FROM (SELECT " . implode(', ', $schema['SSMSLOG']) . " FROM SSMSLOG ORDER BY MS07_1 DESC, MS07_2 DESC) WHERE ROWNUM <= :lim",
    array(':lim' => $queueLimit)
);

echo "[4/5] 擷取最近 {$days} 日案件（SCRSMS 上限 {$caseLimit} 筆）及其關聯表...\n";
$cutoffDt = (new DateTimeImmutable('now'))->modify('-' . ($days - 1) . ' days');
$cutoffRoc = SmsV1Encoding::getRocDate($cutoffDt);
$scrsmsCols = implode(', ', $schema['SCRSMS']);

$scrsmsRows = fetchReadOnlyUtf8(
    $oraDb,
    "SELECT * FROM (
        SELECT {$scrsmsCols}
        FROM SCRSMS
        WHERE (RM07_1 >= :cutoff OR RM56_1 >= :cutoff)
        ORDER BY GREATEST(NVL(RM56_1, '0000000'), NVL(RM07_1, '0000000')) DESC, RM07_2 DESC, RM03 DESC
    ) WHERE ROWNUM <= :lim",
    array(':cutoff' => $cutoffRoc, ':lim' => $caseLimit)
);

if (empty($scrsmsRows)) {
    $maxRows = fetchReadOnlyUtf8($oraDb, "SELECT MAX(RM07_1) AS MAX_07, MAX(RM56_1) AS MAX_56 FROM SCRSMS WHERE RM07_1 IS NOT NULL OR RM56_1 IS NOT NULL");
    $anchorDate = '';
    if (!empty($maxRows)) {
        $m07 = trim((string)($maxRows[0]['MAX_07'] ?? ''));
        $m56 = trim((string)($maxRows[0]['MAX_56'] ?? ''));
        $anchorDate = strcmp($m56, $m07) >= 0 ? $m56 : $m07;
    }
    if ($anchorDate !== '' && strlen($anchorDate) === 7) {
        $adYear = (int)substr($anchorDate, 0, 3) + 1911;
        $adMonth = (int)substr($anchorDate, 3, 2);
        $adDay = (int)substr($anchorDate, 5, 2);
        $anchorDt = DateTimeImmutable::createFromFormat('!Y-m-d', sprintf('%04d-%02d-%02d', $adYear, $adMonth, $adDay));
        if ($anchorDt !== false) {
            $cutoffRoc = SmsV1Encoding::getRocDate($anchorDt->modify('-' . ($days - 1) . ' days'));
        } else {
            $cutoffRoc = $anchorDate;
        }
        $scrsmsRows = fetchReadOnlyUtf8(
            $oraDb,
            "SELECT * FROM (
                SELECT {$scrsmsCols}
                FROM SCRSMS
                WHERE (RM07_1 >= :cutoff OR RM56_1 >= :cutoff)
                ORDER BY GREATEST(NVL(RM56_1, '0000000'), NVL(RM07_1, '0000000')) DESC, RM07_2 DESC, RM03 DESC
            ) WHERE ROWNUM <= :lim",
            array(':cutoff' => $cutoffRoc, ':lim' => $caseLimit)
        );
    }
}

$caseKeys = array();
foreach ($scrsmsRows as $row) {
    addCaseKey($caseKeys, $row['RM01'] ?? '', $row['RM02'] ?? '', $row['RM03'] ?? '');
}

// 補齊佇列/紀錄表所引用但尚未在近 100 筆 SCRSMS 內的案件主檔
$missingQueueCaseKeys = array();
foreach (array('SSMSMAIN', 'SSMSWAIT', 'SSMSLOG') as $qTable) {
    foreach ($dataset[$qTable] as $row) {
        $k1 = trim((string)($row['MS03'] ?? ''));
        $k2 = trim((string)($row['MS04_1'] ?? ''));
        $k3 = trim((string)($row['MS04_2'] ?? ''));
        if ($k1 !== '' && $k2 !== '' && $k3 !== '') {
            $composite = $k1 . '|' . $k2 . '|' . $k3;
            if (!isset($caseKeys[$composite])) {
                $missingQueueCaseKeys[$composite] = array($k1, $k2, $k3);
            }
        }
    }
}
if (!empty($missingQueueCaseKeys)) {
    $extraScrsms = fetchByCaseTuples($oraDb, 'SCRSMS', $schema['SCRSMS'], array('RM01', 'RM02', 'RM03'), $missingQueueCaseKeys);
    foreach ($extraScrsms as $row) {
        $scrsmsRows[] = $row;
        addCaseKey($caseKeys, $row['RM01'] ?? '', $row['RM02'] ?? '', $row['RM03'] ?? '');
    }
}

$dataset['SCRSMS'] = $scrsmsRows;
$dataset['SCMSMS'] = fetchByCaseTuples($oraDb, 'SCMSMS', $schema['SCMSMS'], array('MM01', 'MM02', 'MM03'), $caseKeys);
$dataset['SCMSDS'] = fetchByCaseTuples($oraDb, 'SCMSDS', $schema['SCMSDS'], array('MD01', 'MD02', 'MD03'), $caseKeys);
$dataset['SCRSMS_RM1821'] = fetchByCaseTuples($oraDb, 'SCRSMS_RM1821', $schema['SCRSMS_RM1821'], array('RM01', 'RM02', 'RM03'), $caseKeys);
$dataset['SCRSMS_RM1215'] = fetchByCaseTuples($oraDb, 'SCRSMS_RM1215', $schema['SCRSMS_RM1215'], array('RM01', 'RM02', 'RM03'), $caseKeys);
$dataset['SRSINDX'] = fetchByCaseTuples($oraDb, 'SRSINDX', $schema['SRSINDX'], array('IS03', 'IS04_1', 'IS04_2'), $caseKeys);
$dataset['TRINDX'] = fetchByCaseTuples($oraDb, 'TRINDX', $schema['TRINDX'], array('II03', 'II04_1', 'II04_2'), $caseKeys);

// 透過 TRINDX 的 II00='R' 關聯精準擷取 SRBLOW / SREBOW
$rCaseKeys = array();
foreach ($dataset['TRINDX'] as $tRow) {
    if (trim((string)($tRow['II00'] ?? '')) === 'R') {
        addCaseKey($rCaseKeys, $tRow['II03'] ?? '', $tRow['II04_1'] ?? '', $tRow['II04_2'] ?? '');
    }
}

$srblowRows = array();
$srebowRows = array();
if (!empty($rCaseKeys)) {
    foreach (array_chunk(array_values($rCaseKeys), 100) as $chunk) {
        $placeholders = array();
        $binds = array();
        foreach ($chunk as $idx => $tuple) {
            $p0 = ':r0_' . $idx;
            $p1 = ':r1_' . $idx;
            $p2 = ':r2_' . $idx;
            $placeholders[] = "({$p0}, {$p1}, {$p2})";
            $binds[$p0] = $tuple[0];
            $binds[$p1] = $tuple[1];
            $binds[$p2] = $tuple[2];
        }
        $inClause = implode(', ', $placeholders);

        $bSql = "SELECT DISTINCT S.BA48, S.BA49, S.BB01, S.BB09
                 FROM SRBLOW S
                 INNER JOIN TRINDX T ON SUBSTR(T.IIKEY, 1, 13) || T.II_DATA = 'C' || S.BA48 || S.BA49 || S.BB01
                 WHERE (T.II03, T.II04_1, T.II04_2) IN ({$inClause})
                   AND T.II00 = 'R'";
        foreach (fetchReadOnlyUtf8($oraDb, $bSql, $binds) as $r) {
            $srblowRows[] = $r;
        }

        $eSql = "SELECT DISTINCT S.ED48, S.ED49, S.EE01, S.EE09
                 FROM SREBOW S
                 INNER JOIN TRINDX T ON SUBSTR(T.IIKEY, 1, 13) || T.II_DATA = 'F' || S.ED48 || S.ED49 || S.EE01
                 WHERE (T.II03, T.II04_1, T.II04_2) IN ({$inClause})
                   AND T.II00 = 'R'";
        foreach (fetchReadOnlyUtf8($oraDb, $eSql, $binds) as $r) {
            $srebowRows[] = $r;
        }
    }
}
$dataset['SRBLOW'] = $srblowRows;
$dataset['SREBOW'] = $srebowRows;

// 收集關聯申請人 ID 並查詢 SSMS_USERINFO_M
$applicantIds = array();
foreach ($dataset['SCRSMS_RM1821'] as $row) {
    $id = trim((string)($row['RM_ID'] ?? ''));
    if ($id !== '') {
        $applicantIds[$id] = true;
    }
}
foreach ($dataset['SRSINDX'] as $row) {
    $id = trim((string)($row['IS09'] ?? ''));
    if ($id !== '') {
        $applicantIds[$id] = true;
    }
}
foreach ($dataset['SRBLOW'] as $row) {
    $id = trim((string)($row['BB09'] ?? ''));
    if ($id !== '') {
        $applicantIds[$id] = true;
    }
}
foreach ($dataset['SREBOW'] as $row) {
    $id = trim((string)($row['EE09'] ?? ''));
    if ($id !== '') {
        $applicantIds[$id] = true;
    }
}

$userRows = fetchBySingleKeyList(
    $oraDb,
    'SSMS_USERINFO_M',
    $schema['SSMS_USERINFO_M'],
    'LIDN',
    array_keys($applicantIds)
);

// 統計已抓到的 SMS_FLAG='Y' 筆數；若不足 $applicantLimit 則額外補齊
$seenLidn = array();
$smsYCount = 0;
foreach ($userRows as $uRow) {
    $lidn = trim((string)($uRow['LIDN'] ?? ''));
    if ($lidn !== '') {
        $seenLidn[$lidn] = true;
    }
    if (trim((string)($uRow['SMS_FLAG'] ?? '')) === 'Y') {
        $smsYCount++;
    }
}
if ($smsYCount < $applicantLimit) {
    $need = $applicantLimit - $smsYCount;
    $extraUsers = fetchReadOnlyUtf8(
        $oraDb,
        "SELECT " . implode(', ', $schema['SSMS_USERINFO_M']) . " FROM SSMS_USERINFO_M WHERE SMS_FLAG = 'Y' AND ROWNUM <= :lim",
        array(':lim' => $need + count($seenLidn))
    );
    foreach ($extraUsers as $eu) {
        $lidn = trim((string)($eu['LIDN'] ?? ''));
        if ($lidn === '' || isset($seenLidn[$lidn])) {
            continue;
        }
        $seenLidn[$lidn] = true;
        $userRows[] = $eu;
        $smsYCount++;
        if ($smsYCount >= $applicantLimit) {
            break;
        }
    }
}
$dataset['SSMS_USERINFO_M'] = $userRows;

echo "[5/5] 寫入本地 SQLite 快照（{$dbPath}）...\n";
$result = SmsV1SnapshotImporter::importSnapshot($dbPath, $dataset, !$noBackup);
$elapsed = round(microtime(true) - $startTime, 2);

echo "\n=== 快照擷取與匯入完成（耗時 {$elapsed} 秒）===\n";
echo "目標資料庫：{$result['db_path']}\n";
echo "備份檔案：" . ($result['backup_path'] !== null ? $result['backup_path'] : '（無既有檔案或略過備份）') . "\n";
echo "總匯入筆數：{$result['total_rows']}\n";
echo "各表寫入明細：\n";
foreach ($result['counts'] as $tableName => $cnt) {
    echo sprintf("  - %-16s %d 筆\n", $tableName, $cnt);
}
echo "\n備註：www/.env 維持 MOCK=true 未變更。\n";
