<?php
declare(strict_types=1);

namespace Tests\Integration;

use STATUS_CODE;

class MoiexpJsonApiTest extends BaseIntegrationTestCase {
    private string $endpoint = 'moiexp_json_api.php';

    /**
     * 路徑 1：查詢規費收費類別 (type=expe)
     */
    public function testExpe(): void {
        $res = $this->post($this->endpoint, [
            'type' => 'expe'
        ]);

        $this->assertResponseSuccess($res);
        $this->assertArrayHasKey('raw', $res);
        $this->assertIsArray($res['raw']);
        $this->assertGreaterThan(0, $res['data_count']);
    }

    /**
     * 路徑 2：查詢規費收費項目 (type=expac)
     */
    public function testExpac(): void {
        $res = $this->post($this->endpoint, [
            'type' => 'expac',
            'year' => '114',
            'num' => '1'
        ]);

        $this->assertResponseSuccess($res);
        $this->assertArrayHasKey('raw', $res);
        $this->assertIsArray($res['raw']);
    }

    /**
     * 路徑 3：查詢規費付款項目 (type=expk)
     */
    public function testExpk(): void {
        $res = $this->post($this->endpoint, [
            'type' => 'expk'
        ]);

        $this->assertResponseSuccess($res);
        $this->assertArrayHasKey('raw', $res);
        $this->assertIsArray($res['raw']);
        $this->assertGreaterThan(0, $res['data_count']);
    }

    /**
     * 路徑 4：查詢規費資料 (type=expaa)
     */
    public function testExpaa(): void {
        $res = $this->post($this->endpoint, [
            'type' => 'expaa',
            'qday' => '1140101'
        ]);

        $this->assertContains($res['status'], [
            STATUS_CODE::SUCCESS_NORMAL,
            STATUS_CODE::SUCCESS_WITH_MULTIPLE_RECORDS
        ]);
        $this->assertArrayHasKey('raw', $res);
    }

    /**
     * 路徑 5：查詢最大電腦給號與最新單據編號 (type=expaa_max_pc|expaa_latest_aa，修復減號筆誤語法)
     */
    public function testExpaaMaxPcAndLatestAa(): void {
        // 最大電腦給號
        $resMax = $this->post($this->endpoint, [
            'type' => 'expaa_max_pc',
            'year' => '114'
        ]);
        $this->assertResponseSuccess($resMax);
        $this->assertArrayHasKey('message', $resMax);
        $this->assertStringContainsString('114年度，最大電腦給號為', $resMax['message']);

        // 最新單據編號
        $resLatest = $this->post($this->endpoint, [
            'type' => 'expaa_latest_aa',
            'year' => '114'
        ]);
        $this->assertResponseSuccess($resLatest);
        $this->assertArrayHasKey('message', $resLatest);
        $this->assertStringContainsString('114年度，最大電腦給號為', $resLatest['message']);
    }

    /**
     * 路徑 6：依電腦給號與規費編號查詢 (type=expaa_by_pc|expaa_by_aa)
     */
    public function testExpaaByPcAndAa(): void {
        // 依電腦給號查詢
        $resPc = $this->post($this->endpoint, [
            'type' => 'expaa_by_pc',
            'year' => '114',
            'keyword' => '1'
        ]);
        $this->assertResponseSuccess($resPc);
        $this->assertArrayHasKey('raw', $resPc);
        $this->assertArrayHasKey('baked', $resPc);

        // 依規費編號查詢
        $resAa = $this->post($this->endpoint, [
            'type' => 'expaa_by_aa',
            'keyword' => '114HA010001'
        ]);
        $this->assertResponseSuccess($resAa);
        $this->assertArrayHasKey('raw', $resAa);
        $this->assertArrayHasKey('baked', $resAa);
    }

    /**
     * 路徑 7：修正規費各欄位 (type=expaa_AA08_update|expaa_AA09_update|expaa_AA100_update|expaa_AA104_update)
     */
    public function testExpaaUpdateColumns(): void {
        $types = ['expaa_AA08_update', 'expaa_AA09_update', 'expaa_AA100_update', 'expaa_AA104_update'];

        foreach ($types as $type) {
            $res = $this->post($this->endpoint, [
                'type' => $type,
                'date' => '1140101',
                'number' => '1',
                'update_value' => 'TEST'
            ]);
            $this->assertArrayHasKey('status', $res);
        }
    }

    /**
     * 路徑 8：查詢作廢規費假資料 (type=get_dummy_ob_fees)
     */
    public function testGetDummyObFees(): void {
        $res = $this->post($this->endpoint, [
            'type' => 'get_dummy_ob_fees'
        ]);

        $this->assertResponseSuccess($res);
        $this->assertArrayHasKey('raw', $res);
        $this->assertIsArray($res['raw']);
    }

    /**
     * 路徑 9：不支援的查詢型態與空請求防護 (default 分支)
     */
    public function testUnsupportedTypeAndEmptyPost(): void {
        // 不支援型態
        $resInvalid = $this->post($this->endpoint, [
            'type' => 'unknown_moiexp_type'
        ]);
        $this->assertSame(STATUS_CODE::UNSUPPORT_FAIL, $resInvalid['status']);
        $this->assertStringContainsString('不支援的查詢型態【unknown_moiexp_type】', $resInvalid['message']);

        // 空參數防護
        $resEmpty = $this->post($this->endpoint, []);
        $this->assertSame(STATUS_CODE::UNSUPPORT_FAIL, $resEmpty['status']);
        $this->assertStringContainsString('不支援的查詢型態【】', $resEmpty['message']);
    }
}
