<?php
declare(strict_types=1);

namespace Tests\Integration;

use STATUS_CODE;

class AuthJsonApiTest extends BaseIntegrationTestCase {
    private string $endpoint = 'auth_json_api.php';

    /**
     * 路徑 1：不支援的查詢型態（default 分支）
     */
    public function testUnsupportedTypeReturnsError(): void {
        $res = $this->post($this->endpoint, [
            'type' => 'unsupported_auth_action'
        ]);

        $this->assertResponseUnsupport($res);
        $this->assertStringContainsString('不支援的查詢型態【unsupported_auth_action】', $res['message']);
    }

    /**
     * 路徑 2：空 type 參數（default 分支防護）
     */
    public function testEmptyTypeReturnsError(): void {
        $res = $this->post($this->endpoint, []);

        $this->assertResponseUnsupport($res);
        $this->assertStringContainsString('不支援的查詢型態', $res['message']);
    }

    /**
     * 路徑 3：預設 client IP 登入（省略 req_ip 參數）
     */
    public function testLoginWithDefaultClientIp(): void {
        $res = $this->post($this->endpoint, [
            'type' => 'login'
        ]);

        $this->assertResponseSuccess($res, '登入資訊');
        $this->assertArrayHasKey('server', $res);
        $this->assertArrayHasKey('ips', $res);
        $this->assertArrayHasKey('configs', $res);
        $this->assertIsArray($res['configs']);

        // 驗證關鍵系統設定值
        $this->assertTrue($res['configs']['mock'], '系統必須處於 MOCK=true 模式');
        $this->assertArrayHasKey('authority', $res['configs']);
        $this->assertArrayHasKey('site', $res['configs']);
    }

    /**
     * 路徑 4：具名使用者 IP 登入（資料庫有使用者記錄，如 127.0.0.1 系統管理者）
     */
    public function testLoginWithMatchedUserIp(): void {
        $res = $this->post($this->endpoint, [
            'type' => 'login',
            'req_ip' => '127.0.0.1'
        ]);

        $this->assertResponseSuccess($res, '取得 127.0.0.1 登入資訊');
        $this->assertArrayHasKey('user', $res);
        $this->assertIsArray($res['user']);
        $this->assertNotEmpty($res['user']['id']);
        $this->assertNotEmpty($res['user']['name']);

        // 127.0.0.1 預設應具備管理者權限
        $this->assertTrue($res['configs']['authority']['isAdmin']);
    }

    /**
     * 路徑 5：未知 IP 登入（資料庫無使用者記錄）
     */
    public function testLoginWithNonexistentUserIp(): void {
        $res = $this->post($this->endpoint, [
            'type' => 'login',
            'req_ip' => '10.99.99.99'
        ]);

        $this->assertResponseSuccess($res, '取得 10.99.99.99 登入資訊');
        $this->assertArrayHasKey('user', $res);
        $this->assertNull($res['user']);

        // 未知 IP 不具備管理者權限
        $this->assertFalse($res['configs']['authority']['isAdmin']);
        $this->assertFalse($res['configs']['authority']['isSuper']);
    }

    /**
     * 路徑 6：檢驗回傳 configs 完整結構契約
     */
    public function testLoginConfigsContract(): void {
        $res = $this->post($this->endpoint, [
            'type' => 'login',
            'req_ip' => '127.0.0.1'
        ]);

        $configs = $res['configs'] ?? [];
        $expectedKeys = [
            'l3hweb_db_ip',
            'webap_ip',
            'webap_jndi_local',
            'webap_jndi_xalocal',
            'webap_postfix',
            'mock',
            'mssql',
            'avatar',
            'officehours',
            'ad_available',
            'authority',
            'master_password',
            'site',
            'monitor',
            'monitor_printers',
            'API_SERVER_IP',
            'API_SERVER_PORT',
            'WS_SERVER_IP',
            'WS_SERVER_PORT'
        ];

        foreach ($expectedKeys as $key) {
            $this->assertArrayHasKey($key, $configs, "configs 缺少必要鍵值: {$key}");
        }
    }
}
