<?php
declare(strict_types=1);

namespace Tests\Unit\Domain;

use PHPUnit\Framework\TestCase;
use SmsV1Encoding;
use SmsV1MockStore;
use SmsV1OracleStore;
use SmsV1Router;
use SmsV1Service;

class SmsV1QueueReportTest extends TestCase {
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
        $dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'sms_v1_report_test_' . uniqid('', true);
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

    /**
     * @return array<string, mixed>
     */
    private function baseIdentity(array $overrides = []): array {
        return array_merge([
            'ms03' => '115',
            'ms04_1' => 'HA01',
            'ms04_2' => '001230',
            'ms30' => 'Y',
            'ms_type' => 'M',
            'ms14' => '0912345678',
            'ms_mail' => '',
            'gov_ext' => null,
        ], $overrides);
    }

    public function testPostQueueReportReturns405ForUnsupportedMethodsAnd400ForInvalidPayloads(): void {
        $store = new SmsV1MockStore($this->createTempDbPath());
        $router = new SmsV1Router(new SmsV1Service($store));

        foreach (['GET', 'PUT', 'DELETE'] as $method) {
            $res405 = $router->dispatch($method, '/queue/report', [], '');
            $this->assertSame(405, $res405['status'], "Expected 405 for {$method}");
            $this->assertSame('Method Not Allowed', $res405['body']['error']);
        }

        $invalidBodies = [
            '',
            'not-json',
            '[1, 2, 3]',
            '123',
            json_encode(['success' => true, 'msg' => 'hi', 'max_retries' => 3]), // missing identity
            json_encode(['identity' => 'invalid', 'success' => true, 'max_retries' => 3]),
            json_encode(['identity' => [], 'success' => true, 'max_retries' => 3]),
            json_encode(['identity' => $this->baseIdentity(), 'msg' => 'hi', 'max_retries' => 3]), // missing success
            json_encode(['identity' => $this->baseIdentity(), 'success' => 'true', 'max_retries' => 3]), // success not bool
            json_encode(['identity' => $this->baseIdentity(), 'success' => 1, 'max_retries' => 3]),
            json_encode(['identity' => $this->baseIdentity(['ms14' => '', 'ms_mail' => '']), 'success' => true, 'max_retries' => 3]), // both channels empty
            json_encode(['identity' => $this->baseIdentity(['ms30' => 'y', 'ms14' => '', 'ms_mail' => 'g@gov.tw', 'gov_ext' => null]), 'success' => true, 'max_retries' => 3]), // gov missing gov_ext
            json_encode(['identity' => $this->baseIdentity(['ms30' => 'z', 'ms14' => '', 'ms_mail' => 'g@gov.tw', 'gov_ext' => []]), 'success' => true, 'max_retries' => 3]),
            json_encode(['identity' => $this->baseIdentity(['ms30' => 'y', 'ms14' => '', 'ms_mail' => 'g@gov.tw', 'gov_ext' => ['app_seq' => '1']]), 'success' => true, 'max_retries' => 3]), // incomplete gov_ext
            json_encode(['identity' => $this->baseIdentity(['ms14' => 12345]), 'success' => true]),
            json_encode(['identity' => $this->baseIdentity(['ms_mail' => ['a@b.c']]), 'success' => true]),
            json_encode(['identity' => $this->baseIdentity(), 'success' => false, 'max_retries' => -1]), // negative max_retries
            json_encode(['identity' => $this->baseIdentity(), 'success' => false, 'max_retries' => '2']), // non-int max_retries
            json_encode(['identity' => $this->baseIdentity(), 'success' => false, 'max_retries' => 1.5]),
            json_encode(['identity' => $this->baseIdentity(), 'success' => false, 'max_retries' => true]),
            json_encode(['identity' => $this->baseIdentity(), 'success' => false]), // missing max_retries on failure
            json_encode(['identity' => $this->baseIdentity(), 'success' => true, 'max_retries' => -1]), // invalid max_retries even on success
            json_encode(['identity' => $this->baseIdentity(), 'success' => true, 'msg' => ['bad']]),
            json_encode(['identity' => $this->baseIdentity(), 'success' => true, 'error_desc' => 999]),
        ];

        foreach ($invalidBodies as $rawBody) {
            $res = $router->dispatch('POST', '/api/v1/sms/queue/report', [], (string)$rawBody);
            $this->assertSame(400, $res['status'], 'Expected 400 for body: ' . (string)$rawBody);
            $this->assertSame('Bad Request', $res['body']['error']);
        }
    }

