<?php
declare(strict_types=1);

namespace Tests\Unit\Domain;

use PHPUnit\Framework\TestCase;
use SmsV1Encoding;
use SmsV1MockStore;
use SmsV1OracleStore;
use SmsV1Router;
use SmsV1Service;

class SmsV1RecordsTest extends TestCase {
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
        $dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'sms_v1_records_test_' . uniqid('', true);
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

    public function testGetRecordsMainFiltersTypeMOrdersDescAndMapsLabels(): void {
        $store = new SmsV1MockStore($this->createTempDbPath());
        $pdo = $store->getPdo();

        $stmt = $pdo->prepare(
            "INSERT INTO SSMSMAIN (MS03, MS04_1, MS04_2, MS07_1, MS07_2, MS14, MS30, MS_TYPE, MS_NOTE, MS_MAIL)
             VALUES (:ms03, :ms04_1, :ms04_2, :ms07_1, :ms07_2, :ms14, :ms30, :ms_type, :ms_note, :ms_mail)"
        );
        // Older record (MS_TYPE='M')
        $stmt->execute([
            ':ms03' => '115', ':ms04_1' => 'HA01', ':ms04_2' => '000001',
            ':ms07_1' => '1151112', ':ms07_2' => '090000',
            ':ms14' => '0912345678', ':ms30' => 'Y', ':ms_type' => 'M',
            ':ms_note' => ' 異動即時通-收件', ':ms_mail' => '',
        ]);
        // Newer record (MS_TYPE='M')
        $stmt->execute([
            ':ms03' => '115', ':ms04_1' => 'HA01', ':ms04_2' => '000002',
            ':ms07_1' => '1151113', ':ms07_2' => '101500',
            ':ms14' => '', ':ms30' => 'z', ':ms_type' => 'M',
            ':ms_note' => '工輔法異動完成', ':ms_mail' => 'gov@example.com',
        ]);
        // Non-'M' record in SSMSMAIN must be excluded per rebuildMainTable (WHERE MS_TYPE='M')
        $stmt->execute([
            ':ms03' => '115', ':ms04_1' => 'HA01', ':ms04_2' => '000003',
            ':ms07_1' => '1151113', ':ms07_2' => '235959',
            ':ms14' => '0988777666', ':ms30' => '0', ':ms_type' => 'R',
            ':ms_note' => '登記案件', ':ms_mail' => '',
        ]);

        $router = new SmsV1Router(new SmsV1Service($store));
        $res = $router->dispatch('GET', '/records', ['table' => 'main'], '');

        $this->assertSame(200, $res['status']);
        $this->assertSame('main', $res['body']['table']);
        $this->assertSame(200, $res['body']['limit']);
        $this->assertSame(2, $res['body']['count']);
        $this->assertCount(2, $res['body']['records']);

        // Ordered DESC by MS07_1 || MS07_2
        $first = $res['body']['records'][0];
        $this->assertSame('000002', $first['ms04_2']);
        $this->assertSame('M', $first['ms_type']);
        $this->assertSame('產權通知', $first['ms_type_label']);
        $this->assertSame('z', $first['ms30']);
        $this->assertSame('產權異動完成通知(工輔法)', $first['ms30_label']);
        $this->assertSame('', $first['ms31_label']);
        $this->assertSame('工輔法異動完成', $first['ms_note']);

        $second = $res['body']['records'][1];
        $this->assertSame('000001', $second['ms04_2']);
        $this->assertSame('Y', $second['ms30']);
        $this->assertSame('產權收件通知', $second['ms30_label']);
        $this->assertSame(' 異動即時通-收件', $second['ms_note']);
    }

