<?php
declare(strict_types=1);

namespace Tests\Integration;

class ExportTmpTxtTest extends BaseIntegrationTestCase {
    private string $endpoint = 'export_tmp_txt.php';
    private string $tmpTxtPath;
    private ?string $originalTxtContent = null;
    private bool $originalTxtExisted = false;
    private array $generatedTestFiles = [];

    protected function setUp(): void {
        parent::setUp();
        $this->tmpTxtPath = EXPORT_DIR . DIRECTORY_SEPARATOR . 'tmp.txt';

        // 備份現有 tmp.txt（若存在）
        if (file_exists($this->tmpTxtPath)) {
            $this->originalTxtExisted = true;
            $this->originalTxtContent = file_get_contents($this->tmpTxtPath);
        } else {
            $this->originalTxtExisted = false;
            $this->originalTxtContent = null;
            $dir = dirname($this->tmpTxtPath);
            if (!is_dir($dir)) {
                @mkdir($dir, 0777, true);
            }
        }
    }

    protected function tearDown(): void {
        // 清理測試產出的附加檔案
        foreach ($this->generatedTestFiles as $filePath) {
            if (file_exists($filePath)) {
                @unlink($filePath);
            }
        }
        $this->generatedTestFiles = [];

        // 還原原始 tmp.txt 狀態
        if ($this->originalTxtExisted && $this->originalTxtContent !== null) {
            file_put_contents($this->tmpTxtPath, $this->originalTxtContent);
        } elseif (file_exists($this->tmpTxtPath)) {
            @unlink($this->tmpTxtPath);
        }
        parent::tearDown();
    }

    /**
     * 路徑 1：預設檔名下載（未傳 filename 參數且無 session，預設 tmp.txt）
     */
    public function testDefaultFilenameDownload(): void {
        $dummyContent = "Log Line 1: Service started\r\nLog Line 2: Process completed successfully\r\n";
        file_put_contents($this->tmpTxtPath, $dummyContent);

        $res = $this->getRaw($this->endpoint);

        $this->assertSame(200, $res['http_code']);
        $this->assertArrayHasKey('content-disposition', $res['headers'], '回應應包含 Content-Disposition 標頭');
        $this->assertStringContainsString('attachment; filename=tmp.txt', $res['headers']['content-disposition']);
        if (isset($res['headers']['content-length'])) {
            $this->assertSame((string)strlen($dummyContent), $res['headers']['content-length']);
        }
        $this->assertSame($dummyContent, $res['body']);
    }

    /**
     * 路徑 2：自訂檔名下載（帶 filename GET 參數）
     */
    public function testCustomFilenameDownload(): void {
        $dummyContent = "Custom Report TXT Content 2026";
        file_put_contents($this->tmpTxtPath, $dummyContent);

        $customName = 'system_monitor_export_' . date('YmdHis');
        $res = $this->getRaw($this->endpoint, ['filename' => $customName]);

        $this->assertSame(200, $res['http_code']);
        $this->assertArrayHasKey('content-disposition', $res['headers']);
        $this->assertStringContainsString("attachment; filename={$customName}.txt", $res['headers']['content-disposition']);
        $this->assertSame($dummyContent, $res['body']);
    }

    /**
     * 路徑 3：中文檔名下載相容性
     */
    public function testChineseFilenameDownload(): void {
        $dummyContent = "桃園地政主機歷史操作稽核紀錄內容";
        file_put_contents($this->tmpTxtPath, $dummyContent);

        $chineseName = '桃園地政主機日誌記錄表';
        $res = $this->getRaw($this->endpoint, ['filename' => $chineseName]);

        $this->assertSame(200, $res['http_code']);
        $this->assertArrayHasKey('content-disposition', $res['headers']);
        $this->assertStringContainsString("filename={$chineseName}.txt", $res['headers']['content-disposition']);
        $this->assertSame($dummyContent, $res['body']);
    }

