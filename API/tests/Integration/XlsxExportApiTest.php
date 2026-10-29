<?php
declare(strict_types=1);

namespace Tests\Integration;

use STATUS_CODE;

class XlsxExportApiTest extends BaseIntegrationTestCase {
    private string $endpoint = 'xlsx_export_api.php';

    /**
     * 路徑 1：不支援的查詢型態（default 分支）
     */
    public function testUnsupportedTypeReturnsError(): void {
        $res = $this->post($this->endpoint, [
            'type' => 'unknown_xlsx_export_action'
        ]);

        $this->assertResponseUnsupport($res);
        $this->assertStringContainsString('不支援的查詢型態【unknown_xlsx_export_action】', $res['message']);
    }

    /**
     * 路徑 2：空 type 參數防護
     */
    public function testEmptyTypeReturnsError(): void {
        $res = $this->post($this->endpoint, []);

        $this->assertResponseUnsupport($res);
        $this->assertStringContainsString('不支援的查詢型態', $res['message']);
    }

    /**
     * 路徑 3：xlsx_export_overdue 逾期匯出作業預留端點
     */
    public function testXlsxExportOverdue(): void {
        $res = $this->post($this->endpoint, [
            'type' => 'xlsx_export_overdue'
        ]);

        $this->assertEquals(STATUS_CODE::DEFAULT_FAIL, $res['status']);
        $this->assertStringContainsString('IMPLing', $res['message']);
    }
}
