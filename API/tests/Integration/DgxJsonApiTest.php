<?php
declare(strict_types=1);

namespace Tests\Integration;

use STATUS_CODE;

class DgxJsonApiTest extends BaseIntegrationTestCase {
    private string $endpoint = 'dgx_json_api.php';

    /**
     * 包裝 POST 請求，預設附帶隨機隔離 IP 避免受到全域單一 IP 頻率限制干擾
     */
    private function postDgx(array $data): array {
        if (!isset($data['req_ip'])) {
            $data['req_ip'] = '10.200.' . random_int(1, 250) . '.' . random_int(1, 250);
        }
        return $this->post($this->endpoint, $data);
    }

    /**
     * 路徑 1：不支援的請求方法（GET 請求觸發 HTTP 405）
     */
    public function testMethodNotAllowedForGet(): void {
        $res = $this->get($this->endpoint);

        $this->assertSame(405, $this->getLastHttpCode(), 'GET 請求應回傳 HTTP 405 Method Not Allowed');
        $this->assertResponseFail($res, STATUS_CODE::DEFAULT_FAIL, '不支援的請求方法');
    }

    /**
     * 路徑 2：不支援的 operation type（default 分支觸發 HTTP 400）
     */
    public function testUnsupportedTypeReturnsError(): void {
        $res = $this->postDgx([
            'type' => 'unknown_dgx_type'
        ]);

        $this->assertSame(400, $this->getLastHttpCode(), '不支援的 type 應回傳 HTTP 400');
        $this->assertResponseUnsupport($res);
        $this->assertStringContainsString('未指定或不支援的操作類型', $res['message']);
    }

    /**
     * 路徑 3：空 type 參數（觸發 HTTP 400）
     */
    public function testEmptyTypeReturnsError(): void {
        $res = $this->postDgx([]);

        $this->assertSame(400, $this->getLastHttpCode());
        $this->assertResponseUnsupport($res);
    }

    /**
     * 路徑 4：case_ids 輸入空字串（觸發 HTTP 400）
     */
    public function testEmptyTextReturnsError(): void {
        $res = $this->postDgx([
            'type' => 'case_ids',
            'text' => ''
        ]);

        $this->assertSame(400, $this->getLastHttpCode());
        $this->assertResponseFail($res, STATUS_CODE::DEFAULT_FAIL, '輸入字串不能為空');
    }

    /**
     * 路徑 5：case_ids 輸入超長字串超過 1000 字元（觸發 HTTP 400）
     */
    public function testOverlyLongTextReturnsError(): void {
        $res = $this->postDgx([
            'type' => 'case_ids',
            'text' => str_repeat('地政案號', 260) // 260 * 4 = 1040 字元
        ]);

        $this->assertSame(400, $this->getLastHttpCode());
        $this->assertResponseFail($res, STATUS_CODE::DEFAULT_FAIL, '輸入字串長度超過限制');
    }

    /**
     * 路徑 6：正常解析地政案號成功（case_ids 標準正規化）
     */
    public function testParseLandCaseSuccess(): void {
        $res = $this->postDgx([
            'type' => 'case_ids',
            'text' => '112年桃資登字第123450號'
        ]);

        $this->assertSame(200, $this->getLastHttpCode());
        $this->assertResponseSuccess($res);
        $this->assertArrayHasKey('data', $res);
        $this->assertNotEmpty($res['data']);
        $this->assertArrayHasKey('query_count', $res);
        $this->assertGreaterThanOrEqual(1, $res['query_count']);

        $item = $res['data'][0];
        $this->assertSame('112-HA81-123450', $item['normalized']);
        $this->assertSame(112, $item['year_miguo']);
        $this->assertSame('HA81', $item['case_word']);
        $this->assertSame('桃資登', $item['case_word_desc']);
        $this->assertSame('123450', $item['case_no']);
    }

    /**
     * 路徑 7：觸發速率限制（Rate Limiting HTTP 429）
     */
    public function testRateLimitExceeded(): void {
        $testIp = '198.51.100.' . random_int(10, 200);

        // 速率限制上限為 10 次，連續發送 10 次正常通過
        for ($i = 0; $i < 10; $i++) {
            $res = $this->post($this->endpoint, [
                'type' => 'case_ids',
                'text' => '112年桃資登字第123450號',
                'req_ip' => $testIp
            ]);
            $this->assertSame(200, $this->getLastHttpCode());
        }

        // 第 11 次請求應觸發 HTTP 429
        $limitRes = $this->post($this->endpoint, [
            'type' => 'case_ids',
            'text' => '112年桃資登字第123450號',
            'req_ip' => $testIp
        ]);

        $this->assertSame(429, $this->getLastHttpCode(), '超出頻率應回傳 HTTP 429 Too Many Requests');
        $this->assertResponseFail($limitRes, STATUS_CODE::DEFAULT_FAIL, '請求過於頻繁，請稍後再試');
    }
}
