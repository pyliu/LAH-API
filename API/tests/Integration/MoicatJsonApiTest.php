<?php
declare(strict_types=1);

namespace Tests\Integration;

use SQLite3;
use STATUS_CODE;

class MoicatJsonApiTest extends BaseIntegrationTestCase {
    private string $endpoint = 'moicat_json_api.php';
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
     * 路徑 1：RXSEQ 查無快取/失敗分支 (type=moicat_rxseq 失敗分支)
     */
    public function testMoicatRxseqFailWhenNoCache(): void {
        $this->clearMockCache('moicat_rxseq');

        $res = $this->post($this->endpoint, [
            'type' => 'moicat_rxseq',
            'year' => '114',
            'month' => '01',
            'day' => '01'
        ]);

        $this->assertSame(STATUS_CODE::FAIL_DB_ERROR, $res['status']);
        $this->assertStringContainsString('查詢 MOICAT.RXSEQ 失敗', $res['message']);
        $this->assertFalse($res['raw']);
    }

    /**
     * 路徑 2：RXSEQ 查詢到快取資料分支 (type=moicat_rxseq 成功分支)
     */
    public function testMoicatRxseqSuccess(): void {
        $mockRows = [
            ['XS16' => '1140001', 'XS03' => '114', 'XS04_1' => 'HA01', 'XS04_2' => '000001']
        ];
        $this->setMockCache('moicat_rxseq', $mockRows);

        $res = $this->post($this->endpoint, [
            'type' => 'moicat_rxseq',
            'year' => '114',
            'month' => '01',
            'day' => '01'
        ]);

        $this->assertResponseSuccess($res);
        $this->assertArrayHasKey('raw', $res);
        $this->assertIsArray($res['raw']);
        $this->assertCount(1, $res['raw']);
        $this->assertStringContainsString('目前查到 MOICAT.RXSEQ 裡有 1 筆資料', $res['message']);
    }

    /**
     * 路徑 3：取得最新一筆權狀序號資料 (type=moicat_rxseq_latest 成功分支)
     */
    public function testMoicatRxseqLatestSuccess(): void {
        $mockLatest = [
            'XS16' => '1140001',
            'XS03' => '114',
            'XS04_1' => 'HA01',
            'XS04_2' => '000001'
        ];
        $this->setMockCache('moicat_rxseq_latest', $mockLatest);

        $res = $this->post($this->endpoint, [
            'type' => 'moicat_rxseq_latest'
        ]);

        $this->assertResponseSuccess($res);
        $this->assertArrayHasKey('raw', $res);
        $this->assertArrayHasKey('serial', $res);
        $this->assertArrayHasKey('caseno', $res);
        $this->assertIsArray($res['raw']);
        $this->assertSame('1140001', $res['serial']);
        $this->assertSame('114-HA01-000001', $res['caseno']);
        $this->assertStringContainsString('查到 MOICAT.RXSEQ 最新一筆資料序號為 1140001', $res['message']);
    }

    /**
     * 路徑 4：查詢暫存 RINDX 記錄 (type=moicat_rindx 成功分支)
     */
    public function testMoicatRindxSuccess(): void {
        $res = $this->post($this->endpoint, [
            'type' => 'moicat_rindx',
            'year' => '114',
            'code' => 'HA01',
            'num' => '000001'
        ]);

        $this->assertResponseSuccess($res);
        $this->assertArrayHasKey('raw', $res);
        $this->assertIsArray($res['raw']);
        $this->assertStringContainsString('目前查到 MOICAT.RINDX 裡有', $res['message']);
    }

    /**
     * 路徑 5：修復 RINDX 暫存 IP_CODE (type=fix_moicat_rindx)
     */
    public function testFixMoicatRindx(): void {
        $res = $this->post($this->endpoint, [
            'type' => 'fix_moicat_rindx',
            'year' => '114',
            'code' => 'HA01',
            'num' => '000001'
        ]);

        $this->assertArrayHasKey('raw', $res);
        $this->assertStringContainsString('設定 114-HA01-000001 MOICAT.RINDX IP_CODE 為 F', $res['message']);
    }

    /**
     * 路徑 6：不支援的查詢型態與空請求防護 (default 分支)
     */
    public function testUnsupportedTypeAndEmptyPost(): void {
        // 不支援型態
        $resInvalid = $this->post($this->endpoint, [
            'type' => 'unknown_moicat_type'
        ]);
        $this->assertSame(STATUS_CODE::UNSUPPORT_FAIL, $resInvalid['status']);
        $this->assertStringContainsString('不支援的查詢型態【unknown_moicat_type】', $resInvalid['message']);

        // 空參數防護
        $resEmpty = $this->post($this->endpoint, []);
        $this->assertSame(STATUS_CODE::UNSUPPORT_FAIL, $resEmpty['status']);
        $this->assertStringContainsString('不支援的查詢型態【】', $resEmpty['message']);
    }
}
