<?php
declare(strict_types=1);

namespace Tests\Integration;

use SQLite3;
use STATUS_CODE;

class MonitorJsonApiTest extends BaseIntegrationTestCase {
    private string $endpoint = 'monitor_json_api.php';
    private ?SQLite3 $mailDb = null;
    private ?SQLite3 $connDb = null;

    protected function setUp(): void {
        parent::setUp();
        $dbMailPath = defined('DB_DIR') ? DB_DIR . DIRECTORY_SEPARATOR . 'monitor_mail.db' : dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'www' . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'db' . DIRECTORY_SEPARATOR . 'monitor_mail.db';
        if (file_exists($dbMailPath)) {
            $this->mailDb = new SQLite3($dbMailPath);
            $this->mailDb->busyTimeout(5000);
        }

        $dbConnPath = defined('DB_DIR') ? DB_DIR . DIRECTORY_SEPARATOR . 'connectivity.db' : dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'www' . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'db' . DIRECTORY_SEPARATOR . 'connectivity.db';
        if (file_exists($dbConnPath)) {
            $this->connDb = new SQLite3($dbConnPath);
            $this->connDb->busyTimeout(5000);
        }
    }

    protected function tearDown(): void {
        // 清理測試過程中可能產生的暫存標的與郵件
        if ($this->connDb !== null) {
            $this->connDb->exec("DELETE FROM target WHERE ip = '192.0.2.254'");
            $this->connDb->exec("DELETE FROM connectivity WHERE target_ip LIKE '192.0.2.254%' OR target_ip = '220.1.34.205:9080'");
            $this->connDb->close();
            $this->connDb = null;
        }

        if ($this->mailDb !== null) {
            $this->mailDb->exec("DELETE FROM mail WHERE id < 0 AND sender = 'phpunit@test.local'");
            $this->mailDb->close();
            $this->mailDb = null;
        }

        parent::tearDown();
    }

    /**
     * 路徑 1：IMAP 連線狀態測試 (type=imap_open)
     */
    public function testImapOpen(): void {
        // SSL 啟用
        $resSsl = $this->post($this->endpoint, [
            'type' => 'imap_open',
            'host' => 'mail.example.local',
            'ssl' => 'true',
            'account' => 'testuser',
            'password' => 'secret123'
        ]);
        $this->assertSame(STATUS_CODE::SUCCESS_NORMAL, $resSsl['status']);
        $this->assertStringContainsString('郵件伺服器(mail.example.local)可正常連線', $resSsl['message']);

        // 非 SSL
        $resNoSsl = $this->post($this->endpoint, [
            'type' => 'imap_open',
            'host' => 'mail.example.local',
            'ssl' => 'false',
            'account' => 'testuser',
            'password' => 'secret123'
        ]);
        $this->assertSame(STATUS_CODE::SUCCESS_NORMAL, $resNoSsl['status']);
        $this->assertStringContainsString('郵件伺服器(mail.example.local)可正常連線', $resNoSsl['message']);
    }

    /**
     * 路徑 2：清除過期監控郵件 (type=clean_mail)
     */
    public function testCleanMail(): void {
        $res = $this->post($this->endpoint, [
            'type' => 'clean_mail'
        ]);
        $this->assertSame(STATUS_CODE::DEFAULT_FAIL, $res['status']);
        $this->assertSame('尚未實作', $res['message']);
    }

    /**
     * 路徑 3：檢查最新監控郵件 (type=check_mail)
     */
    public function testCheckMail(): void {
        $res = $this->post($this->endpoint, [
            'type' => 'check_mail'
        ]);
        // 在 MOCK 模式下略過實際伺服器連線，回傳 0 封郵件
        $this->assertSame(STATUS_CODE::DEFAULT_FAIL, $res['status']);
        $this->assertSame('沒有新的監控郵件', $res['message']);
    }

    /**
     * 路徑 4：查詢最新一封監控郵件 (type=latest)
     */
    public function testLatestMail(): void {
        $res = $this->post($this->endpoint, [
            'type' => 'latest',
            'convert' => 'false'
        ]);
        $this->assertSame(STATUS_CODE::SUCCESS_NORMAL, $res['status']);
        $this->assertSame('查詢到1封郵件', $res['message']);
        $this->assertGreaterThanOrEqual(1, $res['data_count']);
        $this->assertIsArray($res['raw']);
        $this->assertArrayHasKey('id', $res['raw']);
        $this->assertArrayHasKey('subject', $res['raw']);

        // 帶 convert=true 轉換編碼
        $resConvert = $this->post($this->endpoint, [
            'type' => 'latest',
            'convert' => 'true'
        ]);
        $this->assertSame(STATUS_CODE::SUCCESS_NORMAL, $resConvert['status']);
        $this->assertIsArray($resConvert['raw']);
    }

