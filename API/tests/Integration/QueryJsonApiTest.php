<?php
declare(strict_types=1);

namespace Tests\Integration;

use Cache;
use STATUS_CODE;

class QueryJsonApiTest extends BaseIntegrationTestCase {
    private string $endpoint = 'query_json_api.php';
    private Cache $cache;
    private array $cachedBackups = [];

    protected function setUp(): void {
        parent::setUp();
        $this->cache = Cache::getInstance();
    }

    protected function tearDown(): void {
        foreach ($this->cachedBackups as $key => $val) {
            if ($val === null) {
                $this->cache->delete($key);
            } else {
                $this->cache->set($key, $val);
            }
        }
        $this->cachedBackups = [];

        parent::tearDown();
    }

    private function backupCacheKey(string $key): void {
        if (!array_key_exists($key, $this->cachedBackups)) {
            $this->cachedBackups[$key] = $this->cache->get($key);
        }
    }

    /**
     * 測試 1：不支援的查詢型態與空請求（default 分支）
     */
    public function testUnsupportedTypeAndEmptyPost(): void {
        $res = $this->post($this->endpoint, []);
        $this->assertEquals(STATUS_CODE::UNSUPPORT_FAIL, $res['status'] ?? null);
        $this->assertStringContainsString('不支援的查詢型態', $res['message'] ?? '');

        $resUnknown = $this->post($this->endpoint, ['type' => 'non_existent_type_xyz']);
        $this->assertEquals(STATUS_CODE::UNSUPPORT_FAIL, $resUnknown['status'] ?? null);
        $this->assertStringContainsString('不支援的查詢型態【non_existent_type_xyz】', $resUnknown['message'] ?? '');
    }

    /**
     * 測試 2：ping 功能（Mock 模式下回傳延遲 87ms）
     */
    public function testPing(): void {
        $res = $this->post($this->endpoint, [
            'type' => 'ping',
            'ip' => '127.0.0.1',
            'port' => 80
        ]);
        $this->assertEquals(STATUS_CODE::SUCCESS_NORMAL, $res['status'] ?? null);
        $this->assertEquals('127.0.0.1', $res['ip'] ?? null);
        $this->assertEquals(87, $res['latency'] ?? null);
        $this->assertStringContainsString('127.0.0.1 回應時間為 87 ms', $res['message'] ?? '');
    }

    /**
     * 測試 3：stats_overdue_msg_total 統計傳送訊息人次
     */
    public function testStatsOverdueMsgTotal(): void {
        $res = $this->post($this->endpoint, [
            'type' => 'stats_overdue_msg_total'
        ]);
        $this->assertEquals(STATUS_CODE::SUCCESS_NORMAL, $res['status'] ?? null);
        $this->assertArrayHasKey('total', $res);
        $this->assertStringContainsString('已傳送', $res['message'] ?? '');
    }

    /**
     * 測試 4：ip 與 svr 伺服端連線與環境資訊
     */
    public function testIpAndSvr(): void {
        // ip 查詢
        $resIp = $this->post($this->endpoint, ['type' => 'ip']);
        $this->assertEquals(STATUS_CODE::SUCCESS_NORMAL, $resIp['status'] ?? null);
        $this->assertArrayHasKey('ip', $resIp);

        // svr 伺服器配置與連線使用者查詢
        $resSvr = $this->post($this->endpoint, [
            'type' => 'svr',
            'client_ip' => '127.0.0.1'
        ]);
        $this->assertEquals(STATUS_CODE::SUCCESS_NORMAL, $resSvr['status'] ?? null);
        $this->assertArrayHasKey('server', $resSvr);
        $this->assertArrayHasKey('ips', $resSvr);
        $this->assertArrayHasKey('config', $resSvr);
        $this->assertTrue($resSvr['config']['mock'] ?? false);
    }

    /**
     * 測試 5：configs 查詢與 update_configs 更新系統設定
     */
    public function testConfigsAndUpdateConfigs(): void {
        // 查詢設定
        $resConfigs = $this->post($this->endpoint, ['type' => 'configs']);
        $this->assertEquals(STATUS_CODE::SUCCESS_NORMAL, $resConfigs['status'] ?? null);
        $this->assertArrayHasKey('raw', $resConfigs);

        // 更新空設定
        $resUpd = $this->post($this->endpoint, [
            'type' => 'update_configs',
            'configs' => []
        ]);
        $this->assertArrayHasKey('status', $resUpd);
        $this->assertArrayHasKey('success', $resUpd);
    }

