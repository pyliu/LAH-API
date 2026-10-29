<?php
declare(strict_types=1);

namespace Tests\Integration;

use STATUS_CODE;

class ScheduleJsonApiTest extends BaseIntegrationTestCase {
    private string $endpoint = 'schedule_json_api.php';

    /**
     * 測試 1：不支援的查詢型態與空請求（default 分支）
     */
    public function testUnsupportedTypeAndEmptyPost(): void {
        // 空請求
        $resEmpty = $this->post($this->endpoint, []);
        $this->assertEquals(STATUS_CODE::UNSUPPORT_FAIL, $resEmpty['status'] ?? null);
        $this->assertStringContainsString('不支援的查詢型態', $resEmpty['message'] ?? '');

        // 未知型態
        $resUnknown = $this->post($this->endpoint, ['type' => 'unsupported_schedule_xyz']);
        $this->assertEquals(STATUS_CODE::UNSUPPORT_FAIL, $resUnknown['status'] ?? null);
        $this->assertStringContainsString('不支援的查詢型態【unsupported_schedule_xyz】', $resUnknown['message'] ?? '');
    }

    /**
     * 測試 2：常規排程 (regular)
     */
    public function testRegularSchedule(): void {
        $res = $this->post($this->endpoint, ['type' => 'regular']);
        $this->assertArrayHasKey('status', $res);
        $this->assertContains($res['status'], [
            STATUS_CODE::SUCCESS_NORMAL,
            STATUS_CODE::DEFAULT_FAIL
        ]);
        $this->assertStringContainsString('排程', $res['message'] ?? '');
    }

    /**
     * 測試 3：15 分鐘週期排程 (15m)
     */
    public function test15MinsSchedule(): void {
        $res = $this->post($this->endpoint, ['type' => '15m']);
        $this->assertArrayHasKey('status', $res);
        $this->assertContains($res['status'], [
            STATUS_CODE::SUCCESS_NORMAL,
            STATUS_CODE::DEFAULT_FAIL
        ]);
        $this->assertStringContainsString('15分鐘排程', $res['message'] ?? '');
    }

    /**
     * 測試 4：30 分鐘週期排程 (30m)
     */
    public function test30MinsSchedule(): void {
        $res = $this->post($this->endpoint, ['type' => '30m']);
        $this->assertArrayHasKey('status', $res);
        $this->assertContains($res['status'], [
            STATUS_CODE::SUCCESS_NORMAL,
            STATUS_CODE::DEFAULT_FAIL
        ]);
        $this->assertStringContainsString('30分鐘排程', $res['message'] ?? '');
    }

    /**
     * 測試 5：每小時週期排程 (1h)
     */
    public function test1HourSchedule(): void {
        $res = $this->post($this->endpoint, ['type' => '1h']);
        $this->assertArrayHasKey('status', $res);
        $this->assertContains($res['status'], [
            STATUS_CODE::SUCCESS_NORMAL,
            STATUS_CODE::DEFAULT_FAIL
        ]);
        $this->assertStringContainsString('每小時排程', $res['message'] ?? '');
    }

    /**
     * 測試 6：每 4 小時週期排程 (4h)
     */
    public function test4HoursSchedule(): void {
        $res = $this->post($this->endpoint, ['type' => '4h']);
        $this->assertArrayHasKey('status', $res);
        $this->assertContains($res['status'], [
            STATUS_CODE::SUCCESS_NORMAL,
            STATUS_CODE::DEFAULT_FAIL
        ]);
        $this->assertStringContainsString('每4小時排程', $res['message'] ?? '');
    }

    /**
     * 測試 7：每 8 小時週期排程 (8h)
     */
    public function test8HoursSchedule(): void {
        $res = $this->post($this->endpoint, ['type' => '8h']);
        $this->assertArrayHasKey('status', $res);
        $this->assertContains($res['status'], [
            STATUS_CODE::SUCCESS_NORMAL,
            STATUS_CODE::DEFAULT_FAIL
        ]);
        $this->assertStringContainsString('每8小時排程', $res['message'] ?? '');
    }

    /**
     * 測試 8：每 12 小時 (半日) 週期排程 (12h)
     */
    public function testHalfDaySchedule(): void {
        $res = $this->post($this->endpoint, ['type' => '12h']);
        $this->assertArrayHasKey('status', $res);
        $this->assertContains($res['status'], [
            STATUS_CODE::SUCCESS_NORMAL,
            STATUS_CODE::DEFAULT_FAIL
        ]);
        $this->assertStringContainsString('每12小時排程', $res['message'] ?? '');
    }

    /**
     * 測試 9：每 24 小時 (每日) 週期排程 (24h)
     */
    public function testOneDaySchedule(): void {
        $res = $this->post($this->endpoint, ['type' => '24h']);
        $this->assertArrayHasKey('status', $res);
        $this->assertContains($res['status'], [
            STATUS_CODE::SUCCESS_NORMAL,
            STATUS_CODE::DEFAULT_FAIL
        ]);
        $this->assertStringContainsString('每24小時排程', $res['message'] ?? '');
    }
}
