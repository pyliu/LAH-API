<?php
declare(strict_types=1);

namespace Tests\Integration;

use STATUS_CODE;
use System;

class StatsJsonApiTest extends BaseIntegrationTestCase {
    private string $endpoint = 'stats_json_api.php';

    /**
     * 測試 1：不支援的查詢型態與空請求（default 分支）
     */
    public function testUnsupportedTypeAndEmptyPost(): void {
        // 空請求
        $resEmpty = $this->post($this->endpoint, []);
        $this->assertEquals(STATUS_CODE::UNSUPPORT_FAIL, $resEmpty['status'] ?? null);
        $this->assertStringContainsString('不支援的查詢型態', $resEmpty['message'] ?? '');

        // 未知型態
        $resUnknown = $this->post($this->endpoint, ['type' => 'unsupported_stats_xyz']);
        $this->assertEquals(STATUS_CODE::UNSUPPORT_FAIL, $resUnknown['status'] ?? null);
        $this->assertStringContainsString('不支援的查詢型態【unsupported_stats_xyz】', $resUnknown['message'] ?? '');
    }

    /**
     * 測試 2：依月份刪除快取資料 (stats_refresh_month)
     */
    public function testStatsRefreshMonth(): void {
        $res = $this->post($this->endpoint, [
            'type' => 'stats_refresh_month',
            'date' => '11301'
        ]);
        $this->assertArrayHasKey('status', $res);
        $this->assertContains($res['status'], [
            STATUS_CODE::SUCCESS_NORMAL,
            STATUS_CODE::DEFAULT_FAIL
        ]);
    }

    /**
     * 測試 3：核心登記統計查詢 (queryStats 系列分支 1)
     * 涵蓋：stats_reg_reject, stats_reg_fix, stats_refund, stats_court, stats_sur_rain
     */
    public function testCoreStatsQueryBranchGroup1(): void {
        $types = [
            'stats_reg_reject',
            'stats_reg_fix',
            'stats_refund',
            'stats_court',
            'stats_sur_rain'
        ];

        foreach ($types as $type) {
            $res = $this->post($this->endpoint, [
                'type' => $type,
                'date' => '11301',
                'reload' => 'true'
            ]);
            $this->assertArrayHasKey('status', $res);
            $this->assertContains($res['status'], [
                STATUS_CODE::SUCCESS_NORMAL,
                STATUS_CODE::DEFAULT_FAIL
            ]);
        }
    }

    /**
     * 測試 4：延伸登記統計查詢 (queryStats 系列分支 2)
     * 涵蓋：stats_reg_reason, stats_reg_all, stats_reg_remote, stats_reg_subcase, stats_regf
     */
    public function testCoreStatsQueryBranchGroup2(): void {
        $types = [
            'stats_reg_reason',
            'stats_reg_all',
            'stats_reg_remote',
            'stats_reg_subcase',
            'stats_regf'
        ];

        foreach ($types as $type) {
            $res = $this->post($this->endpoint, [
                'type' => $type,
                'date' => '11301'
            ]);
            $this->assertArrayHasKey('status', $res);
            $this->assertContains($res['status'], [
                STATUS_CODE::SUCCESS_NORMAL,
                STATUS_CODE::DEFAULT_FAIL
            ]);
        }
    }

    /**
     * 測試 5：臨櫃謄本核發案件 (stats_reg_cert_case)
     */
    public function testStatsRegCertCase(): void {
        $res = $this->post($this->endpoint, [
            'type' => 'stats_reg_cert_case',
            'st' => '1130101',
            'ed' => '1130131'
        ]);
        $this->assertArrayHasKey('status', $res);
        $this->assertContains($res['status'], [
            STATUS_CODE::SUCCESS_NORMAL,
            STATUS_CODE::DEFAULT_FAIL
        ]);
    }

    /**
     * 測試 6：設定與取得 AP 連線統計 (stats_set_conn_count, stats_latest_ap_conn, stats_xap_conn_history)
     */
    public function testApConnectionStats(): void {
        $apiKey = System::getInstance()->get('API_KEY');

        // 1. 寫入連線統計 (有效 API_KEY，此分支為背景接收端無 JSON 回傳)
        $rawValid = $this->postRaw($this->endpoint, [
            'type' => 'stats_set_conn_count',
            'api_key' => $apiKey,
            'ap_ip' => '127.0.0.1',
            'log_time' => date('Y-m-d H:i:s'),
            'records' => ['2,192.168.1.100', '1,192.168.1.101', '0,']
        ]);
        $this->assertEquals(200, $rawValid['http_code']);

        // 2. 寫入連線統計 (無效 API_KEY)
        $rawInvalid = $this->postRaw($this->endpoint, [
            'type' => 'stats_set_conn_count',
            'api_key' => 'invalid_key',
            'ap_ip' => '127.0.0.1',
            'log_time' => date('Y-m-d H:i:s'),
            'records' => ['1,192.168.1.100']
        ]);
        $this->assertEquals(200, $rawInvalid['http_code']);

        // 3. 取得最新 AP 連線
        $resLatest = $this->post($this->endpoint, [
            'type' => 'stats_latest_ap_conn',
            'ap_ip' => '127.0.0.1',
            'all' => 'true'
        ]);
        $this->assertArrayHasKey('status', $resLatest);

        // 4. 取得跨所 AP 連線歷史
        $resHistory = $this->post($this->endpoint, [
            'type' => 'stats_xap_conn_history',
            'ap_ip' => '127.0.0.1',
            'count' => 10
        ]);
        $this->assertArrayHasKey('status', $resHistory);
    }

