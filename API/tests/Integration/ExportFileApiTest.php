<?php
declare(strict_types=1);

namespace Tests\Integration;

use STATUS_CODE;

class ExportFileApiTest extends BaseIntegrationTestCase {
    private string $endpoint = 'export_file_api.php';
    private array $tempExportFiles = [];

    protected function tearDown(): void {
        foreach ($this->tempExportFiles as $file) {
            if (file_exists($file)) {
                @unlink($file);
            }
        }
        $this->tempExportFiles = [];
        parent::tearDown();
    }

    /**
     * 路徑 1：不支援的匯出類型（default 分支回傳 CSV 錯誤說明）
     */
    public function testUnsupportedTypeReturnsErrorCsv(): void {
        $res = $this->postRaw($this->endpoint, [
            'type' => 'invalid_export_type_xyz'
        ]);

        $this->assertSame(200, $res['http_code']);
        $this->assertStringContainsString('text/csv', $res['content_type']);

        $utf8Body = mb_convert_encoding($res['body'], 'UTF-8', 'BIG-5');
        $this->assertStringContainsString('不支援的匯出型態', $utf8Body);
    }

    /**
     * 路徑 2：空 type 參數（防護分支）
     */
    public function testEmptyTypeReturnsErrorCsv(): void {
        $res = $this->postRaw($this->endpoint, []);

        $this->assertSame(200, $res['http_code']);
        $this->assertStringContainsString('text/csv', $res['content_type']);

        $utf8Body = mb_convert_encoding($res['body'], 'UTF-8', 'BIG-5');
        $this->assertStringContainsString('不支援的匯出型態', $utf8Body);
    }

    /**
     * 路徑 3：查詢已產出地籍檔案清單（file_data_list）
     */
    public function testFileDataList(): void {
        $res = $this->post($this->endpoint, [
            'type' => 'file_data_list'
        ]);

        $this->assertResponseSuccess($res, '取得後端產出檔案清單成功');
        $this->assertArrayHasKey('data', $res);
        $this->assertIsArray($res['data']);
    }

    /**
     * 路徑 4：清理地籍產出檔案無相符檔案（file_data_clean 查無資料分支）
     */
    public function testFileDataCleanNoFiles(): void {
        $res = $this->post($this->endpoint, [
            'type' => 'file_data_clean',
            'filenames' => 'NON_EXISTENT_FILE_' . uniqid() . '.txt'
        ]);

        $this->assertSame(STATUS_CODE::SUCCESS_WITH_NO_RECORD, $res['status']);
        $this->assertStringContainsString('後端無符合條件之產出檔案可供清理', $res['message']);
        $this->assertSame(0, $res['deleted_count']);
    }

    /**
     * 路徑 5：產出檔案清單列出與清理生命週期（file_data_list 與 file_data_clean 刪除成功）
     */
    public function testFileDataListAndCleanLifecycle(): void {
        $expFolder = EXPORT_DIR . DIRECTORY_SEPARATOR;
        if (!is_dir($expFolder)) {
            @mkdir($expFolder, 0777, true);
        }

        $dummyName = 'AI00301_integ_test_' . uniqid() . '.txt';
        $dummyPath = $expFolder . $dummyName;
        file_put_contents($dummyPath, 'Dummy export data');
        $this->tempExportFiles[] = $dummyPath;

        // 1. 驗證能在清單中取得此檔案
        $listRes = $this->post($this->endpoint, [
            'type' => 'file_data_list'
        ]);
        $this->assertResponseSuccess($listRes);
        $filenames = array_column($listRes['data'], 'filename');
        $this->assertContains($dummyName, $filenames, "清單中應包含新產出檔案 {$dummyName}");

        // 2. 執行清理刪除
        $cleanRes = $this->post($this->endpoint, [
            'type' => 'file_data_clean',
            'filenames' => $dummyName
        ]);
        $this->assertResponseSuccess($cleanRes, '成功清理');
        $this->assertSame(1, $cleanRes['deleted_count']);
        $this->assertFalse(file_exists($dummyPath), '檔案應已被成功刪除');
    }

