<?php
declare(strict_types=1);

namespace Tests\Integration;

use STATUS_CODE;
use SQLite3;

class XCaseJsonApiTest extends BaseIntegrationTestCase {
    private string $endpoint = 'xcase_json_api.php';
    private array $cachedBackups = [];

    private array $trackedKeys = [
        'xcase-check',
        'val-xcase-check',
        'fix_xcase',
        'fix_xcase_val',
        'diff_xcase',
        'find_xcase_writeback_failures',
        'inst_xcase',
        'sync_xcase',
        'sync_xcase_column',
        'check_xcase_fix_data',
        'sync_xcase_fix_data',
        'get_xcase_fix_data',
        'get_local_crcld',
        'get_local_fix_data'
    ];

    protected function setUp(): void {
        parent::setUp();
        $db = new SQLite3(ROOT_DIR . '/assets/db/cache.db');
        foreach ($this->trackedKeys as $key) {
            $val = $db->querySingle("SELECT value from cache WHERE key = '$key'");
            if ($val !== null && $val !== false) {
                $this->cachedBackups[$key] = unserialize($val);
            }
        }
    }

    protected function tearDown(): void {
        foreach ($this->trackedKeys as $key) {
            if (array_key_exists($key, $this->cachedBackups)) {
                $this->setMockCache($key, $this->cachedBackups[$key]);
            } else {
                $this->deleteMockCache($key);
            }
        }
        parent::tearDown();
    }

    private function setMockCache(string $key, mixed $val): void {
        $db = new SQLite3(ROOT_DIR . '/assets/db/cache.db');
        $stmt = $db->prepare("REPLACE INTO cache ('key', 'value', 'expire') VALUES (:key, :value, :expire)");
        $stmt->bindValue(':key', $key);
        $stmt->bindValue(':value', serialize($val));
        $stmt->bindValue(':expire', time() + 86400);
        $stmt->execute();
    }

    private function deleteMockCache(string $key): void {
        $db = new SQLite3(ROOT_DIR . '/assets/db/cache.db');
        $stmt = $db->prepare("DELETE FROM cache WHERE key = :key");
        $stmt->bindValue(':key', $key);
        $stmt->execute();
    }