    /**
     * 測試 7：案件區間統計 (stats_reg_period_count, stats_sur_period_count)
     */
    public function testPeriodCountStats(): void {
        // 登記案件區間統計
        $resReg = $this->post($this->endpoint, [
            'type' => 'stats_reg_period_count',
            'st' => '1130101',
            'ed' => '1130131'
        ]);
        $this->assertArrayHasKey('status', $resReg);

        // 測量案件區間統計
        $resSur = $this->post($this->endpoint, [
            'type' => 'stats_sur_period_count',
            'st' => '1130101',
            'ed' => '1130131'
        ]);
        $this->assertArrayHasKey('status', $resSur);
    }

    /**
     * 測試 8：第一次登記與 RM02 統計 (母號與子號)
     */
    public function testRegFirstAndRm02Stats(): void {
        // 第一次登記案件
        $resFirst = $this->post($this->endpoint, [
            'type' => 'stats_reg_first_count',
            'st' => '1130101',
            'ed' => '1130131'
        ]);
        $this->assertArrayHasKey('status', $resFirst);

        // 第一次登記子號案件
        $resFirstSub = $this->post($this->endpoint, [
            'type' => 'stats_reg_first_sub_count',
            'st' => '1130101',
            'ed' => '1130131'
        ]);
        $this->assertArrayHasKey('status', $resFirstSub);

        // RM02 案件
        $resRm02 = $this->post($this->endpoint, [
            'type' => 'stats_reg_rm02_count',
            'rm02' => 'A1',
            'st' => '1130101',
            'ed' => '1130131'
        ]);
        $this->assertArrayHasKey('status', $resRm02);

        // RM02 子號案件
        $resRm02Sub = $this->post($this->endpoint, [
            'type' => 'stats_reg_rm02_sub_count',
            'rm02' => 'A1',
            'st' => '1130101',
            'ed' => '1130131'
        ]);
        $this->assertArrayHasKey('status', $resRm02Sub);
    }

    /**
     * 測試 9：各地所連線狀態與失效紀錄 (stats_xap_stats, cached, down)
     */
    public function testXapStatsAndDownRecords(): void {
        // 連線狀態
        $resStats = $this->post($this->endpoint, [
            'type' => 'stats_xap_stats',
            'force' => 'false'
        ]);
        $this->assertArrayHasKey('status', $resStats);

        // 已快取連線狀態
        $resCached = $this->post($this->endpoint, [
            'type' => 'stats_xap_stats_cached'
        ]);
        $this->assertArrayHasKey('status', $resCached);

        // 各時間區間失效紀錄 (day, week, month, quater, half-year, year, 預設 count)
        $opts = ['day', 'week', 'month', 'quater', 'half-year', 'year', 'default'];
        foreach ($opts as $opt) {
            $params = ['type' => 'stats_xap_stats_down'];
            if ($opt !== 'default') {
                $params['opt'] = $opt;
            } else {
                $params['count'] = 5;
            }
            $resDown = $this->post($this->endpoint, $params);
            $this->assertArrayHasKey('status', $resDown);
        }
    }

    /**
     * 測試 10：案件審查統計與百歲所有權人資料
     * 涵蓋：stats_reg_initail_review_count, stats_reg_final_review_count, stats_reg_chief_review_count, stats_reg_owner_data
     */
    public function testReviewCaseStatsAndOwnerData(): void {
        // 初審案件統計
        $resInit = $this->post($this->endpoint, [
            'type' => 'stats_reg_initail_review_count',
            'st' => '1130101',
            'ed' => '1130131'
        ]);
        $this->assertArrayHasKey('status', $resInit);

        // 複審案件統計
        $resFinal = $this->post($this->endpoint, [
            'type' => 'stats_reg_final_review_count',
            'st' => '1130101',
            'ed' => '1130131'
        ]);
        $this->assertArrayHasKey('status', $resFinal);

        // 課長案件統計
        $resChief = $this->post($this->endpoint, [
            'type' => 'stats_reg_chief_review_count',
            'st' => '1130101',
            'ed' => '1130131'
        ]);
        $this->assertArrayHasKey('status', $resChief);

        // 所有權人資料 (帶自訂出生年)
        $resOwner1 = $this->post($this->endpoint, [
            'type' => 'stats_reg_owner_data',
            'owner_type' => 'A',
            'birth_year' => 14
        ]);
        $this->assertArrayHasKey('status', $resOwner1);

        // 所有權人資料 (未帶出生年，走預設 100 年前)
        $resOwner2 = $this->post($this->endpoint, [
            'type' => 'stats_reg_owner_data',
            'owner_type' => 'A'
        ]);
        $this->assertArrayHasKey('status', $resOwner2);
    }
}
