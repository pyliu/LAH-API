<?php
declare(strict_types=1);

namespace Tests\Integration;

use SQLite3;
use STATUS_CODE;

class MoiprcJsonApiTest extends BaseIntegrationTestCase {
    private string $endpoint = 'moiprc_json_api.php';
    private ?SQLite3 $sqlite = null;
    private array $originalCacheBackup = [];

    protected function setUp(): void {
        parent::setUp();
        $dbPath = defined('DB_DIR') ? DB_DIR . DIRECTORY_SEPARATOR . 'cache.db' : dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'www' . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'db' . DIRECTORY_SEPARATOR . 'cache.db';
        if (file_exists($dbPath)) {
            $this->sqlite = new SQLite3($dbPath);
            $this->sqlite->busyTimeout(5000);
        }
        $this->originalCacheBackup = [];
    }

    protected function tearDown(): void {
        if ($this->sqlite !== null) {
            foreach ($this->originalCacheBackup as $key => $backup) {
                if ($backup === null) {
                    $stmt = $this->sqlite->prepare("DELETE FROM cache WHERE key = :key");
                    if ($stmt) {
                        $stmt->bindValue(':key', $key);
                        $stmt->execute();
                    }
                } else {
                    $stmt = $this->sqlite->prepare("REPLACE INTO cache ('key', 'value', 'expire') VALUES (:key, :value, :expire)");
                    if ($stmt) {
                        $stmt->bindValue(':key', $key);
                        $stmt->bindValue(':value', $backup['value']);
                        $stmt->bindValue(':expire', $backup['expire']);
                        $stmt->execute();
                    }
                }
            }
            $this->sqlite->close();
            $this->sqlite = null;
        }
        $this->originalCacheBackup = [];
        parent::tearDown();
    }

    private function backupKey(string $key): void {
        if ($this->sqlite === null || array_key_exists($key, $this->originalCacheBackup)) {
            return;
        }
        $stmt = $this->sqlite->prepare("SELECT value, expire FROM cache WHERE key = :key");
        if ($stmt) {
            $stmt->bindValue(':key', $key);
            $res = $stmt->execute();
            $row = $res ? $res->fetchArray(SQLITE3_ASSOC) : false;
            if ($row) {
                $this->originalCacheBackup[$key] = [
                    'value' => $row['value'],
                    'expire' => $row['expire']
                ];
            } else {
                $this->originalCacheBackup[$key] = null;
            }
        }
    }

    private function setMockCache(string $key, $data): void {
        if ($this->sqlite === null) {
            return;
        }
        $this->backupKey($key);
        $stmt = $this->sqlite->prepare("REPLACE INTO cache ('key', 'value', 'expire') VALUES (:key, :value, :expire)");
        if ($stmt) {
            $stmt->bindValue(':key', $key);
            $stmt->bindValue(':value', serialize($data));
            $stmt->bindValue(':expire', time() + 86400);
            $stmt->execute();
        }
    }

    /**
     * 路徑 1：實價登錄申報序號查詢 - 時間區間不正確分支 (type=val_realprice_caseno_map 邊界分支)
     */
    public function testValRealpriceCasenoMapInvalidDateRange(): void {
        // 空參數
        $resEmpty = $this->post($this->endpoint, [
            'type' => 'val_realprice_caseno_map'
        ]);
        $this->assertSame(STATUS_CODE::DEFAULT_FAIL, $resEmpty['status']);
        $this->assertStringContainsString('時間區間資料不正確', $resEmpty['message']);

        // 結束日期小於起始日期
        $resInvalid = $this->post($this->endpoint, [
            'type' => 'val_realprice_caseno_map',
            'st_date' => '1140131',
            'ed_date' => '1140101'
        ]);
        $this->assertSame(STATUS_CODE::DEFAULT_FAIL, $resInvalid['status']);
        $this->assertStringContainsString('時間區間資料不正確【1140131 ~ 1140101】', $resInvalid['message']);
    }

