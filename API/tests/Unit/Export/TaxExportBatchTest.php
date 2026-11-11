<?php
declare(strict_types=1);
namespace Tests\Unit\Export;

use PHPUnit\Framework\TestCase;
use TaxExportBatch;

require_once ROOT_DIR . '/vendor/autoload.php';
require_once ROOT_DIR . '/include/api/TaxExportBatch.class.php';

final class TaxExportBatchTest extends TestCase {
    private $directory;

    protected function setUp(): void {
        $this->directory = sys_get_temp_dir() . '/lah-tax-test-' . bin2hex(random_bytes(8));
    }

    protected function tearDown(): void {
        if (!is_dir($this->directory)) return;
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->directory, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($iterator as $file) {
            $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        }
        rmdir($this->directory);
    }

    public function testRejectsInvalidAndReversedDates(): void {
        $service = new TaxExportBatch($this->directory, 'office:operator', true);
        foreach ([['', '2026-10-15'], ['2026-02-30', '2026-03-01'], ['2026-10-16', '2026-10-15']] as $dates) {
            try {
                $service->start($dates[0], $dates[1]);
                $this->fail('Invalid dates were accepted');
            } catch (\InvalidArgumentException $expected) {
                $this->assertNotEmpty($expected->getMessage());
            }
        }
    }

    public function testBatchIsIsolatedByOwnerAndMode(): void {
        $service = new TaxExportBatch($this->directory, 'office:first', true);
        $batch = $service->start('2026-10-01', '2026-10-01');
        $this->assertArrayNotHasKey('owner', $batch);
        foreach ([['office:second', true], ['office:first', false]] as $other) {
            try {
                (new TaxExportBatch($this->directory, $other[0], $other[1]))->status($batch['batch']);
                $this->fail('Another owner or mode accessed batch');
            } catch (\InvalidArgumentException $expected) {
                $this->assertNotEmpty($expected->getMessage());
            }
        }
    }

    public function testPendingWorkResumesAndSuccessfulWorkIsNotReplaced(): void {
        $provider = new class {
            public function advance(array $item, array $batch, string $path, callable $remember): array {
                if (!$item['source_job']) {
                    $remember(['original_job' => 'fixture-only']);
                    return ['state' => 'pending'];
                }
                file_put_contents($path, '%PDF-fixture');
                return ['state' => 'success'];
            }
        };
        $service = new TaxExportBatch($this->directory, 'office:operator', false, $provider);
        $batch = $service->start('2026-10-01', '2026-10-15');
        $code = $batch['items'][0]['code'];
        $pending = $service->advance($batch['batch'], $code);
        $this->assertSame('pending', $pending['items'][0]['state']);
        $this->assertArrayNotHasKey('source_job', $pending['items'][0]);
        $completed = $service->advance($batch['batch'], $code);
        $this->assertSame('success', $completed['items'][0]['state']);
        $file = $service->download($batch['batch'], $code);
        $this->assertSame('%PDF-fixture', file_get_contents($file['path']));
        $again = $service->advance($batch['batch'], $code, true);
        $this->assertSame($completed, $again);
    }

    public function testPartialFailureCanBeRetriedWithoutLosingOtherResults(): void {
        $provider = new class {
            public $fail = true;
            public function advance(array $item, array $batch, string $path, callable $remember): array {
                if ($this->fail && $item['code'] === 'RCD0101_REMOTE') throw new \RuntimeException('sensitive fixture exception');
                file_put_contents($path, '%PDF-' . $item['code']);
                return ['state' => 'success'];
            }
        };
        $service = new TaxExportBatch($this->directory, 'office:operator', false, $provider);
        $batch = $service->start('2026-10-01', '2026-10-15');
        $service->advance($batch['batch'], 'RCD0101_LOCAL');
        $failed = $service->advance($batch['batch'], 'RCD0101_REMOTE');
        $this->assertSame('success', $failed['items'][0]['state']);
        $this->assertSame('failed', $failed['items'][1]['state']);
        $this->assertStringNotContainsString('sensitive', $failed['items'][1]['message']);
        $provider->fail = false;
        $retried = $service->advance($batch['batch'], 'RCD0101_REMOTE', true);
        $this->assertSame('success', $retried['items'][1]['state']);
        $this->assertSame($failed['items'][0], $retried['items'][0]);
    }

