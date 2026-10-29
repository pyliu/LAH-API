<?php
declare(strict_types=1);

namespace Tests\Integration;

use SQLite3;
use STATUS_CODE;

class MoiadmJsonApiTest extends BaseIntegrationTestCase {
    private string $endpoint = 'moiadm_json_api.php';
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
     * 路徑 1：成功查詢公告歷史記錄 (type=moiadm_publication_history 成功分支)
     */
    public function testMoiadmPublicationHistorySuccess(): void {
        $res = $this->post($this->endpoint, [
            'type' => 'moiadm_publication_history',
            'date' => date('Y/m/d'),
            'status' => 'rdy'
        ]);

        $this->assertResponseSuccess($res);
        $this->assertArrayHasKey('raw', $res);
        $this->assertIsArray($res['raw']);
        $this->assertGreaterThanOrEqual(1, count($res['raw']));
        $this->assertStringContainsString('目前查到 MOIADM.PUBLICATION_HISTORY 裡有', $res['message']);
    }

    /**
     * 路徑 2：公告歷史記錄查無快取/失敗分支 (type=moiadm_publication_history 失敗分支)
     */
    public function testMoiadmPublicationHistoryFail(): void {
        $this->clearMockCache('moiadm_publication_history');

        $res = $this->post($this->endpoint, [
            'type' => 'moiadm_publication_history'
        ]);

        $this->assertSame(STATUS_CODE::FAIL_DB_ERROR, $res['status']);
        $this->assertStringContainsString('查詢 MOIADM.PUBLICATION_HISTORY 失敗', $res['message']);
        $this->assertFalse($res['raw']);
    }

    /**
     * 路徑 3：簡訊紀錄查詢多型態分支 (type=moiadm_smslog, searchType=date|cell|note|email|default)
     */
    public function testMoiadmSmslogWithSearchTypes(): void {
        $searchTypes = ['date', 'cell', 'note', 'email', ''];

        foreach ($searchTypes as $sType) {
            $res = $this->post($this->endpoint, [
                'type' => 'moiadm_smslog',
                'searchType' => $sType,
                'keyword' => 'test'
            ]);

            $this->assertResponseSuccess($res);
            $this->assertArrayHasKey('raw', $res);
            $this->assertIsArray($res['raw']);
            $this->assertCount(32, $res['raw']);
            $this->assertStringContainsString('目前查到 MOIADM.SMSLog 裡有 32 筆資料', $res['message']);
        }
    }

    /**
     * 路徑 4：簡訊紀錄查無快取/失敗分支 (type=moiadm_smslog 失敗分支)
     */
    public function testMoiadmSmslogFail(): void {
        $this->clearMockCache('moiadm_smslog');

        $res = $this->post($this->endpoint, [
            'type' => 'moiadm_smslog'
        ]);

        $this->assertSame(STATUS_CODE::FAIL_DB_ERROR, $res['status']);
        $this->assertStringContainsString('查詢 MOIADM.SMSLog 失敗', $res['message']);
        $this->assertFalse($res['raw']);
    }

    /**
     * 路徑 5：成功查詢管轄地段清單 (type=host_sections 成功分支)
     */
    public function testHostSectionsSuccess(): void {
        $res = $this->post($this->endpoint, [
            'type' => 'host_sections'
        ]);

        $this->assertResponseSuccess($res);
        $this->assertArrayHasKey('raw', $res);
        $this->assertIsArray($res['raw']);
        $this->assertNotEmpty($res['raw']);
        $this->assertStringContainsString('筆本所管轄地段資料', $res['message']);

        // 驗證過濾後的地段結構
        $first = $res['raw'][0];
        $this->assertArrayHasKey('code', $first);
        $this->assertArrayHasKey('name', $first);
    }

    /**
     * 路徑 6：管轄地段查無快取/失敗分支 (type=host_sections 失敗分支)
     */
    public function testHostSectionsFail(): void {
        $this->clearMockCache('host_sections');

        $res = $this->post($this->endpoint, [
            'type' => 'host_sections'
        ]);

        $this->assertSame(STATUS_CODE::FAIL_DB_ERROR, $res['status']);
        $this->assertStringContainsString('查詢快取的 RKEYN_ALL 失敗', $res['message']);
        $this->assertSame([], $res['raw']);
    }

    /**
     * 路徑 7：不支援的查詢型態與空請求防護 (default 分支)
     */
    public function testUnsupportedTypeAndEmptyPost(): void {
        // 不支援型態
        $resInvalid = $this->post($this->endpoint, [
            'type' => 'unknown_moiadm_type'
        ]);
        $this->assertSame(STATUS_CODE::UNSUPPORT_FAIL, $resInvalid['status']);
        $this->assertStringContainsString('不支援的查詢型態【unknown_moiadm_type】', $resInvalid['message']);

        // 空參數防護
        $resEmpty = $this->post($this->endpoint, []);
        $this->assertSame(STATUS_CODE::UNSUPPORT_FAIL, $resEmpty['status']);
        $this->assertStringContainsString('不支援的查詢型態【】', $resEmpty['message']);
    }
}
