<?php
declare(strict_types=1);

namespace Tests\Integration;

use STATUS_CODE;

class LoadFileApiTest extends BaseIntegrationTestCase {
    private string $endpoint = 'load_file_api.php';
    private array $tempFiles = [];

    protected function tearDown(): void {
        foreach ($this->tempFiles as $file) {
            if (file_exists($file)) {
                @unlink($file);
            }
        }
        $this->tempFiles = [];
        parent::tearDown();
    }

    private function createTempLogFile(string $filename, string $content): string {
        $dirs = [
            LOG_DIR
        ];
        $written = [];
        foreach ($dirs as $dir) {
            if (is_dir($dir)) {
                $p = $dir . DIRECTORY_SEPARATOR . $filename;
                file_put_contents($p, $content);
                $this->tempFiles[] = $p;
                $written[] = $p;
            }
        }
        return $written[0] ?? '';
    }

    /**
     * 路徑 1：成功讀取現有 SQL 範本檔案（load_select_sql 成功分支）
     */
    public function testLoadSelectSqlSuccess(): void {
        $res = $this->post($this->endpoint, [
            'type' => 'load_select_sql',
            'file_name' => 'AI00301_sql.tpl'
        ]);

        $this->assertResponseSuccess($res);
        $this->assertArrayHasKey('data', $res);
        $this->assertNotEmpty($res['data']);
        $this->assertStringContainsString('file_name=AI00301_sql.tpl', $res['query_string'] ?? '');
    }

    /**
     * 路徑 2：讀取不存在之 SQL 檔案（load_select_sql 查無檔案分支）
     */
    public function testLoadSelectSqlNotFound(): void {
        $missingName = 'non_existent_' . uniqid() . '.tpl';
        $res = $this->post($this->endpoint, [
            'type' => 'load_select_sql',
            'file_name' => $missingName
        ]);

        $this->assertSame(STATUS_CODE::DEFAULT_FAIL, $res['status']);
        $this->assertStringContainsString('找不到檔案', $res['message']);
        $this->assertSame('0', (string)$res['data_count']);
    }

    /**
     * 路徑 3：成功讀取 LOG 檔案全文並驗證過濾邏輯（load_log 成功分支）
     */
    public function testLoadLogSuccess(): void {
        $dummyLog = 'test_integ_' . uniqid() . '.log';
        $content = "2026-10-27 10:00:00 [INFO] System boot ok\n" .
                   "2026-10-27 10:01:00 [INFO] XHR load_log query filtered\n" .
                   "2026-10-27 10:02:00 [INFO] Task finished successfully\n";
        $this->createTempLogFile($dummyLog, $content);

        $res = $this->post($this->endpoint, [
            'type' => 'load_log',
            'log_filename' => $dummyLog
        ]);

        $this->assertResponseSuccess($res);
        $this->assertArrayHasKey('data', $res);
        $this->assertIsArray($res['data']);
        $this->assertSame(2, $res['data_count'], '應過濾掉含有 load_log 的第 2 列');
        $this->assertSame(2, $res['total_count']);
        $values = array_values($res['data']);
        $this->assertStringContainsString('System boot ok', $values[0]);
        $this->assertStringContainsString('Task finished successfully', $values[1]);
    }

    /**
     * 路徑 4：讀取 LOG 檔案切片分頁（load_log 帶 slice_offset 分支）
     */
    public function testLoadLogWithSliceOffset(): void {
        $dummyLog = 'test_slice_' . uniqid() . '.log';
        $content = "Line 1: Alpha\nLine 2: Beta\nLine 3: Gamma\n";
        $this->createTempLogFile($dummyLog, $content);

        $res = $this->post($this->endpoint, [
            'type' => 'load_log',
            'log_filename' => $dummyLog,
            'slice_offset' => 1
        ]);

        $this->assertResponseSuccess($res);
        $this->assertSame(3, $res['total_count']);
        $this->assertSame(2, $res['data_count']);
        $this->assertStringContainsString('Beta', $res['data'][0]);
        $this->assertStringContainsString('Gamma', $res['data'][1]);
    }

    /**
     * 路徑 5：讀取當日真實系統 LOG 檔案相容性
     */
    public function testLoadRealSystemTodayLog(): void {
        $todayLogName = 'log-' . date('Y-m-d') . '.log';
        $res = $this->post($this->endpoint, [
            'type' => 'load_log',
            'log_filename' => $todayLogName,
            'slice_offset' => -10
        ]);

        $this->assertResponseSuccess($res);
        $this->assertArrayHasKey('data', $res);
        $this->assertIsArray($res['data']);
        $this->assertLessThanOrEqual(10, $res['data_count']);
    }

    /**
     * 路徑 6：讀取不存在之 LOG 檔案（load_log 查無檔案分支）
     */
    public function testLoadLogNotFound(): void {
        $missingLog = 'missing_' . uniqid() . '.log';
        $res = $this->post($this->endpoint, [
            'type' => 'load_log',
            'log_filename' => $missingLog
        ]);

        $this->assertSame(STATUS_CODE::DEFAULT_FAIL, $res['status']);
        $this->assertStringContainsString('找不到檔案', $res['message']);
        $this->assertSame('0', (string)$res['data_count']);
    }

    /**
     * 路徑 7：不支援的讀取型態（default 分支）
     */
    public function testUnsupportedTypeReturnsFail(): void {
        $res = $this->post($this->endpoint, [
            'type' => 'unsupported_file_load_type'
        ]);

        $this->assertResponseUnsupport($res);
        $this->assertStringContainsString('不支援的讀取型態', $res['message']);
    }

    /**
     * 路徑 8：未傳入任何參數防護（空 POST 請求）
     */
    public function testEmptyPostParametersSafeFallback(): void {
        $res = $this->post($this->endpoint, []);

        $this->assertResponseUnsupport($res);
        $this->assertStringContainsString('不支援的讀取型態', $res['message']);
    }
}