    public function testAllEmptyZipStillHasAllItemResults(): void {
        $provider = new class {
            public function advance(array $item, array $batch, string $path, callable $remember): array {
                return ['state' => 'empty'];
            }
        };
        $service = new TaxExportBatch($this->directory, 'office:operator', false, $provider);
        $batch = $service->start('2026-10-01', '2026-10-15');
        foreach ($batch['items'] as $item) $service->advance($batch['batch'], $item['code']);
        $file = $service->download($batch['batch'], 'zip');
        $zip = new \ZipArchive();
        $this->assertTrue($zip->open($file['path']));
        $this->assertSame(1, $zip->numFiles);
        $manifest = $zip->getFromName('產製結果清單.txt');
        foreach ($batch['items'] as $item) $this->assertStringContainsString($item['name'], $manifest);
        $this->assertStringContainsString('empty', $manifest);
        $zip->close();
    }

    public function testBuildingNoticesSupportedAndDeliverPdf(): void {
        $service = new TaxExportBatch($this->directory, 'office:operator', true);
        $batch = $service->start('2026-10-16', '2026-10-31');
        
        $supported = array_values(array_filter(array_map(function ($it) {
            return $it['supported'] ? $it['code'] : null;
        }, $batch['items'])));

        $this->assertContains('RCD0102_LOCAL', $supported);
        $this->assertContains('RCD0102_REMOTE', $supported);

        // Advance 建物通知書兩項
        $resLocal = $service->advance($batch['batch'], 'RCD0102_LOCAL');
        $itemLocal = array_values(array_filter($resLocal['items'], fn($i) => $i['code'] === 'RCD0102_LOCAL'))[0];
        $this->assertSame('success', $itemLocal['state']);
        $this->assertNotEmpty($itemLocal['filename']);
        $this->assertStringContainsString('建物異動通知書（本所）', $itemLocal['filename']);

        $resRemote = $service->advance($batch['batch'], 'RCD0102_REMOTE');
        $itemRemote = array_values(array_filter($resRemote['items'], fn($i) => $i['code'] === 'RCD0102_REMOTE'))[0];
        $this->assertSame('success', $itemRemote['state']);
        $this->assertNotEmpty($itemRemote['filename']);
        $this->assertStringContainsString('建物異動通知書（他所）', $itemRemote['filename']);

        // 驗證單檔下載
        $downLocal = $service->download($batch['batch'], 'RCD0102_LOCAL');
        $this->assertFileExists($downLocal['path']);
        $this->assertStringStartsWith('%PDF-', file_get_contents($downLocal['path']));

        $downRemote = $service->download($batch['batch'], 'RCD0102_REMOTE');
        $this->assertFileExists($downRemote['path']);
        $this->assertStringStartsWith('%PDF-', file_get_contents($downRemote['path']));
    }

    public function testAi001LandChangeTxtDeliverTxtWithBothOffices(): void {
        $service = new TaxExportBatch($this->directory, 'office:operator', true);
        $batch = $service->start('2026-10-16', '2026-10-31');

        $supported = array_values(array_filter(array_map(function ($it) {
            return $it['supported'] ? $it['code'] : null;
        }, $batch['items'])));

        $this->assertContains('AI001', $supported);

        // Advance AI001
        $resAi001 = $service->advance($batch['batch'], 'AI001');
        $itemAi001 = array_values(array_filter($resAi001['items'], fn($i) => $i['code'] === 'AI001'))[0];
        $this->assertSame('success', $itemAi001['state']);
        $this->assertNotEmpty($itemAi001['filename']);
        $this->assertStringContainsString('地籍異動資料.txt', $itemAi001['filename']);

        // 驗證單檔下載
        $downAi001 = $service->download($batch['batch'], 'AI001');
        $this->assertFileExists($downAi001['path']);
        $txtContent = file_get_contents($downAi001['path']);
        
        // 包含本所案件 A1 與 他所案件 U1
        $this->assertStringContainsString('115A1HA000001A', $txtContent);
        $this->assertStringContainsString('115U1HA000002A', $txtContent);
        // 包含結尾 TAG
        $this->assertStringContainsString("##### TAG #####\xA6\x40\xB2\xA3\xBB\x73 4 \xB5\xA7\xB8\xEA\xAE\xC6", $txtContent);
    }

