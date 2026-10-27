<?php
declare(strict_types=1);

namespace Tests\Integration;

use STATUS_CODE;
use System;

class NotificationJsonApiTest extends BaseIntegrationTestCase {
    private string $endpoint = 'notification_json_api.php';

    /**
     * 路徑 1：新增廣播公告訊息 (type=add_notification)
     */
    public function testAddNotification(): void {
        // 1. 指定頻道新增
        $res = $this->post($this->endpoint, [
            'type' => 'add_notification',
            'channels' => ['zztest'],
            'title' => '測試自動化公告標題',
            'content' => '這是自動化測試發布的公告內容',
            'priority' => '1',
            'sender' => 'TEST_SENDER',
            'from_ip' => '127.0.0.1'
        ]);
        $this->assertSame(STATUS_CODE::SUCCESS_NORMAL, $res['status']);
        $this->assertSame(1, $res['data_count']);
        $this->assertSame('測試自動化公告標題', $res['title']);
        $this->assertNotEmpty($res['added']);

        // 2. 空頻道時自動發送給 sender
        $resSender = $this->post($this->endpoint, [
            'type' => 'add_notification',
            'channels' => [],
            'sender' => 'zztest',
            'title' => '回傳給發送者公告',
            'content' => '測試內容'
        ]);
        $this->assertSame(STATUS_CODE::SUCCESS_NORMAL, $resSender['status']);

        // 3. myself 頻道測試 (符合站台代碼)
        $siteCode = System::getInstance()->getSiteCode();
        $resMyselfValid = $this->post($this->endpoint, [
            'type' => 'add_notification',
            'channels' => ['myself'],
            'sender' => $siteCode . '9999',
            'title' => '給自己的提醒',
            'content' => '內容'
        ]);
        $this->assertSame(STATUS_CODE::SUCCESS_NORMAL, $resMyselfValid['status']);

        // 4. myself 頻道測試 (非本所代碼略過)
        $resMyselfInvalid = $this->post($this->endpoint, [
            'type' => 'add_notification',
            'channels' => ['myself'],
            'sender' => 'OTHER_SITE_USER',
            'title' => '略過提醒',
            'content' => '內容'
        ]);
        $this->assertSame(STATUS_CODE::SUCCESS_NORMAL, $resMyselfInvalid['status']);
        $this->assertSame(0, $resMyselfInvalid['data_count']);
    }

    /**
     * 路徑 2：更新公告訊息 (type=upd_notification - 尚未實作)
     */
    public function testUpdNotification(): void {
        $res = $this->post($this->endpoint, [
            'type' => 'upd_notification'
        ]);
        $this->assertSame('NOT IMPLEMENTED', $res['message']);
    }

    /**
     * 路徑 3：移除公告訊息與依標題移除 (remove_notification / remove_notification_by_title)
     */
    public function testRemoveNotificationAndByTitle(): void {
        // 依 ID 與型態移除
        $resRemove = $this->post($this->endpoint, [
            'type' => 'remove_notification',
            'message_type' => 'inf',
            'channels' => [
                ['channel' => 'zztest', 'id' => 999999]
            ]
        ]);
        $this->assertSame(STATUS_CODE::SUCCESS_NORMAL, $resRemove['status']);
        $this->assertStringContainsString('移除訊息成功', $resRemove['message']);

        // 依標題移除過期公告
        $resRemoveTitle = $this->post($this->endpoint, [
            'type' => 'remove_notification_by_title',
            'channels' => ['zztest'],
            'title' => '測試自動化公告標題'
        ]);
        $this->assertSame(STATUS_CODE::SUCCESS_NORMAL, $resRemoveTitle['status']);
        $this->assertStringContainsString('根據標題移除訊息成功', $resRemoveTitle['message']);
    }

    /**
     * 路徑 4：取得頻道公告訊息 (get_notification / get_notification_before)
     */
    public function testGetNotificationAndBefore(): void {
        // 取得頻道最新訊息
        $resLatest = $this->post($this->endpoint, [
            'type' => 'get_notification',
            'channel' => 'inf',
            'limit' => 5
        ]);
        $this->assertContains($resLatest['status'], [
            STATUS_CODE::SUCCESS_NORMAL,
            STATUS_CODE::SUCCESS_WITH_MULTIPLE_RECORDS
        ]);
        $this->assertIsArray($resLatest['raw']);

        // 取得指定 ID 之前之歷史訊息
        $resBefore = $this->post($this->endpoint, [
            'type' => 'get_notification_before',
            'channel' => 'inf',
            'before' => '999999',
            'limit' => 5
        ]);
        $this->assertContains($resBefore['status'], [
            STATUS_CODE::SUCCESS_NORMAL,
            STATUS_CODE::SUCCESS_WITH_MULTIPLE_RECORDS
        ]);
        $this->assertIsArray($resBefore['raw']);
    }