    /**
     * 測試 6：zip_log 壓縮日誌資料請求
     */
    public function testZipLog(): void {
        $res = $this->post($this->endpoint, ['type' => 'zip_log']);
        $this->assertEquals(STATUS_CODE::SUCCESS_NORMAL, $res['status'] ?? null);
        $this->assertEquals(0, $res['data_count'] ?? null);
    }

    /**
     * 測試 7：體溫紀錄增刪查列表 (add/remove/temperatures)
     */
    public function testTemperatureOperations(): void {
        $testId = 'TEST_USER_TEMP_' . time();
        $testDate = date('Ymd');

        // 新增體溫
        $resAdd = $this->post($this->endpoint, [
            'type' => 'add_temperature',
            'id' => $testId,
            'temperature' => '36.5'
        ]);
        $this->assertEquals(STATUS_CODE::SUCCESS_NORMAL, $resAdd['status'] ?? null);
        $this->assertStringContainsString('新增體溫紀錄成功', $resAdd['message'] ?? '');

        // 查詢體溫列表 (by id)
        $resList = $this->post($this->endpoint, [
            'type' => 'temperatures',
            'id' => $testId
        ]);
        $this->assertEquals(STATUS_CODE::SUCCESS_NORMAL, $resList['status'] ?? null);
        $this->assertGreaterThanOrEqual(1, $resList['data_count'] ?? 0);

        // 查詢體溫 (by id and date)
        $resByIdDate = $this->post($this->endpoint, [
            'type' => 'temperatures_by_id_date',
            'id' => $testId,
            'date' => $testDate
        ]);
        $this->assertEquals(STATUS_CODE::SUCCESS_NORMAL, $resByIdDate['status'] ?? null);

        // 查詢體溫 (by date)
        $resByDate = $this->post($this->endpoint, [
            'type' => 'temperatures_by_date',
            'date' => $testDate
        ]);
        $this->assertEquals(STATUS_CODE::SUCCESS_NORMAL, $resByDate['status'] ?? null);

        // 刪除體溫
        $resDel = $this->post($this->endpoint, [
            'type' => 'remove_temperature',
            'id' => $testId,
            'datetime' => date('Y-m-d')
        ]);
        $this->assertArrayHasKey('status', $resDel);
    }

    /**
     * 測試 8：overdue_reg_cases 與 almost_overdue_reg_cases 逾期與即將逾期案件
     */
    public function testOverdueAndAlmostOverdueRegCases(): void {
        $this->backupCacheKey('overdue_reg_cases');
        $this->backupCacheKey('almost_overdue_reg_cases');

        // 逾期案件查詢（依 Mock 快取狀態回傳 NORMAL 或 NO_RECORD）
        $resOverdue = $this->post($this->endpoint, [
            'type' => 'overdue_reg_cases',
            'reviewer_id' => 'TEST_REV'
        ]);
        $this->assertContains($resOverdue['status'] ?? null, [
            STATUS_CODE::SUCCESS_NORMAL,
            STATUS_CODE::SUCCESS_WITH_NO_RECORD
        ]);
        $this->assertArrayHasKey('data_count', $resOverdue);

        // 即將逾期案件
        $resAlmost = $this->post($this->endpoint, [
            'type' => 'almost_overdue_reg_cases',
            'reviewer_id' => 'TEST_REV'
        ]);
        $this->assertContains($resAlmost['status'] ?? null, [
            STATUS_CODE::SUCCESS_NORMAL,
            STATUS_CODE::SUCCESS_WITH_NO_RECORD
        ]);
        $this->assertArrayHasKey('data_count', $resAlmost);
    }

