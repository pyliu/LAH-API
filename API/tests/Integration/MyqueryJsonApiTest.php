<?php
declare(strict_types=1);

namespace Tests\Integration;

use STATUS_CODE;

class MyqueryJsonApiTest extends BaseIntegrationTestCase {
    private string $endpoint = 'myquery_json_api.php';

    /**
     * 路徑 1：尚未實作之保留端點測試 (type=???)
     */
    public function testQuestionMarksType(): void {
        $res = $this->post($this->endpoint, [
            'type' => '???'
        ]);
        $this->assertSame(STATUS_CODE::DEFAULT_FAIL, $res['status']);
        $this->assertSame('IMPLing', $res['message']);
    }

    /**
     * 路徑 2：不支援的查詢型態防護 (type=unsupported)
     */
    public function testUnsupportedType(): void {
        $res = $this->post($this->endpoint, [
            'type' => 'unsupported_test_type'
        ]);
        $this->assertSame(STATUS_CODE::UNSUPPORT_FAIL, $res['status']);
        $this->assertStringContainsString('不支援的查詢型態【unsupported_test_type】', $res['message']);
    }

    /**
     * 路徑 3：空請求防護 (empty post)
     */
    public function testEmptyPost(): void {
        $res = $this->post($this->endpoint, []);
        $this->assertSame(STATUS_CODE::UNSUPPORT_FAIL, $res['status']);
        $this->assertStringContainsString('不支援的查詢型態【】', $res['message']);
    }
}
