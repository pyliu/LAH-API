<?php
declare(strict_types=1);

namespace Tests\Unit\Domain;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use SQLiteDBFactory;
use SmsV1Encoding;
use SmsV1MockStore;
use SmsV1OracleStore;
use SmsV1Router;
use SmsV1Service;
use SmsV1StoreInterface;

class SmsV1MetadataTest extends TestCase {
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
        $dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'sms_v1_test_' . uniqid('', true);
        $this->tempDirs[] = $dir;
        return $dir . DIRECTORY_SEPARATOR . 'nested' . DIRECTORY_SEPARATOR . $filename;
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
    public function testOracleEncodingRoundTripWithTraditionalChinese(): void {
        $utf8 = '桃園地政事務所';
        $cp950Bytes = SmsV1Encoding::toOracleEncoding($utf8);

        $this->assertNotSame($utf8, $cp950Bytes);
        $this->assertSame(mb_convert_encoding($utf8, 'CP950', 'UTF-8'), $cp950Bytes);
        $this->assertSame($utf8, SmsV1Encoding::fromOracleEncoding($cp950Bytes));
    }

    public function testOracleEncodingHandlesNullAndEmpty(): void {
        $this->assertSame('', SmsV1Encoding::fromOracleEncoding(null));
        $this->assertSame('', SmsV1Encoding::fromOracleEncoding(''));
        $this->assertSame('', SmsV1Encoding::toOracleEncoding(null));
        $this->assertSame('', SmsV1Encoding::toOracleEncoding(''));
    }

    public function testIso88591Big5RoundTripWithTraditionalChinese(): void {
        $utf8 = '異動即時通-115年桃資字001230號';
        $isoWrapped = SmsV1Encoding::utf8ToIso88591($utf8);

        $this->assertNotSame($utf8, $isoWrapped);
        $this->assertSame($utf8, SmsV1Encoding::iso88591ToUtf8($isoWrapped));
    }

    public function testRocDateAndTimeFormatting(): void {
        $dt = new DateTimeImmutable('2026-11-13 09:05:03');
        $this->assertSame('1151113', SmsV1Encoding::getRocDate($dt));
        $this->assertSame('115', SmsV1Encoding::getRocYear($dt));
        $this->assertSame('090503', SmsV1Encoding::getTimeHhmmss($dt));

        $early = new DateTimeImmutable('2006-01-02 00:00:00');
        $this->assertSame('0950102', SmsV1Encoding::getRocDate($early));
        $this->assertSame('095', SmsV1Encoding::getRocYear($early));
    }

    public function testMockStoreInitializesRequiredTablesAndDefaultSrkeynFixtures(): void {
        $dbPath = $this->createTempDbPath();
        $store = new SmsV1MockStore($dbPath);

        $this->assertInstanceOf(SmsV1StoreInterface::class, $store);
        $this->assertFileExists($dbPath);

        $pdo = $store->getPdo();
        $expectedTables = [
            'SRKEYN',
            'SSMSMAIN',
            'SSMSWAIT',
            'SSMSLOG',
            'SCRSMS',
            'SCRSMS_RM1821',
            'SCRSMS_RM1215',
            'TRINDX',
            'SRSINDX',
            'SRBLOW',
            'SREBOW',
            'SSMS_CODE04',
            'SSMS_CODE06',
            'SSMS_USERINFO_M',
            'SSMS_GOVINFO_D',
            'SMS_GOVINFO_M',
            'SSMS_GOVINFO_M',
        ];
        $stmt = $pdo->query("SELECT name FROM sqlite_master WHERE type = 'table'");
        $actualTables = $stmt ? $stmt->fetchAll(\PDO::FETCH_COLUMN) : [];
        foreach ($expectedTables as $table) {
            $this->assertContains($table, $actualTables, "Missing table {$table} in sms_mock.db");
        }

        $srkeynRows = $pdo->query("SELECT KCDE_1, KCDE_2, KCNT, KRMK FROM SRKEYN")->fetchAll(\PDO::FETCH_ASSOC);
        $keys = array_map(function (array $row): string {
            return $row['KCDE_1'] . '/' . $row['KCDE_2'];
        }, $srkeynRows);
        $this->assertContains('55/01', $keys);
        $this->assertContains('66/01', $keys);
        $this->assertContains('55/02', $keys);
        $this->assertContains('SMS/01', $keys);
    }

    public function testMockStoreGetMetadataQueriesSrkeyn(): void {
        $dbPath = $this->createTempDbPath();
        $store = new SmsV1MockStore($dbPath);

        $this->assertSame([
            'site_id' => 'HA',
            'office_name' => '桃園地政事務所',
            'county_name' => '桃園市',
        ], $store->getMetadata());

        $pdo = $store->getPdo();
        $pdo->exec("UPDATE SRKEYN SET KRMK = 'HB', KCNT = '中壢地政事務所' WHERE KCDE_1 = '55' AND KCDE_2 = '01'");

        $this->assertSame([
            'site_id' => 'HB',
            'office_name' => '中壢地政事務所',
            'county_name' => '桃園市',
        ], $store->getMetadata());
    }

    public function testMockStoreUsesSqliteDbFactoryFolderByDefault(): void {
        $store = new SmsV1MockStore();
        $expectedPath = SQLiteDBFactory::getDbFolder() . DIRECTORY_SEPARATOR . 'sms_mock.db';
        $this->assertSame($expectedPath, $store->getDbPath());
    }