    public function testGetRecordsWaitFiltersTypesMWOOrdersDescAndIncludesMs32(): void {
        $store = new SmsV1MockStore($this->createTempDbPath());
        $pdo = $store->getPdo();

        $stmt = $pdo->prepare(
            "INSERT INTO SSMSWAIT (MS03, MS04_1, MS04_2, MS07_1, MS07_2, MS14, MS30, MS32, MS_TYPE, MS_MAIL, MS_NOTE)
             VALUES (:ms03, :ms04_1, :ms04_2, :ms07_1, :ms07_2, :ms14, :ms30, :ms32, :ms_type, :ms_mail, :ms_note)"
        );
        $stmt->execute([
            ':ms03' => '115', ':ms04_1' => 'HA01', ':ms04_2' => '000010',
            ':ms07_1' => '1151113', ':ms07_2' => '080000',
            ':ms14' => '0911111111', ':ms30' => 'X', ':ms32' => '1', ':ms_type' => 'M',
            ':ms_mail' => '', ':ms_note' => '審核結果',
        ]);
        $stmt->execute([
            ':ms03' => '115', ':ms04_1' => 'HA01', ':ms04_2' => '000011',
            ':ms07_1' => '1151113', ':ms07_2' => '120000',
            ':ms14' => '0922222222', ':ms30' => '0', ':ms32' => '0', ':ms_type' => 'W',
            ':ms_mail' => '', ':ms_note' => '指定送達處所',
        ]);
        $stmt->execute([
            ':ms03' => '115', ':ms04_1' => 'HA01', ':ms04_2' => '000012',
            ':ms07_1' => '1151113', ':ms07_2' => '093000',
            ':ms14' => '0933333333', ':ms30' => '7', ':ms32' => '2', ':ms_type' => 'O',
            ':ms_mail' => '', ':ms_note' => '調案主旨#內容',
        ]);
        // Excluded type 'S'
        $stmt->execute([
            ':ms03' => '115', ':ms04_1' => 'HA01', ':ms04_2' => '000013',
            ':ms07_1' => '1151113', ':ms07_2' => '235959',
            ':ms14' => '0944444444', ':ms30' => 'C', ':ms32' => '0', ':ms_type' => 'S',
            ':ms_mail' => '', ':ms_note' => '複丈通知',
        ]);

        $router = new SmsV1Router(new SmsV1Service($store));
        $res = $router->dispatch('GET', '/records', ['table' => 'wait'], '');

        $this->assertSame(200, $res['status']);
        $this->assertSame('wait', $res['body']['table']);
        $this->assertSame(3, $res['body']['count']);

        $records = $res['body']['records'];
        $this->assertSame(['000011', '000012', '000010'], array_column($records, 'ms04_2'));
        $this->assertSame('指定送達處所', $records[0]['ms_type_label']);
        $this->assertSame('待傳送', $records[0]['ms30_label']);
        $this->assertSame('0', $records[0]['ms32']);
        $this->assertSame('', $records[0]['ms31_label']);

        $this->assertSame('其他', $records[1]['ms_type_label']);
        $this->assertSame('調案', $records[1]['ms30_label']);
        $this->assertSame('2', $records[1]['ms32']);

        $this->assertSame('產權通知', $records[2]['ms_type_label']);
        $this->assertSame('產權審核結果通知', $records[2]['ms30_label']);
    }

    public function testGetRecordsLogFiltersTypesMWOAndMapsMs31SuccessAndFailure(): void {
        $store = new SmsV1MockStore($this->createTempDbPath());
        $pdo = $store->getPdo();

        $stmt = $pdo->prepare(
            "INSERT INTO SSMSLOG (MS03, MS04_1, MS04_2, MS07_1, MS07_2, MS14, MS30, MS31, MS_TYPE, MS_MAIL, MS_NOTE)
             VALUES (:ms03, :ms04_1, :ms04_2, :ms07_1, :ms07_2, :ms14, :ms30, :ms31, :ms_type, :ms_mail, :ms_note)"
        );
        $stmt->execute([
            ':ms03' => '115', ':ms04_1' => 'HA01', ':ms04_2' => '000020',
            ':ms07_1' => '1151113', ':ms07_2' => '140000',
            ':ms14' => '0911111111', ':ms30' => 'Z', ':ms31' => 'S', ':ms_type' => 'M',
            ':ms_mail' => '', ':ms_note' => '異動完成',
        ]);
        $stmt->execute([
            ':ms03' => '115', ':ms04_1' => 'HA01', ':ms04_2' => '000021',
            ':ms07_1' => '1151113', ':ms07_2' => '150000',
            ':ms14' => '', ':ms30' => 'y', ':ms31' => 'F', ':ms_type' => 'M',
            ':ms_mail' => 'fail@example.com', ':ms_note' => '機關收件',
        ]);
        $stmt->execute([
            ':ms03' => '115', ':ms04_1' => 'HA01', ':ms04_2' => '000022',
            ':ms07_1' => '1151113', ':ms07_2' => '160000',
            ':ms14' => '0955555555', ':ms30' => 'I', ':ms31' => 'S', ':ms_type' => 'R',
            ':ms_mail' => '', ':ms_note' => '登記補正',
        ]);

        $router = new SmsV1Router(new SmsV1Service($store));
        $res = $router->dispatch('GET', '/records', ['table' => 'log'], '');

        $this->assertSame(200, $res['status']);
        $this->assertSame('log', $res['body']['table']);
        $this->assertSame(2, $res['body']['count']);

        $records = $res['body']['records'];
        $this->assertSame('000021', $records[0]['ms04_2']);
        $this->assertSame('F', $records[0]['ms31']);
        $this->assertSame('失敗', $records[0]['ms31_label']);
        $this->assertSame('產權收件通知(工輔法)', $records[0]['ms30_label']);

        $this->assertSame('000020', $records[1]['ms04_2']);
        $this->assertSame('S', $records[1]['ms31']);
        $this->assertSame('成功', $records[1]['ms31_label']);
        $this->assertSame('產權異動完成通知', $records[1]['ms30_label']);
    }

