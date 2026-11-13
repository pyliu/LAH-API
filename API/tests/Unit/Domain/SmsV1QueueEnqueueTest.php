<?php
declare(strict_types=1);

namespace Tests\Unit\Domain;

use PHPUnit\Framework\TestCase;
use SmsV1Encoding;
use SmsV1MockStore;
use SmsV1OracleStore;
use SmsV1Router;
use SmsV1Service;

class SmsV1QueueEnqueueTest extends TestCase {
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
        $dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'sms_v1_enqueue_test_' . uniqid('', true);
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

    public function testPostQueueEnqueueReturnsZeroCountsOnEmptyDb(): void {
        $store = new SmsV1MockStore($this->createTempDbPath());
        $router = new SmsV1Router(new SmsV1Service($store));

        $resEmpty = $router->dispatch('POST', '/queue/enqueue', [], '');
        $this->assertSame(200, $resEmpty['status']);
        $this->assertSame([
            'moved_to_wait_count' => 0,
            'deleted_from_main_count' => 0,
        ], $resEmpty['body']);

        $resEmptyJson = $router->dispatch('POST', '/api/v1/sms/queue/enqueue', [], '{}');
        $this->assertSame(200, $resEmptyJson['status']);
        $this->assertSame([
            'moved_to_wait_count' => 0,
            'deleted_from_main_count' => 0,
        ], $resEmptyJson['body']);
    }

    public function testPostQueueEnqueueReturns400ForInvalidPayloadAnd405ForGet(): void {
        $store = new SmsV1MockStore($this->createTempDbPath());
        $router = new SmsV1Router(new SmsV1Service($store));

        $invalidPayloads = [
            'not-json',
            '[true]',
            '123',
            '{"check_register_a": "true"}',
            '{"check_register_b": 1}',
            '{"check_register_c": null}',
            '{"check_register_d": []}',
        ];

        foreach ($invalidPayloads as $rawBody) {
            $res = $router->dispatch('POST', '/queue/enqueue', [], $rawBody);
            $this->assertSame(400, $res['status'], "Expected 400 for body: {$rawBody}");
            $this->assertSame('Bad Request', $res['body']['error']);
        }

        $res405 = $router->dispatch('GET', '/queue/enqueue', [], '');
        $this->assertSame(405, $res405['status']);
        $this->assertSame('Method Not Allowed', $res405['body']['error']);
    }