    /**
     * 路徑 5：取得系統日誌紀錄 (type=get_log)
     */
    public function testGetLog(): void {
        // 成功查詢存在之日誌範圍
        $resSuccess = $this->post($this->endpoint, [
            'type' => 'get_log',
            'st' => '1151001',
            'ed' => '1151031'
        ]);
        $this->assertContains($resSuccess['status'], [
            STATUS_CODE::SUCCESS_NORMAL,
            STATUS_CODE::SUCCESS_WITH_MULTIPLE_RECORDS
        ]);
        $this->assertIsArray($resSuccess['raw']);

        // 日期格式錯誤防護
        $resInvalid = $this->post($this->endpoint, [
            'type' => 'get_log',
            'st' => 'invalid_st',
            'ed' => 'invalid_ed'
        ]);
        $this->assertSame(STATUS_CODE::DEFAULT_FAIL, $resInvalid['status']);
        $this->assertStringContainsString('時間格式錯誤', $resInvalid['message']);
    }

    /**
     * 路徑 6：使用者即時通私訊查詢 (type=user_message)
     */
    public function testUserMessage(): void {
        $res = $this->post($this->endpoint, [
            'type' => 'user_message',
            'id' => 'HATEST01',
            'count' => 5
        ]);
        $this->assertContains($res['status'], [
            STATUS_CODE::SUCCESS_NORMAL,
            STATUS_CODE::SUCCESS_WITH_MULTIPLE_RECORDS,
            STATUS_CODE::SUCCESS_WITH_NO_RECORD
        ]);
        $this->assertIsArray($res['raw']);
    }

    /**
     * 路徑 7：使用者未讀即時通私訊查詢 (type=user_unread_message)
     */
    public function testUserUnreadMessage(): void {
        $res = $this->post($this->endpoint, [
            'type' => 'user_unread_message',
            'id' => 'HATEST01'
        ]);
        $this->assertContains($res['status'], [
            STATUS_CODE::SUCCESS_NORMAL,
            STATUS_CODE::SUCCESS_WITH_MULTIPLE_RECORDS,
            STATUS_CODE::SUCCESS_WITH_NO_RECORD
        ]);
        $this->assertIsArray($res['raw']);
    }

    /**
     * 路徑 8：即時通私訊設定已讀、未讀與刪除 (set_read, set_unread, del)
     */
    public function testSetReadAndUnreadAndDelUserMessage(): void {
        // 1. 設定已讀
        $resRead = $this->post($this->endpoint, [
            'type' => 'set_read_user_message',
            'id' => 'HATEST01',
            'sn' => 1
        ]);
        $this->assertSame(STATUS_CODE::SUCCESS_NORMAL, $resRead['status']);
        $this->assertStringContainsString('已讀成功', $resRead['message']);

        // 2. 設定未讀
        $resUnread = $this->post($this->endpoint, [
            'type' => 'set_unread_user_message',
            'id' => 'HATEST01',
            'sn' => 1
        ]);
        $this->assertSame(STATUS_CODE::SUCCESS_NORMAL, $resUnread['status']);
        $this->assertStringContainsString('未讀成功', $resUnread['message']);

        // 3. 刪除私訊
        $resDel = $this->post($this->endpoint, [
            'type' => 'del_user_message',
            'id' => 'HATEST01',
            'sn' => 1
        ]);
        $this->assertSame(STATUS_CODE::SUCCESS_NORMAL, $resDel['status']);
        $this->assertStringContainsString('刪除「1」訊息成功', $resDel['message']);
    }

    /**
     * 路徑 9：不支援的查詢型態與空請求防護 (type=unsupported / empty)
     */
    public function testUnsupportedTypeAndEmptyPost(): void {
        // 不支援型態
        $resUnsupported = $this->post($this->endpoint, [
            'type' => 'unsupported_notification_type'
        ]);
        $this->assertSame(STATUS_CODE::UNSUPPORT_FAIL, $resUnsupported['status']);
        $this->assertStringContainsString('不支援的查詢型態【unsupported_notification_type】', $resUnsupported['message']);

        // 空請求
        $resEmpty = $this->post($this->endpoint, []);
        $this->assertSame(STATUS_CODE::UNSUPPORT_FAIL, $resEmpty['status']);
        $this->assertStringContainsString('不支援的查詢型態【】', $resEmpty['message']);
    }
}