    public function testAi002PriceChangeTxtDeliverTxtWithBothOffices(): void {
        $service = new TaxExportBatch($this->directory, 'office:operator', true);
        $batch = $service->start('2026-10-16', '2026-10-31');

        $supported = array_values(array_filter(array_map(function ($it) {
            return $it['supported'] ? $it['code'] : null;
        }, $batch['items'])));

        $this->assertContains('AI002', $supported);

        // Advance AI002
        $resAi002 = $service->advance($batch['batch'], 'AI002');
        $itemAi002 = array_values(array_filter($resAi002['items'], fn($i) => $i['code'] === 'AI002'))[0];
        $this->assertSame('success', $itemAi002['state']);
        $this->assertNotEmpty($itemAi002['filename']);
        $this->assertStringContainsString('地價異動資料.txt', $itemAi002['filename']);

        // 驗證單檔下載
        $downAi002 = $service->download($batch['batch'], 'AI002');
        $this->assertFileExists($downAi002['path']);
        $txtContent = file_get_contents($downAi002['path']);

        // 驗證換行與長度
        $lines = explode("\r\n", rtrim($txtContent, "\r\n"));
        $this->assertCount(5, $lines); // 4 筆資料 + 1 筆結尾 TAG

        // 資料列固定每行 103 位元組
        for ($i = 0; $i < 4; $i++) {
            $this->assertSame(103, strlen($lines[$i]), "Line $i must be exactly 103 bytes");
        }

        // 包含本所案件（81 字號）與他所跨所案件（U1 字號）
        $this->assertStringContainsString('115HA81', $txtContent);
        $this->assertStringContainsString('115HAU1', $txtContent);

        // 包含結尾 TAG
        $this->assertStringContainsString("##### TAG #####\xA6\x40\xB2\xA3\xBB\x73 4 \xB5\xA7\xB8\xEA\xAE\xC6", $txtContent);
    }

    public function testAi037BuildingChangeTxtDeliverTxtWithBothOffices(): void {
        $service = new TaxExportBatch($this->directory, 'office:operator', true);
        $batch = $service->start('2026-10-16', '2026-10-31');

        $supported = array_values(array_filter(array_map(function ($it) {
            return $it['supported'] ? $it['code'] : null;
        }, $batch['items'])));

        $this->assertSame(['RCD0101_LOCAL', 'RCD0101_REMOTE', 'RCD0102_LOCAL', 'RCD0102_REMOTE', 'AI001', 'AI002', 'AI037'], $supported);

        // Advance AI037
        $resAi037 = $service->advance($batch['batch'], 'AI037');
        $itemAi037 = array_values(array_filter($resAi037['items'], fn($i) => $i['code'] === 'AI037'))[0];
        $this->assertSame('success', $itemAi037['state']);
        $this->assertNotEmpty($itemAi037['filename']);
        $this->assertStringContainsString('建物異動資料.txt', $itemAi037['filename']);

        // 驗證單檔下載
        $downAi037 = $service->download($batch['batch'], 'AI037');
        $this->assertFileExists($downAi037['path']);
        $txtContent = file_get_contents($downAi037['path']);

        // 驗證換行與長度
        $lines = explode("\r\n", rtrim($txtContent, "\r\n"));
        $this->assertCount(5, $lines); // 4 筆資料 + 1 筆結尾 TAG

        // D 紀錄 106 位元組，E 紀錄 207 位元組
        $this->assertSame(106, strlen($lines[0]), 'Line 0 (D record) must be 106 bytes');
        $this->assertSame(207, strlen($lines[1]), 'Line 1 (E record) must be 207 bytes');
        $this->assertSame(106, strlen($lines[2]), 'Line 2 (D record) must be 106 bytes');
        $this->assertSame(207, strlen($lines[3]), 'Line 3 (E record) must be 207 bytes');

        // 包含本所收件字與他所跨所收件字
        $this->assertStringContainsString('115A3HA', $txtContent);
        $this->assertStringContainsString('115A1HA', $txtContent);
        $this->assertStringContainsString('115U1HA', $txtContent);

        // 包含結尾 TAG
        $this->assertStringContainsString("##### TAG #####\xA6\x40\xB2\xA3\xBB\x73 4 \xB5\xA7\xB8\xEA\xAE\xC6", $txtContent);

        // 推進所有項目並驗證全部成功 ZIP
        foreach ($batch['items'] as $it) {
            $service->advance($batch['batch'], $it['code']);
        }
        $zipDown = $service->download($batch['batch'], 'zip');
        $this->assertFileExists($zipDown['path']);
        $zip = new \ZipArchive();
        $this->assertTrue($zip->open($zipDown['path']));
        $this->assertSame(8, $zip->numFiles); // 7 份成果 + 1 份清單
        $manifest = $zip->getFromName('產製結果清單.txt');
        $this->assertStringContainsString('建物異動資料', $manifest);
        $this->assertStringContainsString('地價異動資料', $manifest);
        $this->assertStringContainsString('地籍異動資料', $manifest);
        $zip->close();
    }
}