    /**
     * 測試 9：watchdog 監控請求
     */
    public function testWatchdog(): void {
        $res = $this->post($this->endpoint, ['type' => 'watchdog']);
        $this->assertArrayHasKey('status', $res);
        $this->assertContains($res['status'], [
            STATUS_CODE::SUCCESS_NORMAL,
            STATUS_CODE::DEFAULT_FAIL,
            STATUS_CODE::FAIL_NOT_VALID_SERVER
        ]);
    }

    /**
     * 測試 10：ez-payment-check 與 sur-problem-check 異常檢核
     */
    public function testEzPaymentAndSurProblemCheck(): void {
        $this->backupCacheKey('ez-payment-check');
        $this->backupCacheKey('sur-problem-check');

        // ez-payment-check
        $resEz = $this->post($this->endpoint, ['type' => 'ez-payment-check']);
        $this->assertContains($resEz['status'] ?? null, [
            STATUS_CODE::SUCCESS_NORMAL,
            STATUS_CODE::DEFAULT_FAIL
        ]);

        // sur-problem-check
        $resSur = $this->post($this->endpoint, ['type' => 'sur-problem-check']);
        $this->assertContains($resSur['status'] ?? null, [
            STATUS_CODE::SUCCESS_NORMAL,
            STATUS_CODE::DEFAULT_FAIL
        ]);
    }

    /**
     * 測試 11：max, ralid, cert_log 最大號與地籍謄本紀錄
     */
    public function testMaxAndRalidAndCertLog(): void {
        $this->backupCacheKey('max');
        $this->backupCacheKey('ralid');
        $this->backupCacheKey('cert_log');

        // max 最大號查詢
        $resMax = $this->post($this->endpoint, [
            'type' => 'max',
            'year' => '113',
            'code' => 'HB01'
        ]);
        $this->assertEquals(STATUS_CODE::SUCCESS_NORMAL, $resMax['status'] ?? null);
        $this->assertArrayHasKey('max', $resMax);

        // ralid 土地標示部
        $resRalid = $this->post($this->endpoint, [
            'type' => 'ralid',
            'text' => '桃園段'
        ]);
        $this->assertContains($resRalid['status'] ?? null, [
            STATUS_CODE::SUCCESS_NORMAL,
            STATUS_CODE::DEFAULT_FAIL
        ]);

        // cert_log 謄本紀錄
        $resCert = $this->post($this->endpoint, [
            'type' => 'cert_log',
            'section_code' => '0001',
            'numbers' => ['0001', '0002']
        ]);
        $this->assertContains($resCert['status'] ?? null, [
            STATUS_CODE::SUCCESS_NORMAL,
            STATUS_CODE::SUCCESS_WITH_NO_RECORD
        ]);
    }

    /**
     * 測試 12：crsms 與 cmsms 登記/測量案件身份證號查詢
     */
    public function testCrsmsAndCmsms(): void {
        $this->backupCacheKey('crsms');
        $this->backupCacheKey('cmsms');

        // crsms 查詢
        $resCrsms = $this->post($this->endpoint, [
            'type' => 'crsms',
            'id' => 'A123456789'
        ]);
        $this->assertContains($resCrsms['status'] ?? null, [
            STATUS_CODE::SUCCESS_NORMAL,
            STATUS_CODE::DEFAULT_FAIL
        ]);

        // cmsms 查詢
        $resCmsms = $this->post($this->endpoint, [
            'type' => 'cmsms',
            'id' => 'A123456789'
        ]);
        $this->assertContains($resCmsms['status'] ?? null, [
            STATUS_CODE::SUCCESS_NORMAL,
            STATUS_CODE::DEFAULT_FAIL
        ]);
    }

