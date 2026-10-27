<?php
declare(strict_types=1);

namespace Tests\Integration;

use SQLite3;
use STATUS_CODE;

class MssqlJsonApiTest extends BaseIntegrationTestCase {
    private string $endpoint = 'mssql_json_api.php';
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
     * 路徑 1：查詢使用者信差訊息 (type=user_message)
     */
    public function testUserMessage(): void {
        // 查無訊息分支 (原快取預設為 false)
        $resNotFound = $this->post($this->endpoint, [
            'type' => 'user_message',
            'id' => 'TEST_USER',
            'count' => '5'
        ]);
        $this->assertSame(STATUS_CODE::DEFAULT_FAIL, $resNotFound['status']);
        $this->assertStringContainsString('查無 TEST_USER 信差訊息', $resNotFound['message']);

        // 查得訊息分支 (模擬一筆或多筆訊息)
        $mockMessages = [
            ['sn' => 99901, 'sender' => 'ADMIN', 'message' => '測試訊息 1'],
            ['sn' => 99902, 'sender' => 'SYSTEM', 'message' => '測試訊息 2']
        ];
        $this->setMockCache('user_message', $mockMessages);

        $resFound = $this->post($this->endpoint, [
            'type' => 'user_message',
            'id' => 'TEST_USER',
            'count' => '5'
        ]);
        $this->assertSame(STATUS_CODE::SUCCESS_NORMAL, $resFound['status']);
        $this->assertSame(2, $resFound['data_count']);
        $this->assertSame($mockMessages, $resFound['raw']);
        $this->assertStringContainsString('查詢 TEST_USER 信差訊息成功', $resFound['message']);
    }

    /**
     * 路徑 2：查詢使用者未讀信差訊息 (type=user_unread_message)
     */
    public function testUserUnreadMessage(): void {
        // 查得未讀訊息分支 (原快取預設有一筆未讀資料)
        $resFound = $this->post($this->endpoint, [
            'type' => 'user_unread_message',
            'id' => 'TEST_USER'
        ]);
        $this->assertSame(STATUS_CODE::SUCCESS_NORMAL, $resFound['status']);
        $this->assertGreaterThanOrEqual(1, $resFound['data_count']);
        $this->assertIsArray($resFound['raw']);
        $this->assertStringContainsString('查詢 TEST_USER 未讀信差訊息成功', $resFound['message']);

        // 查無未讀訊息分支
        $this->setMockCache('user_unread_message', false);
        $resNotFound = $this->post($this->endpoint, [
            'type' => 'user_unread_message',
            'id' => 'TEST_USER'
        ]);
        $this->assertSame(STATUS_CODE::DEFAULT_FAIL, $resNotFound['status']);
        $this->assertStringContainsString('查無 TEST_USER 未讀信差訊息', $resNotFound['message']);
    }

    /**
     * 路徑 3：設定已讀使用者信差訊息 (type=set_read_user_message)
     */
    public function testSetReadUserMessage(): void {
        // 成功分支 (原快取為 true)
        $resSuccess = $this->post($this->endpoint, [
            'type' => 'set_read_user_message',
            'sn' => '487487'
        ]);
        $this->assertSame(STATUS_CODE::SUCCESS_NORMAL, $resSuccess['status']);
        $this->assertStringContainsString('設定 487487 已讀成功', $resSuccess['message']);
        $this->assertSame(1, $resSuccess['data_count']);

        // 失敗分支
        $this->setMockCache('set_read_user_message', false);
        $resFail = $this->post($this->endpoint, [
            'type' => 'set_read_user_message',
            'sn' => '487487'
        ]);
        $this->assertSame(STATUS_CODE::DEFAULT_FAIL, $resFail['status']);
    }

    /**
     * 路徑 4：設定未讀使用者信差訊息 (type=set_unread_user_message)
     */
    public function testSetUnreadUserMessage(): void {
        // 成功分支 (原快取為 true)
        $resSuccess = $this->post($this->endpoint, [
            'type' => 'set_unread_user_message',
            'sn' => '487487'
        ]);
        $this->assertSame(STATUS_CODE::SUCCESS_NORMAL, $resSuccess['status']);
        $this->assertStringContainsString('設定 487487 未讀成功', $resSuccess['message']);
        $this->assertSame(1, $resSuccess['data_count']);

        // 失敗分支
        $this->setMockCache('set_unread_user_message', false);
        $resFail = $this->post($this->endpoint, [
            'type' => 'set_unread_user_message',
            'sn' => '487487'
        ]);
        $this->assertSame(STATUS_CODE::DEFAULT_FAIL, $resFail['status']);
    }

    /**
     * 路徑 5：刪除訊息 (type=del_user_message)
     */
    public function testDelUserMessage(): void {
        // 失敗分支 (原快取為 0)
        $resFail = $this->post($this->endpoint, [
            'type' => 'del_user_message',
            'sn' => '487487'
        ]);
        $this->assertSame(STATUS_CODE::DEFAULT_FAIL, $resFail['status']);
        $this->assertStringContainsString('刪除「487487」訊息失敗', $resFail['message']);

        // 成功分支
        $this->setMockCache('del_user_message', 1);
        $resSuccess = $this->post($this->endpoint, [
            'type' => 'del_user_message',
            'sn' => '487487'
        ]);
        $this->assertSame(STATUS_CODE::SUCCESS_NORMAL, $resSuccess['status']);
        $this->assertStringContainsString('刪除「487487」訊息成功', $resSuccess['message']);
        $this->assertSame('487487', $resSuccess['sn']);
    }

