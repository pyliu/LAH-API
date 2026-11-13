<?php
declare(strict_types=1);

namespace Tests\Unit\Domain;

use PHPUnit\Framework\TestCase;
use SmsV1Encoding;
use SmsV1MockStore;
use SmsV1OracleStore;
use SmsV1Router;
use SmsV1Service;

class SmsV1QueuePendingTest extends TestCase {
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
        $dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'sms_v1_pending_test_' . uniqid('', true);
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

    public function testGetQueuePendingReturnsEmptyItemsWithSiteIdAnd405ForNonGet(): void {
        $store = new SmsV1MockStore($this->createTempDbPath());
        $router = new SmsV1Router(new SmsV1Service($store));

        $res = $router->dispatch('GET', '/queue/pending', [], '');
        $this->assertSame(200, $res['status']);
        $this->assertSame([
            'site_id' => 'HA',
            'items' => [],
        ], $res['body']);

        $resAliased = $router->dispatch('GET', '/api/v1/sms/queue/pending', [], '');
        $this->assertSame(200, $resAliased['status']);
        $this->assertSame([
            'site_id' => 'HA',
            'items' => [],
        ], $resAliased['body']);

        foreach (['POST', 'PUT', 'DELETE'] as $method) {
            $res405 = $router->dispatch($method, '/queue/pending', [], '');
            $this->assertSame(405, $res405['status'], "Expected 405 for {$method}");
            $this->assertSame('Method Not Allowed', $res405['body']['error']);
        }
    }