    public function testGetRecordsRespectsLimitAndDefaultTableAndSortOrder(): void {
        $store = new SmsV1MockStore($this->createTempDbPath());
        $pdo = $store->getPdo();

        $stmt = $pdo->prepare(
            "INSERT INTO SSMSMAIN (MS03, MS04_1, MS04_2, MS07_1, MS07_2, MS14, MS30, MS_TYPE, MS_NOTE)
             VALUES (:ms03, :ms04_1, :ms04_2, :ms07_1, :ms07_2, :ms14, :ms30, 'M', '測試')"
        );
        $times = [
            ['000001', '1151110', '080000'],
            ['000002', '1151112', '090000'],
            ['000003', '1151113', '070000'],
            ['000004', '1151113', '180000'],
        ];
        foreach ($times as [$docNo, $d, $t]) {
            $stmt->execute([
                ':ms03' => '115', ':ms04_1' => 'HA01', ':ms04_2' => $docNo,
                ':ms07_1' => $d, ':ms07_2' => $t, ':ms14' => '0912000000', ':ms30' => 'Y',
            ]);
        }

        $router = new SmsV1Router(new SmsV1Service($store));

        // Omitted table defaults to main, limit=2 returns top 2 DESC
        $res = $router->dispatch('GET', '/api/v1/sms/records', ['limit' => '2'], '');
        $this->assertSame(200, $res['status']);
        $this->assertSame('main', $res['body']['table']);
        $this->assertSame(2, $res['body']['limit']);
        $this->assertSame(2, $res['body']['count']);
        $this->assertSame(['000004', '000003'], array_column($res['body']['records'], 'ms04_2'));
    }

    public function testLabelMappingsCoverAllCodesFromSmsMainFrame(): void {
        $msTypeCases = [
            'M' => '產權通知',
            'O' => '其他',
            'R' => '登記',
            'S' => '複丈',
            'W' => '指定送達處所',
            '' => '',
            'UNKNOWN' => '',
        ];
        foreach ($msTypeCases as $code => $expected) {
            $this->assertSame($expected, SmsV1Service::mapMsTypeLabel($code), "MS_TYPE={$code}");
        }

        $ms30Cases = [
            '0' => '待傳送',
            'I' => '補正',
            'D' => '駁回',
            'F' => '結案',
            'C' => '延期複丈',
            '2' => '未辦繼承通知',
            'X' => '產權審核結果通知',
            'Y' => '產權收件通知',
            'Z' => '產權異動完成通知',
            'y' => '產權收件通知(工輔法)',
            'z' => '產權異動完成通知(工輔法)',
            '7' => '調案',
            '8' => '異議連繫',
            '9' => '跨所代收',
            '' => '',
        ];
        foreach ($ms30Cases as $code => $expected) {
            $codeStr = (string)$code;
            $this->assertSame($expected, SmsV1Service::mapMs30Label($codeStr), "MS30={$codeStr}");
        }

        $this->assertSame('成功', SmsV1Service::mapMs31Label('S'));
        $this->assertSame('失敗', SmsV1Service::mapMs31Label('F'));
        $this->assertSame('', SmsV1Service::mapMs31Label(''));
    }