    /**
     * 路徑 5：主旨關鍵字查詢監控郵件 (type=subject)
     */
    public function testMailSearchBySubject(): void {
        // 成功查得郵件分支 (資料庫內既有包含 BACKUP 或 check 之主旨)
        $resFound = $this->post($this->endpoint, [
            'type' => 'subject',
            'keyword' => 'check',
            'days' => '3650',
            'convert' => 'false'
        ]);
        $this->assertSame(STATUS_CODE::SUCCESS_WITH_MULTIPLE_RECORDS, $resFound['status']);
        $this->assertIsArray($resFound['raw']);
        $this->assertGreaterThan(0, $resFound['data_count']);
        $this->assertStringContainsString('查詢到', $resFound['message']);

        // 查無郵件分支
        $resNotFound = $this->post($this->endpoint, [
            'type' => 'subject',
            'keyword' => 'NonExistentSubjectKeyword_999999',
            'days' => '1'
        ]);
        $this->assertSame(STATUS_CODE::DEFAULT_FAIL, $resNotFound['status']);
        $this->assertStringContainsString('沒有找到郵件', $resNotFound['message']);
    }

    /**
     * 路徑 6：寄件者查詢監控郵件 (type=sender)
     */
    public function testMailSearchBySender(): void {
        // 成功查得寄件者分支
        $resFound = $this->post($this->endpoint, [
            'type' => 'sender',
            'keyword' => 'orahaha1',
            'days' => '3650',
            'convert' => 'false'
        ]);
        $this->assertSame(STATUS_CODE::SUCCESS_WITH_MULTIPLE_RECORDS, $resFound['status']);
        $this->assertIsArray($resFound['raw']);
        $this->assertGreaterThan(0, $resFound['data_count']);

        // 查無寄件者分支
        $resNotFound = $this->post($this->endpoint, [
            'type' => 'sender',
            'keyword' => 'nonexistent_sender_xyz@example.local',
            'days' => '1'
        ]);
        $this->assertSame(STATUS_CODE::DEFAULT_FAIL, $resNotFound['status']);
        $this->assertStringContainsString('沒有找到郵件', $resNotFound['message']);
    }

    /**
     * 路徑 7：查詢監控系統標的列表 (type=monitor_targets)
     */
    public function testMonitorTargets(): void {
        // 僅啟用中標的 (all != true)
        $resActive = $this->post($this->endpoint, [
            'type' => 'monitor_targets'
        ]);
        $this->assertSame(STATUS_CODE::SUCCESS_NORMAL, $resActive['status']);
        $this->assertIsArray($resActive['raw']);
        $this->assertGreaterThanOrEqual(1, $resActive['data_count']);

        // 全數標的 (all = true)
        $resAll = $this->post($this->endpoint, [
            'type' => 'monitor_targets',
            'all' => 'true'
        ]);
        $this->assertSame(STATUS_CODE::SUCCESS_NORMAL, $resAll['status']);
        $this->assertIsArray($resAll['raw']);
        $this->assertGreaterThanOrEqual(1, $resAll['data_count']);
    }

    /**
     * 路徑 8：取代、更新與刪除監控標的 (replace, edit, remove)
     */
    public function testReplaceEditRemoveConnectivityTarget(): void {
        $testIp = '192.0.2.254';
        $testPort = '9999';

        // 1. 新增/取代監控標的
        $resReplace = $this->post($this->endpoint, [
            'type' => 'replace_connectivity_target',
            'name' => '整合測試標的',
            'ip' => $testIp,
            'port' => $testPort,
            'monitor' => 'Y',
            'note' => '測試備註說明'
        ]);
        $this->assertSame(STATUS_CODE::SUCCESS_NORMAL, $resReplace['status']);
        $this->assertStringContainsString("已取代 整合測試標的 {$testIp}:{$testPort} 監控標的資料", $resReplace['message']);

        // 2. 更新監控標的 (變更 Port 與名稱)
        $newPort = '9998';
        $resEdit = $this->post($this->endpoint, [
            'type' => 'edit_connectivity_target',
            'name' => '整合測試標的修改',
            'ip' => $testIp,
            'port' => $newPort,
            'monitor' => 'N',
            'note' => '更新後備註',
            'editIp' => $testIp,
            'editPort' => $testPort
        ]);
        $this->assertSame(STATUS_CODE::SUCCESS_NORMAL, $resEdit['status']);
        $this->assertStringContainsString("已更新 整合測試標的修改 {$testIp}:{$newPort} 監控標的資料", $resEdit['message']);

        // 3. 刪除監控標的
        $resRemove = $this->post($this->endpoint, [
            'type' => 'remove_connectivity_target',
            'name' => '整合測試標的修改',
            'ip' => $testIp,
            'port' => $newPort
        ]);
        $this->assertSame(STATUS_CODE::SUCCESS_NORMAL, $resRemove['status']);
        $this->assertStringContainsString("已刪除 整合測試標的修改 {$testIp}:{$newPort} 監控標的資料", $resRemove['message']);
    }

