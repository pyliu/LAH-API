<?php
declare(strict_types=1);

namespace Tests\Unit\Domain;

use PHPUnit\Framework\TestCase;
use SmsV1Encoding;
use SmsV1MockStore;
use SmsV1OracleStore;
use SmsV1Router;
use SmsV1Service;

class SmsV1CasesSyncTest extends TestCase {
    /** @var array<int, string> */
    private array $tempDirs = [];

    protected function tearDown(): void {
        foreach ($this->tempDirs as $dir) {
            $this->removeDirectory($dir);
        }
        $this->tempDirs = [];
        parent::tearDown();
    }

    private function createTempDbPath(string $filename = 'sms_mock.db'): string {
        $dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'sms_v1_sync_test_' . uniqid('', true);
        $this->tempDirs[] = $dir;
        return $dir . DIRECTORY_SEPARATOR . $filename;
    }

    private function removeDirectory(string $dir): void {
        if (!is_dir($dir)) {
            return;
        }
        $items = scandir($dir);
        if ($items === false) {
            return;
        }
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir . DIRECTORY_SEPARATOR . $item;
            if (is_dir($path)) {
                $this->removeDirectory($path);
            } else {
                @unlink($path);
            }
        }
        @rmdir($dir);
    }

    public function testPostCasesSyncDefaultsToServerRocDateAndReturnsZeroCountsOnEmptyDb(): void {
        $store = new SmsV1MockStore($this->createTempDbPath());
        $router = new SmsV1Router(new SmsV1Service($store));

        $res = $router->dispatch('POST', '/cases/sync', [], '');
        $this->assertSame(200, $res['status']);
        $this->assertSame([
            'target_date' => SmsV1Encoding::getRocDate(),
            'user_cases_scanned' => 0,
            'user_notifications_inserted' => 0,
            'gov_cases_scanned' => 0,
            'gov_notifications_inserted' => 0,
        ], $res['body']);

        $resEmptyJson = $router->dispatch('POST', '/api/v1/sms/cases/sync', [], '{}');
        $this->assertSame(200, $resEmptyJson['status']);
        $this->assertSame(SmsV1Encoding::getRocDate(), $resEmptyJson['body']['target_date']);
    }

    public function testPostCasesSyncReturns400ForInvalidDateOrMalformedJson(): void {
        $store = new SmsV1MockStore($this->createTempDbPath());
        $router = new SmsV1Router(new SmsV1Service($store));

        $invalidPayloads = [
            'not-json',
            '[]',
            '"1151113"',
            '123',
            '{"target_date_yyy_mm_dd": ""}',
            '{"target_date_yyy_mm_dd": "20261113"}',
            '{"target_date_yyy_mm_dd": "115111"}',
            '{"target_date_yyy_mm_dd": "1151301"}',
            '{"target_date_yyy_mm_dd": "1150230"}',
            '{"target_date_yyy_mm_dd": 1151113}',
        ];

        foreach ($invalidPayloads as $rawBody) {
            $res = $router->dispatch('POST', '/cases/sync', [], $rawBody);
            $this->assertSame(400, $res['status'], "Expected 400 for body: {$rawBody}");
            $this->assertSame('Bad Request', $res['body']['error']);
        }
    }

    public function testSyncUserReceiptCasesWithMobileEmailAndSecondaryContacts(): void {
        $store = new SmsV1MockStore($this->createTempDbPath());
        $pdo = $store->getPdo();

        // Seed SRKEYN lookups & codes
        $pdo->exec("INSERT INTO SRKEYN (KCDE_1, KCDE_2, KCNT, KRMK) VALUES ('04', 'HA01', '桃資', '')");
        $pdo->exec("INSERT INTO SRKEYN (KCDE_1, KCDE_2, KCNT, KRMK) VALUES ('06', '01', '買賣', '')");
        $pdo->exec("UPDATE SRKEYN SET KCNT = '（測試備註）' WHERE KCDE_1 = 'SMS' AND KCDE_2 = '01'");
        $pdo->exec("INSERT INTO SSMS_CODE04 (COD04) VALUES ('HA01')");
        $pdo->exec("INSERT INTO SSMS_CODE06 (COD06, ITEMS) VALUES ('01', '0'), ('01', '1')");

        // Use non-current ROC year 1140506 to prove A.RM01 uses target_date's first 3 digits ('114')
        // Case 1 (權利人 RM_TYPE=18, ITEMS=0): matches all criteria
        $pdo->exec("INSERT INTO SCRSMS (RM01, RM02, RM03, RM07_1, RM07_2, RM09, RM32, RM99, RM100, RM101)
                    VALUES ('114', 'HA01', '001230', '1140506', '093000', '01', '1', NULL, NULL, NULL)");
        $pdo->exec("INSERT INTO SCRSMS_RM1821 (RM01, RM02, RM03, RM_TYPE, RM_ID)
                    VALUES ('114', 'HA01', '001230', '18', 'A123456789')");
        $pdo->exec("INSERT INTO SSMS_USERINFO_M (LIDN, LNAM, REPLY_MOBILE, MOBILE, MOBILE2, REPLY_EMAIL, EMAIL, EMAIL2, SMS_FLAG)
                    VALUES ('A123456789', '王小明', '1', '0912345678', '0987654321', '1', 'u1@example.com', 'u2@example.com', 'Y')");

        // Case 2 (義務人 RM_TYPE=21, ITEMS=1, RM99='Y' matching KRMK='HA'): only mobile
        $pdo->exec("INSERT INTO SCRSMS (RM01, RM02, RM03, RM07_1, RM07_2, RM09, RM32, RM99, RM100, RM101)
                    VALUES ('114', 'HA01', '001240', '1140506', '101500', '01', '1', 'Y', 'HA', 'HB')");
        $pdo->exec("INSERT INTO SCRSMS_RM1821 (RM01, RM02, RM03, RM_TYPE, RM_ID)
                    VALUES ('114', 'HA01', '001240', '21', 'B223456789')");
        $pdo->exec("INSERT INTO SSMS_USERINFO_M (LIDN, LNAM, REPLY_MOBILE, MOBILE, MOBILE2, REPLY_EMAIL, EMAIL, EMAIL2, SMS_FLAG)
                    VALUES ('B223456789', '陳大華', '1', '0922222222', '', '0', '', '', 'Y')");

        // Excluded cases:
        // - 6th char of RM03 != '0'
        $pdo->exec("INSERT INTO SCRSMS (RM01, RM02, RM03, RM07_1, RM07_2, RM09, RM32)
                    VALUES ('114', 'HA01', '001251', '1140506', '110000', '01', '1')");
        $pdo->exec("INSERT INTO SCRSMS_RM1821 (RM01, RM02, RM03, RM_TYPE, RM_ID)
                    VALUES ('114', 'HA01', '001251', '18', 'A123456789')");
        // - RM32 IS NULL
        $pdo->exec("INSERT INTO SCRSMS (RM01, RM02, RM03, RM07_1, RM07_2, RM09, RM32)
                    VALUES ('114', 'HA01', '001260', '1140506', '110000', '01', NULL)");
        $pdo->exec("INSERT INTO SCRSMS_RM1821 (RM01, RM02, RM03, RM_TYPE, RM_ID)
                    VALUES ('114', 'HA01', '001260', '18', 'A123456789')");
        // - RM99='Y' but RM100 != 'HA'
        $pdo->exec("INSERT INTO SCRSMS (RM01, RM02, RM03, RM07_1, RM07_2, RM09, RM32, RM99, RM100)
                    VALUES ('114', 'HA01', '001270', '1140506', '110000', '01', '1', 'Y', 'HC')");
        $pdo->exec("INSERT INTO SCRSMS_RM1821 (RM01, RM02, RM03, RM_TYPE, RM_ID)
                    VALUES ('114', 'HA01', '001270', '18', 'A123456789')");
        // - SMS_FLAG != 'Y'
        $pdo->exec("INSERT INTO SCRSMS (RM01, RM02, RM03, RM07_1, RM07_2, RM09, RM32)
                    VALUES ('114', 'HA01', '001280', '1140506', '110000', '01', '1')");
        $pdo->exec("INSERT INTO SCRSMS_RM1821 (RM01, RM02, RM03, RM_TYPE, RM_ID)
                    VALUES ('114', 'HA01', '001280', '18', 'C333333333')");
        $pdo->exec("INSERT INTO SSMS_USERINFO_M (LIDN, LNAM, REPLY_MOBILE, MOBILE, MOBILE2, REPLY_EMAIL, EMAIL, EMAIL2, SMS_FLAG)
                    VALUES ('C333333333', '停用者', '1', '0933333333', '', '0', '', '', 'N')");

        $router = new SmsV1Router(new SmsV1Service($store));
        $res = $router->dispatch('POST', '/cases/sync', [], '{"target_date_yyy_mm_dd":"1140506"}');

        $this->assertSame(200, $res['status']);
        $this->assertSame([
            'target_date' => '1140506',
            'user_cases_scanned' => 2,
            'user_notifications_inserted' => 5,
            'gov_cases_scanned' => 0,
            'gov_notifications_inserted' => 0,
        ], $res['body']);

        $rows = $pdo->query("SELECT MS03, MS04_1, MS04_2, MS07_1, MS07_2, MS14, MS_MAIL, MS30, MS_TYPE, MS_NOTE FROM SSMSMAIN ORDER BY MS04_2, MS14, MS_MAIL")->fetchAll(\PDO::FETCH_ASSOC);
        $this->assertCount(5, $rows);

        $expectedCase1SmsNote = ' 異動即時通-114年桃資字001230號買賣案已於114年05月06日完成收件，如有疑義請向地政事務所洽詢。（測試備註）';
        $expectedCase1MailNote = '桃園地政' . $expectedCase1SmsNote;
        $expectedCase2SmsNote = ' 異動即時通-114年桃資字001240號買賣案已於114年05月06日完成收件，如有疑義請向地政事務所洽詢。（測試備註）';

        // Case 1 has 2 emails + 2 mobiles
        $mobilesCase1 = array_values(array_filter($rows, fn(array $r): bool => $r['MS04_2'] === '001230' && (string)$r['MS14'] !== ''));
        $mailsCase1 = array_values(array_filter($rows, fn(array $r): bool => $r['MS04_2'] === '001230' && (string)$r['MS_MAIL'] !== ''));
        $this->assertCount(2, $mobilesCase1);
        $this->assertSame(['0912345678', '0987654321'], array_column($mobilesCase1, 'MS14'));
        $this->assertSame('Y', $mobilesCase1[0]['MS30']);
        $this->assertSame('M', $mobilesCase1[0]['MS_TYPE']);
        $this->assertSame($expectedCase1SmsNote, $mobilesCase1[0]['MS_NOTE']);

        $this->assertCount(2, $mailsCase1);
        $this->assertSame(['u1@example.com', 'u2@example.com'], array_column($mailsCase1, 'MS_MAIL'));
        $this->assertSame($expectedCase1MailNote, $mailsCase1[0]['MS_NOTE']);

        // Case 2 has 1 mobile
        $case2Rows = array_values(array_filter($rows, fn(array $r): bool => $r['MS04_2'] === '001240'));
        $this->assertCount(1, $case2Rows);
        $this->assertSame('0922222222', $case2Rows[0]['MS14']);
        $this->assertSame('Y', $case2Rows[0]['MS30']);
        $this->assertSame('M', $case2Rows[0]['MS_TYPE']);
        $this->assertSame($expectedCase2SmsNote, $case2Rows[0]['MS_NOTE']);
    }

    public function testSyncUserCompletionCasesSqlCToF(): void {
        $store = new SmsV1MockStore($this->createTempDbPath());
        $pdo = $store->getPdo();

        $pdo->exec("INSERT INTO SRKEYN (KCDE_1, KCDE_2, KCNT, KRMK) VALUES ('04', 'HA01', '桃資', '')");
        $pdo->exec("INSERT INTO SRKEYN (KCDE_1, KCDE_2, KCNT, KRMK) VALUES ('06', '02', '設定', ''), ('06', '03', '塗銷', '')");
        $pdo->exec("INSERT INTO SSMS_CODE04 (COD04) VALUES ('HA01')");
        $pdo->exec("INSERT INTO SSMS_CODE06 (COD06, ITEMS) VALUES ('02', '0'), ('03', '1')");

        // Users
        $pdo->exec("INSERT INTO SSMS_USERINFO_M (LIDN, LNAM, REPLY_MOBILE, MOBILE, REPLY_EMAIL, EMAIL, SMS_FLAG) VALUES
            ('U_C', '權利人C', '1', '0911000001', '0', '', 'Y'),
            ('U_D', '義務人D', '1', '0911000002', '0', '', 'Y'),
            ('U_E', '土地義務E', '1', '0911000003', '0', '', 'Y'),
            ('U_F', '建物義務F', '1', '0911000004', '0', '', 'Y')");

        // SQL_C: cross-year RM01='114' completing on 1151113, LEFT JOIN TRINDX (no TRINDX row)
        $pdo->exec("INSERT INTO SCRSMS (RM01, RM02, RM03, RM07_1, RM07_2, RM09, RM56_1, RM56_2)
                    VALUES ('114', 'HA01', '002010', '1141230', '080000', '02', '1151113', '140000')");
        $pdo->exec("INSERT INTO SRSINDX (IS03, IS04_1, IS04_2, IS00, IS01, IS09, IS48, IS49)
                    VALUES ('114', 'HA01', '002010', 'B', '0001', 'U_C', '0101', '00010000')");

        // SQL_D: INNER JOIN TRINDX II_DATA='M', ITEMS='1'
        $pdo->exec("INSERT INTO SCRSMS (RM01, RM02, RM03, RM07_1, RM07_2, RM09, RM56_1, RM56_2)
                    VALUES ('115', 'HA01', '002020', '1151110', '090000', '03', '1151113', '150000')");
        $pdo->exec("INSERT INTO SRSINDX (IS03, IS04_1, IS04_2, IS00, IS01, IS09, IS48, IS49)
                    VALUES ('115', 'HA01', '002020', 'E', '0002', 'U_D', '0101', '00020000')");
        $pdo->exec("INSERT INTO TRINDX (II03, II04_1, II04_2, II00, II_DATA, IIKEY)
                    VALUES ('115', 'HA01', '002020', 'E', 'M', 'F0101000200000002')");

        // SQL_E: TRINDX II00='R' + SRBLOW
        $pdo->exec("INSERT INTO SCRSMS (RM01, RM02, RM03, RM07_1, RM07_2, RM09, RM56_1, RM56_2)
                    VALUES ('115', 'HA01', '002030', '1151111', '090000', '03', '1151113', '160000')");
        $pdo->exec("INSERT INTO TRINDX (II03, II04_1, II04_2, II00, II_DATA, IIKEY)
                    VALUES ('115', 'HA01', '002030', 'R', '1', 'C010100030000XXX')");
        $pdo->exec("INSERT INTO SRBLOW (BA48, BA49, BB01, BB09)
                    VALUES ('0101', '00030000', '1', 'U_E')");

        // SQL_F: TRINDX II00='R' + SREBOW
        $pdo->exec("INSERT INTO SCRSMS (RM01, RM02, RM03, RM07_1, RM07_2, RM09, RM56_1, RM56_2)
                    VALUES ('115', 'HA01', '002040', '1151112', '090000', '03', '1151113', '170000')");
        $pdo->exec("INSERT INTO TRINDX (II03, II04_1, II04_2, II00, II_DATA, IIKEY)
                    VALUES ('115', 'HA01', '002040', 'R', '1', 'F010100040000XXX')");
        $pdo->exec("INSERT INTO SREBOW (ED48, ED49, EE01, EE09)
                    VALUES ('0101', '00040000', '1', 'U_F')");

        $router = new SmsV1Router(new SmsV1Service($store));
        $res = $router->dispatch('POST', '/cases/sync', [], '{"target_date_yyy_mm_dd":"1151113"}');

        $this->assertSame(200, $res['status']);
        $this->assertSame([
            'target_date' => '1151113',
            'user_cases_scanned' => 4,
            'user_notifications_inserted' => 4,
            'gov_cases_scanned' => 0,
            'gov_notifications_inserted' => 0,
        ], $res['body']);

        $rows = $pdo->query("SELECT MS03, MS04_2, MS14, MS30, MS_NOTE FROM SSMSMAIN ORDER BY MS14")->fetchAll(\PDO::FETCH_ASSOC);
        $this->assertCount(4, $rows);
        $this->assertSame('Z', $rows[0]['MS30']);
        $this->assertSame(' 異動即時通-114年桃資字002010號設定案已於115年11月13日異動完成，如有疑義請向地政事務所洽詢。', $rows[0]['MS_NOTE']);
        $this->assertSame(['0911000001', '0911000002', '0911000003', '0911000004'], array_column($rows, 'MS14'));
    }

    public function testUserSyncDeduplicatesAcrossAllThreeTablesAndIsIdempotent(): void {
        $store = new SmsV1MockStore($this->createTempDbPath());
        $pdo = $store->getPdo();

        $pdo->exec("INSERT INTO SRKEYN (KCDE_1, KCDE_2, KCNT, KRMK) VALUES ('04', 'HA01', '桃資', '')");
        $pdo->exec("INSERT INTO SRKEYN (KCDE_1, KCDE_2, KCNT, KRMK) VALUES ('06', '01', '買賣', '')");
        $pdo->exec("INSERT INTO SSMS_CODE04 (COD04) VALUES ('HA01')");
        $pdo->exec("INSERT INTO SSMS_CODE06 (COD06, ITEMS) VALUES ('01', '0')");

        $pdo->exec("INSERT INTO SCRSMS (RM01, RM02, RM03, RM07_1, RM07_2, RM09, RM32)
                    VALUES ('115', 'HA01', '003010', '1151113', '093000', '01', '1')");
        $pdo->exec("INSERT INTO SCRSMS_RM1821 (RM01, RM02, RM03, RM_TYPE, RM_ID)
                    VALUES ('115', 'HA01', '003010', '18', 'U1')");
        $pdo->exec("INSERT INTO SSMS_USERINFO_M (LIDN, LNAM, REPLY_MOBILE, MOBILE, MOBILE2, REPLY_EMAIL, EMAIL, EMAIL2, SMS_FLAG)
                    VALUES ('U1', '重複測', '1', '0911111111', '0922222222', '1', 'a@test.tw', 'b@test.tw', 'Y')");

        // Seed existing entries: MOBILE in SSMSWAIT, EMAIL in SSMSLOG, MOBILE2 in SSMSMAIN
        $pdo->exec("INSERT INTO SSMSWAIT (MS03, MS04_1, MS04_2, MS14, MS30, MS_TYPE)
                    VALUES ('115', 'HA01', '003010', '0911111111', 'Y', 'M')");
        $pdo->exec("INSERT INTO SSMSLOG (MS03, MS04_1, MS04_2, MS_MAIL, MS30, MS31, MS_TYPE)
                    VALUES ('115', 'HA01', '003010', 'a@test.tw', 'Y', 'S', 'M')");
        $pdo->exec("INSERT INTO SSMSMAIN (MS03, MS04_1, MS04_2, MS14, MS30, MS_TYPE)
                    VALUES ('115', 'HA01', '003010', '0922222222', 'Y', 'M')");

        $router = new SmsV1Router(new SmsV1Service($store));

        // Only EMAIL2 (b@test.tw) should be newly inserted
        $first = $router->dispatch('POST', '/cases/sync', [], '{"target_date_yyy_mm_dd":"1151113"}');
        $this->assertSame(200, $first['status']);
        $this->assertSame(1, $first['body']['user_cases_scanned']);
        $this->assertSame(1, $first['body']['user_notifications_inserted']);

        // Second call must be idempotent
        $second = $router->dispatch('POST', '/cases/sync', [], '{"target_date_yyy_mm_dd":"1151113"}');
        $this->assertSame(200, $second['status']);
        $this->assertSame(1, $second['body']['user_cases_scanned']);
        $this->assertSame(0, $second['body']['user_notifications_inserted']);
    }

    public function testSyncGovCasesSplitsEmailsDeduplicatesAndIsIdempotent(): void {
        $store = new SmsV1MockStore($this->createTempDbPath());
        $pdo = $store->getPdo();

        $pdo->exec("INSERT INTO SRKEYN (KCDE_1, KCDE_2, KCNT, KRMK) VALUES ('04', 'HA01', '桃資', '')");
        $pdo->exec("INSERT INTO SRKEYN (KCDE_1, KCDE_2, KCNT, KRMK) VALUES ('06', '01', '買賣', '')");
        $pdo->exec("INSERT INTO SRKEYN (KCDE_1, KCDE_2, KCNT, KRMK) VALUES ('48', '0101', '桃園段', '')");
        $pdo->exec("INSERT INTO SSMS_CODE06 (COD06, ITEMS) VALUES ('01', '2')");

        // Gov definitions: 1 active (with 2 emails), 1 stopped (APP_STOP='Y'), 1 active with empty GOV_OID for completion
        $pdo->exec("INSERT INTO SMS_GOVINFO_M (APP_SEQ, APP_ID, GOV_NAME, EMAIL, APP_STOP, GOV_OID) VALUES
            ('1', 'GOV01', '工務局', 'g1@gov.tw;g2@gov.tw;', 'N', '2.16.886.101'),
            ('2', 'GOV02', '停用局', 'stop@gov.tw', 'Y', '2.16.886.102'),
            ('3', 'GOV03', '建管處', 'b1@gov.tw', NULL, '')");
        $pdo->exec("INSERT INTO SSMS_GOVINFO_D (APP_SEQ, GOV_ID, CC00, CC48, CC49) VALUES
            ('1', 'G01', 'C', '0101', '00010002'),
            ('2', 'G02', 'C', '0101', '00010002'),
            ('3', 'G03', 'F', '0101', '00012003')");

        // Gov receipt ('y') for land CC00='C'
        $pdo->exec("INSERT INTO SCRSMS (RM01, RM02, RM03, RM07_1, RM07_2, RM09, RM32)
                    VALUES ('115', 'HA01', '004010', '1151113', '091000', '01', '1')");
        $pdo->exec("INSERT INTO SCRSMS_RM1215 (RM01, RM02, RM03, RM00, RM11, RM1215)
                    VALUES ('115', 'HA01', '004010', 'C', '0101', '00010002')");

        // Gov completion ('z') for building CC00='F'
        $pdo->exec("INSERT INTO SCRSMS (RM01, RM02, RM03, RM07_1, RM07_2, RM09, RM56_1, RM56_2)
                    VALUES ('115', 'HA01', '004020', '1151110', '090000', '01', '1151113', '163000')");
        $pdo->exec("INSERT INTO SRSINDX (IS03, IS04_1, IS04_2, IS00, IS01, IS09, IS48, IS49)
                    VALUES ('115', 'HA01', '004020', 'D', '0001', 'X1', '0101', '00012003')");

        // Pre-seed 'z' in SSMSWAIT to verify CheckGData dedup when GOV_OID='' (matches by GOV_ID)
        $expectedZNote = '桃園市桃園段00012-003建號已於115年桃資字004020號買賣案115年11月13日異動完成，如有任何問題請逕向桃園地政事務所洽詢。';
        $pdo->exec("INSERT INTO SSMSWAIT (MS03, MS04_1, MS04_2, MS30, MS_TYPE, MS_NOTE, APP_SEQ, APP_ID, GOV_ID, CC00, CC48, CC49, GOV_OID)
                    VALUES ('115', 'HA01', '004020', 'z', 'M', '{$expectedZNote}', '3', 'GOV03', 'G03', 'F', '0101', '00012003', '')");

        $router = new SmsV1Router(new SmsV1Service($store));
        $first = $router->dispatch('POST', '/cases/sync', [], '{"target_date_yyy_mm_dd":"1151113"}');

        $this->assertSame(200, $first['status']);
        $this->assertSame([
            'target_date' => '1151113',
            'user_cases_scanned' => 0,
            'user_notifications_inserted' => 0,
            'gov_cases_scanned' => 2,
            'gov_notifications_inserted' => 2,
        ], $first['body']);

        $govRows = $pdo->query("SELECT MS03, MS04_1, MS04_2, MS_MAIL, MS30, MS_TYPE, MS_NOTE, APP_SEQ, APP_ID, GOV_ID, CC00, CC48, CC49, GOV_OID FROM SSMSMAIN ORDER BY MS_MAIL")->fetchAll(\PDO::FETCH_ASSOC);
        $this->assertCount(2, $govRows);
        $this->assertSame(['g1@gov.tw', 'g2@gov.tw'], array_column($govRows, 'MS_MAIL'));
        $this->assertSame('y', $govRows[0]['MS30']);
        $this->assertSame('M', $govRows[0]['MS_TYPE']);
        $this->assertSame('2.16.886.101', $govRows[0]['GOV_OID']);
        $this->assertSame(
            '桃園市桃園段0001-0002地號已於115年桃資字004010號買賣案115年11月13日完成收件，如有任何問題請逕向桃園地政事務所洽詢。',
            $govRows[0]['MS_NOTE']
        );

        // Second call must be idempotent
        $second = $router->dispatch('POST', '/cases/sync', [], '{"target_date_yyy_mm_dd":"1151113"}');
        $this->assertSame(200, $second['status']);
        $this->assertSame(2, $second['body']['gov_cases_scanned']);
        $this->assertSame(0, $second['body']['gov_notifications_inserted']);
    }

    public function testOracleStoreSyncCasesUsesBindVariablesCp950EncodingAndAtomicPlSqlBlock(): void {
        $fakeDb = new class extends \OraDB {
            /** @var array<int, array{sql: string, binds: array<string, mixed>}> */
            public array $executed = [];
            private string $currentSql = '';
            /** @var array<string, mixed> */
            private array $currentBinds = [];

            public function __construct() {}
            public function __destruct() {}
            public function close() {}
            public function parse($str) {
                $this->currentSql = (string)$str;
                $this->currentBinds = [];
            }
            public function bind($bind_var, $real_var) {
                $this->currentBinds[(string)$bind_var] = $real_var;
            }
            public function execute() {
                $this->executed[] = [
                    'sql' => $this->currentSql,
                    'binds' => $this->currentBinds,
                ];
                return true;
            }
            public function fetchAll($raw = false) {
                $sql = $this->currentSql;
                if (str_starts_with($sql, 'SELECT KCNT FROM SRKEYN') && str_contains($sql, ':kcde_1')) {
                    $k1 = (string)($this->currentBinds[':kcde_1'] ?? '');
                    $k2 = (string)($this->currentBinds[':kcde_2'] ?? '');
                    if ($k1 === '55' && $k2 === '02') {
                        return [['KCNT' => SmsV1Encoding::toOracleEncoding('桃園')]];
                    }
                    if ($k1 === '55' && $k2 === '01') {
                        return [['KCNT' => SmsV1Encoding::toOracleEncoding('桃園地政事務所'), 'KRMK' => 'HA']];
                    }
                    if ($k1 === '66' && $k2 === '01') {
                        return [['KCNT' => SmsV1Encoding::toOracleEncoding('桃園市')]];
                    }
                    if ($k1 === 'SMS' && $k2 === '01') {
                        return [['KCNT' => '']];
                    }
                    if ($k1 === '04') {
                        return [['KCNT' => SmsV1Encoding::toOracleEncoding('桃資')]];
                    }
                    if ($k1 === '06') {
                        return [['KCNT' => SmsV1Encoding::toOracleEncoding('買賣')]];
                    }
                    if ($k1 === '48') {
                        return [['KCNT' => SmsV1Encoding::toOracleEncoding('桃園段')]];
                    }
                }
                if (str_contains($sql, "B.RM_TYPE = '18'")) {
                    return [[
                        'MS30' => 'Y',
                        'RM01' => '115',
                        'RM02' => 'HA01',
                        'RM03' => '001230',
                        'RM09' => '01',
                        'MSID' => 'A123456789',
                        'T07_1' => '1151113',
                        'T07_2' => '093000',
                        'TLAND' => 'HA',
                        'LNAM' => SmsV1Encoding::toOracleEncoding('王小明'),
                        'REPLY_MOBILE' => '1',
                        'MOBILE' => '0912345678',
                        'MOBILE2' => '0912345678', // duplicate within batch should only insert once
                        'REPLY_EMAIL' => '1',
                        'EMAIL' => 'user@example.com',
                        'EMAIL2' => '',
                    ]];
                }
                if (str_contains($sql, 'SCRSMS_RM1215')) {
                    return [[
                        'MS30' => 'y',
                        'RM01' => '115',
                        'RM02' => 'HA01',
                        'RM03' => '004010',
                        'RM09' => '01',
                        'T07_1' => '1151113',
                        'T07_2' => '091000',
                        'TLAND' => 'HA',
                        'GOV_ID' => 'G01',
                        'CC00' => 'C',
                        'CC48' => '0101',
                        'CC49' => '00010002',
                        'GOV_NAME' => SmsV1Encoding::toOracleEncoding('工務局'),
                        'EMAIL' => 'gov1@gov.tw;gov2@gov.tw',
                        'APP_ID' => 'GOV01',
                        'APP_SEQ' => '1',
                        'GOV_OID' => '2.16.886.101',
                    ]];
                }
                return [];
            }
        };

        $fakeWrapper = new class($fakeDb) extends \OraDBWrapper {
            private $fakeDb;
            public function __construct($fakeDb) { $this->fakeDb = $fakeDb; }
            public function __destruct() {}
            public function reachable() { return true; }
            public function getDB() { return $this->fakeDb; }
        };

        $store = new SmsV1OracleStore($fakeWrapper);
        $res = $store->syncCases('1151113');

        $this->assertSame([
            'target_date' => '1151113',
            'user_cases_scanned' => 1,
            'user_notifications_inserted' => 2,
            'gov_cases_scanned' => 1,
            'gov_notifications_inserted' => 2,
        ], $res);

        // Verify the final PL/SQL block executed with bind variables & CP950 encoded MS_NOTE
        $plsqlExecs = array_values(array_filter($fakeDb->executed, fn(array $e): bool => str_starts_with($e['sql'], 'BEGIN')));
        $this->assertCount(1, $plsqlExecs);
        $plsql = $plsqlExecs[0]['sql'];
        $binds = $plsqlExecs[0]['binds'];

        $this->assertStringContainsString('INSERT INTO SSMSMAIN', $plsql);
        $this->assertStringContainsString('ROLLBACK;', $plsql);
        $this->assertStringNotContainsString('0912345678', $plsql);
        $this->assertStringNotContainsString('gov1@gov.tw', $plsql);

        $expectedUserSmsNote = ' 異動即時通-115年桃資字001230號買賣案已於115年11月13日完成收件，如有疑義請向地政事務所洽詢。';
        $expectedUserMailNote = '桃園地政' . $expectedUserSmsNote;
        $expectedGovNote = '桃園市桃園段0001-0002地號已於115年桃資字004010號買賣案115年11月13日完成收件，如有任何問題請逕向桃園地政事務所洽詢。';

        $this->assertContains(SmsV1Encoding::toOracleEncoding($expectedUserSmsNote), $binds);
        $this->assertContains(SmsV1Encoding::toOracleEncoding($expectedUserMailNote), $binds);
        $this->assertContains(SmsV1Encoding::toOracleEncoding($expectedGovNote), $binds);
    }

    public function testSyncCasesRollsBackMockTransactionAndMasksErrorOnRouter500(): void {
        $store = new SmsV1MockStore($this->createTempDbPath());
        $pdo = $store->getPdo();

        $pdo->exec("INSERT INTO SRKEYN (KCDE_1, KCDE_2, KCNT, KRMK) VALUES ('04', 'HA01', '桃資', '')");
        $pdo->exec("INSERT INTO SRKEYN (KCDE_1, KCDE_2, KCNT, KRMK) VALUES ('06', '01', '買賣', '')");
        $pdo->exec("INSERT INTO SSMS_CODE04 (COD04) VALUES ('HA01')");
        $pdo->exec("INSERT INTO SSMS_CODE06 (COD06, ITEMS) VALUES ('01', '0')");
        $pdo->exec("INSERT INTO SCRSMS (RM01, RM02, RM03, RM07_1, RM07_2, RM09, RM32)
                    VALUES ('115', 'HA01', '005010', '1151113', '093000', '01', '1')");
        $pdo->exec("INSERT INTO SCRSMS_RM1821 (RM01, RM02, RM03, RM_TYPE, RM_ID)
                    VALUES ('115', 'HA01', '005010', '18', 'U1')");
        $pdo->exec("INSERT INTO SSMS_USERINFO_M (LIDN, LNAM, REPLY_MOBILE, MOBILE, REPLY_EMAIL, EMAIL, SMS_FLAG)
                    VALUES ('U1', '測試者', '1', '0911111111', '1', 'fail@example.com', 'Y')");

        // Fail on the second INSERT (when MS_MAIL is populated) to verify BEGIN IMMEDIATE rolls back the first INSERT
        $pdo->exec("CREATE TRIGGER fail_on_mail BEFORE INSERT ON SSMSMAIN
                    WHEN NEW.MS_MAIL IS NOT NULL
                    BEGIN
                        SELECT RAISE(ABORT, 'secret_db_failure_token');
                    END;");

        $router = new SmsV1Router(new SmsV1Service($store));
        $res = $router->dispatch('POST', '/cases/sync', [], '{"target_date_yyy_mm_dd":"1151113"}');

        $this->assertSame(500, $res['status']);
        $this->assertSame('Internal Server Error', $res['body']['error']);
        $this->assertStringNotContainsString('secret_db_failure_token', $res['body']['message']);

        // First insert must have been rolled back
        $count = (int)$pdo->query("SELECT COUNT(*) FROM SSMSMAIN")->fetchColumn();
        $this->assertSame(0, $count);
    }

    public function testSyncGovCasesDeduplicatesWhenGovIdGovOidAndAppIdAreEmptyOrNull(): void {
        $store = new SmsV1MockStore($this->createTempDbPath());
        $pdo = $store->getPdo();

        $pdo->exec("INSERT INTO SRKEYN (KCDE_1, KCDE_2, KCNT, KRMK) VALUES ('04', 'HA01', '桃資', '')");
        $pdo->exec("INSERT INTO SRKEYN (KCDE_1, KCDE_2, KCNT, KRMK) VALUES ('06', '01', '買賣', '')");
        $pdo->exec("INSERT INTO SRKEYN (KCDE_1, KCDE_2, KCNT, KRMK) VALUES ('48', '0101', '桃園段', '')");
        $pdo->exec("INSERT INTO SSMS_CODE06 (COD06, ITEMS) VALUES ('01', '2')");

        // Gov case with NULL/empty APP_ID, GOV_ID, and GOV_OID
        $pdo->exec("INSERT INTO SMS_GOVINFO_M (APP_SEQ, APP_ID, GOV_NAME, EMAIL, APP_STOP, GOV_OID) VALUES
            ('10', '', '匿名機關', 'anon@gov.tw', 'N', NULL)");
        $pdo->exec("INSERT INTO SSMS_GOVINFO_D (APP_SEQ, GOV_ID, CC00, CC48, CC49) VALUES
            ('10', NULL, 'C', '0101', '00090001')");

        $pdo->exec("INSERT INTO SCRSMS (RM01, RM02, RM03, RM07_1, RM07_2, RM09, RM32)
                    VALUES ('115', 'HA01', '009010', '1151113', '091000', '01', '1')");
        $pdo->exec("INSERT INTO SCRSMS_RM1215 (RM01, RM02, RM03, RM00, RM11, RM1215)
                    VALUES ('115', 'HA01', '009010', 'C', '0101', '00090001')");

        $first = $store->syncCases('1151113');
        $this->assertSame(1, $first['gov_notifications_inserted']);

        // Second sync must deduplicate despite empty/NULL GOV_ID, GOV_OID, and APP_ID
        $second = $store->syncCases('1151113');
        $this->assertSame(0, $second['gov_notifications_inserted']);
        $this->assertSame(1, (int)$pdo->query("SELECT COUNT(*) FROM SSMSMAIN")->fetchColumn());
    }

    public function testOracleStoreSyncCasesUsesNvlUnionAllAndChunksPlSqlBlocks(): void {
        $fakeDb = new class extends \OraDB {
            /** @var array<int, array{sql: string, binds: array<string, mixed>}> */
            public array $executed = [];
            private string $currentSql = '';
            /** @var array<string, mixed> */
            private array $currentBinds = [];

            public function __construct() {}
            public function __destruct() {}
            public function close() {}
            public function parse($str) {
                $this->currentSql = (string)$str;
                $this->currentBinds = [];
            }
            public function bind($bind_var, $real_var) {
                $this->currentBinds[(string)$bind_var] = $real_var;
            }
            public function execute() {
                $this->executed[] = [
                    'sql' => $this->currentSql,
                    'binds' => $this->currentBinds,
                ];
                return true;
            }
            public function fetchAll($raw = false) {
                $sql = $this->currentSql;
                if (str_starts_with($sql, 'SELECT KCNT FROM SRKEYN') && str_contains($sql, ':kcde_1')) {
                    $k1 = (string)($this->currentBinds[':kcde_1'] ?? '');
                    $k2 = (string)($this->currentBinds[':kcde_2'] ?? '');
                    if ($k1 === '55' && $k2 === '01') {
                        return [['KCNT' => SmsV1Encoding::toOracleEncoding('桃園地政事務所')]];
                    }
                    if ($k1 === '66' && $k2 === '01') {
                        return [['KCNT' => SmsV1Encoding::toOracleEncoding('桃園市')]];
                    }
                    if ($k1 === '55' && $k2 === '02') {
                        return [['KCNT' => SmsV1Encoding::toOracleEncoding('桃園')]];
                    }
                    if ($k1 === 'SMS') {
                        return [['KCNT' => '']];
                    }
                    return [['KCNT' => SmsV1Encoding::toOracleEncoding('代碼')]];
                }
                if (str_contains($sql, "B.RM_TYPE = '18'")) {
                    $rows = [];
                    for ($i = 1; $i <= 45; $i++) {
                        $rows[] = [
                            'MS30' => 'Y',
                            'RM01' => '115',
                            'RM02' => 'HA01',
                            'RM03' => sprintf('%06d', $i),
                            'RM09' => '01',
                            'MSID' => 'A' . sprintf('%09d', $i),
                            'T07_1' => '1151113',
                            'T07_2' => '093000',
                            'TLAND' => 'HA',
                            'LNAM' => SmsV1Encoding::toOracleEncoding('測試'),
                            'REPLY_MOBILE' => '1',
                            'MOBILE' => '0912' . sprintf('%06d', $i),
                            'MOBILE2' => '',
                            'REPLY_EMAIL' => '0',
                            'EMAIL' => '',
                            'EMAIL2' => '',
                        ];
                    }
                    return $rows;
                }
                if (str_contains($sql, 'SCRSMS_RM1215')) {
                    return [[
                        'MS30' => 'y',
                        'RM01' => '115',
                        'RM02' => 'HA01',
                        'RM03' => '009999',
                        'RM09' => '01',
                        'T07_1' => '1151113',
                        'T07_2' => '091000',
                        'TLAND' => 'HA',
                        'GOV_ID' => '',
                        'CC00' => 'C',
                        'CC48' => '0101',
                        'CC49' => '00010002',
                        'GOV_NAME' => SmsV1Encoding::toOracleEncoding('機關'),
                        'EMAIL' => 'g@gov.tw',
                        'APP_ID' => '',
                        'APP_SEQ' => '1',
                        'GOV_OID' => '',
                    ]];
                }
                return [];
            }
        };

        $fakeWrapper = new class($fakeDb) extends \OraDBWrapper {
            private $fakeDb;
            public function __construct($fakeDb) { $this->fakeDb = $fakeDb; }
            public function __destruct() {}
            public function reachable() { return true; }
            public function getDB() { return $this->fakeDb; }
        };

        $store = new SmsV1OracleStore($fakeWrapper);
        $res = $store->syncCases('1151113');

        $this->assertSame(45, $res['user_notifications_inserted']);
        $this->assertSame(1, $res['gov_notifications_inserted']);

        // Verify gov dedup SQL uses NVL + UNION ALL (no plain UNION)
        $govDedupQueries = array_values(array_filter(
            $fakeDb->executed,
            fn(array $e): bool => str_contains($e['sql'], 'APP_SEQ = :app_seq')
        ));
        $this->assertCount(1, $govDedupQueries);
        $govSql = $govDedupQueries[0]['sql'];
        $this->assertStringContainsString("NVL(GOV_ID, 'X') = NVL(:gov_key, 'X')", $govSql);
        $this->assertStringContainsString("NVL(APP_ID, 'X') = NVL(:app_id, 'X')", $govSql);
        $this->assertStringContainsString('UNION ALL', $govSql);
        $this->assertDoesNotMatchRegularExpression('/\bUNION\b(?!\s+ALL)/', $govSql);

        // Verify 46 inserts were chunked into 2 PL/SQL blocks (40 + 6) with exact per-chunk binds
        $plsqlExecs = array_values(array_filter($fakeDb->executed, fn(array $e): bool => str_starts_with($e['sql'], 'BEGIN')));
        $this->assertCount(2, $plsqlExecs);
        $this->assertSame(40, substr_count($plsqlExecs[0]['sql'], 'INSERT INTO SSMSMAIN'));
        $this->assertSame(6, substr_count($plsqlExecs[1]['sql'], 'INSERT INTO SSMSMAIN'));
        $this->assertCount(40 * 8, $plsqlExecs[0]['binds']);
        $this->assertCount(5 * 8 + 15, $plsqlExecs[1]['binds']);
    }
}
