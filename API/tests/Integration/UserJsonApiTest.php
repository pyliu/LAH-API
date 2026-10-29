<?php
declare(strict_types=1);

namespace Tests\Integration;

use STATUS_CODE;

class UserJsonApiTest extends BaseIntegrationTestCase {
    private string $endpoint = 'user_json_api.php';
    private string $testUserId;

    protected function setUp(): void {
        parent::setUp();
        $this->testUserId = 'U_INT_' . substr(uniqid(), -6);
    }

    /**
     * 路徑 1：不支援的查詢型態（default 分支）
     */
    public function testUnsupportedTypeReturnsError(): void {
        $res = $this->post($this->endpoint, [
            'type' => 'unknown_user_type_action'
        ]);

        $this->assertResponseUnsupport($res);
        $this->assertStringContainsString('不支援的查詢型態【unknown_user_type_action】', $res['message']);
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
     * 路徑 3：l3hweb_user_mappings 查詢與 import_l3hweb_users 匯入
     */
    public function testL3hwebMappingsAndImport(): void {
        $res = $this->post($this->endpoint, [
            'type' => 'l3hweb_user_mappings'
        ]);
        $this->assertResponseSuccess($res, '查詢同步異動使用者對應表成功');
        $this->assertArrayHasKey('raw', $res);

        $importRes = $this->post($this->endpoint, [
            'type' => 'import_l3hweb_users'
        ]);
        $this->assertResponseSuccess($importRes, '匯入同步異動使用者執行完成');
        $this->assertArrayHasKey('succeed', $importRes);
        $this->assertArrayHasKey('failed', $importRes);
    }

    /**
     * 路徑 4：search_user 查詢（IP、名稱、帳號關鍵字與查無資料）
     */
    public function testSearchUser(): void {
        // IP 查詢
        $resIp = $this->post($this->endpoint, [
            'type' => 'search_user',
            'keyword' => '127.0.0.1'
        ]);
        $this->assertResponseSuccess($resIp, '查詢 127.0.0.1 成功');
        $this->assertArrayHasKey('raw', $resIp);
        $this->assertGreaterThan(0, $resIp['data_count']);

        // 查無資料
        $resNotFound = $this->post($this->endpoint, [
            'type' => 'search_user',
            'keyword' => 'NON_EXISTENT_KEYWORD_XYZ999'
        ]);
        $this->assertEquals(STATUS_CODE::DEFAULT_FAIL, $resNotFound['status']);
        $this->assertStringContainsString('查無 NON_EXISTENT_KEYWORD_XYZ999 資料', $resNotFound['message']);
    }

    /**
     * 路徑 5：user_mapping 與 user_names 查詢
     */
    public function testUserMappingAndUserNames(): void {
        $resMapping = $this->post($this->endpoint, [
            'type' => 'user_mapping'
        ]);
        $this->assertResponseSuccess($resMapping, '筆使用者資料');
        $this->assertArrayHasKey('data', $resMapping);

        $resNames = $this->post($this->endpoint, [
            'type' => 'user_names'
        ]);
        $this->assertResponseSuccess($resNames, '查詢使用者名冊資料成功');
        $this->assertArrayHasKey('raw', $resNames);
    }

    /**
     * 路徑 6：使用者新增、儲存更新、離職、復職生命週期
     */
    public function testUserLifecycleAddOffboardOnboard(): void {
        $userData = [
            'id' => $this->testUserId,
            'name' => '測試自動化人員',
            'sex' => 1,
            'title' => '科員',
            'work' => '自動化整合測試',
            'ext' => '888',
            'birthday' => '080/05/20',
            'unit' => '資訊課',
            'ip' => '192.168.10.188',
            'education' => '碩士',
            'exam' => '高考三級',
            'cell' => '0988776655',
            'onboard_date' => '113/05/01'
        ];

        // 1. 新增使用者
        $addRes = $this->post($this->endpoint, [
            'type' => 'add_user',
            'data' => $userData
        ]);
        $this->assertResponseSuccess($addRes, '新增 ' . $this->testUserId . ', 測試自動化人員 成功');

        // 2. 儲存使用者更新
        $userData['title'] = '主任';
        $saveRes = $this->post($this->endpoint, [
            'type' => 'save_user_info',
            'data' => $userData
        ]);
        $this->assertResponseSuccess($saveRes, '儲存 ' . $this->testUserId . ', 測試自動化人員 成功');

        // 3. 設定使用者離職
        $offRes = $this->post($this->endpoint, [
            'type' => 'user_offboard',
            'id' => $this->testUserId
        ]);
        $this->assertResponseSuccess($offRes, $this->testUserId . ' 設定離職成功');

        // 4. 設定使用者復職
        $onRes = $this->post($this->endpoint, [
            'type' => 'user_onboard',
            'id' => $this->testUserId
        ]);
        $this->assertResponseSuccess($onRes, $this->testUserId . ' 設定復職成功');
    }

    /**
     * 路徑 7：user_info 查詢（依帳號、姓名、IP 或查無資料）
     */
    public function testUserInfoQueries(): void {
        // 依 127.0.0.1 IP 查詢
        $resIp = $this->post($this->endpoint, [
            'type' => 'user_info',
            'ip' => '127.0.0.1'
        ]);
        $this->assertResponseSuccess($resIp, '成功');
        $this->assertArrayHasKey('raw', $resIp);

        // 查無資料
        $resNotFound = $this->post($this->endpoint, [
            'type' => 'user_info',
            'id' => 'NON_EXISTENT_ID_99999'
        ]);
        $this->assertEquals(STATUS_CODE::DEFAULT_FAIL, $resNotFound['status']);
        $this->assertStringContainsString('查無', $resNotFound['message']);
    }

    /**
     * 路徑 8：org_data 組織階層架構查詢
     */
    public function testOrgData(): void {
        $res = $this->post($this->endpoint, [
            'type' => 'org_data'
        ]);
        $this->assertResponseSuccess($res, '查詢組織資料成功');
        $this->assertArrayHasKey('raw', $res);
    }

    /**
     * 路徑 9：on_board_users、off_board_users、all_users 與 department_users 清單查詢
     */
    public function testUserListFilters(): void {
        $resOn = $this->post($this->endpoint, [
            'type' => 'on_board_users'
        ]);
        $this->assertResponseSuccess($resOn, '查詢正常使用者資料成功');
        $this->assertArrayHasKey('raw', $resOn);

        $resOff = $this->post($this->endpoint, [
            'type' => 'off_board_users'
        ]);
        $this->assertArrayHasKey('status', $resOff);

        $resAll = $this->post($this->endpoint, [
            'type' => 'all_users'
        ]);
        $this->assertResponseSuccess($resAll, '查詢所有使用者資料成功');

        $resDept = $this->post($this->endpoint, [
            'type' => 'department_users',
            'department' => '全所',
            'valid' => 'all_users'
        ]);
        $this->assertResponseSuccess($resDept, '查詢部門使用者資料成功');
    }

    /**
     * 路徑 10：authority_list、add_authority、update_authority、remove_authority 授權異動
     */
    public function testAuthorityManagement(): void {
        $resList = $this->post($this->endpoint, [
            'type' => 'authority_list'
        ]);
        $this->assertResponseSuccess($resList, '查詢所有使用者授權列表成功');
        $this->assertArrayHasKey('raw', $resList);

        $testIp = '192.168.99.199';
        $roleId = 'TEST_ROLE_1';
        $newRoleId = 'TEST_ROLE_2';

        // 新增授權
        $addRes = $this->post($this->endpoint, [
            'type' => 'add_authority',
            'role_id' => $roleId,
            'ip' => $testIp
        ]);
        $this->assertResponseSuccess($addRes, '增加使用者授權 ' . $roleId . ' ' . $testIp . ' 成功');

        // 更新授權
        $updRes = $this->post($this->endpoint, [
            'type' => 'update_authority',
            'old_role_id' => $roleId,
            'role_id' => $newRoleId,
            'ip' => $testIp
        ]);
        $this->assertResponseSuccess($updRes, '變更使用者授權 ' . $testIp . ' 成功');

        // 移除授權
        $delRes = $this->post($this->endpoint, [
            'type' => 'remove_authority',
            'user' => [
                'role_id' => $newRoleId,
                'role_ip' => $testIp,
                'role_name' => '測試角色'
            ]
        ]);
        $this->assertResponseSuccess($delRes, '移除使用者授權 測試角色 ' . $testIp . ' 成功');
    }

    /**
     * 路徑 11：upd_ext、upd_dept、upd_ip 使用者欄位更新
     */
    public function testUpdateUserFields(): void {
        // 先建立測試使用者
        $this->post($this->endpoint, [
            'type' => 'add_user',
            'data' => [
                'id' => $this->testUserId,
                'name' => '欄位更新人員',
                'ip' => '192.168.10.150'
            ]
        ]);

        $resExt = $this->post($this->endpoint, [
            'type' => 'upd_ext',
            'id' => $this->testUserId,
            'ext' => '777'
        ]);
        $this->assertResponseSuccess($resExt, '更新分機號碼 ' . $this->testUserId . ', 777 成功');

        $resDept = $this->post($this->endpoint, [
            'type' => 'upd_dept',
            'id' => $this->testUserId,
            'dept' => '登記課'
        ]);
        $this->assertResponseSuccess($resDept, '更新部門 ' . $this->testUserId . ', 登記課 成功');

        $resIp = $this->post($this->endpoint, [
            'type' => 'upd_ip',
            'id' => $this->testUserId,
            'ip' => '192.168.10.151'
        ]);
        $this->assertResponseSuccess($resIp, '更新IP位址 ' . $this->testUserId . ', 192.168.10.151 成功');
    }

    /**
     * 路徑 12：my_info 與 authentication 使用者自身資訊查詢
     */
    public function testMyInfoAndAuthentication(): void {
        $resAdmin = $this->post($this->endpoint, [
            'type' => 'my_info',
            'ip' => '127.0.0.1'
        ]);
        $this->assertResponseSuccess($resAdmin, '查詢 127.0.0.1 成功');
        $this->assertArrayHasKey('info', $resAdmin);
        $this->assertArrayHasKey('authority', $resAdmin);

        $resAuthNotFound = $this->post($this->endpoint, [
            'type' => 'authentication',
            'ip' => '10.254.254.254'
        ]);
        $this->assertEquals(STATUS_CODE::FAIL_NOT_FOUND, $resAuthNotFound['status']);
        $this->assertStringContainsString('查無 10.254.254.254 資料/授權', $resAuthNotFound['message']);
    }

    /**
     * 路徑 13：AD 相關操作（ad_status, ad_config, ad_test_connection, ad_authentication, ad_users_valid, ad_users_locked, ad_pw_reset, ad_user_info, ad_sync_users）
     */
    public function testAdServiceActions(): void {
        $resStatus = $this->post($this->endpoint, [
            'type' => 'ad_status'
        ]);
        $this->assertArrayHasKey('ad_available', $resStatus);

        $resConfig = $this->post($this->endpoint, [
            'type' => 'ad_config'
        ]);
        $this->assertArrayHasKey('status', $resConfig);

        $resTest = $this->post($this->endpoint, [
            'type' => 'ad_test_connection'
        ]);
        $this->assertArrayHasKey('status', $resTest);

        $resAuth = $this->post($this->endpoint, [
            'type' => 'ad_authentication',
            'id' => 'mock_ad_user',
            'password' => 'secret123'
        ]);
        $this->assertArrayHasKey('status', $resAuth);

        $resValid = $this->post($this->endpoint, [
            'type' => 'ad_users_valid'
        ]);
        $this->assertArrayHasKey('status', $resValid);

        $resLocked = $this->post($this->endpoint, [
            'type' => 'ad_users_locked'
        ]);
        $this->assertArrayHasKey('status', $resLocked);

        $resUnlock = $this->post($this->endpoint, [
            'type' => 'ad_user_unlock',
            'id' => 'mock_ad_user'
        ]);
        $this->assertArrayHasKey('status', $resUnlock);

        $resUserInfo = $this->post($this->endpoint, [
            'type' => 'ad_user_info',
            'id' => 'mock_ad_user'
        ]);
        $this->assertArrayHasKey('status', $resUserInfo);

        $resSync = $this->post($this->endpoint, [
            'type' => 'ad_sync_users'
        ]);
        $this->assertArrayHasKey('status', $resSync);
    }
}
