<?php
declare(strict_types=1);

namespace Tests\Integration;

use SQLite3;
use STATUS_CODE;

class MoicadJsonApiTest extends BaseIntegrationTestCase {
    private string $endpoint = 'moicad_json_api.php';
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
     * 路徑 1：RCODA 查無快取/失敗分支 (type=rcoda 查無快取分支)
     */
    public function testRcodaFailWhenNoCache(): void {
        $this->clearMockCache('moicad_rcoda');

        $res = $this->post($this->endpoint, [
            'type' => 'rcoda'
        ]);

        $this->assertSame(STATUS_CODE::FAIL_DB_ERROR, $res['status']);
        $this->assertStringContainsString('查詢 MOICAD.RCODA 失敗', $res['message']);
        $this->assertFalse($res['raw']);
    }

    /**
     * 路徑 2：RCODA 查詢到快取資料分支 (type=rcoda 成功分支)
     */
    public function testRcodaSuccessWithMockData(): void {
        $mockRcoda = [
            ['CODE' => '01', 'NAME' => '土地標示變更'],
            ['CODE' => '02', 'NAME' => '建物標示變更']
        ];
        $this->setMockCache('moicad_rcoda', $mockRcoda);

        $res = $this->post($this->endpoint, [
            'type' => 'rcoda'
        ]);

        $this->assertResponseSuccess($res);
        $this->assertArrayHasKey('raw', $res);
        $this->assertIsArray($res['raw']);
        $this->assertCount(2, $res['raw']);
        $this->assertStringContainsString('目前查到 MOICAD.RCODA 裡有 2 筆資料', $res['message']);
    }

    /**
     * 路徑 3：REGA 成功查詢既有快取區間資料 (type=rega 成功分支)
     */
    public function testRegaSuccessWithExistingDateRange(): void {
        $st = '1140101';
        $ed = '1140131';

        $res = $this->post($this->endpoint, [
            'type' => 'rega',
            'st' => $st,
            'ed' => $ed
        ]);

        $this->assertResponseSuccess($res);
        $this->assertArrayHasKey('raw', $res);
        $this->assertIsArray($res['raw']);
        $this->assertNotEmpty($res['raw']);
        $this->assertStringContainsString("{$st} ~ {$ed} 查到 MOICAD.REGA 裡有", $res['message']);
    }

    /**
     * 路徑 4：REGA 查無快取區間資料失敗分支 (type=rega 查無快取分支)
     */
    public function testRegaFailWithUncachedDateRange(): void {
        $st = '9990101';
        $ed = '9990131';

        $res = $this->post($this->endpoint, [
            'type' => 'rega',
            'st' => $st,
            'ed' => $ed
        ]);

        $this->assertSame(STATUS_CODE::FAIL_DB_ERROR, $res['status']);
        $this->assertStringContainsString('查詢 MOICAD.REGA 失敗', $res['message']);
        $this->assertFalse($res['raw']);
    }

    /**
     * 路徑 5：REGA 搭配 reload 旗標與參數安全性 (type=rega reload 測試)
     */
    public function testRegaWithReloadFlag(): void {
        $st = '1140101';
        $ed = '1140131';

        $res = $this->post($this->endpoint, [
            'type' => 'rega',
            'st' => $st,
            'ed' => $ed,
            'reload' => 'true'
        ]);

        $this->assertResponseSuccess($res);
        $this->assertArrayHasKey('raw', $res);
        $this->assertIsArray($res['raw']);
    }

    /**
     * 路徑 6：不支援的查詢型態與空請求防護 (default 分支)
     */
    public function testUnsupportedTypeAndEmptyPost(): void {
        // 不支援型態
        $resInvalid = $this->post($this->endpoint, [
            'type' => 'unknown_moicad_type'
        ]);
        $this->assertSame(STATUS_CODE::UNSUPPORT_FAIL, $resInvalid['status']);
        $this->assertStringContainsString('不支援的查詢型態【unknown_moicad_type】', $resInvalid['message']);

        // 空參數防護
        $resEmpty = $this->post($this->endpoint, []);
        $this->assertSame(STATUS_CODE::UNSUPPORT_FAIL, $resEmpty['status']);
        $this->assertStringContainsString('不支援的查詢型態【】', $resEmpty['message']);
    }
}
