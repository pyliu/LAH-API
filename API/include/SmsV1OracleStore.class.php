<?php
require_once(__DIR__ . DIRECTORY_SEPARATOR . 'SmsV1CaseSyncHelper.class.php');
require_once(__DIR__ . DIRECTORY_SEPARATOR . 'SmsV1Encoding.class.php');
require_once(__DIR__ . DIRECTORY_SEPARATOR . 'SmsV1QueueHelper.class.php');
require_once(__DIR__ . DIRECTORY_SEPARATOR . 'SmsV1StoreInterface.class.php');

/**
 * SmsV1OracleStore - Oracle 9i 資料存取實作（透過 OraDBWrapper 支援原生 OCI8 與 PHP 8.5 橋接模式）
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
     * 掃描指定民國日期案件並寫入 SSMSMAIN（Oracle 9i Bind Variables + CP950 轉碼 + PL/SQL 原子交易）
     *
     * @param string $targetDate
     * @return array{target_date: string, user_cases_scanned: int, user_notifications_inserted: int, gov_cases_scanned: int, gov_notifications_inserted: int}
     */
    public function syncCases(string $targetDate): array {
        $targetYear = substr($targetDate, 0, 3);
        $srkeynCache = array();

        $lookupStaticSrkeyn = function (string $k1, string $k2): ?string {
            $rows = $this->queryRaw(
                "SELECT KCNT FROM SRKEYN WHERE KCDE_1 = '{$k1}' AND KCDE_2 = '{$k2}'"
            );
            if (empty($rows)) {
                return null;
            }
            return trim(SmsV1Encoding::fromOracleEncoding((string)($rows[0]['KCNT'] ?? '')));
        };

        $lookupParamSrkeyn = function (string $k1, string $k2) use (&$srkeynCache): string {
            $cacheKey = $k1 . "\0" . $k2;
            if (array_key_exists($cacheKey, $srkeynCache)) {
                return $srkeynCache[$cacheKey];
            }
            $rows = $this->queryRaw(
                "SELECT KCNT FROM SRKEYN WHERE KCDE_1 = :kcde_1 AND KCDE_2 = :kcde_2",
                array(':kcde_1' => $k1, ':kcde_2' => $k2)
            );
            $val = empty($rows) ? '' : trim(SmsV1Encoding::fromOracleEncoding((string)($rows[0]['KCNT'] ?? '')));
            $srkeynCache[$cacheKey] = $val;
            return $val;
        };

        $maLo = (string)($lookupStaticSrkeyn('55', '01') ?? '');
        $county = (string)($lookupStaticSrkeyn('66', '01') ?? '');
        $k5502Raw = $lookupStaticSrkeyn('55', '02');
        $k5502 = $k5502Raw !== null ? ($k5502Raw . '地政') : '';
        $remark = (string)($lookupStaticSrkeyn('SMS', '01') ?? '');

        $userCasesScanned = 0;
        $userNotificationsInserted = 0;
        $govCasesScanned = 0;
        $govNotificationsInserted = 0;

        $insertStatements = array();
        $insertBinds = array();
        $bindIdx = 0;

        $seenUserSms = array();
        $seenUserMail = array();
        $seenGov = array();

        // 一般民眾 6 組查詢（SQL_A ~ SQL_F）
        foreach ($this->getUserCaseQueries() as $querySpec) {
            $binds = $querySpec['has_year']
                ? array(':target_year' => $targetYear, ':target_date' => $targetDate)
                : array(':target_date' => $targetDate);
            $rows = $this->queryRaw($querySpec['sql'], $binds);

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

                $chtWord = $lookupParamSrkeyn('04', $rm02);
                $chtRm09 = $lookupParamSrkeyn('06', $rm09);
                $msNote = SmsV1CaseSyncHelper::buildUserNote($ms30, $rm01, $chtWord, $rm03, $chtRm09, $t07_1, $remark);

                foreach (SmsV1CaseSyncHelper::expandUserContactPairs($row) as $pair) {
                    if ($replyMobile === '1' && $pair['mobile'] !== '') {
                        $smsKey = $rm01 . '|' . $rm02 . '|' . $rm03 . '|' . $ms30 . '|' . $pair['mobile'];
                        if (!isset($seenUserSms[$smsKey]) && !$this->existsUserSmsInThreeTables($rm01, $rm02, $rm03, $ms30, $pair['mobile'])) {
                            $seenUserSms[$smsKey] = true;
                            $p = ':bv_u' . $bindIdx++;
                            $insertStatements[] = "INSERT INTO SSMSMAIN (MS03, MS04_1, MS04_2, MS07_1, MS07_2, MS14, MS30, MS_TYPE, MS_NOTE)
                                                   VALUES ({$p}_ms03, {$p}_ms04_1, {$p}_ms04_2, {$p}_ms07_1, {$p}_ms07_2, {$p}_ms14, {$p}_ms30, 'M', {$p}_note)";
                            $insertBinds["{$p}_ms03"] = $rm01;
                            $insertBinds["{$p}_ms04_1"] = $rm02;
                            $insertBinds["{$p}_ms04_2"] = $rm03;
                            $insertBinds["{$p}_ms07_1"] = $t07_1;
                            $insertBinds["{$p}_ms07_2"] = $t07_2;
                            $insertBinds["{$p}_ms14"] = $pair['mobile'];
                            $insertBinds["{$p}_ms30"] = $ms30;
                            $insertBinds["{$p}_note"] = SmsV1Encoding::toOracleEncoding($msNote);
                            $userNotificationsInserted++;
                        }
                    }

                    if ($replyEmail === '1' && $pair['email'] !== '') {
                        $mailKey = $rm01 . '|' . $rm02 . '|' . $rm03 . '|' . $ms30 . '|' . $pair['email'];
                        if (!isset($seenUserMail[$mailKey]) && !$this->existsUserMailInThreeTables($rm01, $rm02, $rm03, $ms30, $pair['email'])) {
                            $seenUserMail[$mailKey] = true;
                            $p = ':bv_m' . $bindIdx++;
                            $insertStatements[] = "INSERT INTO SSMSMAIN (MS03, MS04_1, MS04_2, MS07_1, MS07_2, MS_MAIL, MS30, MS_TYPE, MS_NOTE)
                                                   VALUES ({$p}_ms03, {$p}_ms04_1, {$p}_ms04_2, {$p}_ms07_1, {$p}_ms07_2, {$p}_mail, {$p}_ms30, 'M', {$p}_note)";
                            $insertBinds["{$p}_ms03"] = $rm01;
                            $insertBinds["{$p}_ms04_1"] = $rm02;
                            $insertBinds["{$p}_ms04_2"] = $rm03;
                            $insertBinds["{$p}_ms07_1"] = $t07_1;
                            $insertBinds["{$p}_ms07_2"] = $t07_2;
                            $insertBinds["{$p}_mail"] = $pair['email'];
                            $insertBinds["{$p}_ms30"] = $ms30;
                            $insertBinds["{$p}_note"] = SmsV1Encoding::toOracleEncoding($k5502 . $msNote);
                            $userNotificationsInserted++;
                        }
                    }
                }
            }
        }

        // 機關通報 2 組查詢（SQL_A 'y'、SQL_C 'z'）
        foreach ($this->getGovCaseQueries() as $querySpec) {
            $binds = $querySpec['has_year']
                ? array(':target_year' => $targetYear, ':target_date' => $targetDate)
                : array(':target_date' => $targetDate);
            $rows = $this->queryRaw($querySpec['sql'], $binds);

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

                $chtWord = $lookupParamSrkeyn('04', $rm02);
                $chtRm09 = $lookupParamSrkeyn('06', $rm09);
                $chtCc48 = $lookupParamSrkeyn('48', $cc48);

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
                $msNoteOra = SmsV1Encoding::toOracleEncoding($msNote);

                $govKey = $rm01 . '|' . $rm02 . '|' . $rm03 . '|' . $ms30 . '|' . $appSeq . '|' . $appId
                    . '|' . ($govOid === '' ? ('ID:' . $govId) : ('OID:' . $govOid))
                    . '|' . $cc00 . '|' . $cc48 . '|' . $cc49 . '|' . $msNote;

                if (!isset($seenGov[$govKey]) && !$this->existsGovInThreeTables($rm01, $rm02, $rm03, $ms30, $appSeq, $appId, $govId, $govOid, $cc00, $cc48, $cc49, $msNoteOra)) {
                    $seenGov[$govKey] = true;
                    foreach (SmsV1CaseSyncHelper::splitGovEmails($emailRaw) as $mail) {
                        $p = ':bv_g' . $bindIdx++;
                        $insertStatements[] = "INSERT INTO SSMSMAIN (MS03, MS04_1, MS04_2, MS07_1, MS07_2, MS_MAIL, MS30, MS_TYPE, MS_NOTE,
                                                                     APP_SEQ, APP_ID, GOV_ID, CC00, CC48, CC49, GOV_OID)
                                               VALUES ({$p}_ms03, {$p}_ms04_1, {$p}_ms04_2, {$p}_ms07_1, {$p}_ms07_2, {$p}_mail, {$p}_ms30, 'M', {$p}_note,
                                                       {$p}_app_seq, {$p}_app_id, {$p}_gov_id, {$p}_cc00, {$p}_cc48, {$p}_cc49, {$p}_gov_oid)";
                        $insertBinds["{$p}_ms03"] = $rm01;
                        $insertBinds["{$p}_ms04_1"] = $rm02;
                        $insertBinds["{$p}_ms04_2"] = $rm03;
                        $insertBinds["{$p}_ms07_1"] = $t07_1;
                        $insertBinds["{$p}_ms07_2"] = $t07_2;
                        $insertBinds["{$p}_mail"] = $mail;
                        $insertBinds["{$p}_ms30"] = $ms30;
                        $insertBinds["{$p}_note"] = $msNoteOra;
                        $insertBinds["{$p}_app_seq"] = $appSeq;
                        $insertBinds["{$p}_app_id"] = $appId;
                        $insertBinds["{$p}_gov_id"] = $govId;
                        $insertBinds["{$p}_cc00"] = $cc00;
                        $insertBinds["{$p}_cc48"] = $cc48;
                        $insertBinds["{$p}_cc49"] = $cc49;
                        $insertBinds["{$p}_gov_oid"] = $govOid;
                        $govNotificationsInserted++;
                    }
                }
            }
        }

        if (!empty($insertStatements)) {
            $this->executePlSqlBlock($insertStatements, $insertBinds);
        }

        return array(
            'target_date' => $targetDate,
            'user_cases_scanned' => $userCasesScanned,
            'user_notifications_inserted' => $userNotificationsInserted,
            'gov_cases_scanned' => $govCasesScanned,
            'gov_notifications_inserted' => $govNotificationsInserted,
        );
    }

    /**
     * @return array<int, array{name: string, has_year: bool, sql: string}>
     */
    private function getUserCaseQueries(): array {
        $usrSubquery = "(SELECT LIDN, LNAM, REPLY_MOBILE, MOBILE, MOBILE2, REPLY_EMAIL, EMAIL, EMAIL2
                         FROM SSMS_USERINFO_M
                         WHERE (REPLY_MOBILE = '1' OR REPLY_EMAIL = '1') AND SMS_FLAG = 'Y'
                         ORDER BY LIDN) USR";
        $k5501Subquery = "(SELECT * FROM SRKEYN WHERE KCDE_1 = '55' AND KCDE_2 = '01')";

        return array(
            array(
                'name' => 'SQL_A',
                'has_year' => true,
                'sql' => "SELECT 'Y' MS30, A.RM01, A.RM02, A.RM03, RM09, B.RM_ID MSID, RM07_1 T07_1, RM07_2 T07_2,
                                 DECODE(RM99, NULL, SUBSTR(KRMK, 1, 2), RM101) TLAND,
                                 LNAM, REPLY_MOBILE, MOBILE, MOBILE2, REPLY_EMAIL, EMAIL, EMAIL2
                          FROM SCRSMS A, SSMS_CODE04, SSMS_CODE06, {$k5501Subquery}, SCRSMS_RM1821 B, {$usrSubquery}
                          WHERE A.RM01 = :target_year
                            AND A.RM02 = COD04
                            AND ((RM99 IS NULL) OR (RM99 = 'Y' AND RM100 = SUBSTR(KRMK, 1, 2)))
                            AND SUBSTR(A.RM03, 6, 1) = '0'
                            AND RM32 IS NOT NULL
                            AND RM07_1 = :target_date
                            AND B.RM01 = A.RM01 AND B.RM02 = A.RM02 AND B.RM03 = A.RM03
                            AND B.RM_TYPE = '18' AND B.RM_ID = USR.LIDN
                            AND RM09 = COD06 AND ITEMS = '0'",
            ),
            array(
                'name' => 'SQL_B',
                'has_year' => true,
                'sql' => "SELECT 'Y' MS30, A.RM01, A.RM02, A.RM03, RM09, B.RM_ID MSID, RM07_1 T07_1, RM07_2 T07_2,
                                 DECODE(RM99, NULL, SUBSTR(KRMK, 1, 2), RM101) TLAND,
                                 LNAM, REPLY_MOBILE, MOBILE, MOBILE2, REPLY_EMAIL, EMAIL, EMAIL2
                          FROM SCRSMS A, SSMS_CODE04, SSMS_CODE06, {$k5501Subquery}, SCRSMS_RM1821 B, {$usrSubquery}
                          WHERE A.RM01 = :target_year
                            AND A.RM02 = COD04
                            AND SUBSTR(A.RM03, 6, 1) = '0'
                            AND ((RM99 IS NULL) OR (RM99 = 'Y' AND RM100 = SUBSTR(KRMK, 1, 2)))
                            AND RM32 IS NOT NULL
                            AND RM07_1 = :target_date
                            AND B.RM01 = A.RM01 AND B.RM02 = A.RM02 AND B.RM03 = A.RM03
                            AND B.RM_TYPE = '21' AND B.RM_ID = USR.LIDN
                            AND RM09 = COD06 AND ITEMS = '1'",
            ),
            array(
                'name' => 'SQL_C',
                'has_year' => false,
                'sql' => "SELECT 'Z' MS30, RM01, RM02, RM03, RM09, IS09 MSID, RM56_1 T07_1, RM56_2 T07_2,
                                 DECODE(RM99, NULL, SUBSTR(KRMK, 1, 2), RM101) TLAND,
                                 LNAM, REPLY_MOBILE, MOBILE, MOBILE2, REPLY_EMAIL, EMAIL, EMAIL2
                          FROM TRINDX, SRSINDX, SCRSMS, SSMS_CODE04, SSMS_CODE06, {$k5501Subquery}, {$usrSubquery}
                          WHERE RM02 = COD04
                            AND SUBSTR(RM03, 6, 1) = '0'
                            AND RM09 = COD06 AND ITEMS = '0'
                            AND RM56_1 = :target_date
                            AND ((RM99 IS NULL) OR (RM99 = 'Y' AND RM100 = SUBSTR(KRMK, 1, 2)))
                            AND IS03 = RM01 AND IS04_1 = RM02 AND IS04_2 = RM03
                            AND IS00 IN ('B', 'E') AND IS09 = USR.LIDN
                            AND II03(+) = IS03 AND II04_1(+) = IS04_1 AND II04_2(+) = IS04_2
                            AND II00(+) = IS00 AND II_DATA(+) = 'N'
                            AND IIKEY(+) = DECODE(IS00, 'B', 'C', 'E', 'F', IS00) || IS48 || IS49 || IS01",
            ),
            array(
                'name' => 'SQL_D',
                'has_year' => false,
                'sql' => "SELECT 'Z' MS30, RM01, RM02, RM03, RM09, IS09 MSID, RM56_1 T07_1, RM56_2 T07_2,
                                 DECODE(RM99, NULL, SUBSTR(KRMK, 1, 2), RM101) TLAND,
                                 LNAM, REPLY_MOBILE, MOBILE, MOBILE2, REPLY_EMAIL, EMAIL, EMAIL2
                          FROM TRINDX, SRSINDX, SCRSMS, SSMS_CODE04, SSMS_CODE06, {$k5501Subquery}, {$usrSubquery}
                          WHERE RM02 = COD04
                            AND SUBSTR(RM03, 6, 1) = '0'
                            AND RM09 = COD06 AND ITEMS = '1'
                            AND RM56_1 = :target_date
                            AND ((RM99 IS NULL) OR (RM99 = 'Y' AND RM100 = SUBSTR(KRMK, 1, 2)))
                            AND IS03 = RM01 AND IS04_1 = RM02 AND IS04_2 = RM03
                            AND IS00 IN ('B', 'E') AND IS09 = USR.LIDN
                            AND II03 = IS03 AND II04_1 = IS04_1 AND II04_2 = IS04_2
                            AND II00 = IS00 AND II_DATA = 'M'
                            AND IIKEY = DECODE(IS00, 'B', 'C', 'E', 'F', IS00) || IS48 || IS49 || IS01",
            ),
            array(
                'name' => 'SQL_E',
                'has_year' => false,
                'sql' => "SELECT 'Z' MS30, RM01, RM02, RM03, RM09, S.BB09 MSID, RM56_1 T07_1, RM56_2 T07_2,
                                 DECODE(RM99, NULL, SUBSTR(KRMK, 1, 2), RM101) TLAND,
                                 LNAM, REPLY_MOBILE, MOBILE, MOBILE2, REPLY_EMAIL, EMAIL, EMAIL2
                          FROM TRINDX T, SRBLOW S, SCRSMS, SSMS_CODE04, SSMS_CODE06, {$k5501Subquery}, {$usrSubquery}
                          WHERE RM01 = SUBSTR(RM07_1, 1, 3)
                            AND RM02 = COD04
                            AND SUBSTR(RM03, 6, 1) = '0'
                            AND RM09 = COD06 AND ITEMS = '1'
                            AND RM56_1 = :target_date
                            AND ((RM99 IS NULL) OR (RM99 = 'Y' AND RM100 = SUBSTR(KRMK, 1, 2)))
                            AND S.BB09 = USR.LIDN
                            AND II03 = RM01 AND II04_1 = RM02 AND II04_2 = RM03
                            AND II00 = 'R' AND SUBSTR(IIKEY, 1, 13) || II_DATA = 'C' || S.BA48 || S.BA49 || S.BB01",
            ),
            array(
                'name' => 'SQL_F',
                'has_year' => false,
                'sql' => "SELECT 'Z' MS30, RM01, RM02, RM03, RM09, S.EE09 MSID, RM56_1 T07_1, RM56_2 T07_2,
                                 DECODE(RM99, NULL, SUBSTR(KRMK, 1, 2), RM101) TLAND,
                                 LNAM, REPLY_MOBILE, MOBILE, MOBILE2, REPLY_EMAIL, EMAIL, EMAIL2
                          FROM TRINDX T, SREBOW S, SCRSMS, SSMS_CODE04, SSMS_CODE06, {$k5501Subquery}, {$usrSubquery}
                          WHERE RM01 = SUBSTR(RM07_1, 1, 3)
                            AND RM02 = COD04
                            AND SUBSTR(RM03, 6, 1) = '0'
                            AND RM09 = COD06 AND ITEMS = '1'
                            AND RM56_1 = :target_date
                            AND ((RM99 IS NULL) OR (RM99 = 'Y' AND RM100 = SUBSTR(KRMK, 1, 2)))
                            AND S.EE09 = USR.LIDN
                            AND II03 = RM01 AND II04_1 = RM02 AND II04_2 = RM03
                            AND II00 = 'R' AND SUBSTR(IIKEY, 1, 13) || II_DATA = 'F' || S.ED48 || S.ED49 || S.EE01",
            ),
        );
    }

    /**
     * @return array<int, array{name: string, has_year: bool, sql: string}>
     */
    private function getGovCaseQueries(): array {
        $govSubquery = "(SELECT a.GOV_ID, a.CC00, a.CC48, a.CC49, b.GOV_NAME, b.EMAIL, b.APP_ID, b.APP_SEQ, b.GOV_OID
                         FROM SSMS_GOVINFO_D a, SMS_GOVINFO_M b
                         WHERE a.APP_SEQ = b.APP_SEQ AND NVL(b.APP_STOP, 'N') <> 'Y') GOVINFO";

        return array(
            array(
                'name' => 'G_SQL_A',
                'has_year' => true,
                'sql' => "SELECT 'y' MS30, A.RM01, A.RM02, A.RM03, RM09, RM07_1 T07_1, RM07_2 T07_2,
                                 DECODE(RM99, NULL, SUBSTR(KRMK, 1, 2), RM101) TLAND,
                                 GOVINFO.GOV_ID, GOVINFO.CC00, GOVINFO.CC48, GOVINFO.CC49,
                                 GOVINFO.GOV_NAME, GOVINFO.EMAIL, GOVINFO.APP_ID, GOVINFO.APP_SEQ, GOVINFO.GOV_OID
                          FROM SCRSMS A, SSMS_CODE06, SRKEYN, SCRSMS_RM1215 B, {$govSubquery}
                          WHERE A.RM01 = :target_year
                            AND KCDE_1 = '55' AND KCDE_2 = '01'
                            AND ((RM99 IS NULL) OR (RM99 = 'Y' AND RM100 = SUBSTR(KRMK, 1, 2)))
                            AND SUBSTR(A.RM03, 6, 1) = '0'
                            AND RM32 IS NOT NULL
                            AND RM07_1 = :target_date
                            AND B.RM01 = A.RM01 AND B.RM02 = A.RM02 AND B.RM03 = A.RM03
                            AND B.RM00 = GOVINFO.CC00 AND B.RM11 = GOVINFO.CC48 AND B.RM1215 = GOVINFO.CC49
                            AND RM09 = COD06 AND ITEMS = '2'",
            ),
            array(
                'name' => 'G_SQL_C',
                'has_year' => false,
                'sql' => "SELECT 'z' MS30, RM01, RM02, RM03, RM09, RM56_1 T07_1, RM56_2 T07_2,
                                 DECODE(RM99, NULL, SUBSTR(KRMK, 1, 2), RM101) TLAND,
                                 GOVINFO.GOV_ID, GOVINFO.CC00, GOVINFO.CC48, GOVINFO.CC49,
                                 GOVINFO.GOV_NAME, GOVINFO.EMAIL, GOVINFO.APP_ID, GOVINFO.APP_SEQ, GOVINFO.GOV_OID
                          FROM SRSINDX, SCRSMS, SSMS_CODE06, SRKEYN, {$govSubquery}
                          WHERE SUBSTR(RM03, 6, 1) = '0'
                            AND RM09 = COD06 AND ITEMS = '2'
                            AND RM56_1 = :target_date
                            AND KCDE_1 = '55' AND KCDE_2 = '01'
                            AND ((RM99 IS NULL) OR (RM99 = 'Y' AND RM100 = SUBSTR(KRMK, 1, 2)))
                            AND IS03 = RM01 AND IS04_1 = RM02 AND IS04_2 = RM03
                            AND ((GOVINFO.CC00 = 'C' AND IS00 IN ('A', 'B', 'C')) OR (GOVINFO.CC00 = 'F' AND IS00 IN ('D', 'E', 'F')))
                            AND IS48 = GOVINFO.CC48 AND IS49 = GOVINFO.CC49",
            ),
        );
    }

    private function existsUserSmsInThreeTables(string $ms03, string $ms04_1, string $ms04_2, string $ms30, string $ms14): bool {
        $sql = "SELECT 'X' S30 FROM SSMSMAIN WHERE MS03 = :ms03 AND MS04_1 = :ms04_1 AND MS04_2 = :ms04_2 AND MS30 = :ms30 AND MS14 = :ms14
                UNION ALL
                SELECT 'X' S30 FROM SSMSWAIT WHERE MS03 = :ms03 AND MS04_1 = :ms04_1 AND MS04_2 = :ms04_2 AND MS30 = :ms30 AND MS14 = :ms14
                UNION ALL
                SELECT 'X' S30 FROM SSMSLOG  WHERE MS03 = :ms03 AND MS04_1 = :ms04_1 AND MS04_2 = :ms04_2 AND MS30 = :ms30 AND MS14 = :ms14";
        $rows = $this->queryRaw($sql, array(
            ':ms03' => $ms03,
            ':ms04_1' => $ms04_1,
            ':ms04_2' => $ms04_2,
            ':ms30' => $ms30,
            ':ms14' => $ms14,
        ));
        return !empty($rows);
    }

    private function existsUserMailInThreeTables(string $ms03, string $ms04_1, string $ms04_2, string $ms30, string $msMail): bool {
        $sql = "SELECT 'X' S30 FROM SSMSMAIN WHERE MS03 = :ms03 AND MS04_1 = :ms04_1 AND MS04_2 = :ms04_2 AND MS30 = :ms30 AND MS_MAIL = :ms_mail
                UNION ALL
                SELECT 'X' S30 FROM SSMSWAIT WHERE MS03 = :ms03 AND MS04_1 = :ms04_1 AND MS04_2 = :ms04_2 AND MS30 = :ms30 AND MS_MAIL = :ms_mail
                UNION ALL
                SELECT 'X' S30 FROM SSMSLOG  WHERE MS03 = :ms03 AND MS04_1 = :ms04_1 AND MS04_2 = :ms04_2 AND MS30 = :ms30 AND MS_MAIL = :ms_mail";
        $rows = $this->queryRaw($sql, array(
            ':ms03' => $ms03,
            ':ms04_1' => $ms04_1,
            ':ms04_2' => $ms04_2,
            ':ms30' => $ms30,
            ':ms_mail' => $msMail,
        ));
        return !empty($rows);
    }

    private function existsGovInThreeTables(
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
        string $msNoteOra
    ): bool {
        $govCond = $govOid === '' ? 'GOV_ID = :gov_key' : 'GOV_OID = :gov_key';
        $where = "MS03 = :ms03 AND MS04_1 = :ms04_1 AND MS04_2 = :ms04_2 AND MS30 = :ms30
                  AND APP_SEQ = :app_seq AND APP_ID = :app_id AND {$govCond}
                  AND CC00 = :cc00 AND CC48 = :cc48 AND CC49 = :cc49 AND MS_NOTE = :ms_note";
        $sql = "SELECT 'X' S30 FROM SSMSMAIN WHERE {$where}
                UNION
                SELECT 'X' S30 FROM SSMSWAIT WHERE {$where}
                UNION
                SELECT 'X' S30 FROM SSMSLOG  WHERE {$where}";
        $rows = $this->queryRaw($sql, array(
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
            ':ms_note' => $msNoteOra,
        ));
        return !empty($rows);
    }

    /**
     * 依 TTSearchTimer 邏輯將 SSMSMAIN 待處理紀錄在單一交易內搬移至 SSMSWAIT
     *
     * @param bool $checkRegisterA
     * @param bool $checkRegisterB
     * @param bool $checkRegisterC
     * @param bool $checkRegisterD
     * @return array{moved_to_wait_count: int, deleted_from_main_count: int}
     */
    public function enqueueQueue(
        bool $checkRegisterA = true,
        bool $checkRegisterB = true,
        bool $checkRegisterC = true,
        bool $checkRegisterD = true
    ): array {
        $now = new \DateTimeImmutable('now');
        $nowDate = SmsV1Encoding::getRocDate($now);
        $nowTime = SmsV1Encoding::getTimeHhmmss($now);

        $rows = $this->queryRaw(
            "SELECT MS03, MS04_1, MS04_2, MS07_1, MS07_2, MS14, MS30, NVL(MS_TYPE, 'R') AS MS_TYPE,
                    MS_NOTE, MS_MAIL, APP_SEQ, APP_ID, GOV_ID, CC00, CC48, CC49, GOV_OID
             FROM SSMSMAIN
             WHERE NVL(MS_TYPE, 'R') IN ('M', 'R', 'S', '2')
             ORDER BY MS07_1, MS07_2"
        );

        $scrsmsSql = "SELECT DECODE(RM30, 'I', 'I',
                                    'F', DECODE(RM31, 'A', 'F', 'D', 'D', 'B', 'X', 'C', 'X', '0'),
                                    'N', DECODE(RM31, 'A', 'F', 'D', 'D', 'B', 'X', 'C', 'X', '0'),
                                    '0') AS RM30
                      FROM SCRSMS
                      WHERE RM01 = :ms03 AND RM02 = :ms04_1 AND RM03 = :ms04_2";

        $scmsmsSql = "SELECT DECODE(MM23, NULL, DECODE(MM22, 'B', 'I', 'C', 'C', '0'),
                                    'A', 'F', 'C', 'D', 'B', 'X', 'X') AS RM30
                      FROM SCMSMS
                      WHERE MM01 = :ms03 AND MM02 = :ms04_1 AND MM03 = :ms04_2";

        $statements = array();
        $binds = array();
        $bindIdx = 0;
        $movedToWaitCount = 0;
        $deletedFromMainCount = 0;

        foreach ($rows as $row) {
            $ms03 = trim((string)($row['MS03'] ?? ''));
            $ms04_1 = trim((string)($row['MS04_1'] ?? ''));
            $ms04_2 = trim((string)($row['MS04_2'] ?? ''));
            $ms14 = trim((string)($row['MS14'] ?? ''));
            $ms30 = trim((string)($row['MS30'] ?? ''));
            $msType = trim((string)($row['MS_TYPE'] ?? 'R'));
            if ($msType === '') {
                $msType = 'R';
            }
            $msNoteRaw = (string)($row['MS_NOTE'] ?? '');
            $msMail = trim((string)($row['MS_MAIL'] ?? ''));
            $appSeq = trim((string)($row['APP_SEQ'] ?? ''));
            $appId = trim((string)($row['APP_ID'] ?? ''));
            $govId = trim((string)($row['GOV_ID'] ?? ''));
            $cc00 = trim((string)($row['CC00'] ?? ''));
            $cc48 = trim((string)($row['CC48'] ?? ''));
            $cc49 = trim((string)($row['CC49'] ?? ''));
            $govOid = trim((string)($row['GOV_OID'] ?? ''));

            $sType = '';
            if ($msType === 'R') {
                $subRows = $this->queryRaw($scrsmsSql, array(
                    ':ms03' => $ms03,
                    ':ms04_1' => $ms04_1,
                    ':ms04_2' => $ms04_2,
                ));
                foreach ($subRows as $subRow) {
                    $sType = trim((string)($subRow['RM30'] ?? ''));
                }
            } elseif ($msType === 'S') {
                $subRows = $this->queryRaw($scmsmsSql, array(
                    ':ms03' => $ms03,
                    ':ms04_1' => $ms04_1,
                    ':ms04_2' => $ms04_2,
                ));
                foreach ($subRows as $subRow) {
                    $sType = trim((string)($subRow['RM30'] ?? ''));
                }
            }

            $action = SmsV1QueueHelper::resolveAction(
                $msType,
                $ms30,
                $sType,
                $checkRegisterA,
                $checkRegisterB,
                $checkRegisterC,
                $checkRegisterD
            );

            if (!$action['insert_wait'] && $action['main_action'] === 'none') {
                continue;
            }

            $p = ':bq' . $bindIdx++;
            $binds["{$p}_m03"] = $ms03;
            $binds["{$p}_m041"] = $ms04_1;
            $binds["{$p}_m042"] = $ms04_2;
            $binds["{$p}_m14"] = $ms14;
            $binds["{$p}_m30"] = $ms30;
            $binds["{$p}_typ"] = $msType;

            if ($action['insert_wait']) {
                $binds["{$p}_071"] = $nowDate;
                $binds["{$p}_072"] = $nowTime;
                $binds["{$p}_w30"] = $action['wait_ms30'];

                if ($action['wait_mode'] === 'm_gov') {
                    $binds["{$p}_not"] = $msNoteRaw;
                    $binds["{$p}_mal"] = $msMail;
                    $binds["{$p}_seq"] = $appSeq;
                    $binds["{$p}_aid"] = $appId;
                    $binds["{$p}_gid"] = $govId;
                    $binds["{$p}_c00"] = $cc00;
                    $binds["{$p}_c48"] = $cc48;
                    $binds["{$p}_c49"] = $cc49;
                    $binds["{$p}_oid"] = $govOid;
                    $statements[] = "INSERT INTO SSMSWAIT (MS03, MS04_1, MS04_2, MS07_1, MS07_2, MS14, MS30, MS32, MS_TYPE, MS_NOTE, MS_MAIL,
                                                           APP_SEQ, APP_ID, GOV_ID, CC00, CC48, CC49, GOV_OID)
                                     VALUES ({$p}_m03, {$p}_m041, {$p}_m042, {$p}_071, {$p}_072, {$p}_m14, {$p}_w30, '0', {$p}_typ, {$p}_not, {$p}_mal,
                                             {$p}_seq, {$p}_aid, {$p}_gid, {$p}_c00, {$p}_c48, {$p}_c49, {$p}_oid)";
                } elseif ($action['wait_mode'] === 'm_general') {
                    $binds["{$p}_not"] = $msNoteRaw;
                    $binds["{$p}_mal"] = $msMail;
                    $statements[] = "INSERT INTO SSMSWAIT (MS03, MS04_1, MS04_2, MS07_1, MS07_2, MS14, MS30, MS32, MS_TYPE, MS_NOTE, MS_MAIL)
                                     VALUES ({$p}_m03, {$p}_m041, {$p}_m042, {$p}_071, {$p}_072, {$p}_m14, {$p}_w30, '0', {$p}_typ, {$p}_not, {$p}_mal)";
                } elseif ($action['wait_mode'] === 'type2') {
                    $binds["{$p}_not"] = $msNoteRaw;
                    $statements[] = "INSERT INTO SSMSWAIT (MS03, MS04_1, MS04_2, MS07_1, MS07_2, MS14, MS30, MS32, MS_TYPE, MS_NOTE)
                                     VALUES ({$p}_m03, {$p}_m041, {$p}_m042, {$p}_071, {$p}_072, {$p}_m14, {$p}_w30, '0', {$p}_typ, {$p}_not)";
                } else {
                    $statements[] = "INSERT INTO SSMSWAIT (MS03, MS04_1, MS04_2, MS07_1, MS07_2, MS14, MS30, MS32, MS_TYPE)
                                     VALUES ({$p}_m03, {$p}_m041, {$p}_m042, {$p}_071, {$p}_072, {$p}_m14, {$p}_w30, '0', {$p}_typ)";
                }
                $movedToWaitCount++;
            }

            if ($action['main_action'] === 'update_ms30') {
                $binds["{$p}_n30"] = $action['new_main_ms30'];
                $statements[] = "UPDATE SSMSMAIN
                                 SET MS30 = {$p}_n30
                                 WHERE MS03 = {$p}_m03 AND MS04_1 = {$p}_m041 AND MS04_2 = {$p}_m042
                                   AND NVL(MS14, 'X') = NVL({$p}_m14, 'X')
                                   AND NVL(MS_TYPE, 'R') = {$p}_typ
                                   AND NVL(MS30, 'X') = NVL({$p}_m30, 'X')";
            } elseif ($action['main_action'] === 'delete_legacy') {
                $statements[] = "DELETE FROM SSMSMAIN
                                 WHERE MS03 = {$p}_m03 AND MS04_1 = {$p}_m041 AND MS04_2 = {$p}_m042
                                   AND NVL(MS14, 'X') = NVL({$p}_m14, 'X')
                                   AND NVL(MS_TYPE, 'R') = {$p}_typ
                                   AND NVL(MS30, 'X') = NVL({$p}_m30, 'X')";
                $deletedFromMainCount++;
            } elseif ($action['main_action'] === 'delete_m') {
                $binds["{$p}_mal"] = $msMail;
                $statements[] = "DELETE FROM SSMSMAIN
                                 WHERE MS03 = {$p}_m03 AND MS04_1 = {$p}_m041 AND MS04_2 = {$p}_m042
                                   AND NVL(MS14, 'X') = NVL({$p}_m14, 'X')
                                   AND NVL(MS_TYPE, 'R') = {$p}_typ
                                   AND NVL(MS_MAIL, 'X') = NVL({$p}_mal, 'X')
                                   AND NVL(MS30, 'X') = NVL({$p}_m30, 'X')";
                $deletedFromMainCount++;
            } elseif ($action['main_action'] === 'delete_gov') {
                $binds["{$p}_mal"] = $msMail;
                $binds["{$p}_seq"] = $appSeq;
                $binds["{$p}_aid"] = $appId;
                $binds["{$p}_gid"] = $govId;
                $binds["{$p}_c00"] = $cc00;
                $binds["{$p}_c48"] = $cc48;
                $binds["{$p}_c49"] = $cc49;
                $binds["{$p}_oid"] = $govOid;
                $statements[] = "DELETE FROM SSMSMAIN
                                 WHERE MS03 = {$p}_m03 AND MS04_1 = {$p}_m041 AND MS04_2 = {$p}_m042
                                   AND NVL(APP_ID, 'X') = NVL({$p}_aid, 'X')
                                   AND NVL(MS14, 'X') = NVL({$p}_m14, 'X')
                                   AND NVL(MS_TYPE, 'R') = {$p}_typ
                                   AND NVL(MS_MAIL, 'X') = NVL({$p}_mal, 'X')
                                   AND NVL(GOV_ID, 'X') = NVL({$p}_gid, 'X')
                                   AND NVL(CC00, 'X') = NVL({$p}_c00, 'X')
                                   AND NVL(CC48, 'X') = NVL({$p}_c48, 'X')
                                   AND NVL(CC49, 'X') = NVL({$p}_c49, 'X')
                                   AND (({$p}_seq IS NULL AND APP_SEQ IS NULL) OR APP_SEQ = {$p}_seq)
                                   AND NVL(GOV_OID, 'X') = NVL({$p}_oid, 'X')
                                   AND NVL(MS30, 'X') = NVL({$p}_m30, 'X')";
                $deletedFromMainCount++;
            }
        }

        if (!empty($statements)) {
            $this->executePlSqlBlock($statements, $binds);
        }

        return array(
            'moved_to_wait_count' => $movedToWaitCount,
            'deleted_from_main_count' => $deletedFromMainCount,
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
     * 查詢 SSMSWAIT 待發送任務清單並組裝完整 UTF-8 subject / msg
     *
     * @return array{site_id: string, items: array<int, array<string, mixed>>}
     */
    public function getPendingQueue(): array {
        $meta = $this->getMetadata();
        $rows = $this->queryRaw(
            "SELECT MS03, MS04_1, MS04_2, MS07_1, MS07_2, MS14, MS30, MS32,
                    NVL(MS_TYPE, 'R') AS MS_TYPE, MS_MAIL, MS_NOTE,
                    APP_SEQ, APP_ID, GOV_ID, CC00, CC48, CC49, GOV_OID
             FROM SSMSWAIT
             WHERE MS_TYPE IN ('M', 'W', 'O')
             ORDER BY MS07_1, MS07_2"
        );

        $rkeynCache = array();
        $lookupRkeyn = function (string $k1, string $k2) use (&$rkeynCache): string {
            $cacheKey = $k1 . "\0" . $k2;
            if (array_key_exists($cacheKey, $rkeynCache)) {
                return $rkeynCache[$cacheKey];
            }
            $res = $this->queryRaw(
                "SELECT KCNT FROM SRKEYN WHERE KCDE_1 = :kcde_1 AND KCDE_2 = :kcde_2",
                array(':kcde_1' => $k1, ':kcde_2' => $k2)
            );
            $val = empty($res) ? '' : rtrim(SmsV1Encoding::fromOracleEncoding((string)($res[0]['KCNT'] ?? '')));
            $rkeynCache[$cacheKey] = $val;
            return $val;
        };

        $emailSubCache = array();
        $lookupEmailSub = function (string $appSeq) use (&$emailSubCache): string {
            if (array_key_exists($appSeq, $emailSubCache)) {
                return $emailSubCache[$appSeq];
            }
            $res = $this->queryRaw(
                "SELECT EMAIL_SUB FROM SSMS_GOVINFO_M WHERE APP_SEQ = :app_seq",
                array(':app_seq' => $appSeq)
            );
            $emailSub = '';
            foreach ($res as $subRow) {
                $emailSub = rtrim(SmsV1Encoding::fromOracleEncoding((string)($subRow['EMAIL_SUB'] ?? '')));
            }
            $emailSubCache[$appSeq] = $emailSub;
            return $emailSub;
        };

        $normalizedRows = array();
        foreach ($rows as $row) {
            $normalized = array();
            foreach ($row as $key => $value) {
                $lowerKey = strtolower(SmsV1Encoding::fromOracleEncoding((string)$key));
                $decoded = $value === null ? '' : SmsV1Encoding::fromOracleEncoding((string)$value);
                $normalized[$lowerKey] = rtrim($decoded);
            }
            $normalizedRows[] = $normalized;
        }

        $items = SmsV1QueueHelper::assemblePendingItems(
            $normalizedRows,
            function (string $ms04_1) use ($lookupRkeyn): string {
                return $lookupRkeyn('04', $ms04_1);
            },
            $lookupEmailSub,
            function (string $ms03, string $ms04_1, string $ms04_2): array {
                $uName = '';
                $md13_1 = null;
                $md13_2 = null;
                $scmsdsRows = $this->queryRaw(
                    "SELECT MD04, RPAD(MD13_1, 7, ' ') AS MD13_1, RPAD(MD13_2, 6, ' ') AS MD13_2
                     FROM SCMSDS
                     WHERE MD01 = :md01 AND MD02 = :md02 AND MD03 = :md03",
                    array(':md01' => $ms03, ':md02' => $ms04_1, ':md03' => $ms04_2)
                );
                foreach ($scmsdsRows as $scmsdsRow) {
                    $md13_1 = SmsV1Encoding::fromOracleEncoding((string)($scmsdsRow['MD13_1'] ?? ''));
                    $md13_2 = SmsV1Encoding::fromOracleEncoding((string)($scmsdsRow['MD13_2'] ?? ''));
                    $md04 = trim(SmsV1Encoding::fromOracleEncoding((string)($scmsdsRow['MD04'] ?? '')));
                    $authRows = $this->queryRaw(
                        "SELECT USER_NAME FROM SSYSAUTH1 WHERE USER_ID = :user_id",
                        array(':user_id' => $md04)
                    );
                    foreach ($authRows as $authRow) {
                        $uName = rtrim(SmsV1Encoding::fromOracleEncoding((string)($authRow['USER_NAME'] ?? '')));
                    }
                }
                return array('u_name' => $uName, 'md13_1' => $md13_1, 'md13_2' => $md13_2);
            }
        );

        return array(
            'site_id' => $meta['site_id'],
            'items' => $items,
        );
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
     * 確保無論原生 OCI8 或 PHP 8.5 單次呼叫橋接模式皆具備原子性。
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

        $plsql = "BEGIN\n" . implode("\n", $cleaned) . "\nEXCEPTION\n  WHEN OTHERS THEN\n    ROLLBACK;\n    RAISE;\nEND;";
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