    public function testSyncThenEnqueueMovesGeneralAndGovRecordsToWaitAndDeletesFromMain(): void {
        $store = new SmsV1MockStore($this->createTempDbPath());
        $pdo = $store->getPdo();

        $pdo->exec("INSERT INTO SRKEYN (KCDE_1, KCDE_2, KCNT, KRMK) VALUES ('04', 'HA01', '桃資', '')");
        $pdo->exec("INSERT INTO SRKEYN (KCDE_1, KCDE_2, KCNT, KRMK) VALUES ('06', '01', '買賣', '')");
        $pdo->exec("INSERT INTO SRKEYN (KCDE_1, KCDE_2, KCNT, KRMK) VALUES ('48', '0101', '桃園段', '')");
        $pdo->exec("INSERT INTO SSMS_CODE04 (COD04) VALUES ('HA01')");
        $pdo->exec("INSERT INTO SSMS_CODE06 (COD06, ITEMS) VALUES ('01', '0'), ('01', '2')");

        // General case (SMS + Email)
        $pdo->exec("INSERT INTO SCRSMS (RM01, RM02, RM03, RM07_1, RM07_2, RM09, RM32)
                    VALUES ('115', 'HA01', '001230', '1151113', '093000', '01', '1')");
        $pdo->exec("INSERT INTO SCRSMS_RM1821 (RM01, RM02, RM03, RM_TYPE, RM_ID)
                    VALUES ('115', 'HA01', '001230', '18', 'A123456789')");
        $pdo->exec("INSERT INTO SSMS_USERINFO_M (LIDN, LNAM, REPLY_MOBILE, MOBILE, REPLY_EMAIL, EMAIL, SMS_FLAG)
                    VALUES ('A123456789', '王小明', '1', '0912345678', '1', 'user@example.com', 'Y')");

        // Gov case ('y', 2 emails)
        $pdo->exec("INSERT INTO SMS_GOVINFO_M (APP_SEQ, APP_ID, GOV_NAME, EMAIL, APP_STOP, GOV_OID)
                    VALUES ('1', 'GOV01', '工務局', 'g1@gov.tw;g2@gov.tw', 'N', '2.16.886.101')");
        $pdo->exec("INSERT INTO SSMS_GOVINFO_D (APP_SEQ, GOV_ID, CC00, CC48, CC49)
                    VALUES ('1', 'G01', 'C', '0101', '00010002')");
        $pdo->exec("INSERT INTO SCRSMS_RM1215 (RM01, RM02, RM03, RM00, RM11, RM1215)
                    VALUES ('115', 'HA01', '001230', 'C', '0101', '00010002')");

        $router = new SmsV1Router(new SmsV1Service($store));

        // Step 1: cases/sync writes 2 user + 2 gov = 4 rows into SSMSMAIN
        $syncRes = $router->dispatch('POST', '/cases/sync', [], '{"target_date_yyy_mm_dd":"1151113"}');
        $this->assertSame(200, $syncRes['status']);
        $this->assertSame(2, $syncRes['body']['user_notifications_inserted']);
        $this->assertSame(2, $syncRes['body']['gov_notifications_inserted']);

        // Capture expected MS_NOTE values in SSMSMAIN before enqueue
        $mainNotes = $pdo->query("SELECT MS14, MS_MAIL, MS30, MS_NOTE FROM SSMSMAIN ORDER BY MS30, MS14, MS_MAIL")->fetchAll(\PDO::FETCH_ASSOC);
        $this->assertCount(4, $mainNotes);

        // Step 2: queue/enqueue moves all 4 rows to SSMSWAIT and deletes from SSMSMAIN
        $enqueueRes = $router->dispatch('POST', '/queue/enqueue', [], '');
        $this->assertSame(200, $enqueueRes['status']);
        $this->assertSame([
            'moved_to_wait_count' => 4,
            'deleted_from_main_count' => 4,
        ], $enqueueRes['body']);

        // Verify SSMSMAIN is now empty
        $mainAfter = $router->dispatch('GET', '/records', ['table' => 'main'], '');
        $this->assertSame(0, $mainAfter['body']['count']);

        // Verify SSMSWAIT has all 4 rows with MS32='0', current ROC date/time, MS_NOTE, and gov 7 cols
        $waitRows = $pdo->query(
            "SELECT MS03, MS04_1, MS04_2, MS07_1, MS07_2, MS14, MS30, MS32, MS_TYPE, MS_MAIL, MS_NOTE,
                    APP_SEQ, APP_ID, GOV_ID, CC00, CC48, CC49, GOV_OID
             FROM SSMSWAIT
             ORDER BY MS30, MS14, MS_MAIL"
        )->fetchAll(\PDO::FETCH_ASSOC);
        $this->assertCount(4, $waitRows);

        $todayRoc = SmsV1Encoding::getRocDate();
        foreach ($waitRows as $idx => $waitRow) {
            $this->assertSame('0', $waitRow['MS32']);
            $this->assertSame('M', $waitRow['MS_TYPE']);
            $this->assertSame($todayRoc, $waitRow['MS07_1']);
            $this->assertMatchesRegularExpression('/^\d{6}$/', (string)$waitRow['MS07_2']);
            $this->assertSame($mainNotes[$idx]['MS_NOTE'], $waitRow['MS_NOTE']);
        }

        // Verify gov rows retained all 7 gov columns
        $govWaitRows = array_values(array_filter($waitRows, fn(array $r): bool => $r['MS30'] === 'y'));
        $this->assertCount(2, $govWaitRows);
        $this->assertSame(['g1@gov.tw', 'g2@gov.tw'], array_column($govWaitRows, 'MS_MAIL'));
        $this->assertSame('1', $govWaitRows[0]['APP_SEQ']);
        $this->assertSame('GOV01', $govWaitRows[0]['APP_ID']);
        $this->assertSame('G01', $govWaitRows[0]['GOV_ID']);
        $this->assertSame('C', $govWaitRows[0]['CC00']);
        $this->assertSame('0101', $govWaitRows[0]['CC48']);
        $this->assertSame('00010002', $govWaitRows[0]['CC49']);
        $this->assertSame('2.16.886.101', $govWaitRows[0]['GOV_OID']);

        // Step 3: Second enqueue is 0/0 and second sync deduplicates against SSMSWAIT
        $enqueueAgain = $router->dispatch('POST', '/queue/enqueue', [], '{}');
        $this->assertSame([
            'moved_to_wait_count' => 0,
            'deleted_from_main_count' => 0,
        ], $enqueueAgain['body']);

        $syncAgain = $router->dispatch('POST', '/cases/sync', [], '{"target_date_yyy_mm_dd":"1151113"}');
        $this->assertSame(0, $syncAgain['body']['user_notifications_inserted']);
        $this->assertSame(0, $syncAgain['body']['gov_notifications_inserted']);
    }

    public function testCheckRegisterFlagsAndLegacyTypesRS2Branches(): void {
        $store = new SmsV1MockStore($this->createTempDbPath());
        $pdo = $store->getPdo();

        // Seed SCRSMS for MS_TYPE='R' cases:
        // 000001: RM30='I' -> sType='I'
        // 000002: RM30='F', RM31='D' -> sType='D'
        // 000003: RM30='N', RM31='B' -> sType='X'
        // 000004: RM30='A' -> sType='0' (noop)
        $pdo->exec("INSERT INTO SCRSMS (RM01, RM02, RM03, RM30, RM31) VALUES
            ('115', 'HA01', '000001', 'I', ''),
            ('115', 'HA01', '000002', 'F', 'D'),
            ('115', 'HA01', '000003', 'N', 'B'),
            ('115', 'HA01', '000004', 'A', '')");

        // Seed SCMSMS for MS_TYPE='S' cases:
        // 000011: MM23=NULL, MM22='C' -> sType='C'
        // 000012: MM23='A' -> sType='F'
        $pdo->exec("INSERT INTO SCMSMS (MM01, MM02, MM03, MM22, MM23) VALUES
            ('115', 'HA02', '000011', 'C', NULL),
            ('115', 'HA02', '000012', '', 'A')");

        // Insert SSMSMAIN rows
        $pdo->exec("INSERT INTO SSMSMAIN (MS03, MS04_1, MS04_2, MS07_1, MS07_2, MS14, MS30, MS_TYPE, MS_NOTE) VALUES
            ('115', 'HA01', '000001', '1151113', '080000', '0911000001', '0', 'R', ''),
            ('115', 'HA01', '000002', '1151113', '080100', '0911000002', '0', 'R', ''),
            ('115', 'HA01', '000003', '1151113', '080200', '0911000003', '0', 'R', ''),
            ('115', 'HA01', '000004', '1151113', '080300', '0911000004', '0', 'R', ''),
            ('115', 'HA02', '000011', '1151113', '080400', '0922000011', '0', 'S', ''),
            ('115', 'HA02', '000012', '1151113', '080500', '0922000012', '0', 'S', ''),
            ('115', 'HA03', '000021', '1151113', '080600', '0933000021', '2', '2', '未辦繼承通知測試')");

        $router = new SmsV1Router(new SmsV1Service($store));

        // Pass 1: all check_register flags false ->
        //   000001 (I) noop, 000011 (C) noop, 000004 (0) noop
        //   000002 (D), 000003 (X), 000012 (F) deleted without inserting to SSMSWAIT
        //   000021 (type 2) moved to SSMSWAIT & deleted from SSMSMAIN
        $resFalse = $router->dispatch('POST', '/queue/enqueue', [], json_encode([
            'check_register_a' => false,
            'check_register_b' => false,
            'check_register_c' => false,
            'check_register_d' => false,
        ]));
        $this->assertSame(200, $resFalse['status']);
        $this->assertSame([
            'moved_to_wait_count' => 1,
            'deleted_from_main_count' => 4,
        ], $resFalse['body']);

        // Verify type '2' row in SSMSWAIT
        $waitAfterPass1 = $pdo->query("SELECT MS04_2, MS30, MS32, MS_TYPE, MS_NOTE FROM SSMSWAIT")->fetchAll(\PDO::FETCH_ASSOC);
        $this->assertCount(1, $waitAfterPass1);
        $this->assertSame('000021', $waitAfterPass1[0]['MS04_2']);
        $this->assertSame('2', $waitAfterPass1[0]['MS30']);
        $this->assertSame('0', $waitAfterPass1[0]['MS32']);
        $this->assertSame('未辦繼承通知測試', $waitAfterPass1[0]['MS_NOTE']);

        // Re-seed D & F rows for Pass 2 with flags true
        $pdo->exec("INSERT INTO SSMSMAIN (MS03, MS04_1, MS04_2, MS07_1, MS07_2, MS14, MS30, MS_TYPE, MS_NOTE) VALUES
            ('115', 'HA01', '000002', '1151113', '090100', '0911000002', '0', 'R', ''),
            ('115', 'HA02', '000012', '1151113', '090500', '0922000012', '0', 'S', '')");

        // Pass 2: all flags true ->
        //   000001 (I) inserted to wait + SSMSMAIN updated MS30='I' (not deleted)
        //   000011 (C) inserted to wait + SSMSMAIN updated MS30='C' (not deleted)
        //   000002 (D) inserted to wait + deleted
        //   000012 (F) inserted to wait + deleted
        //   000004 (0) still noop
        $resTrue = $router->dispatch('POST', '/queue/enqueue', [], '{}');
        $this->assertSame(200, $resTrue['status']);
        $this->assertSame([
            'moved_to_wait_count' => 4,
            'deleted_from_main_count' => 2,
        ], $resTrue['body']);

        $remainingMain = $pdo->query("SELECT MS04_2, MS30 FROM SSMSMAIN ORDER BY MS04_2")->fetchAll(\PDO::FETCH_ASSOC);
        $this->assertSame([
            ['MS04_2' => '000001', 'MS30' => 'I'],
            ['MS04_2' => '000004', 'MS30' => '0'],
            ['MS04_2' => '000011', 'MS30' => 'C'],
        ], $remainingMain);

        // Pass 3: 000001 now has MS30='I' so sType='I' must not re-fire; 000011 now has MS30='C' so sType='C' must not re-fire
        $resPass3 = $router->dispatch('POST', '/queue/enqueue', [], '{}');
        $this->assertSame([
            'moved_to_wait_count' => 0,
            'deleted_from_main_count' => 0,
        ], $resPass3['body']);
    }

    public function testEnqueueRollsBackMockTransactionOnDeleteFailure(): void {
        $store = new SmsV1MockStore($this->createTempDbPath());
        $pdo = $store->getPdo();

        $pdo->exec("INSERT INTO SSMSMAIN (MS03, MS04_1, MS04_2, MS07_1, MS07_2, MS14, MS30, MS_TYPE, MS_NOTE)
                    VALUES ('115', 'HA01', '009001', '1151113', '100000', '0912345678', 'Y', 'M', ' 測試回滾')");

        // Force failure on DELETE FROM SSMSMAIN after INSERT INTO SSMSWAIT has already executed
        $pdo->exec("CREATE TRIGGER fail_delete_main BEFORE DELETE ON SSMSMAIN
                    BEGIN
                        SELECT RAISE(ABORT, 'secret_delete_failure_token');
                    END;");

        $router = new SmsV1Router(new SmsV1Service($store));
        $res = $router->dispatch('POST', '/queue/enqueue', [], '{}');

        $this->assertSame(500, $res['status']);
        $this->assertSame('Internal Server Error', $res['body']['error']);
        $this->assertStringNotContainsString('secret_delete_failure_token', $res['body']['message']);

        // Atomicity check: SSMSWAIT must remain empty and SSMSMAIN must retain the original row
        $this->assertSame(0, (int)$pdo->query("SELECT COUNT(*) FROM SSMSWAIT")->fetchColumn());
        $this->assertSame(1, (int)$pdo->query("SELECT COUNT(*) FROM SSMSMAIN")->fetchColumn());
    }

    public function testOracleStoreEnqueueUsesBindVariablesCp950NoteAndSinglePlSqlTransaction(): void {
        $cp950UserNote = SmsV1Encoding::toOracleEncoding(' 異動即時通-115年桃資字001230號收件');
        $cp950GovNote = SmsV1Encoding::toOracleEncoding('桃園市桃園段0001-0002地號完成收件');

        $fakeDb = new class($cp950UserNote, $cp950GovNote) extends \OraDB {
            /** @var array<int, array{sql: string, binds: array<string, mixed>}> */
            public array $executed = [];
            private string $currentSql = '';
            /** @var array<string, mixed> */
            private array $currentBinds = [];
            private string $cp950UserNote;
            private string $cp950GovNote;

            public function __construct(string $cp950UserNote, string $cp950GovNote) {
                $this->cp950UserNote = $cp950UserNote;
                $this->cp950GovNote = $cp950GovNote;
            }
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
                if (str_contains($sql, 'FROM SSMSMAIN')) {
                    return [
                        [
                            'MS03' => '115', 'MS04_1' => 'HA01', 'MS04_2' => '001230',
                            'MS07_1' => '1151113', 'MS07_2' => '090000',
                            'MS14' => '0912345678', 'MS30' => 'Y', 'MS_TYPE' => 'M ',
                            'MS_NOTE' => $this->cp950UserNote, 'MS_MAIL' => null,
                            'APP_SEQ' => null, 'APP_ID' => null, 'GOV_ID' => null,
                            'CC00' => null, 'CC48' => null, 'CC49' => null, 'GOV_OID' => null,
                        ],
                        [
                            'MS03' => '115', 'MS04_1' => 'HA01', 'MS04_2' => '004010',
                            'MS07_1' => '1151113', 'MS07_2' => '091000',
                            'MS14' => null, 'MS30' => 'y', 'MS_TYPE' => 'M',
                            'MS_NOTE' => $this->cp950GovNote, 'MS_MAIL' => 'g1@gov.tw',
                            'APP_SEQ' => '1', 'APP_ID' => 'GOV01', 'GOV_ID' => 'G01',
                            'CC00' => 'C', 'CC48' => '0101', 'CC49' => '00010002', 'GOV_OID' => '2.16.886.101',
                        ],
                        [
                            'MS03' => '115', 'MS04_1' => 'HA01', 'MS04_2' => '000001',
                            'MS07_1' => '1151113', 'MS07_2' => '092000',
                            'MS14' => '0911111111', 'MS30' => '0', 'MS_TYPE' => 'R',
                            'MS_NOTE' => '', 'MS_MAIL' => null,
                            'APP_SEQ' => null, 'APP_ID' => null, 'GOV_ID' => null,
                            'CC00' => null, 'CC48' => null, 'CC49' => null, 'GOV_OID' => null,
                        ],
                        [
                            'MS03' => '115', 'MS04_1' => 'HA01', 'MS04_2' => '000002',
                            'MS07_1' => '1151113', 'MS07_2' => '093000',
                            'MS14' => '0922222222', 'MS30' => '0', 'MS_TYPE' => 'R',
                            'MS_NOTE' => '', 'MS_MAIL' => null,
                            'APP_SEQ' => null, 'APP_ID' => null, 'GOV_ID' => null,
                            'CC00' => null, 'CC48' => null, 'CC49' => null, 'GOV_OID' => null,
                        ],
                    ];
                }
                if (str_contains($sql, 'FROM SCRSMS')) {
                    $rm03 = (string)($this->currentBinds[':ms04_2'] ?? '');
                    if ($rm03 === '000001') {
                        return [['RM30' => 'I']];
                    }
                    if ($rm03 === '000002') {
                        return [['RM30' => 'X']];
                    }
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
        $res = $store->enqueueQueue(true, true, true, true);

        $this->assertSame([
            'moved_to_wait_count' => 3,
            'deleted_from_main_count' => 3,
        ], $res);

        $plsqlExecs = array_values(array_filter($fakeDb->executed, fn(array $e): bool => str_starts_with($e['sql'], 'BEGIN')));
        $this->assertCount(1, $plsqlExecs);
        $plsql = $plsqlExecs[0]['sql'];
        $binds = $plsqlExecs[0]['binds'];

        $normalizedPlsql = preg_replace('/\s+/', ' ', $plsql) ?? $plsql;
        $this->assertStringContainsString('INSERT INTO SSMSWAIT', $normalizedPlsql);
        $this->assertStringContainsString('UPDATE SSMSMAIN SET MS30', $normalizedPlsql);
        $this->assertStringContainsString('DELETE FROM SSMSMAIN', $normalizedPlsql);
        $this->assertStringContainsString('ROLLBACK;', $normalizedPlsql);
        $this->assertStringNotContainsString('0912345678', $plsql);
        $this->assertStringNotContainsString('g1@gov.tw', $plsql);
        $this->assertStringNotContainsString('2.16.886.101', $plsql);

        $this->assertContains($cp950UserNote, $binds);
        $this->assertContains($cp950GovNote, $binds);
        $this->assertContains('GOV01', $binds);
        $this->assertContains('2.16.886.101', $binds);
    }
}