    /**
     * 路徑 6：匯出 LOG 檔案（file_log 分支）
     */
    public function testFileLogExport(): void {
        $today = date('Y-m-d');
        $res = $this->postRaw($this->endpoint, [
            'type' => 'file_log',
            'date' => $today
        ]);

        $this->assertSame(200, $res['http_code']);
        $this->assertStringContainsString('text/log', $res['content_type']);
        $this->assertNotEmpty($res['body']);
    }

    /**
     * 路徑 7：匯出 SQL CSV 檔案暫存（file_sql_csv 分支）
     */
    public function testFileSqlCsvCreatesTmpFile(): void {
        $res = $this->postRaw($this->endpoint, [
            'type' => 'file_sql_csv',
            'sql' => 'SELECT 1 FROM DUAL'
        ]);

        $this->assertSame(200, $res['http_code']);
        $tmpCsv = EXPORT_DIR . DIRECTORY_SEPARATOR . 'tmp.csv';
        $this->assertTrue(file_exists($tmpCsv), '應該產出 export/tmp.csv 暫存檔');
    }

    /**
     * 路徑 8：匯出 SQL TXT 檔案暫存（file_sql_txt 分支）
     */
    public function testFileSqlTxtCreatesTmpFile(): void {
        $res = $this->postRaw($this->endpoint, [
            'type' => 'file_sql_txt',
            'sql' => 'SELECT 1 FROM DUAL'
        ]);

        $this->assertSame(200, $res['http_code']);
        $tmpTxt = EXPORT_DIR . DIRECTORY_SEPARATOR . 'tmp.txt';
        $this->assertTrue(file_exists($tmpTxt), '應該產出 export/tmp.txt 暫存檔');
    }

    /**
     * 路徑 9：地籍代碼 TXT 匯出範本不存在錯誤（file_data_export 錯誤分支）
     */
    public function testFileDataExportInvalidCodeReturnsError(): void {
        $res = $this->post($this->endpoint, [
            'type' => 'file_data_export',
            'code' => 'INVALID_TPL_CODE'
        ]);

        $this->assertSame(STATUS_CODE::FAIL_LOAD_ERROR, $res['status']);
        $this->assertStringContainsString('無法讀取', $res['message']);
    }

    /**
     * 路徑 10：匯出使用者名冊 XLSX（file_xlsx 分支）
     */
    public function testFileXlsxExport(): void {
        $res = $this->postRaw($this->endpoint, [
            'type' => 'file_xlsx',
            'xlsx_type' => 'all_users_export'
        ]);

        $this->assertSame(200, $res['http_code']);
        $this->assertStringContainsString('spreadsheet', $res['content_type']);
        $this->assertStringStartsWith("PK\x03\x04", $res['body'], '回傳二進位串流必須為合法 ZIP/XLSX 檔案標頭');
    }

    /**
     * 路徑 11：匯出外國人地權限制清冊 XLSX（file_inheritance_restriction_xlsx 分支）
     */
    public function testFileInheritanceRestrictionXlsxExport(): void {
        $res = $this->postRaw($this->endpoint, [
            'type' => 'file_inheritance_restriction_xlsx',
            'site' => 'HA',
            'is17' => 'true'
        ]);

        $this->assertSame(200, $res['http_code']);
        $this->assertStringContainsString('spreadsheet', $res['content_type']);
        $this->assertStringStartsWith("PK\x03\x04", $res['body'], '回傳二進位串流必須為合法 ZIP/XLSX 檔案標頭');
    }

    /**
     * 路徑 12：匯出測量案件追蹤清冊 XLSX（file_sur_tracking_xlsx 分支）
     */
    public function testFileSurTrackingXlsxExport(): void {
        $res = $this->postRaw($this->endpoint, [
            'type' => 'file_sur_tracking_xlsx',
            'site' => 'HA'
        ]);

        $this->assertSame(200, $res['http_code']);
        $this->assertStringContainsString('spreadsheet', $res['content_type']);
        $this->assertStringStartsWith("PK\x03\x04", $res['body'], '回傳二進位串流必須為合法 ZIP/XLSX 檔案標頭');
    }
}