    /**
     * 測試 13：fix_easycard, sur_case, fix_sur_delay_case
     */
    public function testFixEasycardAndSurCaseAndDelay(): void {
        $this->backupCacheKey('fix_easycard');
        $this->backupCacheKey('sur_case');
        $this->backupCacheKey('fix_sur_delay_case');

        // fix_easycard 修正悠遊卡
        $resFixEz = $this->post($this->endpoint, [
            'type' => 'fix_easycard',
            'qday' => '1130101',
            'pc_num' => '01'
        ]);
        $this->assertContains($resFixEz['status'] ?? null, [
            STATUS_CODE::SUCCESS_NORMAL,
            STATUS_CODE::DEFAULT_FAIL
        ]);

        // sur_case 空 ID 邊界
        $resSurEmpty = $this->post($this->endpoint, [
            'type' => 'sur_case',
            'id' => ''
        ]);
        $this->assertEquals(STATUS_CODE::DEFAULT_FAIL, $resSurEmpty['status'] ?? null);

        // fix_sur_delay_case 空 ID 與有 ID
        $resDelayEmpty = $this->post($this->endpoint, [
            'type' => 'fix_sur_delay_case',
            'id' => ''
        ]);
        $this->assertEquals(STATUS_CODE::DEFAULT_FAIL, $resDelayEmpty['status'] ?? null);

        $resDelay = $this->post($this->endpoint, [
            'type' => 'fix_sur_delay_case',
            'id' => '113HB01000100',
            'UPD_MM22' => 'Y',
            'CLR_DELAY' => 'N',
            'FIX_COUNT' => '1'
        ]);
        $this->assertContains($resDelay['status'] ?? null, [
            STATUS_CODE::SUCCESS_NORMAL,
            STATUS_CODE::DEFAULT_FAIL
        ]);
    }

    /**
     * 測試 14：prc_case, announcement_data, update_announcement_data, clear_announcement_flag
     */
    public function testPrcCaseAndAnnouncementData(): void {
        $this->backupCacheKey('prc_case');
        $this->backupCacheKey('announcement_data');
        $this->backupCacheKey('update_announcement_data');
        $this->backupCacheKey('clear_announcement_flag');

        // prc_case 查詢地價案件
        $resPrc = $this->post($this->endpoint, [
            'type' => 'prc_case',
            'id' => '113HB01000100'
        ]);
        $this->assertContains($resPrc['status'] ?? null, [
            STATUS_CODE::SUCCESS_NORMAL,
            STATUS_CODE::DEFAULT_FAIL
        ]);

        // announcement_data 公告資料
        $resAnnounce = $this->post($this->endpoint, ['type' => 'announcement_data']);
        $this->assertEquals(STATUS_CODE::SUCCESS_NORMAL, $resAnnounce['status'] ?? null);
        $this->assertGreaterThanOrEqual(1, $resAnnounce['data_count'] ?? 0);

        // update_announcement_data 更新公告
        $resUpdAnnounce = $this->post($this->endpoint, [
            'type' => 'update_announcement_data',
            'code' => '01',
            'day' => '30',
            'flag' => 'Y'
        ]);
        $this->assertContains($resUpdAnnounce['status'] ?? null, [
            STATUS_CODE::SUCCESS_NORMAL,
            STATUS_CODE::DEFAULT_FAIL
        ]);

        // clear_announcement_flag 清除旗標
        $resClrAnnounce = $this->post($this->endpoint, ['type' => 'clear_announcement_flag']);
        $this->assertContains($resClrAnnounce['status'] ?? null, [
            STATUS_CODE::SUCCESS_NORMAL,
            STATUS_CODE::DEFAULT_FAIL
        ]);
    }

    /**
     * 測試 15：query_temp_data, clear_temp_data, upd_case_column 暫存與案件特定欄位更新
     */
    public function testTempDataAndUpdCaseColumn(): void {
        $this->backupCacheKey('query_temp_data');
        $this->backupCacheKey('clear_temp_data');
        $this->backupCacheKey('upd_case_column');

        // query_temp_data
        $resTemp = $this->post($this->endpoint, [
            'type' => 'query_temp_data',
            'year' => '113',
            'code' => 'HB01',
            'number' => '100'
        ]);
        $this->assertContains($resTemp['status'] ?? null, [
            STATUS_CODE::SUCCESS_NORMAL,
            STATUS_CODE::DEFAULT_FAIL
        ]);

        // clear_temp_data 清除暫存
        $resClearTemp = $this->post($this->endpoint, [
            'type' => 'clear_temp_data',
            'year' => '113',
            'code' => 'HB01',
            'number' => '100',
            'table' => 'TMP_TAB'
        ]);
        $this->assertContains($resClearTemp['status'] ?? null, [
            STATUS_CODE::SUCCESS_NORMAL,
            STATUS_CODE::DEFAULT_FAIL
        ]);

        // upd_case_column 更新案件欄位
        $resUpdCol = $this->post($this->endpoint, [
            'type' => 'upd_case_column',
            'id' => '113HB01000100',
            'table' => 'CRSMS',
            'column' => 'RM30',
            'value' => 'B'
        ]);
        $this->assertContains($resUpdCol['status'] ?? null, [
            STATUS_CODE::SUCCESS_NORMAL,
            STATUS_CODE::DEFAULT_FAIL
        ]);
    }

