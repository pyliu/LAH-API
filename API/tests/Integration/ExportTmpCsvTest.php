<?php
declare(strict_types=1);

namespace Tests\Integration;

class ExportTmpCsvTest extends BaseIntegrationTestCase {
    private string $endpoint = 'export_tmp_csv.php';
    private string $tmpCsvPath;
    private ?string $originalCsvContent = null;
    private bool $originalCsvExisted = false;

    protected function setUp(): void {
        parent::setUp();
        $this->tmpCsvPath = EXPORT_DIR . DIRECTORY_SEPARATOR . 'tmp.csv';

        // 備份現有 tmp.csv（若存在）以維持環境純淨
        if (file_exists($this->tmpCsvPath)) {
            $this->originalCsvExisted = true;
            $this->originalCsvContent = file_get_contents($this->tmpCsvPath);
        } else {
            $this->originalCsvExisted = false;
            $this->originalCsvContent = null;
            $dir = dirname($this->tmpCsvPath);
            if (!is_dir($dir)) {
                @mkdir($dir, 0777, true);
            }
        }
    }

    protected function tearDown(): void {
        // 還原原先檔案狀態
        if ($this->originalCsvExisted && $this->originalCsvContent !== null) {
            file_put_contents($this->tmpCsvPath, $this->originalCsvContent);
        } elseif (file_exists($this->tmpCsvPath)) {
            @unlink($this->tmpCsvPath);
        }
        parent::tearDown();
    }

    /**
     * 路徑 1：預設檔名下載（未帶 filename 參數，預設 tmp.csv）
     */
    public function testDefaultFilenameDownload(): void {
        $dummyContent = "id,name,role\r\n1,Alice,Admin\r\n2,Bob,User\r\n";
        file_put_contents($this->tmpCsvPath, $dummyContent);

        $res = $this->getRaw($this->endpoint);

        $this->assertSame(200, $res['http_code']);
        $this->assertArrayHasKey('content-disposition', $res['headers'], '回應應包含 Content-Disposition 標頭');
        $this->assertStringContainsString('attachment; filename=tmp.csv', $res['headers']['content-disposition']);
        if (isset($res['headers']['content-length'])) {
            $this->assertSame((string)strlen($dummyContent), $res['headers']['content-length']);
        }
        $this->assertSame($dummyContent, $res['body']);
    }

    /**
     * 路徑 2：自訂檔名下載（帶 filename 參數）
     */
    public function testCustomFilenameDownload(): void {
        $dummyContent = "code,description\r\nA01,Success\r\nE01,Fail\r\n";
        file_put_contents($this->tmpCsvPath, $dummyContent);

        $customName = 'system_export_log_' . date('Ymd');
        $res = $this->getRaw($this->endpoint, ['filename' => $customName]);

        $this->assertSame(200, $res['http_code']);
        $this->assertArrayHasKey('content-disposition', $res['headers']);
        $this->assertStringContainsString("attachment; filename={$customName}.csv", $res['headers']['content-disposition']);
        $this->assertSame($dummyContent, $res['body']);
    }

    /**
     * 路徑 3：中文檔名下載相容性
     */
    public function testChineseFilenameDownload(): void {
        $dummyContent = "序號,名稱,備註\r\n1,土地標示部,測試\r\n";
        file_put_contents($this->tmpCsvPath, $dummyContent);

        $chineseName = '桃園地政跨所案件統計';
        $res = $this->getRaw($this->endpoint, ['filename' => $chineseName]);

        $this->assertSame(200, $res['http_code']);
        $this->assertArrayHasKey('content-disposition', $res['headers']);
        $this->assertStringContainsString("filename={$chineseName}.csv", $res['headers']['content-disposition']);
        $this->assertSame($dummyContent, $res['body']);
    }

    /**
     * 路徑 4：特殊符號與版本號檔名下載
     */
    public function testSpecialCharactersFilenameDownload(): void {
        $dummyContent = "timestamp,event\r\n2026-10-27,sync_ok\r\n";
        file_put_contents($this->tmpCsvPath, $dummyContent);

        $specialName = 'export-audit_v2.1(final)';
        $res = $this->getRaw($this->endpoint, ['filename' => $specialName]);

        $this->assertSame(200, $res['http_code']);
        $this->assertArrayHasKey('content-disposition', $res['headers']);
        $this->assertStringContainsString("filename={$specialName}.csv", $res['headers']['content-disposition']);
        $this->assertSame($dummyContent, $res['body']);
    }

    /**
     * 路徑 5：POST 請求下載相容性
     */
    public function testPostRequestCompatibility(): void {
        $dummyContent = "item,qty\r\npen,10\r\npaper,50\r\n";
        file_put_contents($this->tmpCsvPath, $dummyContent);

        $res = $this->postRaw($this->endpoint . '?filename=post_test_csv');

        $this->assertSame(200, $res['http_code']);
        $this->assertArrayHasKey('content-disposition', $res['headers']);
        $this->assertStringContainsString('filename=post_test_csv.csv', $res['headers']['content-disposition']);
        $this->assertSame($dummyContent, $res['body']);
    }

    /**
     * 路徑 6：檔案不存在時的邊界情境（驗證不觸發 500 錯誤與致命異常）
     */
    public function testWhenTmpCsvDoesNotExist(): void {
        if (file_exists($this->tmpCsvPath)) {
            @unlink($this->tmpCsvPath);
        }

        $res = $this->getRaw($this->endpoint, ['filename' => 'missing_file_test']);

        $this->assertNotEquals(500, $res['http_code'], '檔案不存在時不應產生 HTTP 500');
        $this->assertStringNotContainsString('Fatal error', $res['body']);
        $this->assertStringNotContainsString('Parse error', $res['body']);
    }
}
