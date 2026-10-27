<?php
declare(strict_types=1);

namespace Tests\Integration;

use SQLite3;
use STATUS_CODE;

class LxhwebJsonApiTest extends BaseIntegrationTestCase {
    private string $endpoint = 'lxhweb_json_api.php';
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
                    // 原本不存在，直接刪除
                    $stmt = $this->sqlite->prepare("DELETE FROM cache WHERE key = :key");
                    if ($stmt) {
                        $stmt->bindValue(':key', $key);
                        $stmt->execute();
                    }
                } else {
                    // 原本存在，還原舊值
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

    private function clearMockCache(string $key): void {
        if ($this->sqlite === null) {
            return;
        }
        $this->backupKey($key);
        $stmt = $this->sqlite->prepare("DELETE FROM cache WHERE key = :key");
        if ($stmt) {
            $stmt->bindValue(':key', $key);
            $stmt->execute();
        }
    }

    /**
     * 路徑 1：取得 LXHWEB 設定資訊 (type=lxhweb_config)
     */
    public function testLxhwebConfig(): void {
        $res = $this->post($this->endpoint, [
            'type' => 'lxhweb_config'
        ]);

        $this->assertResponseSuccess($res);
        $this->assertArrayHasKey('raw', $res);
        $this->assertIsArray($res['raw']);
        $this->assertStringContainsString('筆設定', $res['message']);

        // 驗證關鍵配置鍵值存在
        $raw = $res['raw'];
        $this->assertArrayHasKey('ORA_DB_L1HWEB_IP', $raw);
        $this->assertArrayHasKey('ORA_DB_L2HWEB_IP', $raw);
        $this->assertArrayHasKey('ORA_DB_L3HWEB_IP', $raw);
        $this->assertArrayHasKey('PING_INTERVAL_SECONDS', $raw);
    }

    /**
     * 路徑 2：各所同步異動更新時間 - 查詢到既有資料分支 (type=lxhweb_site_update_time)
     */
    public function testLxhwebSiteUpdateTimeSuccess(): void {
        $res = $this->post($this->endpoint, [
            'type' => 'lxhweb_site_update_time',
            'site' => 'L1HWEB'
        ]);

        $this->assertResponseSuccess($res);
        $this->assertGreaterThan(0, $res['data_count']);
        $this->assertIsArray($res['raw']);
        $this->assertStringContainsString('共查詢到', $res['message']);
    }

    /**
     * 路徑 3：各所同步異動更新時間 - 查無資料分支 (type=lxhweb_site_update_time)
     */
    public function testLxhwebSiteUpdateTimeNoRecord(): void {
        $this->clearMockCache('lxhweb_site_update_time');

        $res = $this->post($this->endpoint, [
            'type' => 'lxhweb_site_update_time',
            'site' => 'L1HWEB'
        ]);

        $this->assertSame(STATUS_CODE::DEFAULT_FAIL, $res['status']);
        $this->assertStringContainsString('查無同步異動資料庫更新時間資料 L1HWEB', $res['message']);
    }

    /**
     * 路徑 4：損毀表格檢測 - 無損毀記錄分支 (type=lxhweb_broken_table)
     */
    public function testLxhwebBrokenTableNoRecord(): void {
        $res = $this->post($this->endpoint, [
            'type' => 'lxhweb_broken_table',
            'site' => 'L2HWEB'
        ]);

        $this->assertSame(STATUS_CODE::SUCCESS_WITH_NO_RECORD, $res['status']);
        $this->assertStringContainsString('查無已損毀的同步異動表格 L2HWEB', $res['message']);
    }

    /**
     * 路徑 5：損毀表格檢測 - 檢測到損毀表格分支 (type=lxhweb_broken_table)
     */
    public function testLxhwebBrokenTableSuccess(): void {
        $mockBroken = [
            ['TABLE_NAME' => 'RBLAP', 'ERROR' => 'ORA-01578']
        ];
        $this->setMockCache('lxhweb_broken_table', $mockBroken);

        $res = $this->post($this->endpoint, [
            'type' => 'lxhweb_broken_table',
            'site' => 'L2HWEB'
        ]);

        $this->assertResponseSuccess($res);
        $this->assertSame(1, $res['data_count']);
        $this->assertSame('RBLAP', $res['raw'][0]['TABLE_NAME']);
    }

    /**
     * 路徑 6：同步異動表格更新時間 - 搭配 office 參數 (type=lxhweb_tbl_update_time)
     */
    public function testLxhwebTblUpdateTimeWithOffice(): void {
        // 既有快取中存在的所別 (L1HWEB_Alt, HA)
        $resExist = $this->post($this->endpoint, [
            'type' => 'lxhweb_tbl_update_time',
            'site' => 'L1HWEB_Alt',
            'office' => 'HA'
        ]);
        $this->assertResponseSuccess($resExist);
        $this->assertGreaterThan(0, $resExist['data_count']);

        // 查無資料的所別與站點 (L3HWEB, HA)
        $resNotFound = $this->post($this->endpoint, [
            'type' => 'lxhweb_tbl_update_time',
            'site' => 'L3HWEB',
            'office' => 'HA'
        ]);
        $this->assertSame(STATUS_CODE::DEFAULT_FAIL, $resNotFound['status']);
        $this->assertStringContainsString('查無 HA 同步異動表格L3HWEB更新時間資料', $resNotFound['message']);
    }

    /**
     * 路徑 7：同步異動表格更新時間 - 依 2 碼 site 自動 fallback office (type=lxhweb_tbl_update_time)
     */
    public function testLxhwebTblUpdateTimeFallbackSite(): void {
        $site = 'HB'; // 2 碼 site，未給 office 時 target_office 應為 HB
        $cacheKey = "lxhweb_tbl_update_time_{$site}_{$site}";
        $this->setMockCache($cacheKey, [
            ['TABLE_NAME' => 'BUILD', 'UPDATE_TIME' => '2026-10-27 11:30:00']
        ]);

        $res = $this->post($this->endpoint, [
            'type' => 'lxhweb_tbl_update_time',
            'site' => $site
        ]);

        $this->assertResponseSuccess($res);
        $this->assertSame(1, $res['data_count']);
        $this->assertSame('BUILD', $res['raw'][0]['TABLE_NAME']);
    }

    /**
     * 路徑 8：地價案件跨所註記檢查 (type=l3hweb_pscrn_check)
     */
    public function testL3hwebPscrnCheck(): void {
        // 查無遺失跨所註記
        $res = $this->post($this->endpoint, [
            'type' => 'l3hweb_pscrn_check',
            'office' => 'HA'
        ]);
        $this->assertSame(STATUS_CODE::DEFAULT_FAIL, $res['status']);
        $this->assertStringContainsString('查無 HA 地價案件遺失跨所註記。', $res['message']);

        // 查有遺失註記案件
        $mockCases = [
            ['CASE_NO' => '115HA010001', 'STATUS' => 'MISSING_XNOTE']
        ];
        $this->setMockCache('l3hweb_pscrn_check', $mockCases);

        $resSuccess = $this->post($this->endpoint, [
            'type' => 'l3hweb_pscrn_check',
            'office' => 'HA'
        ]);
        $this->assertResponseSuccess($resSuccess);
        $this->assertSame(1, $resSuccess['data_count']);
        $this->assertSame('115HA010001', $resSuccess['raw'][0]['CASE_NO']);
    }

    /**
     * 路徑 9：支援各 site 連線類型與不支援型態防護 (default 分支)
     */
    public function testSiteConnectionsAndUnsupportedType(): void {
        $sites = ['L1HWEB', 'L1HWEB_Alt', 'L2HWEB', 'L3HWEB'];
        foreach ($sites as $site) {
            $res = $this->post($this->endpoint, [
                'type' => 'lxhweb_config',
                'site' => $site
            ]);
            $this->assertResponseSuccess($res);
        }

        // 不支援的 type
        $resInvalid = $this->post($this->endpoint, [
            'type' => 'unsupported_lxhweb_type'
        ]);
        $this->assertSame(STATUS_CODE::UNSUPPORT_FAIL, $resInvalid['status']);
        $this->assertStringContainsString('不支援的查詢型態【unsupported_lxhweb_type】', $resInvalid['message']);

        // 空 type 請求
        $resEmpty = $this->post($this->endpoint, []);
        $this->assertSame(STATUS_CODE::UNSUPPORT_FAIL, $resEmpty['status']);
        $this->assertStringContainsString('不支援的查詢型態【】', $resEmpty['message']);
    }
}