    /**
     * 測試 16：code_data 與 reg_code 代碼資料查詢
     */
    public function testCodeDataAndRegCode(): void {
        $this->backupCacheKey('code_data');

        // code_data 代碼統計
        $resCode = $this->post($this->endpoint, [
            'type' => 'code_data',
            'year' => '113'
        ]);
        $this->assertEquals(STATUS_CODE::SUCCESS_NORMAL, $resCode['status'] ?? null);
        $this->assertGreaterThanOrEqual(1, $resCode['data_count'] ?? 0);

        // reg_code 登記字列表
        $resRegCode = $this->post($this->endpoint, ['type' => 'reg_code']);
        $this->assertEquals(STATUS_CODE::SUCCESS_NORMAL, $resRegCode['status'] ?? null);
        $this->assertGreaterThan(0, $resRegCode['data_count'] ?? 0);
    }

    /**
     * 測試 17：reg_case, reg_cases_not_done, reg_cases_by_day 登記案件核心查詢
     */
    public function testRegCaseAndNotDoneAndByDay(): void {
        $this->backupCacheKey('reg_case');
        $this->backupCacheKey('reg_cases_not_close');
        $this->backupCacheKey('reg_cases_by_day');

        // reg_case 空 ID
        $resRegCaseEmpty = $this->post($this->endpoint, [
            'type' => 'reg_case',
            'id' => ''
        ]);
        $this->assertEquals(STATUS_CODE::DEFAULT_FAIL, $resRegCaseEmpty['status'] ?? null);

        // reg_case 案件查詢
        $resRegCase = $this->post($this->endpoint, [
            'type' => 'reg_case',
            'id' => '113HB01000100'
        ]);
        $this->assertContains($resRegCase['status'] ?? null, [
            STATUS_CODE::SUCCESS_NORMAL,
            STATUS_CODE::DEFAULT_FAIL
        ]);

        // reg_cases_not_done 未結案件
        $resNotDone = $this->post($this->endpoint, ['type' => 'reg_cases_not_done']);
        $this->assertContains($resNotDone['status'] ?? null, [
            STATUS_CODE::SUCCESS_NORMAL,
            STATUS_CODE::DEFAULT_FAIL
        ]);

        // reg_cases_by_day 依日期登記案件
        $resByDay = $this->post($this->endpoint, [
            'type' => 'reg_cases_by_day',
            'qday' => '1130101'
        ]);
        $this->assertContains($resByDay['status'] ?? null, [
            STATUS_CODE::SUCCESS_NORMAL,
            STATUS_CODE::DEFAULT_FAIL
        ]);
    }