    /**
     * 路徑 1：不支援的查詢型態（default 分支）
     */
    public function testUnsupportedTypeReturnsError(): void {
        $res = $this->post($this->endpoint, [
            'type' => 'unknown_xcase_action'
        ]);

        $this->assertResponseUnsupport($res);
        $this->assertStringContainsString('不支援的查詢型態【unknown_xcase_action】', $res['message']);
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
     * 路徑 3：xcase-check 查詢登記案件跨所註記遺失（查無資料與查獲資料）
     */
    public function testXCaseCheck(): void {
        // 1. 查無資料
        $this->setMockCache('xcase-check', []);
        $resEmpty = $this->post($this->endpoint, [
            'type' => 'xcase-check'
        ]);
        $this->assertEquals(STATUS_CODE::DEFAULT_FAIL, $resEmpty['status']);
        $this->assertStringContainsString('查無資料', $resEmpty['message']);

        // 2. 查獲資料
        $this->setMockCache('xcase-check', [
            ['RM01' => '113', 'RM02' => 'HBA1', 'RM03' => '001234']
        ]);
        $resFound = $this->post($this->endpoint, [
            'type' => 'xcase-check'
        ]);
        $this->assertResponseSuccess($resFound);
        $this->assertEquals(1, $resFound['data_count']);
        $this->assertEquals(['113-HBA1-001234'], $resFound['case_ids']);
    }

    /**
     * 路徑 4：val-xcase-check 查詢地價案件跨所註記遺失（查無資料與查獲資料）
     */
    public function testValXCaseCheck(): void {
        // 1. 查無資料
        $this->setMockCache('val-xcase-check', []);
        $resEmpty = $this->post($this->endpoint, [
            'type' => 'val-xcase-check'
        ]);
        $this->assertEquals(STATUS_CODE::DEFAULT_FAIL, $resEmpty['status']);
        $this->assertStringContainsString('查無資料', $resEmpty['message']);

        // 2. 查獲資料
        $this->setMockCache('val-xcase-check', [
            ['SS03' => '113', 'SS04_1' => 'HBA1', 'SS04_2' => '005678']
        ]);
        $resFound = $this->post($this->endpoint, [
            'type' => 'val-xcase-check'
        ]);
        $this->assertResponseSuccess($resFound);
        $this->assertEquals(1, $resFound['data_count']);
        $this->assertEquals(['113-HBA1-005678'], $resFound['case_ids']);
    }

    /**
     * 路徑 5：fix_xcase 與 fix_xcase_val 修正跨所註記
     */
    public function testFixXCaseAndVal(): void {
        $this->setMockCache('fix_xcase', true);
        $resReg = $this->post($this->endpoint, [
            'type' => 'fix_xcase',
            'id' => '113-HBA1-001234'
        ]);
        $this->assertResponseSuccess($resReg);

        $this->setMockCache('fix_xcase_val', true);
        $resVal = $this->post($this->endpoint, [
            'type' => 'fix_xcase_val',
            'id' => '113-HBA1-005678'
        ]);
        $this->assertResponseSuccess($resVal);

        // 失敗分支
        $this->setMockCache('fix_xcase', false);
        $resFail = $this->post($this->endpoint, [
            'type' => 'fix_xcase',
            'id' => '113-HBA1-001234'
        ]);
        $this->assertEquals(STATUS_CODE::DEFAULT_FAIL, $resFail['status']);
    }

    /**
     * 路徑 6：diff_xcase 同步案件資料比對（一致、相異與異常代碼）
     */
    public function testDiffXCase(): void {
        // 資料一致
        $this->setMockCache('diff_xcase', []);
        $resSame = $this->post($this->endpoint, [
            'type' => 'diff_xcase',
            'id' => '113-HBA1-001234'
        ]);
        $this->assertStringContainsString('遠端資料與本所一致', $resSame['message']);

        // 比對有相異資料
        $this->setMockCache('diff_xcase', [
            ['column' => 'RM09', 'local' => 'A', 'remote' => 'B']
        ]);
        $resDiff = $this->post($this->endpoint, [
            'type' => 'diff_xcase',
            'id' => '113-HBA1-001234'
        ]);
        $this->assertResponseSuccess($resDiff);
        $this->assertEquals(1, $resDiff['data_count']);

        // 格式錯誤 (-1)
        $this->setMockCache('diff_xcase', -1);
        $resFormatErr = $this->post($this->endpoint, [
            'type' => 'diff_xcase',
            'id' => 'INVALID'
        ]);
        $this->assertStringContainsString('參數格式錯誤', $resFormatErr['message']);

        // 遠端查無資料 (-2)
        $this->setMockCache('diff_xcase', -2);
        $resRemoteNone = $this->post($this->endpoint, [
            'type' => 'diff_xcase',
            'id' => '113-HBA1-009999'
        ]);
        $this->assertEquals(STATUS_CODE::FAIL_WITH_REMOTE_NO_RECORD, $resRemoteNone['status']);

        // 本地查無資料 (-3)
        $this->setMockCache('diff_xcase', -3);
        $resLocalNone = $this->post($this->endpoint, [
            'type' => 'diff_xcase',
            'id' => '113-HBA1-009999'
        ]);
        $this->assertEquals(STATUS_CODE::FAIL_WITH_LOCAL_NO_RECORD, $resLocalNone['status']);
    }

    /**
     * 路徑 7：find_xcase_writeback_failures 查詢跨所回寫失敗案件
     */
    public function testFindXCaseWritebackFailures(): void {
        // 無失敗案件
        $this->setMockCache('find_xcase_writeback_failures', [
            'HBA1' => ['foundIds' => []]
        ]);
        $resEmpty = $this->post($this->endpoint, [
            'type' => 'find_xcase_writeback_failures'
        ]);
        $this->assertEquals(STATUS_CODE::SUCCESS_WITH_NO_RECORD, $resEmpty['status']);

        // 有失敗案件
        $this->setMockCache('find_xcase_writeback_failures', [
            'HBA1' => ['foundIds' => ['113-HBA1-001234']]
        ]);
        $resFound = $this->post($this->endpoint, [
            'type' => 'find_xcase_writeback_failures'
        ]);
        $this->assertEquals(STATUS_CODE::SUCCESS_WITH_MULTIPLE_RECORDS, $resFound['status']);
        $this->assertEquals(['113-HBA1-001234'], $resFound['found']);
    }

    /**
     * 路徑 8：inst_xcase 插入遠端案件（成功與各項錯誤代碼）
     */
    public function testInstXCase(): void {
        // 成功
        $this->setMockCache('inst_xcase', true);
        $resSuccess = $this->post($this->endpoint, [
            'type' => 'inst_xcase',
            'id' => '113-HBA1-001234'
        ]);
        $this->assertResponseSuccess($resSuccess);

        // 傳入 ID 錯誤 (-1)
        $this->setMockCache('inst_xcase', -1);
        $resIdErr = $this->post($this->endpoint, [
            'type' => 'inst_xcase',
            'id' => 'BAD'
        ]);
        $this->assertStringContainsString('傳入ID錯誤', $resIdErr['message']);

        // 遠端無案件資料 (-2)
        $this->setMockCache('inst_xcase', -2);
        $resNoRemote = $this->post($this->endpoint, [
            'type' => 'inst_xcase',
            'id' => '113-HBA1-009999'
        ]);
        $this->assertStringContainsString('遠端無案件資料', $resNoRemote['message']);
    }

    /**
     * 路徑 9：sync_xcase 與 sync_xcase_column 同步遠端案件及特定欄位
     */
    public function testSyncXCaseAndColumn(): void {
        $this->setMockCache('sync_xcase', true);
        $resSync = $this->post($this->endpoint, [
            'type' => 'sync_xcase',
            'id' => '113-HBA1-001234'
        ]);
        $this->assertResponseSuccess($resSync);

        $this->setMockCache('sync_xcase_column', true);
        $resSyncCol = $this->post($this->endpoint, [
            'type' => 'sync_xcase_column',
            'id' => '113-HBA1-001234',
            'column' => 'RM09'
        ]);
        $this->assertResponseSuccess($resSyncCol);
    }

    /**
     * 路徑 10：check_xcase_fix_data、sync_xcase_fix_data、get_xcase_fix_data 與 get_local_fix_data 補正資料操作
     */
    public function testXCaseFixDataOperations(): void {
        // check_xcase_fix_data 成功
        $this->setMockCache('check_xcase_fix_data', ['cl01' => '113', 'cl02' => 'HB04']);
        $resCheck = $this->post($this->endpoint, [
            'type' => 'check_xcase_fix_data',
            'id' => '113-HBA1-001234'
        ]);
        $this->assertResponseSuccess($resCheck, '局端案件補正連結取得成功');

        // check_xcase_fix_data 錯誤分支 (-1, -2, -3)
        $this->setMockCache('check_xcase_fix_data', -1);
        $resL3Err = $this->post($this->endpoint, [
            'type' => 'check_xcase_fix_data',
            'id' => '113-HBA1-001234'
        ]);
        $this->assertStringContainsString('無法連線', $resL3Err['message']);

        // sync_xcase_fix_data
        $this->setMockCache('sync_xcase_fix_data', ['cr01' => '113']);
        $resSyncFix = $this->post($this->endpoint, [
            'type' => 'sync_xcase_fix_data',
            'id' => '113-HBA1-001234'
        ]);
        $this->assertResponseSuccess($resSyncFix, '同步補正資料成功');

        // get_xcase_fix_data
        $this->setMockCache('get_xcase_fix_data', ['cr01' => '113']);
        $resGetFix = $this->post($this->endpoint, [
            'type' => 'get_xcase_fix_data',
            'id' => '113-HBA1-001234'
        ]);
        $this->assertResponseSuccess($resGetFix, '取得遠端同步補正資料成功');

        // get_local_fix_data
        $this->setMockCache('get_local_crcld', ['cl01' => '113']);
        $this->setMockCache('get_local_fix_data', ['cr01' => '113']);
        $resGetLocal = $this->post($this->endpoint, [
            'type' => 'get_local_fix_data',
            'id' => '113-HBA1-001234'
        ]);
        $this->assertResponseSuccess($resGetLocal, '取得本地同步補正資料成功');
    }
}