    /**
     * 路徑 6：送出訊息 (type=send_message)
     */
    public function testSendMessage(): void {
        // 1. 成功送出分支 (原快取為 sn 548205 > 0)
        $resSuccess = $this->post($this->endpoint, [
            'type' => 'send_message',
            'title' => '系統通知測試',
            'content' => '這是測試內文',
            'who' => 'TEST_RECIPIENT',
            'send_time' => '08:00:00',
            'end_time' => '17:00:00'
        ]);
        $this->assertSame(STATUS_CODE::SUCCESS_NORMAL, $resSuccess['status']);
        $this->assertStringContainsString('給「TEST_RECIPIENT」訊息傳送成功', $resSuccess['message']);
        $this->assertSame(548205, $resSuccess['sn']);

        // 2. 找不到人員分支 (sn = -1)
        $this->setMockCache('send_message', -1);
        $resUserNotFound = $this->post($this->endpoint, [
            'type' => 'send_message',
            'title' => '測試',
            'content' => '測試',
            'who' => 'NON_EXISTENT_USER',
            'send_time' => '08:00:00',
            'end_time' => '17:00:00'
        ]);
        $this->assertSame(STATUS_CODE::DEFAULT_FAIL, $resUserNotFound['status']);
        $this->assertStringContainsString('現職人員找不到 NON_EXISTENT_USER 故無法傳送訊息', $resUserNotFound['message']);

        // 3. 時間區間有問題分支 (sn = -2)
        $this->setMockCache('send_message', -2);
        $resTimeError = $this->post($this->endpoint, [
            'type' => 'send_message',
            'title' => '測試',
            'content' => '測試',
            'who' => 'TEST_RECIPIENT',
            'send_time' => '18:00:00',
            'end_time' => '08:00:00'
        ]);
        $this->assertSame(STATUS_CODE::DEFAULT_FAIL, $resTimeError['status']);
        $this->assertStringContainsString('時間區間有問題，故無法傳送訊息', $resTimeError['message']);

        // 4. 忽略時間已逾時分支 (sn = -4)
        $this->setMockCache('send_message', -4);
        $resExpired = $this->post($this->endpoint, [
            'type' => 'send_message',
            'title' => '測試',
            'content' => '測試',
            'who' => 'TEST_RECIPIENT',
            'send_time' => '07:00:00',
            'end_time' => '07:30:00'
        ]);
        $this->assertSame(STATUS_CODE::DEFAULT_FAIL, $resExpired['status']);
        $this->assertStringContainsString('忽略時間已超過現在時間，故無需傳送訊息', $resExpired['message']);

        // 5. 新增失敗分支 (sn = 0)
        $this->setMockCache('send_message', 0);
        $resOtherFail = $this->post($this->endpoint, [
            'type' => 'send_message',
            'title' => '失敗測試',
            'content' => '失敗內文',
            'who' => 'TEST_RECIPIENT',
            'send_time' => '08:00:00',
            'end_time' => '17:00:00'
        ]);
        $this->assertSame(STATUS_CODE::DEFAULT_FAIL, $resOtherFail['status']);
        $this->assertStringContainsString('新增 失敗測試 訊息失敗', $resOtherFail['message']);
    }

    /**
     * 路徑 7：更新舊資料庫分機號碼 (type=upd_ext_doc)
     */
    public function testUpdExtDoc(): void {
        // 失敗分支 (原快取為 false)
        $resFail = $this->post($this->endpoint, [
            'type' => 'upd_ext_doc',
            'id' => 'USER01',
            'ext' => '1234'
        ]);
        $this->assertSame(STATUS_CODE::DEFAULT_FAIL, $resFail['status']);
        $this->assertStringContainsString('更新舊資料庫分機號碼 USER01, 1234 失敗', $resFail['message']);

        // 成功分支
        $this->setMockCache('upd_ext_doc', true);
        $resSuccess = $this->post($this->endpoint, [
            'type' => 'upd_ext_doc',
            'id' => 'USER01',
            'ext' => '1234'
        ]);
        $this->assertSame(STATUS_CODE::SUCCESS_NORMAL, $resSuccess['status']);
        $this->assertStringContainsString('更新舊資料庫分機號碼 USER01, 1234 成功', $resSuccess['message']);
    }

    /**
     * 路徑 8：更新知識網使用者 IP (type=upd_ip_tdoc)
     */
    public function testUpdIpTdoc(): void {
        $res = $this->post($this->endpoint, [
            'type' => 'upd_ip_tdoc',
            'id' => 'HA0001',
            'ip' => '192.168.1.100'
        ]);
        $this->assertSame(STATUS_CODE::SUCCESS_NORMAL, $res['status']);
        $this->assertStringContainsString('更新知識網使用者 HA0001 IP  => 192.168.1.100 已送出', $res['message']);
    }

    /**
     * 路徑 9：不支援的查詢型態與空請求防護 (type=unsupported / empty)
     */
    public function testUnsupportedTypeAndEmptyPost(): void {
        // 不支援型態
        $resUnsupported = $this->post($this->endpoint, [
            'type' => 'unsupported_mssql_type'
        ]);
        $this->assertSame(STATUS_CODE::UNSUPPORT_FAIL, $resUnsupported['status']);
        $this->assertStringContainsString('不支援的查詢型態【unsupported_mssql_type】', $resUnsupported['message']);

        // 空請求
        $resEmpty = $this->post($this->endpoint, []);
        $this->assertSame(STATUS_CODE::UNSUPPORT_FAIL, $resEmpty['status']);
        $this->assertStringContainsString('不支援的查詢型態【】', $resEmpty['message']);
    }
}
