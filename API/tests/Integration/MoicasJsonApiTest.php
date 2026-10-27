<?php
declare(strict_types=1);

namespace Tests\Integration;

use STATUS_CODE;

class MoicasJsonApiTest extends BaseIntegrationTestCase {
    private string $endpoint = 'moicas_json_api.php';

    /**
     * 路徑 1：依據時戳查詢 CRSMS 案件紀錄 (type=crsms_records_by_clock)
     */
    public function testCrsmsRecordsByClock(): void {
        $res = $this->post($this->endpoint, [
            'type' => 'crsms_records_by_clock',
            'st' => '1140101',
            'ed' => '1140131',
            'clock' => '120000'
        ]);

        $this->assertResponseSuccess($res);
        $this->assertArrayHasKey('raw', $res);
        $this->assertArrayHasKey('baked', $res);
        $this->assertIsArray($res['raw']);
        $this->assertIsArray($res['baked']);
        $this->assertStringContainsString('目前查到 CRSMS 裡有', $res['message']);
    }

    /**
     * 路徑 2：測量地上權通知書暫存檢測 (type=sur_notify_application_tmp_check)
     */
    public function testSurNotifyApplicationTmpCheck(): void {
        $res = $this->post($this->endpoint, [
            'type' => 'sur_notify_application_tmp_check',
            'year' => '114'
        ]);

        $this->assertResponseSuccess($res);
        $this->assertArrayHasKey('raw', $res);
        $this->assertIsArray($res['raw']);
        $this->assertStringContainsString('目前查到CMCRD裡有', $res['message']);
    }

    /**
     * 路徑 3：清除 CMCRD 懸浮暫存記錄 (type=remove_dangling_sur_notify_application_tmp_record)
     */
    public function testRemoveDanglingSurNotifyApplicationTmpRecord(): void {
        $res = $this->post($this->endpoint, [
            'type' => 'remove_dangling_sur_notify_application_tmp_record',
            'year' => '114'
        ]);

        $this->assertResponseSuccess($res);
        $this->assertArrayHasKey('raw', $res);
        $this->assertStringContainsString('清除CMCRD裡懸浮的暫存', $res['message']);
    }

    /**
     * 路徑 4：單筆刪除暫存記錄與連結檔 (type=remove_sur_notify_application_tmp_record)
     */
    public function testRemoveSurNotifyApplicationTmpRecord(): void {
        $res = $this->post($this->endpoint, [
            'type' => 'remove_sur_notify_application_tmp_record',
            'MC01' => '114',
            'MC02' => '000001'
        ]);

        $this->assertArrayHasKey('raw', $res);
        $this->assertStringContainsString('114-000001', $res['message']);
    }

    /**
     * 路徑 5：修復登記錯動 RM38/RM39 案件 (type=fix_rm38_rm39_problem)
     */
    public function testFixRm38Rm39Problem(): void {
        $res = $this->post($this->endpoint, [
            'type' => 'fix_rm38_rm39_problem',
            'year' => '114',
            'code' => 'HA01',
            'number' => '000001'
        ]);

        $this->assertArrayHasKey('raw', $res);
        $this->assertStringContainsString('更新 114-HA01-000001 RM38/RM39', $res['message']);
    }

    /**
     * 路徑 6：依日期查詢 CRSMS 案件時間異動更新記錄 (type=crsms_update_by_date)
     */
    public function testCrsmsUpdateByDate(): void {
        $res = $this->post($this->endpoint, [
            'type' => 'crsms_update_by_date',
            'qday' => '1140101'
        ]);

        $this->assertResponseSuccess($res);
        $this->assertArrayHasKey('baked', $res);
        $this->assertIsArray($res['baked']);
        $this->assertStringContainsString('已找到', $res['message']);
    }

    /**
     * 路徑 7：依日期區間調閱謄本紀錄 (type=cusmm_by_date)
     */
    public function testCusmmByDate(): void {
        $res = $this->post($this->endpoint, [
            'type' => 'cusmm_by_date',
            'begin' => '1140101',
            'end' => '1140131'
        ]);

        $this->assertResponseSuccess($res);
        $this->assertArrayHasKey('raw', $res);
        $this->assertIsArray($res['raw']);
        $this->assertStringContainsString('已找到', $res['message']);
    }

    /**
     * 路徑 8：審查案件（初審、複審、課長）與熱門 RM02 查詢 (type=crsms_rm45_case|crsms_rm47_case|crsms_rm106_case|most_popular_rm02)
     */
    public function testReviewsAndPopularRm02(): void {
        // 初審案件
        $resRm45 = $this->post($this->endpoint, [
            'type' => 'crsms_rm45_case',
            'st' => '1140101',
            'ed' => '1140131',
            'user_id' => 'HAADMIN'
        ]);
        $this->assertResponseSuccess($resRm45);
        $this->assertArrayHasKey('baked', $resRm45);

        // 複審案件
        $resRm47 = $this->post($this->endpoint, [
            'type' => 'crsms_rm47_case',
            'st' => '1140101',
            'ed' => '1140131',
            'user_id' => 'HAADMIN'
        ]);
        $this->assertResponseSuccess($resRm47);
        $this->assertArrayHasKey('baked', $resRm47);

        // 課長案件
        $resRm106 = $this->post($this->endpoint, [
            'type' => 'crsms_rm106_case',
            'st' => '1140101',
            'ed' => '1140131',
            'user_id' => 'HAADMIN'
        ]);
        $this->assertResponseSuccess($resRm106);
        $this->assertArrayHasKey('baked', $resRm106);

        // 熱門 RM02
        $resRm02 = $this->post($this->endpoint, [
            'type' => 'most_popular_rm02',
            'filter' => 'all'
        ]);
        $this->assertResponseSuccess($resRm02);
        $this->assertArrayHasKey('raw', $resRm02);
    }

    /**
     * 路徑 9：查無資料情境（工作天與櫃台印表機對應）(type=workdays|counter_printer_map)
     */
    public function testUncachedItemsReturnDefaultFail(): void {
        // 工作天
        $resWorkdays = $this->post($this->endpoint, [
            'type' => 'workdays',
            'year' => '114'
        ]);
        $this->assertSame(STATUS_CODE::DEFAULT_FAIL, $resWorkdays['status']);

        // 櫃台印表機對應
        $resPrinters = $this->post($this->endpoint, [
            'type' => 'counter_printer_map'
        ]);
        $this->assertSame(STATUS_CODE::DEFAULT_FAIL, $resPrinters['status']);
    }

    /**
     * 路徑 10：不支援的查詢型態與空請求防護 (default 分支)
     */
    public function testUnsupportedTypeAndEmptyPost(): void {
        // 不支援型態
        $resInvalid = $this->post($this->endpoint, [
            'type' => 'unknown_moicas_type'
        ]);
        $this->assertSame(STATUS_CODE::UNSUPPORT_FAIL, $resInvalid['status']);
        $this->assertStringContainsString('不支援的查詢型態【unknown_moicas_type】', $resInvalid['message']);

        // 空參數防護
        $resEmpty = $this->post($this->endpoint, []);
        $this->assertSame(STATUS_CODE::UNSUPPORT_FAIL, $resEmpty['status']);
        $this->assertStringContainsString('不支援的查詢型態【】', $resEmpty['message']);
    }
}
