<?php
require_once(__DIR__ . DIRECTORY_SEPARATOR . 'SQLiteDBFactory.class.php');
require_once(__DIR__ . DIRECTORY_SEPARATOR . 'SmsV1CaseSyncHelper.class.php');
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
     * @param string|null $dbPath 自訂 SQLite 路徑（預設使用 SQLiteDBFactory::getDbFolder() . '/sms_mock.db'）
     */
    public function __construct(?string $dbPath = null) {
        if ($dbPath !== null && $dbPath !== '') {
            $this->dbPath = $dbPath;
        } else {
            $this->dbPath = SQLiteDBFactory::getDbFolder() . DIRECTORY_SEPARATOR . 'sms_mock.db';
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
     * 掃描指定民國日期案件並寫入 SSMSMAIN（SQLite BEGIN IMMEDIATE 交易與三表去重）
     *
     * @param string $targetDate
     * @return array{target_date: string, user_cases_scanned: int, user_notifications_inserted: int, gov_cases_scanned: int, gov_notifications_inserted: int}
     */
    public function syncCases(string $targetDate): array {
        $pdo = $this->getPdo();
        $targetYear = substr($targetDate, 0, 3);
        $inTx = false;

        try {
            $pdo->exec('BEGIN IMMEDIATE');
            $inTx = true;

            $srkeynCache = array();
            $lookupSrkeyn = function (string $k1, string $k2) use ($pdo, &$srkeynCache): ?string {
                $cacheKey = $k1 . "\0" . $k2;
                if (array_key_exists($cacheKey, $srkeynCache)) {
                    return $srkeynCache[$cacheKey];
                }
                $stmt = $pdo->prepare('SELECT KCNT FROM SRKEYN WHERE KCDE_1 = :k1 AND KCDE_2 = :k2 LIMIT 1');
                $stmt->execute(array(':k1' => $k1, ':k2' => $k2));
                $val = $stmt->fetchColumn();
                $srkeynCache[$cacheKey] = $val === false ? null : trim((string)$val);
                return $srkeynCache[$cacheKey];
            };

            $maLo = (string)($lookupSrkeyn('55', '01') ?? '');
            $county = (string)($lookupSrkeyn('66', '01') ?? '');
            $k5502Raw = $lookupSrkeyn('55', '02');
            $k5502 = $k5502Raw !== null ? ($k5502Raw . '地政') : '';
            $remark = (string)($lookupSrkeyn('SMS', '01') ?? '');

            $userCasesScanned = 0;
            $userNotificationsInserted = 0;
            $govCasesScanned = 0;
            $govNotificationsInserted = 0;

            // 一般民眾 6 組查詢（SQL_A ~ SQL_F）
            foreach ($this->getUserCaseQueries() as $querySpec) {
                $stmt = $pdo->prepare($querySpec['sql']);
                $stmt->execute($querySpec['has_year']
                    ? array(':target_year' => $targetYear, ':target_date' => $targetDate)
                    : array(':target_date' => $targetDate)
                );
                $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
                foreach ($rows as $row) {
                    $userCasesScanned++;
                    $ms30 = trim((string)($row['MS30'] ?? ''));
                    $rm01 = trim((string)($row['RM01'] ?? ''));
                    $rm02 = trim((string)($row['RM02'] ?? ''));
                    $rm03 = trim((string)($row['RM03'] ?? ''));
                    $rm09 = trim((string)($row['RM09'] ?? ''));
                    $t07_1 = trim((string)($row['T07_1'] ?? ''));
                    $t07_2 = trim((string)($row['T07_2'] ?? ''));
                    $replyMobile = trim((string)($row['REPLY_MOBILE'] ?? ''));
                    $replyEmail = trim((string)($row['REPLY_EMAIL'] ?? ''));

                    $chtWord = (string)($lookupSrkeyn('04', $rm02) ?? '');
                    $chtRm09 = (string)($lookupSrkeyn('06', $rm09) ?? '');
                    $msNote = SmsV1CaseSyncHelper::buildUserNote($ms30, $rm01, $chtWord, $rm03, $chtRm09, $t07_1, $remark);

                    foreach (SmsV1CaseSyncHelper::expandUserContactPairs($row) as $pair) {
                        if ($replyMobile === '1' && $pair['mobile'] !== '') {
                            if (!$this->existsUserSmsInThreeTables($pdo, $rm01, $rm02, $rm03, $ms30, $pair['mobile'])) {
                                $this->insertUserSmsToMain($pdo, $rm01, $rm02, $rm03, $t07_1, $t07_2, $pair['mobile'], $ms30, $msNote);
                                $userNotificationsInserted++;
                            }
                        }
                        if ($replyEmail === '1' && $pair['email'] !== '') {
                            if (!$this->existsUserMailInThreeTables($pdo, $rm01, $rm02, $rm03, $ms30, $pair['email'])) {
                                $this->insertUserMailToMain($pdo, $rm01, $rm02, $rm03, $t07_1, $t07_2, $pair['email'], $ms30, $k5502 . $msNote);
                                $userNotificationsInserted++;
                            }
                        }
                    }
                }
            }

            // 機關通報 2 組查詢（SQL_A 'y'、SQL_C 'z'）
            foreach ($this->getGovCaseQueries() as $querySpec) {
                $stmt = $pdo->prepare($querySpec['sql']);
                $stmt->execute($querySpec['has_year']
                    ? array(':target_year' => $targetYear, ':target_date' => $targetDate)
                    : array(':target_date' => $targetDate)
                );
                $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
                foreach ($rows as $row) {
                    $govCasesScanned++;
                    $ms30 = trim((string)($row['MS30'] ?? ''));
                    $rm01 = trim((string)($row['RM01'] ?? ''));
                    $rm02 = trim((string)($row['RM02'] ?? ''));
                    $rm03 = trim((string)($row['RM03'] ?? ''));
                    $rm09 = trim((string)($row['RM09'] ?? ''));
                    $t07_1 = trim((string)($row['T07_1'] ?? ''));
                    $t07_2 = trim((string)($row['T07_2'] ?? ''));
                    $appSeq = trim((string)($row['APP_SEQ'] ?? ''));
                    $appId = trim((string)($row['APP_ID'] ?? ''));
                    $govId = trim((string)($row['GOV_ID'] ?? ''));
                    $cc00 = trim((string)($row['CC00'] ?? ''));
                    $cc48 = trim((string)($row['CC48'] ?? ''));
                    $cc49 = trim((string)($row['CC49'] ?? ''));
                    $govOid = trim((string)($row['GOV_OID'] ?? ''));
                    $emailRaw = (string)($row['EMAIL'] ?? '');

                    $chtWord = (string)($lookupSrkeyn('04', $rm02) ?? '');
                    $chtRm09 = (string)($lookupSrkeyn('06', $rm09) ?? '');
                    $chtCc48 = (string)($lookupSrkeyn('48', $cc48) ?? '');

                    $msNote = SmsV1CaseSyncHelper::buildGovNote(
                        $ms30,
                        $county,
                        $chtCc48,
                        $cc00,
                        $cc49,
                        $rm01,
                        $chtWord,
                        $rm03,
                        $chtRm09,
                        $t07_1,
                        $maLo
                    );

                    if (!$this->existsGovInThreeTables($pdo, $rm01, $rm02, $rm03, $ms30, $appSeq, $appId, $govId, $govOid, $cc00, $cc48, $cc49, $msNote)) {
                        foreach (SmsV1CaseSyncHelper::splitGovEmails($emailRaw) as $mail) {
                            $this->insertGovMailToMain($pdo, $rm01, $rm02, $rm03, $t07_1, $t07_2, $mail, $ms30, $msNote, $appSeq, $appId, $govId, $cc00, $cc48, $cc49, $govOid);
                            $govNotificationsInserted++;
                        }
                    }
                }
            }

            $pdo->exec('COMMIT');
            $inTx = false;

            return array(
                'target_date' => $targetDate,
                'user_cases_scanned' => $userCasesScanned,
                'user_notifications_inserted' => $userNotificationsInserted,
                'gov_cases_scanned' => $govCasesScanned,
                'gov_notifications_inserted' => $govNotificationsInserted,
            );
        } catch (\Throwable $e) {
            if ($inTx) {
                try {
                    $pdo->exec('ROLLBACK');
                } catch (\Throwable $ignored) {
                }
            }
            throw $e;
        }
    }

    /**
     * @return array<int, array{name: string, has_year: bool, sql: string}>
     */
    private function getUserCaseQueries(): array {
        $usrSubquery = "SELECT LIDN, LNAM, REPLY_MOBILE, MOBILE, MOBILE2, REPLY_EMAIL, EMAIL, EMAIL2
                        FROM SSMS_USERINFO_M
                        WHERE (REPLY_MOBILE = '1' OR REPLY_EMAIL = '1') AND SMS_FLAG = 'Y'";
        $k5501Subquery = "SELECT * FROM SRKEYN WHERE KCDE_1 = '55' AND KCDE_2 = '01'";

        return array(
            array(
                'name' => 'SQL_A',
                'has_year' => true,
                'sql' => "SELECT 'Y' AS MS30, A.RM01, A.RM02, A.RM03, A.RM09, B.RM_ID AS MSID,
                                 A.RM07_1 AS T07_1, A.RM07_2 AS T07_2,
                                 CASE WHEN A.RM99 IS NULL OR A.RM99 = '' THEN SUBSTR(K5501.KRMK, 1, 2) ELSE A.RM101 END AS TLAND,
                                 USR.LNAM, USR.REPLY_MOBILE, USR.MOBILE, USR.MOBILE2, USR.REPLY_EMAIL, USR.EMAIL, USR.EMAIL2
                          FROM SCRSMS A
                          INNER JOIN SSMS_CODE04 C04 ON A.RM02 = C04.COD04
                          INNER JOIN SSMS_CODE06 C06 ON A.RM09 = C06.COD06 AND C06.ITEMS = '0'
                          INNER JOIN ({$k5501Subquery}) K5501 ON ((A.RM99 IS NULL OR A.RM99 = '') OR (A.RM99 = 'Y' AND A.RM100 = SUBSTR(K5501.KRMK, 1, 2)))
                          INNER JOIN SCRSMS_RM1821 B ON B.RM01 = A.RM01 AND B.RM02 = A.RM02 AND B.RM03 = A.RM03 AND B.RM_TYPE = '18'
                          INNER JOIN ({$usrSubquery}) USR ON B.RM_ID = USR.LIDN
                          WHERE A.RM01 = :target_year
                            AND SUBSTR(A.RM03, 6, 1) = '0'
                            AND A.RM32 IS NOT NULL AND A.RM32 <> ''
                            AND A.RM07_1 = :target_date",
            ),
            array(
                'name' => 'SQL_B',
                'has_year' => true,
                'sql' => "SELECT 'Y' AS MS30, A.RM01, A.RM02, A.RM03, A.RM09, B.RM_ID AS MSID,
                                 A.RM07_1 AS T07_1, A.RM07_2 AS T07_2,
                                 CASE WHEN A.RM99 IS NULL OR A.RM99 = '' THEN SUBSTR(K5501.KRMK, 1, 2) ELSE A.RM101 END AS TLAND,
                                 USR.LNAM, USR.REPLY_MOBILE, USR.MOBILE, USR.MOBILE2, USR.REPLY_EMAIL, USR.EMAIL, USR.EMAIL2
                          FROM SCRSMS A
                          INNER JOIN SSMS_CODE04 C04 ON A.RM02 = C04.COD04
                          INNER JOIN SSMS_CODE06 C06 ON A.RM09 = C06.COD06 AND C06.ITEMS = '1'
                          INNER JOIN ({$k5501Subquery}) K5501 ON ((A.RM99 IS NULL OR A.RM99 = '') OR (A.RM99 = 'Y' AND A.RM100 = SUBSTR(K5501.KRMK, 1, 2)))
                          INNER JOIN SCRSMS_RM1821 B ON B.RM01 = A.RM01 AND B.RM02 = A.RM02 AND B.RM03 = A.RM03 AND B.RM_TYPE = '21'
                          INNER JOIN ({$usrSubquery}) USR ON B.RM_ID = USR.LIDN
                          WHERE A.RM01 = :target_year
                            AND SUBSTR(A.RM03, 6, 1) = '0'
                            AND A.RM32 IS NOT NULL AND A.RM32 <> ''
                            AND A.RM07_1 = :target_date",
            ),
            array(
                'name' => 'SQL_C',
                'has_year' => false,
                'sql' => "SELECT 'Z' AS MS30, A.RM01, A.RM02, A.RM03, A.RM09, I.IS09 AS MSID,
                                 A.RM56_1 AS T07_1, A.RM56_2 AS T07_2,
                                 CASE WHEN A.RM99 IS NULL OR A.RM99 = '' THEN SUBSTR(K5501.KRMK, 1, 2) ELSE A.RM101 END AS TLAND,
                                 USR.LNAM, USR.REPLY_MOBILE, USR.MOBILE, USR.MOBILE2, USR.REPLY_EMAIL, USR.EMAIL, USR.EMAIL2
                          FROM SRSINDX I
                          INNER JOIN SCRSMS A ON I.IS03 = A.RM01 AND I.IS04_1 = A.RM02 AND I.IS04_2 = A.RM03
                          INNER JOIN SSMS_CODE04 C04 ON A.RM02 = C04.COD04
                          INNER JOIN SSMS_CODE06 C06 ON A.RM09 = C06.COD06 AND C06.ITEMS = '0'
                          INNER JOIN ({$k5501Subquery}) K5501 ON ((A.RM99 IS NULL OR A.RM99 = '') OR (A.RM99 = 'Y' AND A.RM100 = SUBSTR(K5501.KRMK, 1, 2)))
                          INNER JOIN ({$usrSubquery}) USR ON I.IS09 = USR.LIDN
                          LEFT JOIN TRINDX T ON T.II03 = I.IS03 AND T.II04_1 = I.IS04_1 AND T.II04_2 = I.IS04_2
                                             AND T.II00 = I.IS00 AND T.II_DATA = 'N'
                                             AND T.IIKEY = (CASE I.IS00 WHEN 'B' THEN 'C' WHEN 'E' THEN 'F' ELSE I.IS00 END) || I.IS48 || I.IS49 || I.IS01
                          WHERE SUBSTR(A.RM03, 6, 1) = '0'
                            AND A.RM56_1 = :target_date
                            AND I.IS00 IN ('B', 'E')",
            ),
            array(
                'name' => 'SQL_D',
                'has_year' => false,
                'sql' => "SELECT 'Z' AS MS30, A.RM01, A.RM02, A.RM03, A.RM09, I.IS09 AS MSID,
                                 A.RM56_1 AS T07_1, A.RM56_2 AS T07_2,
                                 CASE WHEN A.RM99 IS NULL OR A.RM99 = '' THEN SUBSTR(K5501.KRMK, 1, 2) ELSE A.RM101 END AS TLAND,
                                 USR.LNAM, USR.REPLY_MOBILE, USR.MOBILE, USR.MOBILE2, USR.REPLY_EMAIL, USR.EMAIL, USR.EMAIL2
                          FROM TRINDX T
                          INNER JOIN SRSINDX I ON T.II03 = I.IS03 AND T.II04_1 = I.IS04_1 AND T.II04_2 = I.IS04_2
                                              AND T.II00 = I.IS00 AND T.II_DATA = 'M'
                                              AND T.IIKEY = (CASE I.IS00 WHEN 'B' THEN 'C' WHEN 'E' THEN 'F' ELSE I.IS00 END) || I.IS48 || I.IS49 || I.IS01
                          INNER JOIN SCRSMS A ON I.IS03 = A.RM01 AND I.IS04_1 = A.RM02 AND I.IS04_2 = A.RM03
                          INNER JOIN SSMS_CODE04 C04 ON A.RM02 = C04.COD04
                          INNER JOIN SSMS_CODE06 C06 ON A.RM09 = C06.COD06 AND C06.ITEMS = '1'
                          INNER JOIN ({$k5501Subquery}) K5501 ON ((A.RM99 IS NULL OR A.RM99 = '') OR (A.RM99 = 'Y' AND A.RM100 = SUBSTR(K5501.KRMK, 1, 2)))
                          INNER JOIN ({$usrSubquery}) USR ON I.IS09 = USR.LIDN
                          WHERE SUBSTR(A.RM03, 6, 1) = '0'
                            AND A.RM56_1 = :target_date
                            AND I.IS00 IN ('B', 'E')",
            ),
            array(
                'name' => 'SQL_E',
                'has_year' => false,
                'sql' => "SELECT 'Z' AS MS30, A.RM01, A.RM02, A.RM03, A.RM09, S.BB09 AS MSID,
                                 A.RM56_1 AS T07_1, A.RM56_2 AS T07_2,
                                 CASE WHEN A.RM99 IS NULL OR A.RM99 = '' THEN SUBSTR(K5501.KRMK, 1, 2) ELSE A.RM101 END AS TLAND,
                                 USR.LNAM, USR.REPLY_MOBILE, USR.MOBILE, USR.MOBILE2, USR.REPLY_EMAIL, USR.EMAIL, USR.EMAIL2
                          FROM TRINDX T
                          INNER JOIN SCRSMS A ON T.II03 = A.RM01 AND T.II04_1 = A.RM02 AND T.II04_2 = A.RM03
                          INNER JOIN SRBLOW S ON SUBSTR(T.IIKEY, 1, 13) || T.II_DATA = 'C' || S.BA48 || S.BA49 || S.BB01
                          INNER JOIN SSMS_CODE04 C04 ON A.RM02 = C04.COD04
                          INNER JOIN SSMS_CODE06 C06 ON A.RM09 = C06.COD06 AND C06.ITEMS = '1'
                          INNER JOIN ({$k5501Subquery}) K5501 ON ((A.RM99 IS NULL OR A.RM99 = '') OR (A.RM99 = 'Y' AND A.RM100 = SUBSTR(K5501.KRMK, 1, 2)))
                          INNER JOIN ({$usrSubquery}) USR ON S.BB09 = USR.LIDN
                          WHERE A.RM01 = SUBSTR(A.RM07_1, 1, 3)
                            AND SUBSTR(A.RM03, 6, 1) = '0'
                            AND A.RM56_1 = :target_date
                            AND T.II00 = 'R'",
            ),
            array(
                'name' => 'SQL_F',
                'has_year' => false,
                'sql' => "SELECT 'Z' AS MS30, A.RM01, A.RM02, A.RM03, A.RM09, S.EE09 AS MSID,
                                 A.RM56_1 AS T07_1, A.RM56_2 AS T07_2,
                                 CASE WHEN A.RM99 IS NULL OR A.RM99 = '' THEN SUBSTR(K5501.KRMK, 1, 2) ELSE A.RM101 END AS TLAND,
                                 USR.LNAM, USR.REPLY_MOBILE, USR.MOBILE, USR.MOBILE2, USR.REPLY_EMAIL, USR.EMAIL, USR.EMAIL2
                          FROM TRINDX T
                          INNER JOIN SCRSMS A ON T.II03 = A.RM01 AND T.II04_1 = A.RM02 AND T.II04_2 = A.RM03
                          INNER JOIN SREBOW S ON SUBSTR(T.IIKEY, 1, 13) || T.II_DATA = 'F' || S.ED48 || S.ED49 || S.EE01
                          INNER JOIN SSMS_CODE04 C04 ON A.RM02 = C04.COD04
                          INNER JOIN SSMS_CODE06 C06 ON A.RM09 = C06.COD06 AND C06.ITEMS = '1'
                          INNER JOIN ({$k5501Subquery}) K5501 ON ((A.RM99 IS NULL OR A.RM99 = '') OR (A.RM99 = 'Y' AND A.RM100 = SUBSTR(K5501.KRMK, 1, 2)))
                          INNER JOIN ({$usrSubquery}) USR ON S.EE09 = USR.LIDN
                          WHERE A.RM01 = SUBSTR(A.RM07_1, 1, 3)
                            AND SUBSTR(A.RM03, 6, 1) = '0'
                            AND A.RM56_1 = :target_date
                            AND T.II00 = 'R'",
            ),
        );
    }

    /**
     * @return array<int, array{name: string, has_year: bool, sql: string}>
     */
    private function getGovCaseQueries(): array {
        $govSubquery = "SELECT a.GOV_ID, a.CC00, a.CC48, a.CC49, b.GOV_NAME, b.EMAIL, b.APP_ID, b.APP_SEQ, b.GOV_OID
                        FROM SSMS_GOVINFO_D a
                        INNER JOIN SMS_GOVINFO_M b ON a.APP_SEQ = b.APP_SEQ
                        WHERE COALESCE(NULLIF(b.APP_STOP, ''), 'N') <> 'Y'";

        return array(
            array(
                'name' => 'G_SQL_A',
                'has_year' => true,
                'sql' => "SELECT 'y' AS MS30, A.RM01, A.RM02, A.RM03, A.RM09, A.RM07_1 AS T07_1, A.RM07_2 AS T07_2,
                                 CASE WHEN A.RM99 IS NULL OR A.RM99 = '' THEN SUBSTR(K.KRMK, 1, 2) ELSE A.RM101 END AS TLAND,
                                 GOVINFO.GOV_ID, GOVINFO.CC00, GOVINFO.CC48, GOVINFO.CC49,
                                 GOVINFO.GOV_NAME, GOVINFO.EMAIL, GOVINFO.APP_ID, GOVINFO.APP_SEQ, GOVINFO.GOV_OID
                          FROM SCRSMS A
                          INNER JOIN SSMS_CODE06 C06 ON A.RM09 = C06.COD06 AND C06.ITEMS = '2'
                          INNER JOIN SRKEYN K ON K.KCDE_1 = '55' AND K.KCDE_2 = '01'
                                              AND ((A.RM99 IS NULL OR A.RM99 = '') OR (A.RM99 = 'Y' AND A.RM100 = SUBSTR(K.KRMK, 1, 2)))
                          INNER JOIN SCRSMS_RM1215 B ON B.RM01 = A.RM01 AND B.RM02 = A.RM02 AND B.RM03 = A.RM03
                          INNER JOIN ({$govSubquery}) GOVINFO ON B.RM00 = GOVINFO.CC00 AND B.RM11 = GOVINFO.CC48 AND B.RM1215 = GOVINFO.CC49
                          WHERE A.RM01 = :target_year
                            AND SUBSTR(A.RM03, 6, 1) = '0'
                            AND A.RM32 IS NOT NULL AND A.RM32 <> ''
                            AND A.RM07_1 = :target_date",
            ),
            array(
                'name' => 'G_SQL_C',
                'has_year' => false,
                'sql' => "SELECT 'z' AS MS30, A.RM01, A.RM02, A.RM03, A.RM09, A.RM56_1 AS T07_1, A.RM56_2 AS T07_2,
                                 CASE WHEN A.RM99 IS NULL OR A.RM99 = '' THEN SUBSTR(K.KRMK, 1, 2) ELSE A.RM101 END AS TLAND,
                                 GOVINFO.GOV_ID, GOVINFO.CC00, GOVINFO.CC48, GOVINFO.CC49,
                                 GOVINFO.GOV_NAME, GOVINFO.EMAIL, GOVINFO.APP_ID, GOVINFO.APP_SEQ, GOVINFO.GOV_OID
                          FROM SRSINDX I
                          INNER JOIN SCRSMS A ON I.IS03 = A.RM01 AND I.IS04_1 = A.RM02 AND I.IS04_2 = A.RM03
                          INNER JOIN SSMS_CODE06 C06 ON A.RM09 = C06.COD06 AND C06.ITEMS = '2'
                          INNER JOIN SRKEYN K ON K.KCDE_1 = '55' AND K.KCDE_2 = '01'
                                              AND ((A.RM99 IS NULL OR A.RM99 = '') OR (A.RM99 = 'Y' AND A.RM100 = SUBSTR(K.KRMK, 1, 2)))
                          INNER JOIN ({$govSubquery}) GOVINFO ON I.IS48 = GOVINFO.CC48 AND I.IS49 = GOVINFO.CC49
                                  AND ((GOVINFO.CC00 = 'C' AND I.IS00 IN ('A', 'B', 'C')) OR (GOVINFO.CC00 = 'F' AND I.IS00 IN ('D', 'E', 'F')))
                          WHERE SUBSTR(A.RM03, 6, 1) = '0'
                            AND A.RM56_1 = :target_date",
            ),
        );
    }

    private function existsUserSmsInThreeTables(\PDO $pdo, string $ms03, string $ms04_1, string $ms04_2, string $ms30, string $ms14): bool {
        $sql = "SELECT 1 FROM SSMSMAIN WHERE MS03 = :ms03 AND MS04_1 = :ms04_1 AND MS04_2 = :ms04_2 AND MS30 = :ms30 AND MS14 = :ms14
                UNION ALL
                SELECT 1 FROM SSMSWAIT WHERE MS03 = :ms03 AND MS04_1 = :ms04_1 AND MS04_2 = :ms04_2 AND MS30 = :ms30 AND MS14 = :ms14
                UNION ALL
                SELECT 1 FROM SSMSLOG  WHERE MS03 = :ms03 AND MS04_1 = :ms04_1 AND MS04_2 = :ms04_2 AND MS30 = :ms30 AND MS14 = :ms14
                LIMIT 1";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(array(
            ':ms03' => $ms03,
            ':ms04_1' => $ms04_1,
            ':ms04_2' => $ms04_2,
            ':ms30' => $ms30,
            ':ms14' => $ms14,
        ));
        return $stmt->fetchColumn() !== false;
    }

    private function existsUserMailInThreeTables(\PDO $pdo, string $ms03, string $ms04_1, string $ms04_2, string $ms30, string $msMail): bool {
        $sql = "SELECT 1 FROM SSMSMAIN WHERE MS03 = :ms03 AND MS04_1 = :ms04_1 AND MS04_2 = :ms04_2 AND MS30 = :ms30 AND MS_MAIL = :ms_mail
                UNION ALL
                SELECT 1 FROM SSMSWAIT WHERE MS03 = :ms03 AND MS04_1 = :ms04_1 AND MS04_2 = :ms04_2 AND MS30 = :ms30 AND MS_MAIL = :ms_mail
                UNION ALL
                SELECT 1 FROM SSMSLOG  WHERE MS03 = :ms03 AND MS04_1 = :ms04_1 AND MS04_2 = :ms04_2 AND MS30 = :ms30 AND MS_MAIL = :ms_mail
                LIMIT 1";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(array(
            ':ms03' => $ms03,
            ':ms04_1' => $ms04_1,
            ':ms04_2' => $ms04_2,
            ':ms30' => $ms30,
            ':ms_mail' => $msMail,
        ));
        return $stmt->fetchColumn() !== false;
    }

    private function existsGovInThreeTables(
        \PDO $pdo,
        string $ms03,
        string $ms04_1,
        string $ms04_2,
        string $ms30,
        string $appSeq,
        string $appId,
        string $govId,
        string $govOid,
        string $cc00,
        string $cc48,
        string $cc49,
        string $msNote
    ): bool {
        $govCond = $govOid === '' ? 'GOV_ID = :gov_key' : 'GOV_OID = :gov_key';
        $where = "MS03 = :ms03 AND MS04_1 = :ms04_1 AND MS04_2 = :ms04_2 AND MS30 = :ms30
                  AND APP_SEQ = :app_seq AND APP_ID = :app_id AND {$govCond}
                  AND CC00 = :cc00 AND CC48 = :cc48 AND CC49 = :cc49 AND MS_NOTE = :ms_note";
        $sql = "SELECT 1 FROM SSMSMAIN WHERE {$where}
                UNION ALL
                SELECT 1 FROM SSMSWAIT WHERE {$where}
                UNION ALL
                SELECT 1 FROM SSMSLOG  WHERE {$where}
                LIMIT 1";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(array(
            ':ms03' => $ms03,
            ':ms04_1' => $ms04_1,
            ':ms04_2' => $ms04_2,
            ':ms30' => $ms30,
            ':app_seq' => $appSeq,
            ':app_id' => $appId,
            ':gov_key' => $govOid === '' ? $govId : $govOid,
            ':cc00' => $cc00,
            ':cc48' => $cc48,
            ':cc49' => $cc49,
            ':ms_note' => $msNote,
        ));
        return $stmt->fetchColumn() !== false;
    }

    private function insertUserSmsToMain(
        \PDO $pdo,
        string $ms03,
        string $ms04_1,
        string $ms04_2,
        string $ms07_1,
        string $ms07_2,
        string $ms14,
        string $ms30,
        string $msNote
    ): void {
        $stmt = $pdo->prepare(
            "INSERT INTO SSMSMAIN (MS03, MS04_1, MS04_2, MS07_1, MS07_2, MS14, MS30, MS_TYPE, MS_NOTE)
             VALUES (:ms03, :ms04_1, :ms04_2, :ms07_1, :ms07_2, :ms14, :ms30, 'M', :ms_note)"
        );
        $stmt->execute(array(
            ':ms03' => $ms03,
            ':ms04_1' => $ms04_1,
            ':ms04_2' => $ms04_2,
            ':ms07_1' => $ms07_1,
            ':ms07_2' => $ms07_2,
            ':ms14' => $ms14,
            ':ms30' => $ms30,
            ':ms_note' => $msNote,
        ));
    }

    private function insertUserMailToMain(
        \PDO $pdo,
        string $ms03,
        string $ms04_1,
        string $ms04_2,
        string $ms07_1,
        string $ms07_2,
        string $msMail,
        string $ms30,
        string $msNote
    ): void {
        $stmt = $pdo->prepare(
            "INSERT INTO SSMSMAIN (MS03, MS04_1, MS04_2, MS07_1, MS07_2, MS_MAIL, MS30, MS_TYPE, MS_NOTE)
             VALUES (:ms03, :ms04_1, :ms04_2, :ms07_1, :ms07_2, :ms_mail, :ms30, 'M', :ms_note)"
        );
        $stmt->execute(array(
            ':ms03' => $ms03,
            ':ms04_1' => $ms04_1,
            ':ms04_2' => $ms04_2,
            ':ms07_1' => $ms07_1,
            ':ms07_2' => $ms07_2,
            ':ms_mail' => $msMail,
            ':ms30' => $ms30,
            ':ms_note' => $msNote,
        ));
    }

    private function insertGovMailToMain(
        \PDO $pdo,
        string $ms03,
        string $ms04_1,
        string $ms04_2,
        string $ms07_1,
        string $ms07_2,
        string $msMail,
        string $ms30,
        string $msNote,
        string $appSeq,
        string $appId,
        string $govId,
        string $cc00,
        string $cc48,
        string $cc49,
        string $govOid
    ): void {
        $stmt = $pdo->prepare(
            "INSERT INTO SSMSMAIN (MS03, MS04_1, MS04_2, MS07_1, MS07_2, MS_MAIL, MS30, MS_TYPE, MS_NOTE,
                                   APP_SEQ, APP_ID, GOV_ID, CC00, CC48, CC49, GOV_OID)
             VALUES (:ms03, :ms04_1, :ms04_2, :ms07_1, :ms07_2, :ms_mail, :ms30, 'M', :ms_note,
                     :app_seq, :app_id, :gov_id, :cc00, :cc48, :cc49, :gov_oid)"
        );
        $stmt->execute(array(
            ':ms03' => $ms03,
            ':ms04_1' => $ms04_1,
            ':ms04_2' => $ms04_2,
            ':ms07_1' => $ms07_1,
            ':ms07_2' => $ms07_2,
            ':ms_mail' => $msMail,
            ':ms30' => $ms30,
            ':ms_note' => $msNote,
            ':app_seq' => $appSeq,
            ':app_id' => $appId,
            ':gov_id' => $govId,
            ':cc00' => $cc00,
            ':cc48' => $cc48,
            ':cc49' => $cc49,
            ':gov_oid' => $govOid,
        ));
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
