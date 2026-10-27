<?php
declare(strict_types=1);

namespace Tests\Integration;

use SQLite3;
use STATUS_CODE;

class MoismsJsonApiTest extends BaseIntegrationTestCase {
    private string $endpoint = 'moisms_json_api.php';
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
     * 路徑 1：全方位簡訊紀錄關鍵字查詢 (type=moisms_log_query)
     */
    public function testMoismsLogQuery(): void {
        // 帶關鍵字查詢
        $res = $this->post($this->endpoint, [
            'type' => 'moisms_log_query',
            'keyword' => '0912345678'
        ]);

        $this->assertSame(STATUS_CODE::SUCCESS_NORMAL, $res['status']);
        $this->assertIsArray($res['raw']);
        $this->assertStringContainsString('目前查到', $res['message']);

        // 驗證排序：SMS_DATE + SMS_TIME 應為降冪排序
        if (count($res['raw']) >= 2) {
            for ($i = 0; $i < count($res['raw']) - 1; $i++) {
                $dtA = ($res['raw'][$i]['SMS_DATE'] ?? '') . ($res['raw'][$i]['SMS_TIME'] ?? '');
                $dtB = ($res['raw'][$i + 1]['SMS_DATE'] ?? '') . ($res['raw'][$i + 1]['SMS_TIME'] ?? '');
                $this->assertGreaterThanOrEqual(0, strcmp($dtA, $dtB));
            }
        }

        // 不帶 keyword 參數
        $resNoKeyword = $this->post($this->endpoint, [
            'type' => 'moisms_log_query'
        ]);
        $this->assertSame(STATUS_CODE::SUCCESS_NORMAL, $resNoKeyword['status']);
        $this->assertIsArray($resNoKeyword['raw']);
    }

    /**
     * 路徑 2：簡訊統計與紀錄日期區間查詢 (type=moisms_log_query_by_date)
     */
    public function testMoismsLogQueryByDate(): void {
        $res = $this->post($this->endpoint, [
            'type' => 'moisms_log_query_by_date',
            'st' => '1130101',
            'ed' => '1130131'
        ]);

        $this->assertSame(STATUS_CODE::SUCCESS_NORMAL, $res['status']);
        $this->assertIsArray($res['raw']);
        $this->assertStringContainsString('目前查到', $res['message']);

        if (count($res['raw']) >= 2) {
            for ($i = 0; $i < count($res['raw']) - 1; $i++) {
                $dtA = ($res['raw'][$i]['SMS_DATE'] ?? '') . ($res['raw'][$i]['SMS_TIME'] ?? '');
                $dtB = ($res['raw'][$i + 1]['SMS_DATE'] ?? '') . ($res['raw'][$i + 1]['SMS_TIME'] ?? '');
                $this->assertGreaterThanOrEqual(0, strcmp($dtA, $dtB));
            }
        }
    }

    /**
     * 路徑 3：地籍異動即時通 LOG 紀錄日期區間查詢 (type=moiadm_smslog_query_by_date)
     */
    public function testMoiadmSmslogQueryByDate(): void {
        // 成功查詢
        $res = $this->post($this->endpoint, [
            'type' => 'moiadm_smslog_query_by_date',
            'st' => '1130101',
            'ed' => '1130131'
        ]);

        $this->assertSame(STATUS_CODE::SUCCESS_NORMAL, $res['status']);
        $this->assertIsArray($res['raw']);
        $this->assertStringContainsString('目前查到', $res['message']);

        // 查無資料或快取為 false 之失敗分支
        $this->setMockCache('moiadm_smslog_query_by_date', false);
        $resFail = $this->post($this->endpoint, [
            'type' => 'moiadm_smslog_query_by_date',
            'st' => '1130101',
            'ed' => '1130131'
        ]);
        $this->assertSame(STATUS_CODE::FAIL_DB_ERROR, $resFail['status']);
        $this->assertStringContainsString('查詢MOIADM.SMSLOG失敗', $resFail['message']);
    }

    /**
     * 路徑 4：取得失敗的地籍異動即時通紀錄 (type=moiadm_failure_sms_query)
     */
    public function testMoiadmFailureSmsQuery(): void {
        // 格式錯誤分支 (無效民國日)
        $resInvalid = $this->post($this->endpoint, [
            'type' => 'moiadm_failure_sms_query',
            'tw_date' => 'invalid_date'
        ]);
        $this->assertSame(STATUS_CODE::DEFAULT_FAIL, $resInvalid['status']);
        $this->assertFalse($resInvalid['raw']);

        // 成功取得紀錄分支 (注入模擬紀錄)
        $mockRecords = [
            ['SMS_DATE' => '1130101', 'SMS_TIME' => '100000', 'STATUS' => 'FAIL', 'MSG' => 'Timeout']
        ];
        $this->setMockCache('moiadm_failure_sms_query', $mockRecords);

        $resSuccess = $this->post($this->endpoint, [
            'type' => 'moiadm_failure_sms_query',
            'tw_date' => '1130101'
        ]);
        $this->assertSame(STATUS_CODE::SUCCESS_NORMAL, $resSuccess['status']);
        $this->assertSame($mockRecords, $resSuccess['raw']);

        // 查無資料或回傳 false 之分支
        $this->setMockCache('moiadm_failure_sms_query', false);
        $resNotFound = $this->post($this->endpoint, [
            'type' => 'moiadm_failure_sms_query',
            'tw_date' => '1130101'
        ]);
        $this->assertSame(STATUS_CODE::DEFAULT_FAIL, $resNotFound['status']);
        $this->assertFalse($resNotFound['raw']);
    }