    /**
     * 路徑 9：查詢全監控系統標的歷史資料 (type=monitor_targets_history)
     */
    public function testMonitorTargetsHistory(): void {
        // 查無資料分支 (資料庫 connectivity 表原本為空)
        $resEmpty = $this->post($this->endpoint, [
            'type' => 'monitor_targets_history'
        ]);
        $this->assertSame(STATUS_CODE::DEFAULT_FAIL, $resEmpty['status']);
        $this->assertStringContainsString('取得全監控系統標的歷史資料失敗', $resEmpty['message']);

        // 暫時注入一筆連線紀錄以驗證成功分支
        if ($this->connDb !== null) {
            $stmt = $this->connDb->prepare("REPLACE INTO connectivity (log_time, target_ip, status, latency) VALUES (:time, :ip, 'UP', 1.5)");
            if ($stmt) {
                $stmt->bindValue(':time', date('YmdHis'));
                $stmt->bindValue(':ip', '220.1.34.205:9080');
                $stmt->execute();
            }
        }

        $resSuccess = $this->post($this->endpoint, [
            'type' => 'monitor_targets_history'
        ]);
        $this->assertSame(STATUS_CODE::SUCCESS_NORMAL, $resSuccess['status']);
        $this->assertIsArray($resSuccess['raw']);
        $this->assertGreaterThanOrEqual(1, $resSuccess['data_count']);
    }

    /**
     * 路徑 10：單一監控標的歷史資料查詢 (type=monitor_target_history)
     */
    public function testMonitorTargetHistory(): void {
        $testIp = '192.0.2.254';
        $testPort = '9999';

        // 查無紀錄分支
        $resNotFound = $this->post($this->endpoint, [
            'type' => 'monitor_target_history',
            'ip' => $testIp,
            'port' => $testPort
        ]);
        $this->assertSame(STATUS_CODE::DEFAULT_FAIL, $resNotFound['status']);
        $this->assertStringContainsString("取得 {$testIp} 監控系統標的歷史資料失敗", $resNotFound['message']);

        // 注入紀錄後成功查詢分支
        if ($this->connDb !== null) {
            $stmt = $this->connDb->prepare("REPLACE INTO connectivity (log_time, target_ip, status, latency) VALUES (:time, :ip, 'UP', 0.8)");
            if ($stmt) {
                $stmt->bindValue(':time', date('YmdHis'));
                $stmt->bindValue(':ip', "{$testIp}:{$testPort}");
                $stmt->execute();
            }
        }

        $resFound = $this->post($this->endpoint, [
            'type' => 'monitor_target_history',
            'ip' => $testIp,
            'port' => $testPort
        ]);
        $this->assertSame(STATUS_CODE::SUCCESS_NORMAL, $resFound['status']);
        $this->assertGreaterThanOrEqual(1, $resFound['data_count']);
        $this->assertIsArray($resFound['raw']);
        $this->assertSame("{$testIp}:{$testPort}", $resFound['raw']['target_ip']);
    }

    /**
     * 路徑 11：手動新增監控郵件紀錄 (type=add_mail_entry)
     */
    public function testAddMailEntry(): void {
        $res = $this->post($this->endpoint, [
            'type' => 'add_mail_entry',
            'SUBJECT' => 'PHPUnit 監控郵件測試',
            'MESSAGE' => '這是一封自動化測試新增的郵件內文',
            'FROM' => 'phpunit@test.local',
            'MAILBOX' => 'INBOX'
        ]);

        $this->assertSame(STATUS_CODE::SUCCESS_NORMAL, $res['status']);
        $this->assertStringContainsString('已新增 PHPUnit 監控郵件測試 監控MAIL', $res['message']);
        $this->assertTrue($res['raw']);
    }

    /**
     * 路徑 12：不支援的查詢型態與空請求防護 (type=unsupported / empty)
     */
    public function testUnsupportedTypeAndEmptyPost(): void {
        // 不支援型態
        $resUnsupported = $this->post($this->endpoint, [
            'type' => 'unsupported_monitor_type'
        ]);
        $this->assertSame(STATUS_CODE::UNSUPPORT_FAIL, $resUnsupported['status']);
        $this->assertStringContainsString('不支援的查詢型態【unsupported_monitor_type】', $resUnsupported['message']);

        // 空請求
        $resEmpty = $this->post($this->endpoint, []);
        $this->assertSame(STATUS_CODE::UNSUPPORT_FAIL, $resEmpty['status']);
        $this->assertStringContainsString('不支援的查詢型態【】', $resEmpty['message']);
    }
}
