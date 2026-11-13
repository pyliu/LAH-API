<?php
declare(strict_types=1);

namespace Tests\Unit\Domain;

use InvalidArgumentException;
use PDO;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SmsV1MockStore;
use SmsV1SnapshotImporter;

class SmsV1SnapshotImporterTest extends TestCase {
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
        $dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'sms_v1_snapshot_test_' . uniqid('', true);
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

    public function testAssertReadOnlySelectSqlAllowsValidSelectQueries(): void {
        SmsV1SnapshotImporter::assertReadOnlySelectSql('SELECT * FROM SRKEYN WHERE KCDE_1 = \'55\'');
        SmsV1SnapshotImporter::assertReadOnlySelectSql("  select RM01, RM02, UPDATE_DATE from SCRSMS where RM07_1 >= '1151107' and ROWNUM <= 100  ");
        SmsV1SnapshotImporter::assertReadOnlySelectSql("SELECT * FROM (SELECT MS03, 'DELETE_FLAG' AS TAG FROM SSMSLOG ORDER BY MS07_1 DESC) WHERE ROWNUM <= 200");
        $this->addToAssertionCount(1);
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function forbiddenSqlProvider(): array {
        return array(
            'empty' => array('   '),
            'insert' => array("INSERT INTO SSMSMAIN (MS03) VALUES ('115')"),
            'update' => array("UPDATE SSMSMAIN SET MS30 = 'Y'"),
            'delete' => array("DELETE FROM SSMSMAIN"),
            'drop' => array("DROP TABLE SSMSMAIN"),
            'alter' => array("ALTER TABLE SSMSMAIN ADD COL1 VARCHAR2(10)"),
            'truncate' => array("TRUNCATE TABLE SSMSMAIN"),
            'create' => array("CREATE TABLE TMP1 (ID VARCHAR2(10))"),
            'merge' => array("MERGE INTO SSMSMAIN USING DUAL ON (1=1) WHEN MATCHED THEN UPDATE SET MS30='Y'"),
            'semicolon_injection' => array("SELECT 1 FROM DUAL; DELETE FROM SSMSMAIN"),
            'subquery_dml' => array("SELECT * FROM (DELETE FROM SSMSMAIN)"),
        );
    }

    #[DataProvider('forbiddenSqlProvider')]
    public function testAssertReadOnlySelectSqlRejectsNonSelectOrDml(string $sql): void {
        $this->expectException(InvalidArgumentException::class);
        SmsV1SnapshotImporter::assertReadOnlySelectSql($sql);
    }

    public function testImportSnapshotReplacesSeedFixturesTrimsCharPaddingAndPreservesNulls(): void {
        $dbPath = $this->createTempDbPath();

        $dataset = array(
            'SRKEYN' => array(
                array('KCDE_1' => '55', 'KCDE_2' => '01', 'KCNT' => '中壢地政事務所   ', 'KRMK' => 'HB  '),
                array('KCDE_1' => '66', 'KCDE_2' => '01', 'KCNT' => '桃園市', 'KRMK' => null),
                array('KCDE_1' => '55', 'KCDE_2' => '02', 'KCNT' => '中壢', 'KRMK' => ''),
                array('KCDE_1' => '04', 'KCDE_2' => 'H1  ', 'KCNT' => '桃資字', 'KRMK' => ''),
                array('KCDE_1' => '06', 'KCDE_2' => '01', 'KCNT' => '買賣', 'KRMK' => ''),
                array('KCDE_1' => 'SMS', 'KCDE_2' => '01', 'KCNT' => '請電洽本所', 'KRMK' => ''),
            ),
            'SSMS_CODE04' => array(array('COD04' => 'H1  ')),
            'SSMS_CODE06' => array(array('COD06' => '01', 'ITEMS' => '0')),
            'SSMS_USERINFO_M' => array(
                array(
                    'LIDN' => 'A123456789',
                    'LNAM' => '王小明  ',
                    'REPLY_MOBILE' => '1',
                    'MOBILE' => '0912345678',
                    'MOBILE2' => null,
                    'REPLY_EMAIL' => '0',
                    'EMAIL' => null,
                    'EMAIL2' => '',
                    'SMS_FLAG' => 'Y',
                ),
            ),
            'SCRSMS' => array(
                array(
                    'RM01' => '115',
                    'RM02' => 'H1  ',
                    'RM03' => '001230',
                    'RM07_1' => '1151113',
                    'RM07_2' => '101500',
                    'RM09' => '01',
                    'RM30' => 'F',
                    'RM31' => '01',
                    'RM32' => '1',
                    'RM56_1' => null,
                    'RM56_2' => null,
                    'RM99' => null,
                    'RM100' => null,
                    'RM101' => null,
                ),
            ),
            'SCRSMS_RM1821' => array(
                array('RM01' => '115', 'RM02' => 'H1', 'RM03' => '001230', 'RM_TYPE' => '18', 'RM_ID' => 'A123456789'),
            ),
            'SCMSMS' => array(
                array('MM01' => '115', 'MM02' => 'H1', 'MM03' => '001230', 'MM22' => '01', 'MM23' => '1151113'),
            ),
            'SSMSWAIT' => array(
                array(
                    'MS03' => '115', 'MS04_1' => 'H1', 'MS04_2' => '001230',
                    'MS07_1' => '1151113', 'MS07_2' => '101500', 'MS14' => '0912345678',
                    'MS30' => 'Y', 'MS32' => '0', 'MS_TYPE' => 'O',
                    'MS_MAIL' => null, 'MS_NOTE' => '測試訊息',
                    'APP_SEQ' => null, 'APP_ID' => null, 'GOV_ID' => null,
                    'CC00' => null, 'CC48' => null, 'CC49' => null, 'GOV_OID' => null,
                ),
            ),
        );

        $result = SmsV1SnapshotImporter::importSnapshot($dbPath, $dataset, true);

        $this->assertNull($result['backup_path']);
        $this->assertSame(6, $result['counts']['SRKEYN']);
        $this->assertSame(1, $result['counts']['SSMSWAIT']);
        $this->assertSame(0, $result['counts']['SSMSLOG']);
        $this->assertSame(13, $result['total_rows']);

        $store = new SmsV1MockStore($dbPath);
        $meta = $store->getMetadata();
        $this->assertSame('HB', $meta['site_id']);
        $this->assertSame('中壢地政事務所', $meta['office_name']);

        // Confirm default seeded SRKEYN (HA / 桃園) was completely replaced by snapshot (HB / 中壢)
        $pdo = $store->getPdo();
        $countStmt = $pdo->query("SELECT COUNT(*) FROM SRKEYN WHERE KCDE_1 = '55' AND KCDE_2 = '01'");
        $this->assertSame(1, (int)$countStmt->fetchColumn());
        $rm07NullStmt = $pdo->query("SELECT RM56_1, RM99 FROM SCRSMS WHERE RM03 = '001230'");
        $scrRow = $rm07NullStmt->fetch(PDO::FETCH_ASSOC);
        $this->assertNull($scrRow['RM56_1']);
        $this->assertNull($scrRow['RM99']);

        // End-to-end check: syncCases dedupes against imported SSMSWAIT, getRecords & getPendingQueue read it
        $sync = $store->syncCases('1151113');
        $this->assertSame(1, $sync['user_cases_scanned']);
        $this->assertSame(0, $sync['user_notifications_inserted']);

        $waitRecords = $store->getRecords('wait', 50);
        $this->assertCount(1, $waitRecords);
        $this->assertCount(0, $store->getRecords('main', 50));

        $pending = $store->getPendingQueue();
        $this->assertSame('HB', $pending['site_id']);
        $this->assertCount(1, $pending['items']);
    }

    public function testImportSnapshotBacksUpExistingDatabaseAndRejectsUnknownTable(): void {
        $dbPath = $this->createTempDbPath();

        SmsV1SnapshotImporter::importSnapshot($dbPath, array(
            'SRKEYN' => array(
                array('KCDE_1' => '55', 'KCDE_2' => '01', 'KCNT' => '舊所名', 'KRMK' => 'HA'),
            ),
        ), true);
        $this->assertFileDoesNotExist($dbPath . '.bak');

        $second = SmsV1SnapshotImporter::importSnapshot($dbPath, array(
            'SRKEYN' => array(
                array('KCDE_1' => '55', 'KCDE_2' => '01', 'KCNT' => '新所名', 'KRMK' => 'HC'),
            ),
        ), true);

        $this->assertSame($dbPath . '.bak', $second['backup_path']);
        $this->assertFileExists($dbPath . '.bak');

        // Confirm backup contains old data and active DB contains new data
        $bakPdo = new PDO('sqlite:' . $dbPath . '.bak');
        $this->assertSame('HA', (string)$bakPdo->query("SELECT KRMK FROM SRKEYN WHERE KCDE_1 = '55' AND KCDE_2 = '01'")->fetchColumn());
        $bakPdo = null;

        $activeStore = new SmsV1MockStore($dbPath);
        $this->assertSame('HC', $activeStore->getMetadata()['site_id']);

        $this->expectException(InvalidArgumentException::class);
        SmsV1SnapshotImporter::importSnapshot($dbPath, array(
            'NON_EXISTENT_TABLE' => array(array('A' => '1')),
        ), false);
    }
}
