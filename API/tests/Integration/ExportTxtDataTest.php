<?php
declare(strict_types=1);

namespace Tests\Integration;

use STATUS_CODE;

class ExportTxtDataTest extends BaseIntegrationTestCase {
    private string $endpoint = 'export_txt_data.php';
    private string $expFolder;
    private array $tempTestFiles = [];

    protected function setUp(): void {
        parent::setUp();
        $this->expFolder = EXPORT_DIR . DIRECTORY_SEPARATOR;
        if (!is_dir($this->expFolder)) {
            @mkdir($this->expFolder, 0777, true);
        }
    }

    protected function tearDown(): void {
        foreach ($this->tempTestFiles as $filePath) {
            if (file_exists($filePath)) {
                @unlink($filePath);
            }
        }
        $this->tempTestFiles = [];
        parent::tearDown();
    }

    private function createTempFile(string $filename, string $content): string {
        $fullPath = $this->expFolder . $filename;
        file_put_contents($fullPath, $content);
        $this->tempTestFiles[] = $fullPath;
        return $fullPath;
    }

    /**
     * 路徑 1：清理已匯出資料（type=clean 分支）
     */
    public function testCleanExportFilesViaTypeClean(): void {
        $dummyName = 'AI00301_dummy_clean_' . uniqid() . '.txt';
        $this->createTempFile($dummyName, 'dummy clean data');

        $res = $this->post($this->endpoint, [
            'type' => 'clean',
            'filenames' => $dummyName
        ]);

        $this->assertResponseSuccess($res);
        $this->assertArrayHasKey('deleted_count', $res);
        $this->assertGreaterThanOrEqual(1, $res['deleted_count']);
        $this->assertFileDoesNotExist($this->expFolder . $dummyName);
    }

    /**
     * 路徑 2：清理已匯出資料（type=file_data_clean 分支）
     */
    public function testCleanExportFilesViaTypeFileDataClean(): void {
        $dummyName = 'AI00401_dummy_clean_' . uniqid() . '.txt';
        $this->createTempFile($dummyName, 'dummy clean data 2');

        $res = $this->post($this->endpoint, [
            'type' => 'file_data_clean',
            'filenames' => $dummyName
        ]);

        $this->assertResponseSuccess($res);
        $this->assertFileDoesNotExist($this->expFolder . $dummyName);
    }

    /**
     * 路徑 3：清理已匯出資料（clean=1 參數分支）
     */
    public function testCleanExportFilesViaCleanParam(): void {
        $dummyName = 'AI00701_dummy_clean_' . uniqid() . '.txt';
        $this->createTempFile($dummyName, 'dummy clean data 3');

        $res = $this->post($this->endpoint, [
            'clean' => '1',
            'filenames' => $dummyName
        ]);

        $this->assertResponseSuccess($res);
        $this->assertFileDoesNotExist($this->expFolder . $dummyName);
    }

    /**
     * 路徑 4：ZIP 打包下載（指定 filenames 逗號分隔與自訂 zip_filename）
     */
    public function testZipDownloadWithFilenames(): void {
        $f1 = 'pack_test_1_' . uniqid() . '.txt';
        $f2 = 'pack_test_2_' . uniqid() . '.txt';
        $content1 = "File 1 content\nLine 2";
        $content2 = "File 2 content\nLine B";
        $this->createTempFile($f1, $content1);
        $this->createTempFile($f2, $content2);

        $zipName = 'custom_pack_' . uniqid();
        $expectedZipPath = $this->expFolder . $zipName . '.zip';
        $this->tempTestFiles[] = $expectedZipPath;

        $res = $this->getRaw($this->endpoint, [
            'type' => 'zip',
            'filenames' => "{$f1},{$f2}",
            'zip_filename' => $zipName
        ]);

        $this->assertSame(200, $res['http_code']);
        $this->assertStringContainsString('application/zip', $res['content_type']);
        $this->assertArrayHasKey('content-disposition', $res['headers']);
        $this->assertStringContainsString("filename*=UTF-8''{$zipName}.zip", $res['headers']['content-disposition']);

        // 驗證 ZIP 檔案特徵碼 (PK\x03\x04)
        $this->assertStringStartsWith("PK\x03\x04", $res['body']);

        // 驗證 ZIP 解壓縮與檔案內容完整性
        $zip = new \ZipArchive();
        $this->assertTrue($zip->open($expectedZipPath));
        $this->assertSame($content1, $zip->getFromName($f1));
        $this->assertSame($content2, $zip->getFromName($f2));
        $zip->close();
    }