    public function testSyncEnqueuePendingPipelineForGeneralCitizenSmsEmailAndMx(): void {
        $store = new SmsV1MockStore($this->createTempDbPath());
        $pdo = $store->getPdo();

        $pdo->exec("INSERT INTO SRKEYN (KCDE_1, KCDE_2, KCNT, KRMK) VALUES ('04', 'HA01', '桃資', '')");
        $pdo->exec("INSERT INTO SRKEYN (KCDE_1, KCDE_2, KCNT, KRMK) VALUES ('06', '01', '買賣', '')");
        $pdo->exec("INSERT INTO SSMS_CODE04 (COD04) VALUES ('HA01')");
        $pdo->exec("INSERT INTO SSMS_CODE06 (COD06, ITEMS) VALUES ('01', '0')");

        // 1) 收件通知 (Y) - 同時有手機與 Email
        $pdo->exec("INSERT INTO SCRSMS (RM01, RM02, RM03, RM07_1, RM07_2, RM09, RM32)
                    VALUES ('115', 'HA01', '001230', '1151113', '093000', '01', '1')");
        $pdo->exec("INSERT INTO SCRSMS_RM1821 (RM01, RM02, RM03, RM_TYPE, RM_ID)
                    VALUES ('115', 'HA01', '001230', '18', 'A123456789')");
        $pdo->exec("INSERT INTO SSMS_USERINFO_M (LIDN, LNAM, REPLY_MOBILE, MOBILE, REPLY_EMAIL, EMAIL, SMS_FLAG)
                    VALUES ('A123456789', '王小明', '1', '0912345678', '1', 'user@example.com', 'Y')");

        // 2) 異動完成通知 (Z) - 僅 Email
        $pdo->exec("INSERT INTO SCRSMS (RM01, RM02, RM03, RM07_1, RM07_2, RM09, RM32, RM56_1, RM56_2)
                    VALUES ('115', 'HA01', '001240', '1151113', '094000', '01', '1', '1151113', '100000')");
        $pdo->exec("INSERT INTO SRSINDX (IS03, IS04_1, IS04_2, IS00, IS01, IS09, IS48, IS49)
                    VALUES ('115', 'HA01', '001240', 'B', '001', 'B223456789', '0101', '00010000')");
        $pdo->exec("INSERT INTO SSMS_USERINFO_M (LIDN, LNAM, REPLY_MOBILE, MOBILE, REPLY_EMAIL, EMAIL, SMS_FLAG)
                    VALUES ('B223456789', '林小華', '0', '', '1', 'done@example.com', 'Y')");

        $router = new SmsV1Router(new SmsV1Service($store));

        // Step 1: cases/sync
        $syncRes = $router->dispatch('POST', '/cases/sync', [], json_encode(['target_date_yyy_mm_dd' => '1151113']));
        $this->assertSame(200, $syncRes['status']);
        $this->assertSame(3, $syncRes['body']['user_notifications_inserted']);

        // Step 2: queue/enqueue
        $enqueueRes = $router->dispatch('POST', '/queue/enqueue', [], '{}');
        $this->assertSame(200, $enqueueRes['status']);
        $this->assertSame(3, $enqueueRes['body']['moved_to_wait_count']);

        // Add an M/X record directly into SSMSWAIT to verify X subject rule too
        $pdo->exec("INSERT INTO SSMSWAIT (MS03, MS04_1, MS04_2, MS07_1, MS07_2, MS14, MS30, MS32, MS_TYPE, MS_MAIL, MS_NOTE)
                    VALUES ('115', 'HA01', '001232', '1151113', '235959', '0933445566', 'X', '2', 'M', '', ' 申請地籍異動即時通服務完成')");

        // Step 3: queue/pending
        $pendingRes = $router->dispatch('GET', '/queue/pending', [], '');
        $this->assertSame(200, $pendingRes['status']);
        $this->assertSame('HA', $pendingRes['body']['site_id']);
        $items = $pendingRes['body']['items'];
        $this->assertCount(4, $items);

        // Find SMS Y item
        $smsY = array_values(array_filter($items, fn(array $i): bool => $i['identity']['ms04_2'] === '001230' && $i['channel'] === 'sms'));
        $this->assertCount(1, $smsY);
        $this->assertSame([
            'identity' => [
                'ms03' => '115',
                'ms04_1' => 'HA01',
                'ms04_2' => '001230',
                'ms30' => 'Y',
                'ms_type' => 'M',
                'ms14' => '0912345678',
                'ms_mail' => '',
                'gov_ext' => null,
            ],
            'retry_count' => 0,
            'channel' => 'sms',
            'recipient' => '0912345678',
            'subject' => '有關您所持有不動產地籍異動1事',
            'msg' => ' 異動即時通-115年桃資字001230號買賣案已於115年11月13日完成收件，如有疑義請向地政事務所洽詢。',
        ], $smsY[0]);

        // Find Email Y item
        $emailY = array_values(array_filter($items, fn(array $i): bool => $i['identity']['ms04_2'] === '001230' && $i['channel'] === 'email'));
        $this->assertCount(1, $emailY);
        $this->assertSame('email', $emailY[0]['channel']);
        $this->assertSame('user@example.com', $emailY[0]['recipient']);
        $this->assertSame('有關您所持有不動產地籍異動1事', $emailY[0]['subject']);
        $this->assertSame('桃園地政 異動即時通-115年桃資字001230號買賣案已於115年11月13日完成收件，如有疑義請向地政事務所洽詢。', $emailY[0]['msg']);

        // Find Email Z item
        $emailZ = array_values(array_filter($items, fn(array $i): bool => $i['identity']['ms04_2'] === '001240'));
        $this->assertCount(1, $emailZ);
        $this->assertSame('email', $emailZ[0]['channel']);
        $this->assertSame('done@example.com', $emailZ[0]['recipient']);
        $this->assertNull($emailZ[0]['identity']['gov_ext']);
        $this->assertSame('有關您所持有不動產地籍異動1事', $emailZ[0]['subject']);
        $this->assertSame('桃園地政 異動即時通-115年桃資字001240號買賣案已於115年11月13日異動完成，如有疑義請向地政事務所洽詢。', $emailZ[0]['msg']);

        // Find SMS X item (retry_count = 2)
        $smsX = array_values(array_filter($items, fn(array $i): bool => $i['identity']['ms04_2'] === '001232'));
        $this->assertCount(1, $smsX);
        $this->assertSame(2, $smsX[0]['retry_count']);
        $this->assertSame('sms', $smsX[0]['channel']);
        $this->assertSame('0933445566', $smsX[0]['recipient']);
        $this->assertSame('有關您申請地籍異動即時通服務1事', $smsX[0]['subject']);
        $this->assertSame(' 申請地籍異動即時通服務完成', $smsX[0]['msg']);
    }

    public function testSyncEnqueuePendingPipelineForGovCasesYAndZ(): void {
        $store = new SmsV1MockStore($this->createTempDbPath());
        $pdo = $store->getPdo();

        // Confirm SCMSDS and SSYSAUTH1 exist and SMS_GOVINFO_M has EMAIL_SUB
        $tables = $pdo->query("SELECT name FROM sqlite_master WHERE type = 'table'")->fetchAll(\PDO::FETCH_COLUMN);
        $this->assertContains('SCMSDS', $tables);
        $this->assertContains('SSYSAUTH1', $tables);

        $pdo->exec("INSERT INTO SRKEYN (KCDE_1, KCDE_2, KCNT, KRMK) VALUES ('04', 'HA01', '桃資', '')");
        $pdo->exec("INSERT INTO SRKEYN (KCDE_1, KCDE_2, KCNT, KRMK) VALUES ('06', '01', '買賣', '')");
        $pdo->exec("INSERT INTO SRKEYN (KCDE_1, KCDE_2, KCNT, KRMK) VALUES ('48', '0101', '桃園段', '')");
        $pdo->exec("INSERT INTO SSMS_CODE06 (COD06, ITEMS) VALUES ('01', '2')");

        // 機關 1 ('y') -> EMAIL_SUB 在 SSMS_GOVINFO_M
        $pdo->exec("INSERT INTO SMS_GOVINFO_M (APP_SEQ, APP_ID, GOV_NAME, EMAIL, APP_STOP, GOV_OID)
                    VALUES ('1', 'GOV01', '桃園市工務局', 'g1@gov.tw', 'N', '2.16.886.101')");
        $pdo->exec("INSERT INTO SSMS_GOVINFO_M (APP_SEQ, EMAIL_SUB)
                    VALUES ('1', '【工輔法】機關收件通知')");
        $pdo->exec("INSERT INTO SSMS_GOVINFO_D (APP_SEQ, GOV_ID, CC00, CC48, CC49)
                    VALUES ('1', 'A123', 'C', '0101', '00010000')");
        $pdo->exec("INSERT INTO SCRSMS (RM01, RM02, RM03, RM07_1, RM07_2, RM09, RM32)
                    VALUES ('115', 'HA01', '002010', '1151113', '083000', '01', '1')");
        $pdo->exec("INSERT INTO SCRSMS_RM1215 (RM01, RM02, RM03, RM00, RM11, RM1215)
                    VALUES ('115', 'HA01', '002010', 'C', '0101', '00010000')");

        // 機關 2 ('z') -> EMAIL_SUB 在 SMS_GOVINFO_M（相容測試）
        $pdo->exec("INSERT INTO SMS_GOVINFO_M (APP_SEQ, APP_ID, GOV_NAME, EMAIL, APP_STOP, GOV_OID, EMAIL_SUB)
                    VALUES ('2', 'GOV02', '都發局', 'g2@gov.tw', 'N', '2.16.886.102', '【工輔法】異動完成通知')");
        $pdo->exec("INSERT INTO SSMS_GOVINFO_D (APP_SEQ, GOV_ID, CC00, CC48, CC49)
                    VALUES ('2', 'B456', 'F', '0101', '00020000')");
        $pdo->exec("INSERT INTO SCRSMS (RM01, RM02, RM03, RM07_1, RM07_2, RM09, RM32, RM56_1, RM56_2)
                    VALUES ('115', 'HA01', '002020', '1151113', '090000', '01', '1', '1151113', '110000')");
        $pdo->exec("INSERT INTO SRSINDX (IS03, IS04_1, IS04_2, IS00, IS01, IS09, IS48, IS49)
                    VALUES ('115', 'HA01', '002020', 'D', '001', 'X999', '0101', '00020000')");

        $router = new SmsV1Router(new SmsV1Service($store));
        $syncRes = $router->dispatch('POST', '/cases/sync', [], json_encode(['target_date_yyy_mm_dd' => '1151113']));
        $this->assertSame(200, $syncRes['status']);
        $this->assertSame(2, $syncRes['body']['gov_notifications_inserted']);

        $enqueueRes = $router->dispatch('POST', '/queue/enqueue', [], '{}');
        $this->assertSame(200, $enqueueRes['status']);
        $this->assertSame(2, $enqueueRes['body']['moved_to_wait_count']);

        $pendingRes = $router->dispatch('GET', '/queue/pending', [], '');
        $this->assertSame(200, $pendingRes['status']);
        $items = $pendingRes['body']['items'];
        $this->assertCount(2, $items);

        $govY = array_values(array_filter($items, fn(array $i): bool => $i['identity']['ms30'] === 'y'));
        $this->assertCount(1, $govY);
        $this->assertSame([
            'identity' => [
                'ms03' => '115',
                'ms04_1' => 'HA01',
                'ms04_2' => '002010',
                'ms30' => 'y',
                'ms_type' => 'M',
                'ms14' => '',
                'ms_mail' => 'g1@gov.tw',
                'gov_ext' => [
                    'app_seq' => '1',
                    'app_id' => 'GOV01',
                    'gov_id' => 'A123',
                    'cc00' => 'C',
                    'cc48' => '0101',
                    'cc49' => '00010000',
                    'gov_oid' => '2.16.886.101',
                ],
            ],
            'retry_count' => 0,
            'channel' => 'email',
            'recipient' => 'g1@gov.tw',
            'subject' => '【工輔法】機關收件通知',
            'msg' => '桃園市桃園段0001-0000地號已於115年桃資字002010號買賣案115年11月13日完成收件，如有任何問題請逕向桃園地政事務所洽詢。',
        ], $govY[0]);

        $govZ = array_values(array_filter($items, fn(array $i): bool => $i['identity']['ms30'] === 'z'));
        $this->assertCount(1, $govZ);
        $this->assertSame('【工輔法】異動完成通知', $govZ[0]['subject']);
        $this->assertSame('2.16.886.102', $govZ[0]['identity']['gov_ext']['gov_oid']);
        $this->assertSame('00020-000建號', substr($govZ[0]['msg'], strlen('桃園市桃園段'), strlen('00020-000建號')));
    }

    public function testPendingQueueHandlesTypeOTypeWOrderingAndExcludesOtherTypes(): void {
        $store = new SmsV1MockStore($this->createTempDbPath());
        $pdo = $store->getPdo();

        $stmt = $pdo->prepare(
            "INSERT INTO SSMSWAIT (MS03, MS04_1, MS04_2, MS07_1, MS07_2, MS14, MS30, MS32, MS_TYPE, MS_MAIL, MS_NOTE)
             VALUES (:ms03, :ms04_1, :ms04_2, :ms07_1, :ms07_2, :ms14, :ms30, :ms32, :ms_type, :ms_mail, :ms_note)"
        );
        // Newer Type W
        $stmt->execute([
            ':ms03' => '115', ':ms04_1' => 'HA01', ':ms04_2' => '000300',
            ':ms07_1' => '1151113', ':ms07_2' => '120000',
            ':ms14' => '0911222333', ':ms30' => 'W', ':ms32' => '1', ':ms_type' => 'W',
            ':ms_mail' => '', ':ms_note' => '您的指定送達處所已建檔完成',
        ]);
        // Older Type O (MS30='7', # split + <br> newline)
        $stmt->execute([
            ':ms03' => '115', ':ms04_1' => 'HA01', ':ms04_2' => '000100',
            ':ms07_1' => '1151113', ':ms07_2' => '080000',
            ':ms14' => '', ':ms30' => '7', ':ms32' => '0', ':ms_type' => 'O',
            ':ms_mail' => 'other@example.com', ':ms_note' => '調案通知主旨#第一行內容<br>第二行內容<br>第三行',
        ]);
        // Middle Type O (MS30='8')
        $stmt->execute([
            ':ms03' => '115', ':ms04_1' => 'HA01', ':ms04_2' => '000200',
            ':ms07_1' => '1151113', ':ms07_2' => '100000',
            ':ms14' => '0922333444', ':ms30' => '8', ':ms32' => '0', ':ms_type' => 'O',
            ':ms_mail' => '', ':ms_note' => '異議連繫#請於三日內聯繫<br>承辦人分機101',
        ]);
        // Excluded legacy types ('R', 'S', '2', NULL)
        $stmt->execute([
            ':ms03' => '115', ':ms04_1' => 'HA01', ':ms04_2' => '000999',
            ':ms07_1' => '1151113', ':ms07_2' => '070000',
            ':ms14' => '0999888777', ':ms30' => 'I', ':ms32' => '0', ':ms_type' => 'R',
            ':ms_mail' => '', ':ms_note' => '不應出現',
        ]);

        $router = new SmsV1Router(new SmsV1Service($store));
        $res = $router->dispatch('GET', '/queue/pending', [], '');

        $this->assertSame(200, $res['status']);
        $items = $res['body']['items'];
        $this->assertCount(3, $items);

        // Verify ORDER BY MS07_1, MS07_2 ascending
        $this->assertSame(['000100', '000200', '000300'], array_map(fn(array $i): string => $i['identity']['ms04_2'], $items));

        // O / 7
        $this->assertSame('email', $items[0]['channel']);
        $this->assertSame('other@example.com', $items[0]['recipient']);
        $this->assertSame('調案通知主旨', $items[0]['subject']);
        $this->assertSame("第一行內容\n第二行內容\n第三行", $items[0]['msg']);

        // O / 8
        $this->assertSame('sms', $items[1]['channel']);
        $this->assertSame('異議連繫', $items[1]['subject']);
        $this->assertSame("請於三日內聯繫\n承辦人分機101", $items[1]['msg']);

        // W
        $this->assertSame(1, $items[2]['retry_count']);
        $this->assertSame('sms', $items[2]['channel']);
        $this->assertSame('指定送達處所建檔完成通知', $items[2]['subject']);
        $this->assertSame('您的指定送達處所已建檔完成', $items[2]['msg']);
    }

    public function testOracleStoreGetPendingQueueDecodesCp950AndAssemblesAllTypes(): void {
        $fakeDb = new class extends \OraDB {
            /** @var array<int, array{sql: string, binds: array<string, mixed>}> */
            public array $queries = [];
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
                $this->queries[] = [
                    'sql' => $this->currentSql,
                    'binds' => $this->currentBinds,
                ];
                return true;
            }
            public function fetchAll($raw = false) {
                $sql = $this->currentSql;
                if (str_contains($sql, "KCDE_1 = '55' AND KCDE_2 = '01'")) {
                    return [
                        [
                            'KCDE_1' => '55',
                            'KCDE_2' => '01',
                            'KCNT' => SmsV1Encoding::toOracleEncoding('桃園地政事務所 '),
                            'KRMK' => 'HA  ',
                        ],
                        [
                            'KCDE_1' => '66',
                            'KCDE_2' => '01',
                            'KCNT' => SmsV1Encoding::toOracleEncoding('桃園市 '),
                            'KRMK' => '',
                        ],
                    ];
                }
                if (str_contains($sql, 'FROM SSMSWAIT')) {
                    return [
                        [
                            'MS03' => '115 ',
                            'MS04_1' => 'HA01',
                            'MS04_2' => '001230',
                            'MS07_1' => '1151113',
                            'MS07_2' => '090000',
                            'MS14' => '0912345678 ',
                            'MS30' => 'Y ',
                            'MS32' => '0',
                            'MS_TYPE' => 'M ',
                            'MS_MAIL' => null,
                            'MS_NOTE' => SmsV1Encoding::toOracleEncoding(' 異動即時通-115年桃資字001230號買賣案收件   '),
                            'APP_SEQ' => null,
                            'APP_ID' => null,
                            'GOV_ID' => null,
                            'CC00' => null,
                            'CC48' => null,
                            'CC49' => null,
                            'GOV_OID' => null,
                        ],
                        [
                            'MS03' => '115',
                            'MS04_1' => 'HA01',
                            'MS04_2' => '002010',
                            'MS07_1' => '1151113',
                            'MS07_2' => '091000',
                            'MS14' => null,
                            'MS30' => 'y',
                            'MS32' => '2',
                            'MS_TYPE' => 'M',
                            'MS_MAIL' => 'gov@example.com ',
                            'MS_NOTE' => SmsV1Encoding::toOracleEncoding('桃園市桃園段0001-0000地號收件'),
                            'APP_SEQ' => '1',
                            'APP_ID' => 'GOV01',
                            'GOV_ID' => 'A123',
                            'CC00' => 'C',
                            'CC48' => '0101',
                            'CC49' => '00010000',
                            'GOV_OID' => '2.16.886.101',
                        ],
                        [
                            'MS03' => '115',
                            'MS04_1' => 'HA01',
                            'MS04_2' => '003010',
                            'MS07_1' => '1151113',
                            'MS07_2' => '092000',
                            'MS14' => '0988777666',
                            'MS30' => '9',
                            'MS32' => '1',
                            'MS_TYPE' => 'O',
                            'MS_MAIL' => '',
                            'MS_NOTE' => SmsV1Encoding::toOracleEncoding('跨所代收通知#請至櫃台領件<br>謝謝'),
                            'APP_SEQ' => null,
                            'APP_ID' => null,
                            'GOV_ID' => null,
                            'CC00' => null,
                            'CC48' => null,
                            'CC49' => null,
                            'GOV_OID' => null,
                        ],
                        [
                            'MS03' => '115',
                            'MS04_1' => 'HA01',
                            'MS04_2' => '004010',
                            'MS07_1' => '1151113',
                            'MS07_2' => '093000',
                            'MS14' => '',
                            'MS30' => 'W',
                            'MS32' => '0',
                            'MS_TYPE' => 'W',
                            'MS_MAIL' => 'w@example.com',
                            'MS_NOTE' => SmsV1Encoding::toOracleEncoding('指定送達處所已建檔'),
                            'APP_SEQ' => null,
                            'APP_ID' => null,
                            'GOV_ID' => null,
                            'CC00' => null,
                            'CC48' => null,
                            'CC49' => null,
                            'GOV_OID' => null,
                        ],
                    ];
                }
                if (str_contains($sql, 'FROM SRKEYN') && ($this->currentBinds[':kcde_1'] ?? '') === '04') {
                    return [
                        ['KCNT' => SmsV1Encoding::toOracleEncoding('桃資 ')],
                    ];
                }
                if (str_contains($sql, 'SSMS_GOVINFO_M')) {
                    if (($this->currentBinds[':app_seq'] ?? '') === '1') {
                        return [
                            ['EMAIL_SUB' => SmsV1Encoding::toOracleEncoding('工輔法通報主旨  ')],
                        ];
                    }
                    return [];
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
        $result = (new SmsV1Service($store))->getPendingQueue();

        $this->assertSame('HA', $result['site_id']);
        $this->assertCount(4, $result['items']);

        $waitSqls = array_values(array_filter($fakeDb->queries, fn(array $q): bool => str_contains($q['sql'], 'FROM SSMSWAIT')));
        $this->assertCount(1, $waitSqls);
        $normalizedSql = preg_replace('/\s+/', ' ', $waitSqls[0]['sql']) ?? $waitSqls[0]['sql'];
        $this->assertStringContainsString("WHERE MS_TYPE IN ('M', 'W', 'O') ORDER BY MS07_1, MS07_2", $normalizedSql);

        // Item 0: M/Y SMS preserving leading space and stripping Oracle trailing padding
        $this->assertSame('sms', $result['items'][0]['channel']);
        $this->assertSame('0912345678', $result['items'][0]['recipient']);
        $this->assertNull($result['items'][0]['identity']['gov_ext']);
        $this->assertSame('有關您所持有不動產地籍異動1事', $result['items'][0]['subject']);
        $this->assertSame(' 異動即時通-115年桃資字001230號買賣案收件', $result['items'][0]['msg']);

        // Item 1: M/y Gov Email with CP950 EMAIL_SUB and gov_ext
        $this->assertSame('email', $result['items'][1]['channel']);
        $this->assertSame(2, $result['items'][1]['retry_count']);
        $this->assertSame('工輔法通報主旨', $result['items'][1]['subject']);
        $this->assertSame('桃園市桃園段0001-0000地號收件', $result['items'][1]['msg']);
        $this->assertSame('2.16.886.101', $result['items'][1]['identity']['gov_ext']['gov_oid']);

        // Item 2: O/9 SMS (# split + <br> newline)
        $this->assertSame('sms', $result['items'][2]['channel']);
        $this->assertSame('跨所代收通知', $result['items'][2]['subject']);
        $this->assertSame("請至櫃台領件\n謝謝", $result['items'][2]['msg']);

        // Item 3: W Email
        $this->assertSame('email', $result['items'][3]['channel']);
        $this->assertSame('指定送達處所建檔完成通知', $result['items'][3]['subject']);
        $this->assertSame('指定送達處所已建檔', $result['items'][3]['msg']);
    }

    public function testQueueHelperSupportsLegacyRAndSTypesAndMockStoreUpgradesLegacySchema(): void {
        $dbPath = $this->createTempDbPath();
        @mkdir(dirname($dbPath), 0777, true);
        // Simulate an older sms_mock.db without EMAIL_SUB on SMS_GOVINFO_M, SCMSDS, or SSYSAUTH1
        $legacyPdo = new \PDO('sqlite:' . $dbPath);
        $legacyPdo->exec("CREATE TABLE SMS_GOVINFO_M (APP_SEQ TEXT, APP_ID TEXT, GOV_NAME TEXT, EMAIL TEXT, APP_STOP TEXT, GOV_OID TEXT)");
        $legacyPdo->exec("INSERT INTO SMS_GOVINFO_M VALUES ('9', 'GOV09', '測試局', 'g9@gov.tw', 'N', '2.16.886.109')");
        $legacyPdo = null;

        $store = new SmsV1MockStore($dbPath);
        $pdo = $store->getPdo();

        $cols = $pdo->query("PRAGMA table_info(SMS_GOVINFO_M)")->fetchAll(\PDO::FETCH_ASSOC);
        $colNames = array_map(fn(array $c): string => strtoupper((string)$c['name']), $cols);
        $this->assertContains('EMAIL_SUB', $colNames);

        $pdo->exec("INSERT INTO SCMSDS (MD01, MD02, MD03, MD04, MD13_1, MD13_2) VALUES ('115', 'HA01', '000555', 'U001', '1151201', '143000')");
        $pdo->exec("INSERT INTO SCMSDS (MD01, MD02, MD03, MD04, MD13_1, MD13_2) VALUES ('115', 'HA01', '000556', 'U001', '1151201', '143000')");
        $pdo->exec("INSERT INTO SSYSAUTH1 (USER_ID, USER_NAME) VALUES ('U001', '張測量員')");

        $items = \SmsV1QueueHelper::assemblePendingItems(
            [
                [
                    'ms03' => '115', 'ms04_1' => 'HA01', 'ms04_2' => '000555',
                    'ms30' => 'C', 'ms_type' => 'S', 'ms14' => '0911000111',
                    'ms_mail' => '', 'ms32' => '0', 'ms_note' => '',
                ],
                [
                    'ms03' => '115', 'ms04_1' => 'HA01', 'ms04_2' => '000556',
                    'ms30' => 'I', 'ms_type' => 'S', 'ms14' => '0911000222',
                    'ms_mail' => '', 'ms32' => '0', 'ms_note' => '',
                ],
            ],
            fn(string $ms04_1): string => '', // Fallback to $ms04_1 when SRKEYN 04 missing
            fn(string $appSeq): string => '',
            function (string $ms03, string $ms04_1, string $ms04_2) use ($pdo): array {
                $q = $pdo->prepare('SELECT MD04, MD13_1, MD13_2 FROM SCMSDS WHERE MD01 = :m1 AND MD02 = :m2 AND MD03 = :m3');
                $q->execute([':m1' => $ms03, ':m2' => $ms04_1, ':m3' => $ms04_2]);
                $r = $q->fetch(\PDO::FETCH_ASSOC);
                if (!is_array($r)) {
                    return ['u_name' => '', 'md13_1' => null, 'md13_2' => null];
                }
                $q2 = $pdo->prepare('SELECT USER_NAME FROM SSYSAUTH1 WHERE USER_ID = :u');
                $q2->execute([':u' => $r['MD04']]);
                return [
                    'u_name' => (string)$q2->fetchColumn(),
                    'md13_1' => (string)$r['MD13_1'],
                    'md13_2' => (string)$r['MD13_2'],
                ];
            }
        );

        $this->assertSame('115年HA01字第000555號一案案件回覆', $items[0]['subject']);
        $this->assertSame('台端於地政事務所申請115年HA01字第000555號一案複丈延期至115年12月01日14時30分辦理,謝謝您,再見', $items[0]['msg']);
        $this->assertSame('台端於地政事務所申請115年HA01字第000556號一案經查尚需要補正,請於接到本通知後速洽測量員張測量員,謝謝您,再見', $items[1]['msg']);
    }

    public function testTypeOPreservesExtraHashCharactersAndScmsdsNullFallsBackToDefaultDate(): void {
        // 1) MS_TYPE='O' with multiple '#' characters in MS_NOTE
        $assembledO = \SmsV1QueueHelper::assemblePendingMessage(
            '115', 'HA01', '000777', '9', 'O',
            '跨所主旨#第一段#案件編號#123<br>請查照',
            '桃資'
        );
        $this->assertSame('跨所主旨', $assembledO['subject']);
        $this->assertSame("第一段#案件編號#123\n請查照", $assembledO['msg']);

        // 2) SCMSDS NULL / blank MD13_1 and MD13_2 fallback in MockStore getPendingQueue
        $store = new SmsV1MockStore($this->createTempDbPath());
        $pdo = $store->getPdo();
        $pdo->exec("INSERT INTO SCMSDS (MD01, MD02, MD03, MD04, MD13_1, MD13_2) VALUES ('115', 'HA01', '000888', 'U002', NULL, NULL)");
        $pdo->exec("INSERT INTO SSYSAUTH1 (USER_ID, USER_NAME) VALUES ('U002', '林測量員')");
        $itemsNull = \SmsV1QueueHelper::assemblePendingItems(
            [[
                'ms03' => '115', 'ms04_1' => 'HA01', 'ms04_2' => '000888',
                'ms30' => 'C', 'ms_type' => 'S', 'ms14' => '0911000333',
                'ms_mail' => '', 'ms32' => '0', 'ms_note' => '',
            ]],
            fn(string $ms04_1): string => '桃資',
            fn(string $appSeq): string => '',
            function (string $ms03, string $ms04_1, string $ms04_2) use ($pdo): array {
                $q = $pdo->prepare('SELECT MD04, MD13_1, MD13_2 FROM SCMSDS WHERE MD01 = :m1 AND MD02 = :m2 AND MD03 = :m3');
                $q->execute([':m1' => $ms03, ':m2' => $ms04_1, ':m3' => $ms04_2]);
                $r = $q->fetch(\PDO::FETCH_ASSOC);
                $raw1 = trim((string)($r['MD13_1'] ?? ''));
                $raw2 = trim((string)($r['MD13_2'] ?? ''));
                return [
                    'u_name' => '林測量員',
                    'md13_1' => $raw1 !== '' ? str_pad($raw1, 7, ' ') : null,
                    'md13_2' => $raw2 !== '' ? str_pad($raw2, 6, ' ') : null,
                ];
            }
        );
        $this->assertSame('台端於地政事務所申請115年桃資字第000888號一案複丈延期至098年05月25日13時辦理,謝謝您,再見', $itemsNull[0]['msg']);

        // 3) Blank string MD13_1/MD13_2 (e.g. '       ') must also fall back
        $assembledBlank = \SmsV1QueueHelper::assemblePendingMessage(
            '115', 'HA01', '000889', 'C', 'S', '', '桃資', '', '林測量員', '       ', '      '
        );
        $this->assertSame('台端於地政事務所申請115年桃資字第000889號一案複丈延期至098年05月25日13時辦理,謝謝您,再見', $assembledBlank['msg']);
    }

    public function testOracleStoreGetPendingQueueCachesSsysauth1UserName(): void {
        $fakeDb = new class extends \OraDB {
            public int $authQueryCount = 0;
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
                if (str_contains($this->currentSql, 'FROM SSYSAUTH1')) {
                    $this->authQueryCount++;
                }
                return true;
            }
            public function fetchAll($raw = false) {
                $sql = $this->currentSql;
                if (str_contains($sql, 'FROM SRKEYN')) {
                    return [['KCDE_1' => '55', 'KCDE_2' => '01', 'KCNT' => 'Test', 'KRMK' => 'HA']];
                }
                if (str_contains($sql, 'FROM SSMSWAIT')) {
                    return [
                        [
                            'MS03' => '115', 'MS04_1' => 'HA01', 'MS04_2' => '000001',
                            'MS07_1' => '1151113', 'MS07_2' => '090000',
                            'MS14' => '0911111111', 'MS30' => 'I', 'MS32' => '0',
                            'MS_TYPE' => 'S', 'MS_MAIL' => null, 'MS_NOTE' => '',
                            'APP_SEQ' => null, 'APP_ID' => null, 'GOV_ID' => null,
                            'CC00' => null, 'CC48' => null, 'CC49' => null, 'GOV_OID' => null,
                        ],
                        [
                            'MS03' => '115', 'MS04_1' => 'HA01', 'MS04_2' => '000002',
                            'MS07_1' => '1151113', 'MS07_2' => '090100',
                            'MS14' => '0922222222', 'MS30' => 'D', 'MS32' => '0',
                            'MS_TYPE' => 'S', 'MS_MAIL' => null, 'MS_NOTE' => '',
                            'APP_SEQ' => null, 'APP_ID' => null, 'GOV_ID' => null,
                            'CC00' => null, 'CC48' => null, 'CC49' => null, 'GOV_OID' => null,
                        ],
                    ];
                }
                if (str_contains($sql, 'FROM SCMSDS')) {
                    return [['MD04' => 'U001', 'MD13_1' => null, 'MD13_2' => null]];
                }
                if (str_contains($sql, 'FROM SSYSAUTH1')) {
                    return [['USER_NAME' => SmsV1Encoding::toOracleEncoding('陳測量員')]];
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

        // Call assemblePendingItems indirectly through OracleStore::getPendingQueue or via reflection on closure?
        // Wait: SSMSWAIT in getPendingQueue filters ('M', 'W', 'O'), so let's verify SSYSAUTH1 caching via Reflection or direct SCMSDS test
        $store = new SmsV1OracleStore($fakeWrapper);
        $this->assertSame(0, $fakeDb->authQueryCount);
        $ref = new \ReflectionClass($store);
        $method = $ref->getMethod('lookupSurveyMetaCached');
        $cache = [];
        $r1 = $method->invokeArgs($store, ['115', 'HA01', '000001', &$cache]);
        $r2 = $method->invokeArgs($store, ['115', 'HA01', '000002', &$cache]);
        $this->assertSame('陳測量員', $r1['u_name']);
        $this->assertSame('陳測量員', $r2['u_name']);
        $this->assertSame(1, $fakeDb->authQueryCount);
    }
}