    public function testOracleStoreImplementsInterfaceAndThrowsWhenUnreachable(): void {
        $fakeWrapper = new class extends \OraDBWrapper {
            public function __construct() {}
            public function reachable() { return false; }
            public function getDB() { return null; }
        };
        $store = new SmsV1OracleStore($fakeWrapper);
        $this->assertInstanceOf(SmsV1StoreInterface::class, $store);

        $this->expectException(\RuntimeException::class);
        $store->getMetadata();
    }

    public function testOracleStoreDecodesCp950SrkeynRows(): void {
        $fakeDb = new class extends \OraDB {
            public function __construct() {}
            public function __destruct() {}
            public function close() {}
            public function parse($str) {}
            public function execute() { return true; }
            public function fetchAll($raw = false) {
                return [
                    [
                        'KCDE_1' => '55',
                        'KCDE_2' => '01',
                        'KCNT' => SmsV1Encoding::toOracleEncoding('中壢地政事務所  '),
                        'KRMK' => 'HB  ',
                    ],
                    [
                        'KCDE_1' => '66',
                        'KCDE_2' => '01',
                        'KCNT' => SmsV1Encoding::toOracleEncoding('桃園市 '),
                        'KRMK' => '',
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

        $store = new SmsV1OracleStore($fakeWrapper);
        $this->assertSame([
            'site_id' => 'HB',
            'office_name' => '中壢地政事務所',
            'county_name' => '桃園市',
        ], $store->getMetadata());
    }

    public function testServiceDefaultsToMockStoreInMockModeAndReturnsMetadata(): void {
        $service = new SmsV1Service();
        $this->assertInstanceOf(SmsV1MockStore::class, $service->getStore());
        $this->assertSame([
            'site_id' => 'HA',
            'office_name' => '桃園地政事務所',
            'county_name' => '桃園市',
        ], $service->getMetadata());
    }

    public function testRouterDispatchesGetMetadataAndVariants(): void {
        $dbPath = $this->createTempDbPath();
        $store = new SmsV1MockStore($dbPath);
        $router = new SmsV1Router(new SmsV1Service($store));

        $expectedBody = [
            'site_id' => 'HA',
            'office_name' => '桃園地政事務所',
            'county_name' => '桃園市',
        ];

        $paths = [
            ['/metadata', []],
            ['metadata', []],
            ['/api/v1/sms/metadata', []],
            ['/LAH-API/v1/sms/metadata', []],
            ['/LAH-API/api/sms/metadata', []],
            ['/api/sms_v1_api.php/metadata', []],
            ['', ['route' => '/metadata']],
            ['/', ['path' => 'metadata']],
        ];

        foreach ($paths as [$path, $query]) {
            $res = $router->dispatch('GET', $path, $query, '');
            $this->assertSame(200, $res['status'], "Failed for path: {$path}");
            $this->assertSame($expectedBody, $res['body'], "Failed body for path: {$path}");
        }
    }

    public function testRouterReturns404ForUndefinedRoute(): void {
        $dbPath = $this->createTempDbPath();
        $router = new SmsV1Router(new SmsV1Service(new SmsV1MockStore($dbPath)));

        $res = $router->dispatch('GET', '/does-not-exist', [], '');
        $this->assertSame(404, $res['status']);
        $this->assertIsArray($res['body']);
        $this->assertSame('Not Found', $res['body']['error']);
        $this->assertArrayHasKey('message', $res['body']);
    }

    public function testRouterReturns405ForUnsupportedMethodOnMetadata(): void {
        $dbPath = $this->createTempDbPath();
        $router = new SmsV1Router(new SmsV1Service(new SmsV1MockStore($dbPath)));

        $res = $router->dispatch('POST', '/metadata', [], '');
        $this->assertSame(405, $res['status']);
        $this->assertSame('Method Not Allowed', $res['body']['error']);
        $this->assertArrayHasKey('message', $res['body']);
    }

    public function testRouterReturns500WithoutLeakingInternalDetailsOnStoreFailure(): void {
        $failingStore = new class implements SmsV1StoreInterface {
            public function getMetadata(): array {
                throw new \RuntimeException('ORA-01017: invalid username/password; secret_pwd=1234');
            }
            public function syncCases(string $targetDate): array {
                return array();
            }
            public function getRecords(string $table, int $limit = 200): array {
                throw new \RuntimeException('ORA-01017: invalid username/password; secret_pwd=1234');
            }
            public function enqueueQueue(
                bool $checkRegisterA = true,
                bool $checkRegisterB = true,
                bool $checkRegisterC = true,
                bool $checkRegisterD = true
            ): array {
                return array('moved_to_wait_count' => 0, 'deleted_from_main_count' => 0);
            }
            public function getPendingQueue(): array {
                return array('site_id' => '', 'items' => array());
            }
            public function reportQueueItem(
                array $identity,
                bool $success,
                string $msg = '',
                int $maxRetries = 0,
                ?string $errorDesc = null
            ): array {
                return array('action' => 'not_found');
            }
        };
        $router = new SmsV1Router(new SmsV1Service($failingStore));

        $res = $router->dispatch('GET', '/metadata', [], '');
        $this->assertSame(500, $res['status']);
        $this->assertSame('Internal Server Error', $res['body']['error']);
        $this->assertStringNotContainsString('secret_pwd', $res['body']['message']);
        $this->assertStringNotContainsString('ORA-01017', $res['body']['message']);
    }
}