    /**
     * 路徑 2：實價登錄申報序號查詢 - 成功查詢快取資料分支 (type=val_realprice_caseno_map 成功分支)
     */
    public function testValRealpriceCasenoMapSuccessWithMockCache(): void {
        $mockMap = [
            '1140101001' => '114HA010001',
            '1140101002' => '114HA010002'
        ];
        $this->setMockCache('val_realprice_caseno_map', $mockMap);

        $res = $this->post($this->endpoint, [
            'type' => 'val_realprice_caseno_map',
            'st_date' => '1140101',
            'ed_date' => '1140131'
        ]);

        $this->assertResponseSuccess($res);
        $this->assertSame(2, $res['data_count']);
        $this->assertArrayHasKey('raw', $res);
        $this->assertSame('114HA010001', $res['raw']['1140101001']);
        $this->assertStringContainsString('查詢實價登錄申報序號資料成功', $res['message']);
    }

    /**
     * 路徑 3：查詢實價登錄備註 - 查無資料分支 (type=val_realprice_memo 查無紀錄分支)
     */
    public function testValRealpriceMemoNoRecord(): void {
        $caseNo = 'NON_EXISTENT_CASE_' . uniqid();

        $res = $this->post($this->endpoint, [
            'type' => 'val_realprice_memo',
            'case_no' => $caseNo
        ]);

        $this->assertSame(STATUS_CODE::SUCCESS_WITH_NO_RECORD, $res['status']);
        $this->assertStringContainsString("{$caseNo} 查無實價登錄備註資料", $res['message']);
    }

    /**
     * 路徑 4：新增/更新實價登錄備註並查詢驗證 (type=upd_val_realprice_memo & type=val_realprice_memo 成功分支)
     */
    public function testUpdValRealpriceMemoAndQuerySuccess(): void {
        $caseNo = 'TEST_CASE_' . uniqid();
        $date = '1140101';
        $note = '自動化測試備註內容_' . uniqid();

        // 1. 更新/新增備註
        $resUpd = $this->post($this->endpoint, [
            'type' => 'upd_val_realprice_memo',
            'case_no' => $caseNo,
            'declare_date' => $date,
            'declare_note' => $note
        ]);
        $this->assertResponseSuccess($resUpd);
        $this->assertStringContainsString("更新 {$caseNo} 實價登錄備註資料成功", $resUpd['message']);

        // 2. 查詢該備註
        $resQuery = $this->post($this->endpoint, [
            'type' => 'val_realprice_memo',
            'case_no' => $caseNo
        ]);
        $this->assertResponseSuccess($resQuery);
        $this->assertSame(5, $resQuery['data_count']);
        $this->assertArrayHasKey('raw', $resQuery);
        $this->assertSame($note, $resQuery['raw']['declare_note']);
    }

    /**
     * 路徑 5：更新實價登錄備註 - 未提供 case_no 失敗分支 (type=upd_val_realprice_memo 失敗分支)
     */
    public function testUpdValRealpriceMemoEmptyCaseNo(): void {
        $res = $this->post($this->endpoint, [
            'type' => 'upd_val_realprice_memo',
            'case_no' => ''
        ]);

        $this->assertSame(STATUS_CODE::DEFAULT_FAIL, $res['status']);
        $this->assertStringContainsString('更新  實價登錄備註資料失敗', $res['message']);
    }

    /**
     * 路徑 6：不支援的查詢型態與空請求防護 (default 分支)
     */
    public function testUnsupportedTypeAndEmptyPost(): void {
        // 不支援型態
        $resInvalid = $this->post($this->endpoint, [
            'type' => 'unknown_moiprc_type'
        ]);
        $this->assertSame(STATUS_CODE::UNSUPPORT_FAIL, $resInvalid['status']);
        $this->assertStringContainsString('不支援的查詢型態【unknown_moiprc_type】', $resInvalid['message']);

        // 空參數防護
        $resEmpty = $this->post($this->endpoint, []);
        $this->assertSame(STATUS_CODE::UNSUPPORT_FAIL, $resEmpty['status']);
        $this->assertStringContainsString('不支援的查詢型態【】', $resEmpty['message']);
    }
}
