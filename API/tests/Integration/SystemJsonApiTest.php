<?php
declare(strict_types=1);

namespace Tests\Integration;

use STATUS_CODE;
use System;

class SystemJsonApiTest extends BaseIntegrationTestCase {
    private string $endpoint = 'system_json_api.php';
    private bool $origMssql = true;
    private bool $origMock = true;
    private bool $origOfficeHours = true;
    private int $origJndiLocal = 3000;
    private int $origJndiXaLocal = 1250;

    protected function setUp(): void {
        parent::setUp();
        $sys = System::getInstance();
        $this->origMssql = $sys->isMSSQLEnable();
        $this->origOfficeHours = $sys->isOfficeHoursEnable();
        $this->origJndiLocal = (int)($sys->get('WEBAP_JNDI_LOCAL') ?: 3000);
        $this->origJndiXaLocal = (int)($sys->get('WEBAP_JNDI_XALOCAL') ?: 1250);
    }

    protected function tearDown(): void {
        // 確保環境狀態完全復原，避免影響其他端點或回歸測試
        $sys = System::getInstance();
        $sys->setMSSQLConnection(true); // 始終維持 MSSQL 啟用狀態以利各項整合測試
        $sys->enableMockMode();         // 始終維持 MOCK 模式
        $sys->setOfficeHoursEnable($this->origOfficeHours);
        $sys->setWebAPJndiLocal($this->origJndiLocal);
        $sys->setWebAPJndiXaLocal($this->origJndiXaLocal);
        parent::tearDown();
    }

    /**
     * 測試 1：不支援的查詢型態與空請求（default 分支）
     */
    public function testUnsupportedTypeAndEmptyPost(): void {
        // 空請求
        $resEmpty = $this->post($this->endpoint, []);
        $this->assertEquals(STATUS_CODE::UNSUPPORT_FAIL, $resEmpty['status'] ?? null);
        $this->assertStringContainsString('不支援的查詢型態', $resEmpty['message'] ?? '');

        // 未知型態
        $resUnknown = $this->post($this->endpoint, ['type' => 'unsupported_system_xyz']);
        $this->assertEquals(STATUS_CODE::UNSUPPORT_FAIL, $resUnknown['status'] ?? null);
        $this->assertStringContainsString('不支援的查詢型態【unsupported_system_xyz】', $resUnknown['message'] ?? '');
    }

    /**
     * 測試 2：查詢系統各項旗標狀態 (switch_mock_flag, switch_mssql_flag, switch_office_hours_flag)
     */
    public function testQuerySystemFlags(): void {
        // 1. switch_mock_flag
        $resMock = $this->post($this->endpoint, ['type' => 'switch_mock_flag']);
        $this->assertEquals(STATUS_CODE::SUCCESS_NORMAL, $resMock['status']);
        $this->assertArrayHasKey('mock_flag', $resMock);
        $this->assertTrue($resMock['mock_flag']);

        // 2. switch_mssql_flag
        $resMssql = $this->post($this->endpoint, ['type' => 'switch_mssql_flag']);
        $this->assertEquals(STATUS_CODE::SUCCESS_NORMAL, $resMssql['status']);
        $this->assertArrayHasKey('mssql_flag', $resMssql);
        $this->assertIsBool($resMssql['mssql_flag']);

        // 3. switch_office_hours_flag
        $resOffice = $this->post($this->endpoint, ['type' => 'switch_office_hours_flag']);
        $this->assertEquals(STATUS_CODE::SUCCESS_NORMAL, $resOffice['status']);
        $this->assertArrayHasKey('office_hours_flag', $resOffice);
        $this->assertIsBool($resOffice['office_hours_flag']);
    }

    /**
     * 測試 3：切換 MOCK 模式開關 (switch_enable_mock, switch_disable_mock)
     */
    public function testSwitchMockMode(): void {
        // 停用 MOCK 模式
        $resDisable = $this->post($this->endpoint, ['type' => 'switch_disable_mock']);
        $this->assertArrayHasKey('status', $resDisable);
        $this->assertContains($resDisable['status'], [STATUS_CODE::SUCCESS_NORMAL, STATUS_CODE::DEFAULT_FAIL]);

        // 重新啟用 MOCK 模式
        $resEnable = $this->post($this->endpoint, ['type' => 'switch_enable_mock']);
        $this->assertEquals(STATUS_CODE::SUCCESS_NORMAL, $resEnable['status']);
        $this->assertStringContainsString('啟用MOCK模式成功', $resEnable['message']);
    }