    public function testEndToEndLifecycleSuccessForGeneralSmsEmailAndGovExt(): void {
        $store = new SmsV1MockStore($this->createTempDbPath());
        $pdo = $store->getPdo();

        $pdo->exec("INSERT INTO SRKEYN (KCDE_1, KCDE_2, KCNT, KRMK) VALUES ('04', 'HA01', '桃資', '')");
        $pdo->exec("INSERT INTO SRKEYN (KCDE_1, KCDE_2, KCNT, KRMK) VALUES ('06', '01', '買賣', '')");
        $pdo->exec("INSERT INTO SRKEYN (KCDE_1, KCDE_2, KCNT, KRMK) VALUES ('48', '0101', '桃園段', '')");
        $pdo->exec("INSERT INTO SSMS_CODE04 (COD04) VALUES ('HA01')");
        $pdo->exec("INSERT INTO SSMS_CODE06 (COD06, ITEMS) VALUES ('01', '0'), ('01', '2')");

        // General citizen case (SMS + Email)
        $pdo->exec("INSERT INTO SCRSMS (RM01, RM02, RM03, RM07_1, RM07_2, RM09, RM32)
                    VALUES ('115', 'HA01', '001230', '1151113', '093000', '01', '1')");
        $pdo->exec("INSERT INTO SCRSMS_RM1821 (RM01, RM02, RM03, RM_TYPE, RM_ID)
                    VALUES ('115', 'HA01', '001230', '18', 'A123456789')");
        $pdo->exec("INSERT INTO SSMS_USERINFO_M (LIDN, LNAM, REPLY_MOBILE, MOBILE, REPLY_EMAIL, EMAIL, SMS_FLAG)
                    VALUES ('A123456789', '王小明', '1', '0912345678', '1', 'user@example.com', 'Y')");

        // Gov case 1 ('y', non-empty GOV_ID and GOV_OID)
        $pdo->exec("INSERT INTO SMS_GOVINFO_M (APP_SEQ, APP_ID, GOV_NAME, EMAIL, APP_STOP, GOV_OID)
                    VALUES ('1', 'GOV01', '桃園市工務局', 'g1@gov.tw', 'N', '2.16.886.101')");
        $pdo->exec("INSERT INTO SSMS_GOVINFO_M (APP_SEQ, EMAIL_SUB) VALUES ('1', '【工輔法】機關收件通知')");
        $pdo->exec("INSERT INTO SSMS_GOVINFO_D (APP_SEQ, GOV_ID, CC00, CC48, CC49)
                    VALUES ('1', 'A123', 'C', '0101', '00010000')");
        $pdo->exec("INSERT INTO SCRSMS_RM1215 (RM01, RM02, RM03, RM00, RM11, RM1215)
                    VALUES ('115', 'HA01', '001230', 'C', '0101', '00010000')");

        // Gov case 2 ('z', empty GOV_ID and NULL GOV_OID to verify COALESCE(..., 'X'))
        $pdo->exec("INSERT INTO SMS_GOVINFO_M (APP_SEQ, APP_ID, GOV_NAME, EMAIL, APP_STOP, GOV_OID)
                    VALUES ('2', 'GOV02', '都發局', 'g2@gov.tw', 'N', NULL)");
        $pdo->exec("INSERT INTO SSMS_GOVINFO_M (APP_SEQ, EMAIL_SUB) VALUES ('2', '【工輔法】異動完成通知')");
        $pdo->exec("INSERT INTO SSMS_GOVINFO_D (APP_SEQ, GOV_ID, CC00, CC48, CC49)
                    VALUES ('2', '', 'F', '0101', '00020000')");
        $pdo->exec("INSERT INTO SCRSMS (RM01, RM02, RM03, RM07_1, RM07_2, RM09, RM32, RM56_1, RM56_2)
                    VALUES ('115', 'HA01', '001240', '1151113', '094000', '01', '1', '1151113', '100000')");
        $pdo->exec("INSERT INTO SRSINDX (IS03, IS04_1, IS04_2, IS00, IS01, IS09, IS48, IS49)
                    VALUES ('115', 'HA01', '001240', 'D', '001', 'X999', '0101', '00020000')");

        $router = new SmsV1Router(new SmsV1Service($store));

        // 1. cases/sync
        $syncRes = $router->dispatch('POST', '/cases/sync', [], '{"target_date_yyy_mm_dd":"1151113"}');
        $this->assertSame(200, $syncRes['status']);
        $this->assertSame(2, $syncRes['body']['user_notifications_inserted']);
        $this->assertSame(2, $syncRes['body']['gov_notifications_inserted']);

        // 2. queue/enqueue
        $enqueueRes = $router->dispatch('POST', '/queue/enqueue', [], '{}');
        $this->assertSame(200, $enqueueRes['status']);
        $this->assertSame(4, $enqueueRes['body']['moved_to_wait_count']);

        // 3. queue/pending
        $pendingRes = $router->dispatch('GET', '/queue/pending', [], '');
        $this->assertSame(200, $pendingRes['status']);
        $items = $pendingRes['body']['items'];
        $this->assertCount(4, $items);

        // 4. queue/report (all succeed)
        foreach ($items as $item) {
            $reportRes = $router->dispatch('POST', '/queue/report', [], json_encode([
                'identity' => $item['identity'],
                'success' => true,
                'msg' => $item['msg'],
                'max_retries' => 3,
                'error_desc' => null,
            ]));
            $this->assertSame(200, $reportRes['status']);
            $this->assertSame(['action' => 'logged_success_and_removed'], $reportRes['body']);
        }

        // Verify SSMSWAIT is empty and queue/pending is empty
        $this->assertSame(0, (int)$pdo->query('SELECT COUNT(*) FROM SSMSWAIT')->fetchColumn());
        $pendingAfter = $router->dispatch('GET', '/queue/pending', [], '');
        $this->assertSame([], $pendingAfter['body']['items']);

        // Verify SSMSLOG via GET /records?table=log
        $recordsRes = $router->dispatch('GET', '/records', ['table' => 'log', 'limit' => '20'], '');
        $this->assertSame(200, $recordsRes['status']);
        $this->assertSame(4, $recordsRes['body']['count']);
        $todayRoc = SmsV1Encoding::getRocDate();
        foreach ($recordsRes['body']['records'] as $rec) {
            $this->assertSame('S', $rec['ms31']);
            $this->assertSame('成功', $rec['ms31_label']);
            $this->assertSame($todayRoc, $rec['ms07_1']);
            $this->assertMatchesRegularExpression('/^\d{6}$/', $rec['ms07_2']);
        }

        // Verify leading space preserved on general SMS MS_NOTE in SSMSLOG
        $smsLog = $pdo->query("SELECT MS_NOTE FROM SSMSLOG WHERE MS14 = '0912345678'")->fetchColumn();
        $this->assertIsString($smsLog);
        $this->assertStringStartsWith(' 異動即時通-115年桃資字001230號', $smsLog);

        // Verify gov columns saved in SSMSLOG
        $govLog = $pdo->query("SELECT APP_SEQ, APP_ID, GOV_ID, CC00, CC48, CC49, GOV_OID FROM SSMSLOG WHERE MS30 = 'y'")->fetch(\PDO::FETCH_ASSOC);
        $this->assertSame([
            'APP_SEQ' => '1',
            'APP_ID' => 'GOV01',
            'GOV_ID' => 'A123',
            'CC00' => 'C',
            'CC48' => '0101',
            'CC49' => '00010000',
            'GOV_OID' => '2.16.886.101',
        ], $govLog);

        // 5. Re-reporting removed item returns 404 Not Found
        $notFoundRes = $router->dispatch('POST', '/queue/report', [], json_encode([
            'identity' => $items[0]['identity'],
            'success' => true,
            'msg' => $items[0]['msg'],
        ]));
        $this->assertSame(404, $notFoundRes['status']);
        $this->assertSame('Not Found', $notFoundRes['body']['error']);

        // 6. Re-syncing does not duplicate because records now live in SSMSLOG
        $resync = $router->dispatch('POST', '/cases/sync', [], '{"target_date_yyy_mm_dd":"1151113"}');
        $this->assertSame(0, $resync['body']['user_notifications_inserted']);
        $this->assertSame(0, $resync['body']['gov_notifications_inserted']);
    }

    public function testFailureRetryStateMachineIncrementsUntilMaxRetriesThenLogsFailureAndRemoves(): void {
        $store = new SmsV1MockStore($this->createTempDbPath());
        $pdo = $store->getPdo();

        $stmt = $pdo->prepare(
            "INSERT INTO SSMSWAIT (MS03, MS04_1, MS04_2, MS07_1, MS07_2, MS14, MS30, MS32, MS_TYPE, MS_MAIL, MS_NOTE,
                                   APP_SEQ, APP_ID, GOV_ID, CC00, CC48, CC49, GOV_OID)
             VALUES (:ms03, :ms04_1, :ms04_2, :ms07_1, :ms07_2, :ms14, :ms30, :ms32, :ms_type, :ms_mail, :ms_note,
                     :app_seq, :app_id, :gov_id, :cc00, :cc48, :cc49, :gov_oid)"
        );
        // 1) General SMS (MS32='0')
        $stmt->execute([
            ':ms03' => '115', ':ms04_1' => 'HA01', ':ms04_2' => '001230',
            ':ms07_1' => '1151113', ':ms07_2' => '090000',
            ':ms14' => '0912345678', ':ms30' => 'Y', ':ms32' => '0', ':ms_type' => 'M',
            ':ms_mail' => '', ':ms_note' => ' 異動即時通測試',
            ':app_seq' => null, ':app_id' => null, ':gov_id' => null,
            ':cc00' => null, ':cc48' => null, ':cc49' => null, ':gov_oid' => null,
        ]);
        // 2) General Email (MS32='0') for max_retries=0 immediate failure test
        $stmt->execute([
            ':ms03' => '115', ':ms04_1' => 'HA01', ':ms04_2' => '001231',
            ':ms07_1' => '1151113', ':ms07_2' => '090500',
            ':ms14' => '', ':ms30' => 'Z', ':ms32' => '0', ':ms_type' => 'M',
            ':ms_mail' => 'fail@example.com', ':ms_note' => '桃園地政 異動完成',
            ':app_seq' => null, ':app_id' => null, ':gov_id' => null,
            ':cc00' => null, ':cc48' => null, ':cc49' => null, ':gov_oid' => null,
        ]);
        // 3) Gov Email ('z') with NULL GOV_ID and '' GOV_OID
        $stmt->execute([
            ':ms03' => '115', ':ms04_1' => 'HA01', ':ms04_2' => '002020',
            ':ms07_1' => '1151113', ':ms07_2' => '091000',
            ':ms14' => '', ':ms30' => 'z', ':ms32' => '0', ':ms_type' => 'M',
            ':ms_mail' => 'gov@example.com', ':ms_note' => '桃園市桃園段0002-0000建號異動完成',
            ':app_seq' => '9', ':app_id' => 'GOV09', ':gov_id' => null,
            ':cc00' => 'F', ':cc48' => '0101', ':cc49' => '00020000', ':gov_oid' => '',
        ]);

        $router = new SmsV1Router(new SmsV1Service($store));

        // --- Case 1: max_retries = 2 (0 -> 1 -> 2 -> log 'F' & remove) ---
        $smsIdentity = $this->baseIdentity();

        // Failure #1: 0 < 2 => retry_incremented (retry_count = 1)
        $r1 = $router->dispatch('POST', '/queue/report', [], json_encode([
            'identity' => $smsIdentity,
            'success' => false,
            'msg' => ' 異動即時通測試',
            'max_retries' => 2,
            'error_desc' => 'gateway timeout',
        ]));
        $this->assertSame(200, $r1['status']);
        $this->assertSame(['action' => 'retry_incremented', 'retry_count' => 1], $r1['body']);
        $this->assertSame('1', $pdo->query("SELECT MS32 FROM SSMSWAIT WHERE MS04_2 = '001230'")->fetchColumn());
        $this->assertSame(0, (int)$pdo->query('SELECT COUNT(*) FROM SSMSLOG')->fetchColumn());

        // Failure #2: 1 < 2 => retry_incremented (retry_count = 2)
        $r2 = $router->dispatch('POST', '/queue/report', [], json_encode([
            'identity' => $smsIdentity,
            'success' => false,
            'msg' => ' 異動即時通測試',
            'max_retries' => 2,
            'error_desc' => 'gateway error',
        ]));
        $this->assertSame(200, $r2['status']);
        $this->assertSame(['action' => 'retry_incremented', 'retry_count' => 2], $r2['body']);
        $this->assertSame('2', $pdo->query("SELECT MS32 FROM SSMSWAIT WHERE MS04_2 = '001230'")->fetchColumn());
        $this->assertSame(0, (int)$pdo->query('SELECT COUNT(*) FROM SSMSLOG')->fetchColumn());

        // Failure #3: 2 >= 2 => logged_failure_and_removed
        $r3 = $router->dispatch('POST', '/queue/report', [], json_encode([
            'identity' => $smsIdentity,
            'success' => false,
            'msg' => ' 異動即時通測試',
            'max_retries' => 2,
            'error_desc' => 'max retries reached',
        ]));
        $this->assertSame(200, $r3['status']);
        $this->assertSame(['action' => 'logged_failure_and_removed'], $r3['body']);
        $this->assertSame(0, (int)$pdo->query("SELECT COUNT(*) FROM SSMSWAIT WHERE MS04_2 = '001230'")->fetchColumn());

        $logRow = $pdo->query("SELECT MS31, MS14, MS_NOTE FROM SSMSLOG WHERE MS04_2 = '001230'")->fetch(\PDO::FETCH_ASSOC);
        $this->assertSame('F', $logRow['MS31']);
        $this->assertSame('0912345678', $logRow['MS14']);
        $this->assertSame(' 異動即時通測試', $logRow['MS_NOTE']);

        // Failure #4: already removed => 404 Not Found
        $r4 = $router->dispatch('POST', '/queue/report', [], json_encode([
            'identity' => $smsIdentity,
            'success' => false,
            'msg' => ' 異動即時通測試',
            'max_retries' => 2,
        ]));
        $this->assertSame(404, $r4['status']);

        // --- Case 2: max_retries = 0 (0 >= 0 => immediate failure log & remove) ---
        $emailIdentity = $this->baseIdentity([
            'ms04_2' => '001231',
            'ms30' => 'Z',
            'ms14' => '',
            'ms_mail' => 'fail@example.com',
        ]);
        $rZero = $router->dispatch('POST', '/queue/report', [], json_encode([
            'identity' => $emailIdentity,
            'success' => false,
            'msg' => '桃園地政 異動完成',
            'max_retries' => 0,
        ]));
        $this->assertSame(200, $rZero['status']);
        $this->assertSame(['action' => 'logged_failure_and_removed'], $rZero['body']);
        $this->assertSame('F', $pdo->query("SELECT MS31 FROM SSMSLOG WHERE MS04_2 = '001231'")->fetchColumn());

        // --- Case 3: Gov retry & failure with empty gov_id/gov_oid ---
        $govIdentity = $this->baseIdentity([
            'ms04_2' => '002020',
            'ms30' => 'z',
            'ms14' => '',
            'ms_mail' => 'gov@example.com',
            'gov_ext' => [
                'app_seq' => '9',
                'app_id' => 'GOV09',
                'gov_id' => '',
                'cc00' => 'F',
                'cc48' => '0101',
                'cc49' => '00020000',
                'gov_oid' => null,
            ],
        ]);
        $g1 = $router->dispatch('POST', '/queue/report', [], json_encode([
            'identity' => $govIdentity,
            'success' => false,
            'msg' => '桃園市桃園段0002-0000建號異動完成',
            'max_retries' => 1,
        ]));
        $this->assertSame(200, $g1['status']);
        $this->assertSame(['action' => 'retry_incremented', 'retry_count' => 1], $g1['body']);

        $g2 = $router->dispatch('POST', '/queue/report', [], json_encode([
            'identity' => $govIdentity,
            'success' => false,
            'msg' => '桃園市桃園段0002-0000建號異動完成',
            'max_retries' => 1,
        ]));
        $this->assertSame(200, $g2['status']);
        $this->assertSame(['action' => 'logged_failure_and_removed'], $g2['body']);

        // Verify via GET /records?table=log
        $recordsRes = $router->dispatch('GET', '/records', ['table' => 'log'], '');
        $this->assertSame(200, $recordsRes['status']);
        $this->assertSame(3, $recordsRes['body']['count']);
        foreach ($recordsRes['body']['records'] as $rec) {
            $this->assertSame('F', $rec['ms31']);
            $this->assertSame('失敗', $rec['ms31_label']);
        }
    }

    public function testMockStoreRollsBackTransactionWhenDeleteWaitFails(): void {
        $store = new SmsV1MockStore($this->createTempDbPath());
        $pdo = $store->getPdo();

        $pdo->exec("INSERT INTO SSMSWAIT (MS03, MS04_1, MS04_2, MS07_1, MS07_2, MS14, MS30, MS32, MS_TYPE, MS_MAIL, MS_NOTE)
                    VALUES ('115', 'HA01', '009001', '1151113', '100000', '0912345678', 'Y', '0', 'M', '', ' 測試回滾')");

        $pdo->exec("CREATE TRIGGER fail_delete_wait BEFORE DELETE ON SSMSWAIT
                    BEGIN
                        SELECT RAISE(ABORT, 'secret_wait_delete_failure');
                    END;");

        $router = new SmsV1Router(new SmsV1Service($store));
        $res = $router->dispatch('POST', '/queue/report', [], json_encode([
            'identity' => $this->baseIdentity(['ms04_2' => '009001']),
            'success' => true,
            'msg' => ' 測試回滾',
            'max_retries' => 3,
        ]));

        $this->assertSame(500, $res['status']);
        $this->assertSame('Internal Server Error', $res['body']['error']);
        $this->assertStringNotContainsString('secret_wait_delete_failure', $res['body']['message']);

        // Atomicity: SSMSLOG must have 0 rows and SSMSWAIT must keep the original row
        $this->assertSame(0, (int)$pdo->query('SELECT COUNT(*) FROM SSMSLOG')->fetchColumn());
        $this->assertSame(1, (int)$pdo->query('SELECT COUNT(*) FROM SSMSWAIT')->fetchColumn());
    }

    public function testOracleStoreReportUsesPlSqlAtomicBlockCp950NoteAndGovNvl(): void {
        $fakeDb = new class extends \OraDB {
            /** @var array<int, array{sql: string, binds: array<string, mixed>}> */
            public array $executed = [];
            /** @var array<int, array<string, mixed>> */
            public array $nextWaitRows = [];
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
                if (str_contains($this->currentSql, 'FROM SSMSWAIT')) {
                    return $this->nextWaitRows;
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
        $router = new SmsV1Router(new SmsV1Service($store));

        // 1) General SMS success -> single PL/SQL block (INSERT SSMSLOG MS31='S' with CP950 MS_NOTE + DELETE SSMSWAIT)
        $fakeDb->nextWaitRows = [['MS32' => '0']];
        $fakeDb->executed = [];
        $utf8Msg = ' 異動即時通-115年桃資字001230號完成收件';
        $resSuccess = $router->dispatch('POST', '/queue/report', [], json_encode([
            'identity' => $this->baseIdentity(),
            'success' => true,
            'msg' => $utf8Msg,
            'max_retries' => 3,
        ]));
        $this->assertSame(200, $resSuccess['status']);
        $this->assertSame(['action' => 'logged_success_and_removed'], $resSuccess['body']);
        $this->assertCount(2, $fakeDb->executed);
        $this->assertStringContainsString('SELECT MS32 FROM SSMSWAIT', $fakeDb->executed[0]['sql']);

        $plsql = $fakeDb->executed[1]['sql'];
        $binds = $fakeDb->executed[1]['binds'];
        $this->assertStringStartsWith('BEGIN', $plsql);
        $this->assertStringContainsString('EXCEPTION', $plsql);
        $this->assertStringContainsString('ROLLBACK;', $plsql);
        $this->assertStringContainsString('INSERT INTO SSMSLOG', $plsql);
        $this->assertStringContainsString('DELETE FROM SSMSWAIT', $plsql);
        $this->assertSame('S', $binds[':ms31']);
        $this->assertSame(SmsV1Encoding::toOracleEncoding($utf8Msg), $binds[':ms_note']);
        $this->assertSame('0912345678', $binds[':ms14']);
        $this->assertMatchesRegularExpression('/^\d{7}$/', (string)$binds[':ms07_1']);
        $this->assertMatchesRegularExpression('/^\d{6}$/', (string)$binds[':ms07_2']);

        // 2) Gov failure with MS32='1' < max_retries=3 -> UPDATE MS32='2' + NVL(GOV_ID,'X') / NVL(GOV_OID,'X')
        $fakeDb->nextWaitRows = [['MS32' => '1 ']];
        $fakeDb->executed = [];
        $govIdentity = $this->baseIdentity([
            'ms04_2' => '002010',
            'ms30' => 'y',
            'ms14' => '',
            'ms_mail' => 'gov@example.com',
            'gov_ext' => [
                'app_seq' => '1',
                'app_id' => 'GOV01',
                'gov_id' => '',
                'cc00' => 'C',
                'cc48' => '0101',
                'cc49' => '00010000',
                'gov_oid' => null,
            ],
        ]);
        $resRetry = $router->dispatch('POST', '/queue/report', [], json_encode([
            'identity' => $govIdentity,
            'success' => false,
            'msg' => '桃園市桃園段0001-0000地號',
            'max_retries' => 3,
        ]));
        $this->assertSame(200, $resRetry['status']);
        $this->assertSame(['action' => 'retry_incremented', 'retry_count' => 2], $resRetry['body']);
        $this->assertCount(2, $fakeDb->executed);
        $this->assertStringContainsString("NVL(GOV_ID, 'X') = NVL(:gov_id, 'X')", $fakeDb->executed[0]['sql']);
        $this->assertStringContainsString("NVL(GOV_OID, 'X') = NVL(:gov_oid, 'X')", $fakeDb->executed[0]['sql']);
        $this->assertStringContainsString('UPDATE SSMSWAIT SET MS32 = :next_ms32', $fakeDb->executed[1]['sql']);
        $this->assertSame('2', $fakeDb->executed[1]['binds'][':next_ms32']);

        // 3) Gov failure with MS32='2' >= max_retries=2 -> PL/SQL block INSERT SSMSLOG(MS31='F' + 7 gov cols) + DELETE SSMSWAIT
        $fakeDb->nextWaitRows = [['MS32' => '2']];
        $fakeDb->executed = [];
        $govFailMsg = '桃園市桃園段0001-0000地號通知';
        $resGovFail = $router->dispatch('POST', '/queue/report', [], json_encode([
            'identity' => $govIdentity,
            'success' => false,
            'msg' => $govFailMsg,
            'max_retries' => 2,
        ]));
        $this->assertSame(200, $resGovFail['status']);
        $this->assertSame(['action' => 'logged_failure_and_removed'], $resGovFail['body']);
        $this->assertCount(2, $fakeDb->executed);
        $govPlsql = $fakeDb->executed[1]['sql'];
        $govBinds = $fakeDb->executed[1]['binds'];
        $this->assertStringContainsString('INSERT INTO SSMSLOG', $govPlsql);
        $this->assertStringContainsString('APP_SEQ, APP_ID, GOV_ID, CC00, CC48, CC49, GOV_OID', $govPlsql);
        $this->assertStringContainsString('DELETE FROM SSMSWAIT', $govPlsql);
        $this->assertSame('F', $govBinds[':ms31']);
        $this->assertSame(SmsV1Encoding::toOracleEncoding($govFailMsg), $govBinds[':ms_note']);
        $this->assertSame('GOV01', $govBinds[':app_id']);

        // 4) Not found in Oracle SSMSWAIT -> 404 without issuing DML
        $fakeDb->nextWaitRows = [];
        $fakeDb->executed = [];
        $resMissing = $router->dispatch('POST', '/queue/report', [], json_encode([
            'identity' => $this->baseIdentity(),
            'success' => true,
            'msg' => $utf8Msg,
        ]));
        $this->assertSame(404, $resMissing['status']);
        $this->assertSame('Not Found', $resMissing['body']['error']);
        $this->assertCount(1, $fakeDb->executed);
    }
}