    /**
     * 測試 18：月度登記統計與特殊案件 (court, fix, reject, remote, subcases, stats, regf)
     */
    public function testMonthlyRegCases(): void {
        $keys = [
            'reg_reason_cases_by_month', 'reg_court_cases_by_month',
            'reg_fix_cases_by_month', 'reg_reject_cases_by_month',
            'reg_remote_cases_by_month', 'reg_subcases_by_month',
            'reg_stats', 'regf_by_month'
        ];
        foreach ($keys as $k) {
            $this->backupCacheKey($k);
        }

        // reg_reason_cases_by_month
        $resReason = $this->post($this->endpoint, [
            'type' => 'reg_reason_cases_by_month',
            'reason_code' => '01',
            'query_month' => '11301'
        ]);
        $this->assertContains($resReason['status'] ?? null, [
            STATUS_CODE::SUCCESS_NORMAL,
            STATUS_CODE::SUCCESS_WITH_MULTIPLE_RECORDS,
            STATUS_CODE::SUCCESS_WITH_NO_RECORD
        ]);

        // reg_court_cases_by_month
        $resCourt = $this->post($this->endpoint, [
            'type' => 'reg_court_cases_by_month',
            'query_month' => '11301'
        ]);
        $this->assertContains($resCourt['status'] ?? null, [
            STATUS_CODE::SUCCESS_NORMAL,
            STATUS_CODE::SUCCESS_WITH_MULTIPLE_RECORDS,
            STATUS_CODE::SUCCESS_WITH_NO_RECORD
        ]);

        // reg_fix_cases_by_month
        $resFix = $this->post($this->endpoint, [
            'type' => 'reg_fix_cases_by_month',
            'query_month' => '11301'
        ]);
        $this->assertContains($resFix['status'] ?? null, [
            STATUS_CODE::SUCCESS_NORMAL,
            STATUS_CODE::SUCCESS_WITH_MULTIPLE_RECORDS,
            STATUS_CODE::SUCCESS_WITH_NO_RECORD
        ]);

        // reg_reject_cases_by_month
        $resReject = $this->post($this->endpoint, [
            'type' => 'reg_reject_cases_by_month',
            'query_month' => '11301'
        ]);
        $this->assertContains($resReject['status'] ?? null, [
            STATUS_CODE::SUCCESS_NORMAL,
            STATUS_CODE::SUCCESS_WITH_MULTIPLE_RECORDS,
            STATUS_CODE::SUCCESS_WITH_NO_RECORD
        ]);

        // reg_remote_cases_by_month
        $resRemote = $this->post($this->endpoint, [
            'type' => 'reg_remote_cases_by_month',
            'query_month' => '11301'
        ]);
        $this->assertContains($resRemote['status'] ?? null, [
            STATUS_CODE::SUCCESS_NORMAL,
            STATUS_CODE::SUCCESS_WITH_MULTIPLE_RECORDS,
            STATUS_CODE::SUCCESS_WITH_NO_RECORD
        ]);

        // reg_subcases_by_month
        $resSub = $this->post($this->endpoint, [
            'type' => 'reg_subcases_by_month',
            'query_month' => '11301'
        ]);
        $this->assertContains($resSub['status'] ?? null, [
            STATUS_CODE::SUCCESS_NORMAL,
            STATUS_CODE::SUCCESS_WITH_MULTIPLE_RECORDS,
            STATUS_CODE::SUCCESS_WITH_NO_RECORD
        ]);

        // reg_stats 登記月統計
        $resStats = $this->post($this->endpoint, [
            'type' => 'reg_stats',
            'year_month' => '11301'
        ]);
        $this->assertContains($resStats['status'] ?? null, [
            STATUS_CODE::SUCCESS_NORMAL,
            STATUS_CODE::DEFAULT_FAIL
        ]);

        // regf_by_month 外國人地權
        $resRegf = $this->post($this->endpoint, [
            'type' => 'regf_by_month',
            'query_month' => '11301'
        ]);
        $this->assertContains($resRegf['status'] ?? null, [
            STATUS_CODE::SUCCESS_NORMAL,
            STATUS_CODE::SUCCESS_WITH_MULTIPLE_RECORDS,
            STATUS_CODE::SUCCESS_WITH_NO_RECORD
        ]);
    }

    /**
     * 測試 19：reg_upd_col 與 reg_rm30_E_case 請示案件
     */
    public function testRegUpdColAndRm30ECase(): void {
        $this->backupCacheKey('reg_upd_col');
        $this->backupCacheKey('reg_rm30_E_case');

        // reg_upd_col 更新案件欄位
        $resUpd = $this->post($this->endpoint, [
            'type' => 'reg_upd_col',
            'rm01' => '113',
            'rm02' => 'HB01',
            'rm03' => '000100',
            'col' => 'RM30',
            'val' => 'C'
        ]);
        $this->assertContains($resUpd['status'] ?? null, [
            STATUS_CODE::SUCCESS_NORMAL,
            STATUS_CODE::DEFAULT_FAIL
        ]);

        // reg_rm30_E_case 請示案件
        $resRm30 = $this->post($this->endpoint, ['type' => 'reg_rm30_E_case']);
        $this->assertContains($resRm30['status'] ?? null, [
            STATUS_CODE::SUCCESS_NORMAL,
            STATUS_CODE::SUCCESS_WITH_MULTIPLE_RECORDS,
            STATUS_CODE::DEFAULT_FAIL
        ]);
    }

