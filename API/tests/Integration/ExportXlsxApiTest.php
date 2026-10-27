<?php
declare(strict_types=1);

namespace Tests\Integration;

class ExportXlsxApiTest extends BaseIntegrationTestCase {
    private string $endpoint = 'export_xlsx_api.php';
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

    /**
     * 路徑 1：預設 type 匯出使用者名冊 XLSX（未帶 type 參數，預設 file_xlsx，帶 xlsx_type=all_users_export）
     */
    public function testDefaultTypeUsersExport(): void {
        $res = $this->postRaw($this->endpoint, [
            'xlsx_type' => 'all_users_export'
        ]);

        $this->assertSame(200, $res['http_code']);
        $this->assertStringContainsString('spreadsheet', $res['content_type']);
        $this->assertArrayHasKey('content-disposition', $res['headers']);
        $this->assertStringContainsString('_users.xlsx', $res['headers']['content-disposition']);
        $this->assertStringStartsWith("PK\x03\x04", $res['body'], '回傳二進位串流必須為合法 ZIP/XLSX 檔案標頭');
    }

    /**
     * 路徑 2：HTTP GET 請求匯出使用者名冊 XLSX 相容性（端點使用 $_REQUEST）
     */
    public function testGetRequestUsersExport(): void {
        $res = $this->getRaw($this->endpoint, [
            'xlsx_type' => 'all_users_export'
        ]);

        $this->assertSame(200, $res['http_code']);
        $this->assertStringContainsString('spreadsheet', $res['content_type']);
        $this->assertArrayHasKey('content-disposition', $res['headers']);
        $this->assertStringContainsString('_users.xlsx', $res['headers']['content-disposition']);
        $this->assertStringStartsWith("PK\x03\x04", $res['body']);
    }

    /**
     * 路徑 3：顯式 type=file_xlsx 匯出謄本列印紀錄（xlsx_type=cert_log 分支）
     */
    public function testCertLogExport(): void {
        $res = $this->postRaw($this->endpoint, [
            'type' => 'file_xlsx',
            'xlsx_type' => 'cert_log',
            'section_code' => 'HA',
            'numbers' => ['0001']
        ]);

        $this->assertSame(200, $res['http_code']);
        $this->assertStringContainsString('spreadsheet', $res['content_type']);
        $this->assertArrayHasKey('content-disposition', $res['headers']);
        $this->assertStringContainsString('.xlsx', $res['headers']['content-disposition']);
        $this->assertStringStartsWith("PK\x03\x04", $res['body']);
    }

    /**
     * 路徑 4：匯出外國人地權限制清冊 XLSX（file_inheritance_restriction_xlsx 分支）
     */
    public function testInheritanceRestrictionXlsxExport(): void {
        $res = $this->postRaw($this->endpoint, [
            'type' => 'file_inheritance_restriction_xlsx',
            'site' => 'HA',
            'is17' => 'true'
        ]);

        $this->assertSame(200, $res['http_code']);
        $this->assertStringContainsString('spreadsheet', $res['content_type']);
        $this->assertArrayHasKey('content-disposition', $res['headers']);
        $this->assertStringContainsString('.xlsx', $res['headers']['content-disposition']);
        $this->assertStringStartsWith("PK\x03\x04", $res['body']);
    }

    /**
     * 路徑 5：匯出測量案件追蹤清冊 XLSX（file_sur_tracking_xlsx 分支）
     */
    public function testSurTrackingXlsxExport(): void {
        $res = $this->postRaw($this->endpoint, [
            'type' => 'file_sur_tracking_xlsx',
            'site' => 'HA'
        ]);

        $this->assertSame(200, $res['http_code']);
        $this->assertStringContainsString('spreadsheet', $res['content_type']);
        $this->assertArrayHasKey('content-disposition', $res['headers']);
        $this->assertStringContainsString('.xlsx', $res['headers']['content-disposition']);
        $this->assertStringStartsWith("PK\x03\x04", $res['body']);
    }

    /**
     * 路徑 6：不支援的匯出型態（走入 FileAPINotSupportCommand 預設錯誤 CSV 分支）
     */
    public function testUnsupportedExportTypeReturnsErrorCsv(): void {
        $res = $this->postRaw($this->endpoint, [
            'type' => 'invalid_excel_export_type_xyz'
        ]);

        $this->assertSame(200, $res['http_code']);
        $this->assertStringContainsString('text/csv', $res['content_type']);

        $utf8Body = mb_convert_encoding($res['body'], 'UTF-8', 'BIG-5');
        $this->assertStringContainsString('不支援的匯出型態', $utf8Body);
    }

    /**
     * 路徑 7：未傳任何參數時的預設防護邊界（無 xlsx_type 不產生 Fatal error）
     */
    public function testEmptyParametersSafeFallback(): void {
        $res = $this->postRaw($this->endpoint, []);

        $this->assertSame(200, $res['http_code']);
        $this->assertNotEquals(500, $res['http_code']);
        $this->assertStringNotContainsString('Fatal error', $res['body']);
        $this->assertStringNotContainsString('Parse error', $res['body']);
    }
}