    /**
     * 測試 4：切換 MSSQL 模式開關 (switch_set_mssql_mode)
     */
    public function testSwitchMssqlMode(): void {
        // 設為停用
        $resFalse = $this->post($this->endpoint, [
            'type' => 'switch_set_mssql_mode',
            'flag' => 'false'
        ]);
        $this->assertEquals(STATUS_CODE::SUCCESS_NORMAL, $resFalse['status']);
        $this->assertStringContainsString('設定MSSQL(停用)成功', $resFalse['message']);

        // 設為啟用
        $resTrue = $this->post($this->endpoint, [
            'type' => 'switch_set_mssql_mode',
            'flag' => 'true'
        ]);
        $this->assertEquals(STATUS_CODE::SUCCESS_NORMAL, $resTrue['status']);
        $this->assertStringContainsString('設定MSSQL(啟用)成功', $resTrue['message']);
    }

    /**
     * 測試 5：切換工作天檢查模式開關 (switch_set_office_hours_mode)
     */
    public function testSwitchOfficeHoursMode(): void {
        // 設為停用
        $resFalse = $this->post($this->endpoint, [
            'type' => 'switch_set_office_hours_mode',
            'flag' => 'false'
        ]);
        $this->assertEquals(STATUS_CODE::SUCCESS_NORMAL, $resFalse['status']);
        $this->assertStringContainsString('設定工作天檢查(停用)成功', $resFalse['message']);

        // 設為啟用
        $resTrue = $this->post($this->endpoint, [
            'type' => 'switch_set_office_hours_mode',
            'flag' => 'true'
        ]);
        $this->assertEquals(STATUS_CODE::SUCCESS_NORMAL, $resTrue['status']);
        $this->assertStringContainsString('設定工作天檢查(啟用)成功', $resTrue['message']);
    }

    /**
     * 測試 6：設定 WEBAP JNDI 連線數門檻 (set_webap_jndi)
     */
    public function testSetWebapJndi(): void {
        $res = $this->post($this->endpoint, [
            'type' => 'set_webap_jndi',
            'local' => 3500,
            'xalocal' => 1500
        ]);
        $this->assertEquals(STATUS_CODE::SUCCESS_NORMAL, $res['status']);
        $this->assertStringContainsString('WEBAP_JNDI_LOCAL 3500 成功', $res['message']);
        $this->assertStringContainsString('WEBAP_JNDI_XALOCAL 1500 成功', $res['message']);
    }

    /**
     * 測試 7：匯入 L3HWEB SYSAUTH1 使用者 (import_l3hweb_sysauth1)
     */
    public function testImportL3hwebSysauth1(): void {
        $res = $this->post($this->endpoint, [
            'type' => 'import_l3hweb_sysauth1'
        ]);
        $this->assertArrayHasKey('status', $res);
        $this->assertContains($res['status'], [STATUS_CODE::SUCCESS_NORMAL, STATUS_CODE::DEFAULT_FAIL]);
        $this->assertStringContainsString('已匯入', $res['message']);
    }

    /**
     * 測試 8：匯入 RKEYN 與 RKEYN_ALL 代碼檔 (import_rkeyn, import_rkeyn_all)
     */
    public function testImportRkeynAndRkeynAll(): void {
        // 1. import_rkeyn
        $resRkeyn = $this->post($this->endpoint, [
            'type' => 'import_rkeyn'
        ]);
        $this->assertArrayHasKey('status', $resRkeyn);
        $this->assertContains($resRkeyn['status'], [STATUS_CODE::SUCCESS_NORMAL, STATUS_CODE::DEFAULT_FAIL]);
        $this->assertStringContainsString('已匯入', $resRkeyn['message']);

        // 2. import_rkeyn_all
        $resRkeynAll = $this->post($this->endpoint, [
            'type' => 'import_rkeyn_all'
        ]);
        $this->assertArrayHasKey('status', $resRkeynAll);
        $this->assertContains($resRkeynAll['status'], [STATUS_CODE::SUCCESS_NORMAL, STATUS_CODE::DEFAULT_FAIL]);
        $this->assertStringContainsString('已匯入', $resRkeynAll['message']);
    }

    /**
     * 測試 9：取得使用分區代碼資料 (rkeyn_use_zone)
     */
    public function testRkeynUseZone(): void {
        $res = $this->post($this->endpoint, [
            'type' => 'rkeyn_use_zone'
        ]);
        $this->assertEquals(STATUS_CODE::SUCCESS_NORMAL, $res['status']);
        $this->assertArrayHasKey('raw', $res);
        $this->assertIsArray($res['raw']);
        $this->assertStringContainsString('使用分區代碼資料', $res['message']);
    }

    /**
     * 測試 10：取得全部地政事務所資料 (all_offices)
     */
    public function testAllOffices(): void {
        $res = $this->post($this->endpoint, [
            'type' => 'all_offices'
        ]);
        $this->assertEquals(STATUS_CODE::SUCCESS_NORMAL, $res['status']);
        $this->assertArrayHasKey('raw', $res);
        $this->assertIsArray($res['raw']);
        $this->assertStringContainsString('使用地政事務所資料', $res['message']);
    }
}