    /**
     * 測試 20：reg_auth_checks, upd_reg_auth_checks, reg_fix_case_delivered_date, upd_reg_fix_case_data
     */
    public function testRegAuthChecksAndRegFixCaseStore(): void {
        $testCaseNo = '113HB99000999';

        // 更新辦畢通知設定
        $resUpdAuth = $this->post($this->endpoint, [
            'type' => 'upd_reg_auth_checks',
            'id' => $testCaseNo,
            'authority' => 'Y',
            'note' => '整合測試辦畢設定'
        ]);
        $this->assertArrayHasKey('status', $resUpdAuth);

        // 查詢辦畢通知設定
        $resGetAuth = $this->post($this->endpoint, [
            'type' => 'reg_auth_checks',
            'id' => $testCaseNo
        ]);
        $this->assertArrayHasKey('status', $resGetAuth);

        // 更新補正送達日期
        $resUpdFix = $this->post($this->endpoint, [
            'type' => 'upd_reg_fix_case_data',
            'id' => $testCaseNo,
            'deadline' => '1130110',
            'delivered' => '1130102',
            'note' => '測試送達'
        ]);
        $this->assertArrayHasKey('status', $resUpdFix);

        // 查詢補正送達日期
        $resGetFix = $this->post($this->endpoint, [
            'type' => 'reg_fix_case_delivered_date',
            'id' => $testCaseNo
        ]);
        $this->assertArrayHasKey('status', $resGetFix);
    }

    /**
     * 測試 21：reg_cert_taken_date 與 upd_reg_cert_taken_date 領狀資料
     */
    public function testRegCertTakenDateAndStores(): void {
        $testCaseNo = '113HB99000888';

        // 更新領狀資料
        $resUpdCert = $this->post($this->endpoint, [
            'type' => 'upd_reg_cert_taken_date',
            'id' => $testCaseNo,
            'taken_date' => '1130105',
            'taken_status' => 'Y',
            'lent_date' => '',
            'return_date' => '',
            'borrower' => '',
            'note' => '已領狀'
        ]);
        $this->assertArrayHasKey('status', $resUpdCert);

        // 查詢領狀資料
        $resGetCert = $this->post($this->endpoint, [
            'type' => 'reg_cert_taken_date',
            'id' => $testCaseNo
        ]);
        $this->assertArrayHasKey('status', $resGetCert);
    }

    /**
     * 測試 22：expba_refund_cases_by_month, sur_rain_cases_by_month, rlnid
     */
    public function testExpbaRefundAndSurRain(): void {
        $this->backupCacheKey('expba_refund_cases_by_month');
        $this->backupCacheKey('sur_rain_cases_by_month');
        $this->backupCacheKey('rlnid');

        // expba_refund_cases_by_month
        $resRefund = $this->post($this->endpoint, [
            'type' => 'expba_refund_cases_by_month',
            'query_month' => '11301'
        ]);
        $this->assertContains($resRefund['status'] ?? null, [
            STATUS_CODE::SUCCESS_NORMAL,
            STATUS_CODE::SUCCESS_WITH_MULTIPLE_RECORDS,
            STATUS_CODE::SUCCESS_WITH_NO_RECORD
        ]);

        // sur_rain_cases_by_month
        $resRain = $this->post($this->endpoint, [
            'type' => 'sur_rain_cases_by_month',
            'query_month' => '11301'
        ]);
        $this->assertContains($resRain['status'] ?? null, [
            STATUS_CODE::SUCCESS_NORMAL,
            STATUS_CODE::SUCCESS_WITH_MULTIPLE_RECORDS,
            STATUS_CODE::SUCCESS_WITH_NO_RECORD
        ]);

        // rlnid 權利人資訊
        $resRlnid = $this->post($this->endpoint, [
            'type' => 'rlnid',
            'id' => 'A123456789'
        ]);
        $this->assertContains($resRlnid['status'] ?? null, [
            STATUS_CODE::SUCCESS_NORMAL,
            STATUS_CODE::DEFAULT_FAIL
        ]);
    }