    /**
     * 路徑 5：ZIP 打包下載（依 section 段別自動 glob 匹配）
     */
    public function testZipDownloadWithSectionMatching(): void {
        $sectionCode = 'SEC' . rand(1000, 9999);
        $matchedFile = "20261027_{$sectionCode}_data.txt";
        $content = "Section matched test data";
        $this->createTempFile($matchedFile, $content);

        $zipName = 'section_pack_' . uniqid();
        $expectedZipPath = $this->expFolder . $zipName . '.zip';
        $this->tempTestFiles[] = $expectedZipPath;

        $res = $this->getRaw($this->endpoint, [
            'type' => 'zip',
            'section' => $sectionCode,
            'zip_filename' => $zipName
        ]);

        $this->assertSame(200, $res['http_code']);
        $this->assertStringStartsWith("PK\x03\x04", $res['body']);

        $zip = new \ZipArchive();
        $this->assertTrue($zip->open($expectedZipPath));
        $this->assertSame($content, $zip->getFromName($matchedFile));
        $zip->close();
    }

    /**
     * 路徑 6：ZIP 打包無檔案可打包（回傳 404 分支）
     */
    public function testZipDownloadNoFilesFoundReturns404(): void {
        $res = $this->getRaw($this->endpoint, [
            'type' => 'zip',
            'filenames' => 'non_existent_file_' . uniqid() . '.txt'
        ]);

        $this->assertSame(404, $res['http_code']);
        $this->assertStringContainsString('找不到可供打包的檔案', $res['body']);
    }

    /**
     * 路徑 7：單檔下載（直接帶 filename 參數）
     */
    public function testSingleFileDownloadWithDirectFilename(): void {
        $filename = 'direct_file_' . uniqid() . '.txt';
        $content = "Direct download test payload: " . uniqid();
        $this->createTempFile($filename, $content);

        $res = $this->getRaw($this->endpoint, [
            'filename' => $filename
        ]);

        $this->assertSame(200, $res['http_code']);
        $this->assertArrayHasKey('content-disposition', $res['headers']);
        $this->assertStringContainsString("filename*=UTF-8''{$filename}", $res['headers']['content-disposition']);
        $this->assertSame($content, $res['body']);
    }

    /**
     * 路徑 8：單檔下載（未帶 filename，由 Session 中的 $_SESSION[$code] 取得檔名）
     */
    public function testSingleFileDownloadViaSessionCode(): void {
        // 1. 使用獨立 Session 呼叫 export_file_api 匯出資料並將檔名寫入 Session[$code]
        $this->useNewSession();
        $code = 'AI00301';
        $exportRes = $this->post('export_file_api.php', [
            'type' => 'file_data_export',
            'code' => $code,
            'section' => ['0001']
        ]);

        $this->assertResponseSuccess($exportRes);
        $generatedFilename = $exportRes['filename'];
        $this->tempTestFiles[] = $this->expFolder . $generatedFilename;

        // 2. 請求 export_txt_data.php?code=AI00301（不帶 filename，帶同一 Session）
        $res = $this->getRaw($this->endpoint, [
            'code' => $code
        ]);

        $this->assertSame(200, $res['http_code']);
        $this->assertArrayHasKey('content-disposition', $res['headers']);
        $this->assertStringContainsString("filename*=UTF-8''" . rawurlencode($generatedFilename), $res['headers']['content-disposition']);
        $this->assertSame(file_get_contents($this->expFolder . $generatedFilename), $res['body']);
    }

    /**
     * 路徑 9：單檔下載（未帶 filename 且無 Session，依 code glob 最新產製檔案）
     */
    public function testSingleFileDownloadViaCodeGlobMatchingLatest(): void {
        $uniqueCode = 'CODE' . rand(10000, 99999);
        $oldFile = "1000_{$uniqueCode}_old.txt";
        $newFile = "2000_{$uniqueCode}_new.txt";

        $oldContent = "Older file content";
        $newContent = "Newer file content";

        $oldPath = $this->createTempFile($oldFile, $oldContent);
        $newPath = $this->createTempFile($newFile, $newContent);

        // 調整檔案修改時間以確保順序
        touch($oldPath, time() - 100);
        touch($newPath, time());

        // 請求 export_txt_data.php?code=...（無 Session、無 filename）
        $this->clearSession();
        $res = $this->getRaw($this->endpoint, [
            'code' => $uniqueCode
        ]);

        $this->assertSame(200, $res['http_code']);
        $this->assertArrayHasKey('content-disposition', $res['headers']);
        $this->assertStringContainsString("filename*=UTF-8''{$newFile}", $res['headers']['content-disposition']);
        $this->assertSame($newContent, $res['body']);
    }

    /**
     * 路徑 10：單檔下載查無檔案（回傳 404 分支）
     */
    public function testSingleFileNotFoundReturns404(): void {
        $missingFilename = 'non_existent_' . uniqid() . '.txt';
        $res = $this->getRaw($this->endpoint, [
            'filename' => $missingFilename,
            'code' => 'XYZ99'
        ]);

        $this->assertSame(404, $res['http_code']);
        $this->assertStringContainsString('找不到可供下載的檔案', $res['body']);
        $this->assertStringContainsString($missingFilename, $res['body']);
        $this->assertStringContainsString('XYZ99', $res['body']);
    }
}