    /**
     * 路徑 4：特殊字元與版號檔名下載相容性
     */
    public function testSpecialCharactersFilenameDownload(): void {
        $dummyContent = "Special build release notes";
        file_put_contents($this->tmpTxtPath, $dummyContent);

        $specialName = 'audit_v2.5-rc1(debug-mode)';
        $res = $this->getRaw($this->endpoint, ['filename' => $specialName]);

        $this->assertSame(200, $res['http_code']);
        $this->assertArrayHasKey('content-disposition', $res['headers']);
        $this->assertStringContainsString("filename={$specialName}.txt", $res['headers']['content-disposition']);
        $this->assertSame($dummyContent, $res['body']);
    }

    /**
     * 路徑 5：POST 請求相容性
     */
    public function testPostRequestCompatibility(): void {
        $dummyContent = "Data fetched via POST query string";
        file_put_contents($this->tmpTxtPath, $dummyContent);

        $res = $this->postRaw($this->endpoint . '?filename=post_txt_check');

        $this->assertSame(200, $res['http_code']);
        $this->assertArrayHasKey('content-disposition', $res['headers']);
        $this->assertStringContainsString('filename=post_txt_check.txt', $res['headers']['content-disposition']);
        $this->assertSame($dummyContent, $res['body']);
    }

    /**
     * 路徑 6：Session 連動複製目標檔案分支（$_SESSION['export_tmp_txt_filename'] != 'tmp'）
     */
    public function testSessionCopyTxtTargetFile(): void {
        $targetBasename = 'session_copy_integ_' . uniqid();
        $targetFullPath = EXPORT_DIR . DIRECTORY_SEPARATOR . $targetBasename . '.txt';
        $this->generatedTestFiles[] = $targetFullPath;

        // 1. 指派新 Session 並透過 export_file_api 設定 $_SESSION['export_tmp_txt_filename']
        $this->useNewSession();
        $this->postRaw('export_file_api.php', [
            'type' => 'file_sql_txt',
            'filename' => $targetBasename,
            'sql' => 'select 1'
        ]);

        // 2. 寫入特定內容至 tmp.txt 以精確驗證複製一致性
        $dummyContent = "Source content to be replicated to session target: " . uniqid();
        file_put_contents($this->tmpTxtPath, $dummyContent);

        // 3. 請求 export_tmp_txt.php（帶同一個 Session）
        $res = $this->getRaw($this->endpoint);

        $this->assertSame(200, $res['http_code']);
        $this->assertSame($dummyContent, $res['body']);

        // 4. 驗證 target 檔案已被複製產出且內容一致
        $this->assertFileExists($targetFullPath, "呼叫端點後應成功複製建立 {$targetFullPath}");
        $this->assertSame($dummyContent, file_get_contents($targetFullPath));
    }

    /**
     * 路徑 7：Session 檔名為 tmp 分支（不複製自身）
     */
    public function testSessionValueIsTmpDoesNotCopy(): void {
        $this->useNewSession();
        $this->postRaw('export_file_api.php', [
            'type' => 'file_sql_txt',
            'filename' => 'tmp',
            'sql' => 'select 1'
        ]);

        $dummyContent = "Content with session set to tmp: " . uniqid();
        file_put_contents($this->tmpTxtPath, $dummyContent);

        $res = $this->getRaw($this->endpoint);

        $this->assertSame(200, $res['http_code']);
        $this->assertSame($dummyContent, $res['body']);

        // 驗證未產生異常重複副檔名檔案
        $this->assertFileDoesNotExist(EXPORT_DIR . DIRECTORY_SEPARATOR . 'tmp.txt.txt');
    }

    /**
     * 路徑 8：檔案不存在邊界情境（驗證不崩潰、無致命錯誤）
     */
    public function testWhenTmpTxtDoesNotExist(): void {
        if (file_exists($this->tmpTxtPath)) {
            @unlink($this->tmpTxtPath);
        }

        $res = $this->getRaw($this->endpoint, ['filename' => 'missing_txt_file']);

        $this->assertNotEquals(500, $res['http_code'], '檔案不存在時不應產生 HTTP 500');
        $this->assertStringNotContainsString('Fatal error', $res['body']);
        $this->assertStringNotContainsString('Parse error', $res['body']);
    }
}