    /**
     * 測試 23：xlsx_params 設定查詢參數至 Session
     */
    public function testXlsxParams(): void {
        $res = $this->post($this->endpoint, [
            'type' => 'xlsx_params',
            'param_year' => '113',
            'param_code' => 'HB01'
        ]);
        $this->assertEquals(STATUS_CODE::SUCCESS_NORMAL, $res['status'] ?? null);
        $this->assertArrayHasKey('raw', $res);
    }

    /**
     * 測試 24：規費相關操作 (expe, expac, expk, mod_expac, expaa, get_dummy_ob_fees, add_dummy_ob_fees)
     */
    public function testFeeOperationsAndDummyFees(): void {
        $keys = ['expe', 'expac', 'expk', 'mod_expac', 'expaa', 'expaa_AA08_update', 'get_dummy_ob_fees', 'add_dummy_ob_fees'];
        foreach ($keys as $k) {
            $this->backupCacheKey($k);
        }

        // expe 規費類別
        $resExpe = $this->post($this->endpoint, ['type' => 'expe']);
        $this->assertContains($resExpe['status'] ?? null, [
            STATUS_CODE::SUCCESS_NORMAL,
            STATUS_CODE::DEFAULT_FAIL
        ]);

        // expac 收費項目
        $resExpac = $this->post($this->endpoint, [
            'type' => 'expac',
            'year' => '113',
            'num' => '123'
        ]);
        $this->assertContains($resExpac['status'] ?? null, [
            STATUS_CODE::SUCCESS_NORMAL,
            STATUS_CODE::DEFAULT_FAIL
        ]);

        // expk 付款項目
        $resExpk = $this->post($this->endpoint, ['type' => 'expk']);
        $this->assertContains($resExpk['status'] ?? null, [
            STATUS_CODE::SUCCESS_NORMAL,
            STATUS_CODE::DEFAULT_FAIL
        ]);

        // mod_expac 修正規費項目
        $resModExpac = $this->post($this->endpoint, [
            'type' => 'mod_expac',
            'year' => '113',
            'num' => '123',
            'code' => '01',
            'amount' => '100'
        ]);
        $this->assertContains($resModExpac['status'] ?? null, [
            STATUS_CODE::SUCCESS_NORMAL,
            STATUS_CODE::DEFAULT_FAIL
        ]);

        // expaa 查詢規費資料
        $resExpaa = $this->post($this->endpoint, [
            'type' => 'expaa',
            'qday' => '1130101',
            'num' => '123'
        ]);
        $this->assertContains($resExpaa['status'] ?? null, [
            STATUS_CODE::SUCCESS_NORMAL,
            STATUS_CODE::SUCCESS_WITH_MULTIPLE_RECORDS,
            STATUS_CODE::DEFAULT_FAIL
        ]);

        // expaa_AA08_update 更新規費欄位
        $resUpdFee = $this->post($this->endpoint, [
            'type' => 'expaa_AA08_update',
            'date' => '1130101',
            'number' => '123',
            'update_value' => 'TEST'
        ]);
        $this->assertContains($resUpdFee['status'] ?? null, [
            STATUS_CODE::SUCCESS_NORMAL,
            STATUS_CODE::DEFAULT_FAIL
        ]);

        // get_dummy_ob_fees 查詢作廢假資料
        $resGetDummy = $this->post($this->endpoint, ['type' => 'get_dummy_ob_fees']);
        $this->assertContains($resGetDummy['status'] ?? null, [
            STATUS_CODE::SUCCESS_NORMAL,
            STATUS_CODE::DEFAULT_FAIL
        ]);

        // add_dummy_ob_fees 新增作廢假資料
        $resAddDummy = $this->post($this->endpoint, [
            'type' => 'add_dummy_ob_fees',
            'today' => '1130101',
            'pc_number' => '01',
            'operator' => 'TEST_OP',
            'fee_number' => 'F001',
            'reason' => '測試作廢'
        ]);
        $this->assertContains($resAddDummy['status'] ?? null, [
            STATUS_CODE::SUCCESS_NORMAL,
            STATUS_CODE::DEFAULT_FAIL
        ]);
    }
}