    /**
     * 路徑 5：重送失敗的地籍異動即時通紀錄 (type=moiadm_failure_sms_resend)
     */
    public function testMoiadmFailureSmsResend(): void {
        // 格式錯誤分支 (無效民國日)
        $resInvalid = $this->post($this->endpoint, [
            'type' => 'moiadm_failure_sms_resend',
            'tw_date' => '9999999'
        ]);
        $this->assertSame(STATUS_CODE::DEFAULT_FAIL, $resInvalid['status']);
        $this->assertFalse($resInvalid['raw']);

        // 重送成功分支
        $this->setMockCache('moiadm_failure_sms_resend', true);
        $resSuccess = $this->post($this->endpoint, [
            'type' => 'moiadm_failure_sms_resend',
            'tw_date' => '1130101'
        ]);
        $this->assertSame(STATUS_CODE::SUCCESS_NORMAL, $resSuccess['status']);
        $this->assertTrue($resSuccess['raw']);

        // 重送失敗分支
        $this->setMockCache('moiadm_failure_sms_resend', false);
        $resFail = $this->post($this->endpoint, [
            'type' => 'moiadm_failure_sms_resend',
            'tw_date' => '1130101'
        ]);
        $this->assertSame(STATUS_CODE::DEFAULT_FAIL, $resFail['status']);
        $this->assertFalse($resFail['raw']);
    }

    /**
     * 路徑 6：利用 MOICAS.MA05 手動發送簡訊 (type=moicas_ma05_sms)
     */
    public function testMoicasMa05Sms(): void {
        // 成功發送分支
        $this->setMockCache('moicas_ma05_sms', '1150615000016');
        $resSuccess = $this->post($this->endpoint, [
            'type' => 'moicas_ma05_sms',
            'cell' => '0912345678',
            'message' => '測試手動簡訊',
            'rdate' => '1130101',
            'rtime' => '120000'
        ]);
        $this->assertSame(STATUS_CODE::SUCCESS_NORMAL, $resSuccess['status']);
        $this->assertSame('1150615000016', $resSuccess['raw']);

        // 失敗分支
        $this->setMockCache('moicas_ma05_sms', false);
        $resFail = $this->post($this->endpoint, [
            'type' => 'moicas_ma05_sms',
            'cell' => '0912345678',
            'message' => '測試手動簡訊'
        ]);
        $this->assertSame(STATUS_CODE::DEFAULT_FAIL, $resFail['status']);
        $this->assertFalse($resFail['raw']);
    }

    /**
     * 路徑 7：利用 MOICAS.MA05 手動重送簡訊 (type=moicas_ma05_resend_sms)
     */
    public function testMoicasMa05ResendSms(): void {
        // 成功重送分支
        $this->setMockCache('moicas_ma05_resend_sms', true);
        $resSuccess = $this->post($this->endpoint, [
            'type' => 'moicas_ma05_resend_sms',
            'ma5_no' => 'MA520261027001',
            'cell' => '0912345678',
            'message' => '測試重送'
        ]);
        $this->assertSame(STATUS_CODE::SUCCESS_NORMAL, $resSuccess['status']);
        $this->assertTrue($resSuccess['raw']);

        // 失敗分支
        $this->setMockCache('moicas_ma05_resend_sms', false);
        $resFail = $this->post($this->endpoint, [
            'type' => 'moicas_ma05_resend_sms',
            'cell' => '0912345678',
            'message' => '測試重送'
        ]);
        $this->assertSame(STATUS_CODE::DEFAULT_FAIL, $resFail['status']);
        $this->assertFalse($resFail['raw']);
    }

    /**
     * 路徑 8：不支援的查詢型態與空請求防護 (type=unsupported / empty)
     */
    public function testUnsupportedTypeAndEmptyPost(): void {
        // 不支援型態
        $resUnsupported = $this->post($this->endpoint, [
            'type' => 'unsupported_test_type'
        ]);
        $this->assertSame(STATUS_CODE::UNSUPPORT_FAIL, $resUnsupported['status']);
        $this->assertStringContainsString('不支援的查詢型態【unsupported_test_type】', $resUnsupported['message']);

        // 空請求
        $resEmpty = $this->post($this->endpoint, []);
        $this->assertSame(STATUS_CODE::UNSUPPORT_FAIL, $resEmpty['status']);
        $this->assertStringContainsString('不支援的查詢型態【】', $resEmpty['message']);
    }
}