    public function testRouterReturns400ForInvalidTableOrLimitAnd405ForPost(): void {
        $store = new SmsV1MockStore($this->createTempDbPath());
        $router = new SmsV1Router(new SmsV1Service($store));

        foreach (['invalid', 'ssmsmain', '', 'users'] as $badTable) {
            $res = $router->dispatch('GET', '/records', ['table' => $badTable], '');
            $this->assertSame(400, $res['status'], "Expected 400 for table={$badTable}");
            $this->assertSame('Bad Request', $res['body']['error']);
        }

        foreach (['0', '-1', 'abc', '12.5', ''] as $badLimit) {
            $res = $router->dispatch('GET', '/records', ['table' => 'main', 'limit' => $badLimit], '');
            $this->assertSame(400, $res['status'], "Expected 400 for limit={$badLimit}");
            $this->assertSame('Bad Request', $res['body']['error']);
        }

        $res405 = $router->dispatch('POST', '/records', ['table' => 'main'], '');
        $this->assertSame(405, $res405['status']);
        $this->assertSame('Method Not Allowed', $res405['body']['error']);
    }

    public function testOracleStoreUsesRownumPaginationAndDecodesCp950Fields(): void {
        $fakeDb = new class extends \OraDB {
            public string $capturedSql = '';
            /** @var array<string, mixed> */
            public array $capturedBinds = [];

            public function __construct() {}
            public function __destruct() {}
            public function close() {}
            public function parse($str) { $this->capturedSql = (string)$str; $this->capturedBinds = []; }
            public function bind($bind_var, $real_var) { $this->capturedBinds[$bind_var] = $real_var; }
            public function execute() { return true; }
            public function fetchAll($raw = false) {
                return [
                    [
                        'MS03' => '115 ',
                        'MS04_1' => 'HA01',
                        'MS04_2' => '001230',
                        'MS07_1' => '1151113',
                        'MS07_2' => '093000',
                        'MS14' => '0912345678',
                        'MS30' => 'Y',
                        'MS31' => 'S',
                        'MS_TYPE' => 'M ',
                        'MS_MAIL' => null,
                        'MS_NOTE' => SmsV1Encoding::toOracleEncoding(' 異動即時通-115年桃資字001230號   '),
                    ],
                ];
            }
        };

        $fakeWrapper = new class($fakeDb) extends \OraDBWrapper {
            private $fakeDb;
            public function __construct($fakeDb) { $this->fakeDb = $fakeDb; }
            public function __destruct() {}
            public function reachable() { return true; }
            public function getDB() { return $this->fakeDb; }
        };

        $oracleStore = new SmsV1OracleStore($fakeWrapper);
        $service = new SmsV1Service($oracleStore);
        $result = $service->getRecords('log', 50);

        $this->assertStringContainsString('WHERE ROWNUM <= :bv_limit', $fakeDb->capturedSql);
        $this->assertStringContainsString("FROM SSMSLOG WHERE MS_TYPE IN ('M', 'W', 'O') ORDER BY MS07_1 || MS07_2 DESC", $fakeDb->capturedSql);
        $this->assertStringNotContainsStringIgnoringCase('FETCH FIRST', $fakeDb->capturedSql);
        $this->assertSame(50, $fakeDb->capturedBinds[':bv_limit']);

        $this->assertSame(1, $result['count']);
        $row = $result['records'][0];
        $this->assertSame('115', $row['ms03']);
        $this->assertSame('M', $row['ms_type']);
        $this->assertSame('產權通知', $row['ms_type_label']);
        $this->assertSame('產權收件通知', $row['ms30_label']);
        $this->assertSame('成功', $row['ms31_label']);
        $this->assertSame('', $row['ms_mail']);
        // Preserves leading space in MS_NOTE while trimming Oracle CHAR trailing spaces
        $this->assertSame(' 異動即時通-115年桃資字001230號', $row['ms_note']);
    }
}
