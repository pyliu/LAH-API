<?php
declare(strict_types=1);

namespace Tests\Integration;

use STATUS_CODE;

class IpJsonApiTest extends BaseIntegrationTestCase {
    private string $endpoint = 'ip_json_api.php';
    private array $testIps = ['192.168.100.201', '192.168.100.202', '192.168.100.203'];

    protected function tearDown(): void {
        // 清理測試產生的 IP 條目
        foreach ($this->testIps as $ip) {
            $this->post($this->endpoint, [
                'type' => 'remove_ip_entry',
                'ip' => $ip,
                'added_type' => 'STATIC',
                'entry_type' => 'OTHER_EP'
            ]);
            $this->post($this->endpoint, [
                'type' => 'remove_ip_entry',
                'ip' => $ip,
                'added_type' => 'DYNAMIC',
                'entry_type' => 'USER'
            ]);
        }
        parent::tearDown();
    }

    /**
     * 路徑 1：新增使用者動態 IP 回報（add_user_ip_entry）
     */
    public function testAddUserIpEntry(): void {
        $res = $this->post($this->endpoint, [
            'type' => 'add_user_ip_entry',
            'ip' => '192.168.100.201',
            'entry_id' => 'TEST_USER_IP_01',
            'entry_desc' => '測試人員動態電腦',
            'entry_type' => 'USER',
            'note' => '整合測試建立'
        ]);

        $this->assertResponseSuccess($res);
        $this->assertStringContainsString('192.168.100.201', $res['message']);
    }

    /**
     * 路徑 2：新增靜態 IP 條目（add_static_ip_entry）
     */
    public function testAddStaticIpEntry(): void {
        $res = $this->post($this->endpoint, [
            'type' => 'add_static_ip_entry',
            'ip' => '192.168.100.202',
            'entry_desc' => '測試專用印表機',
            'note' => '靜態條目'
        ]);

        $this->assertResponseSuccess($res);
        $this->assertStringContainsString('192.168.100.202', $res['message']);
    }

    /**
     * 路徑 3：編輯靜態 IP 條目（edit_static_ip_entry）
     */
    public function testEditStaticIpEntry(): void {
        // 先建立初始條目
        $this->post($this->endpoint, [
            'type' => 'add_static_ip_entry',
            'ip' => '192.168.100.202',
            'entry_desc' => '修改前印表機'
        ]);

        // 執行編輯更新
        $res = $this->post($this->endpoint, [
            'type' => 'edit_static_ip_entry',
            'orig_ip' => '192.168.100.202',
            'orig_added_type' => 'STATIC',
            'orig_entry_type' => 'OTHER_EP',
            'ip' => '192.168.100.203',
            'added_type' => 'STATIC',
            'entry_type' => 'OTHER_EP',
            'entry_desc' => '修改後之網路設備',
            'note' => '已完成修改'
        ]);

        $this->assertResponseSuccess($res);
        $this->assertStringContainsString('192.168.100.203', $res['message']);
    }

    /**
     * 路徑 4：刪除 IP 條目（remove_ip_entry）
     */
    public function testRemoveIpEntry(): void {
        // 先建立一筆供刪除之條目
        $this->post($this->endpoint, [
            'type' => 'add_static_ip_entry',
            'ip' => '192.168.100.202',
            'entry_desc' => '待刪除條目'
        ]);

        $res = $this->post($this->endpoint, [
            'type' => 'remove_ip_entry',
            'ip' => '192.168.100.202',
            'added_type' => 'STATIC',
            'entry_type' => 'OTHER_EP'
        ]);

        $this->assertResponseSuccess($res);
        $this->assertStringContainsString('資料刪除', $res['message']);
    }

    /**
     * 路徑 5：查詢區間所有 IP 條目（ip_entries）
     */
    public function testQueryIpEntries(): void {
        $res = $this->post($this->endpoint, [
            'type' => 'ip_entries',
            'offset' => 86400
        ]);

        $this->assertResponseSuccess($res);
        $this->assertArrayHasKey('raw', $res);
        $this->assertArrayHasKey('data_count', $res);
        $this->assertIsArray($res['raw']);
        $this->assertSame(count($res['raw']), $res['data_count']);
    }

    /**
     * 路徑 6：查詢動態 IP 條目（dynamic_ip_entries）
     */
    public function testQueryDynamicIpEntries(): void {
        $res = $this->post($this->endpoint, [
            'type' => 'dynamic_ip_entries',
            'offset' => 604800
        ]);

        $this->assertResponseSuccess($res);
        $this->assertArrayHasKey('raw', $res);
        $this->assertArrayHasKey('data_count', $res);
        $this->assertIsArray($res['raw']);
    }

    /**
     * 路徑 7：查詢靜態 IP 條目（static_ip_entries）
     */
    public function testQueryStaticIpEntries(): void {
        $res = $this->post($this->endpoint, [
            'type' => 'static_ip_entries'
        ]);

        $this->assertResponseSuccess($res);
        $this->assertArrayHasKey('raw', $res);
        $this->assertArrayHasKey('data_count', $res);
        $this->assertIsArray($res['raw']);
    }

    /**
     * 路徑 8：Ping 測試（ping 分支，MOCK 模式下模擬延遲回傳 87ms）
     */
    public function testPingMockMode(): void {
        $res = $this->post($this->endpoint, [
            'type' => 'ping',
            'ip' => '127.0.0.1'
        ]);

        $this->assertSame(STATUS_CODE::SUCCESS_NORMAL, $res['status']);
        $this->assertSame('127.0.0.1', $res['ip']);
        $this->assertSame(87, $res['latency']);
        $this->assertStringContainsString('87 ms', $res['message']);
    }

    /**
     * 路徑 9：檢查地政站點 HTTP 服務狀態（check_site_http 分支）
     */
    public function testCheckSiteHttp(): void {
        $res = $this->post($this->endpoint, [
            'type' => 'check_site_http',
            'site' => 'HA'
        ]);

        $this->assertSame(STATUS_CODE::SUCCESS_NORMAL, $res['status']);
        $this->assertSame('HA', $res['site']);
        $this->assertSame('HA服務正常', $res['message']);
        $this->assertArrayHasKey('raw', $res);
    }

    /**
     * 路徑 10：不支援的查詢型態（default 分支）
     */
    public function testUnsupportedTypeReturnsFail(): void {
        $res = $this->post($this->endpoint, [
            'type' => 'non_existent_ip_type_xyz'
        ]);

        $this->assertResponseUnsupport($res);
        $this->assertStringContainsString('不支援的查詢型態', $res['message']);
    }

    /**
     * 路徑 11：未傳入任何參數防護（空 POST 請求）
     */
    public function testEmptyPostParametersSafeFallback(): void {
        $res = $this->post($this->endpoint, []);

        $this->assertResponseUnsupport($res);
        $this->assertStringContainsString('不支援的查詢型態', $res['message']);
    }
}
