<?php
declare(strict_types=1);

namespace Tests\Integration;

use SQLite3;
use STATUS_CODE;

class PrefetchJsonApiTest extends BaseIntegrationTestCase {
    private string $endpoint = 'prefetch_json_api.php';
    private ?SQLite3 $sqlite = null;
    private array $originalCacheBackup = [];

    protected function setUp(): void {
        parent::setUp();
        $dbPath = defined('DB_DIR') ? DB_DIR . DIRECTORY_SEPARATOR . 'prefetch.db' : dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'www' . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'db' . DIRECTORY_SEPARATOR . 'prefetch.db';
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
     * 路徑 1：逾期與即將逾期案件無資料分支 (overdue_reg_cases_filtered, overdue_reg_cases, almost_overdue_reg_cases)
     */
    public function testOverdueRegCasesEmpty(): void {
        // 1. overdue_reg_cases_filtered
        $resFiltered = $this->post($this->endpoint, [
            'type' => 'overdue_reg_cases_filtered'
        ]);
        $this->assertSame(STATUS_CODE::SUCCESS_WITH_NO_RECORD, $resFiltered['status']);
        $this->assertSame('查無逾期資料', $resFiltered['message']);
        $this->assertSame(0, $resFiltered['data_count']);

        // 2. overdue_reg_cases
        $resOverdue = $this->post($this->endpoint, [
            'type' => 'overdue_reg_cases'
        ]);
        $this->assertSame(STATUS_CODE::SUCCESS_WITH_NO_RECORD, $resOverdue['status']);
        $this->assertSame('15天內查無逾期資料', $resOverdue['message']);
        $this->assertSame(0, $resOverdue['data_count']);

        // 3. almost_overdue_reg_cases
        $resAlmost = $this->post($this->endpoint, [
            'type' => 'almost_overdue_reg_cases'
        ]);
        $this->assertContains($resAlmost['status'], [STATUS_CODE::SUCCESS_WITH_NO_RECORD, STATUS_CODE::SUCCESS_NORMAL]);
    }

    /**
     * 路徑 2：逾期案件含模擬資料分支 (overdue_reg_cases_filtered 與 overdue_reg_cases)
     */
    public function testOverdueRegCasesWithMockData(): void {
        $mockRow = [
            'RM01' => '113',
            'RM02' => 'HB01',
            'RM03' => '000100',
            'RM09' => '01',
            'RM29_1' => '1130101',
            'RM29_2' => '080000',
            'RM31' => null,
            'RM32' => '1',
            'KCNT' => '買賣'
        ];
        $this->setMockCache('Prefetch::getOverdueCaseIn15Days', [$mockRow]);

        // 測試 overdue_reg_cases
        $resOverdue = $this->post($this->endpoint, [
            'type' => 'overdue_reg_cases'
        ]);
        $this->assertSame(STATUS_CODE::SUCCESS_NORMAL, $resOverdue['status']);
        $this->assertSame(1, $resOverdue['data_count']);
        $this->assertNotEmpty($resOverdue['items']);

        // 測試 overdue_reg_cases_filtered
        $resFiltered = $this->post($this->endpoint, [
            'type' => 'overdue_reg_cases_filtered'
        ]);
        $this->assertSame(STATUS_CODE::SUCCESS_NORMAL, $resFiltered['status']);
        $this->assertSame(1, $resFiltered['data_count']);
    }

    /**
     * 路徑 3：登記公告中案件查詢 (type=reg_rm30_H_case)
     */
    public function testRegRm30HCase(): void {
        // 查無資料
        $resEmpty = $this->post($this->endpoint, [
            'type' => 'reg_rm30_H_case'
        ]);
        $this->assertContains($resEmpty['status'], [STATUS_CODE::DEFAULT_FAIL, STATUS_CODE::SUCCESS_WITH_MULTIPLE_RECORDS, STATUS_CODE::SUCCESS_NORMAL]);

        // 含資料分支
        $mockRow = [
            'RM01' => '113',
            'RM02' => 'HB01',
            'RM03' => '000200',
            'RM09' => '01',
            'RM30' => 'H',
            'KCNT' => '公告買賣'
        ];
        $this->setMockCache('Prefetch::getRM30HCase', [$mockRow]);

        $resFound = $this->post($this->endpoint, [
            'type' => 'reg_rm30_H_case'
        ]);
        $this->assertSame(STATUS_CODE::SUCCESS_WITH_MULTIPLE_RECORDS, $resFound['status']);
        $this->assertSame(1, $resFound['data_count']);
        $this->assertIsArray($resFound['baked']);
    }

    /**
     * 路徑 4：請示(取消)案件查詢 (type=reg_cancel_ask_case)
     */
    public function testRegCancelAskCase(): void {
        // 在 MOCK 模式下 Prefetch::getAskCase 內建回傳 8 筆模擬資料
        $res = $this->post($this->endpoint, [
            'type' => 'reg_cancel_ask_case',
            'begin' => '1130101',
            'end' => '1130131'
        ]);
        $this->assertSame(STATUS_CODE::SUCCESS_WITH_MULTIPLE_RECORDS, $res['status']);
        $this->assertSame(8, $res['data_count']);
        $this->assertIsArray($res['baked']);
        $this->assertStringContainsString('找到 8 筆請示(取消)案件資料', $res['message']);
    }

    /**
     * 路徑 5：信託案件登記查詢 (type=reg_trust_case - E, B, TE, TB)
     */
    public function testRegTrustCase(): void {
        foreach (['E', 'B', 'TE', 'TB'] as $q) {
            $res = $this->post($this->endpoint, [
                'type' => 'reg_trust_case',
                'query' => $q,
                'year' => '113'
            ]);
            $this->assertSame(STATUS_CODE::DEFAULT_FAIL, $res['status']);
            $this->assertSame('查無信託註記資料', $res['message']);
        }
    }

    /**
     * 路徑 6：非專代案件查詢 (reg_non_scrivener_case, reg_non_scrivener_reg_case, reg_non_scrivener_sur_case)
     */
    public function testRegNonScrivenerCases(): void {
        $params = [
            'start_date' => '1130101',
            'end_date' => '1130131'
        ];

        // 1. reg_non_scrivener_case
        $res1 = $this->post($this->endpoint, array_merge(['type' => 'reg_non_scrivener_case'], $params));
        $this->assertSame(STATUS_CODE::DEFAULT_FAIL, $res1['status']);
        $this->assertSame('查無非專代案件', $res1['message']);

        // 2. reg_non_scrivener_reg_case
        $res2 = $this->post($this->endpoint, array_merge(['type' => 'reg_non_scrivener_reg_case'], $params));
        $this->assertSame(STATUS_CODE::DEFAULT_FAIL, $res2['status']);
        $this->assertSame('查無非專代案件', $res2['message']);

        // 3. reg_non_scrivener_sur_case
        $res3 = $this->post($this->endpoint, array_merge(['type' => 'reg_non_scrivener_sur_case'], $params));
        $this->assertSame(STATUS_CODE::DEFAULT_FAIL, $res3['status']);
        $this->assertSame('查無非專代測量案件', $res3['message']);
    }

    /**
     * 路徑 7：外國人地權與信託相關查詢 (reg_foreigner_case, trust_query)
     */
    public function testForeignerAndTrustQuery(): void {
        // reg_foreigner_case
        $resForeigner = $this->post($this->endpoint, [
            'type' => 'reg_foreigner_case',
            'begin' => '1130101',
            'end' => '1130131'
        ]);
        $this->assertSame(STATUS_CODE::DEFAULT_FAIL, $resForeigner['status']);
        $this->assertSame('查無外國人地權案件', $resForeigner['message']);

        // trust_query 各子類型
        foreach (['land', 'building', 'reg_reason'] as $q) {
            $resTrust = $this->post($this->endpoint, [
                'type' => 'trust_query',
                'query' => $q,
                'start' => '1130101',
                'end' => '1130131'
            ]);
            $this->assertSame(STATUS_CODE::DEFAULT_FAIL, $resTrust['status']);
            $this->assertSame('查無信託相關資料', $resTrust['message']);
        }
    }

    /**
     * 路徑 8：375租約與未辦標的、土地參考資訊查詢 (375_land_change, 375_owner_change, not_done_change, land_ref_change)
     */
    public function test375AndChangeCases(): void {
        $params = [
            'start' => '1130101',
            'end' => '1130131'
        ];

        // 375_land_change
        $resLand = $this->post($this->endpoint, array_merge(['type' => '375_land_change'], $params));
        $this->assertSame(STATUS_CODE::DEFAULT_FAIL, $resLand['status']);

        // 375_owner_change
        $resOwner = $this->post($this->endpoint, array_merge(['type' => '375_owner_change'], $params));
        $this->assertSame(STATUS_CODE::DEFAULT_FAIL, $resOwner['status']);

        // not_done_change
        $resNotDone = $this->post($this->endpoint, array_merge(['type' => 'not_done_change'], $params));
        $this->assertSame(STATUS_CODE::DEFAULT_FAIL, $resNotDone['status']);

        // land_ref_change
        $resLandRef = $this->post($this->endpoint, array_merge(['type' => 'land_ref_change'], $params));
        $this->assertSame(STATUS_CODE::DEFAULT_FAIL, $resLandRef['status']);
    }

    /**
     * 路徑 9：補正、未歸檔與結案未領案件查詢 (reg_fix_case, reg_not_done_case, reg_untaken_case)
     */
    public function testRegFixAndNotDoneAndUntakenCases(): void {
        // reg_fix_case
        $resFix = $this->post($this->endpoint, [
            'type' => 'reg_fix_case'
        ]);
        $this->assertContains($resFix['status'], [STATUS_CODE::DEFAULT_FAIL, STATUS_CODE::SUCCESS_WITH_MULTIPLE_RECORDS, STATUS_CODE::SUCCESS_NORMAL]);

        // reg_not_done_case
        $resNotDone = $this->post($this->endpoint, [
            'type' => 'reg_not_done_case',
            'start' => '1130101',
            'end' => '1130131'
        ]);
        $this->assertContains($resNotDone['status'], [STATUS_CODE::DEFAULT_FAIL, STATUS_CODE::SUCCESS_WITH_MULTIPLE_RECORDS, STATUS_CODE::SUCCESS_NORMAL]);

        // reg_untaken_case
        $resUntaken = $this->post($this->endpoint, [
            'type' => 'reg_untaken_case',
            'start' => '1130101',
            'end' => '1130131'
        ]);
        $this->assertContains($resUntaken['status'], [STATUS_CODE::DEFAULT_FAIL, STATUS_CODE::SUCCESS_WITH_MULTIPLE_RECORDS, STATUS_CODE::SUCCESS_NORMAL]);
    }

    /**
     * 路徑 10：測量各類案件查詢 (sur_overdue_case, sur_not_close_case, sur_near_case)
     */
    public function testSurCases(): void {
        // sur_overdue_case
        $resOverdue = $this->post($this->endpoint, [
            'type' => 'sur_overdue_case'
        ]);
        $this->assertContains($resOverdue['status'], [STATUS_CODE::DEFAULT_FAIL, STATUS_CODE::SUCCESS_WITH_MULTIPLE_RECORDS, STATUS_CODE::SUCCESS_NORMAL]);

        // sur_not_close_case
        $resNotClose = $this->post($this->endpoint, [
            'type' => 'sur_not_close_case'
        ]);
        $this->assertContains($resNotClose['status'], [STATUS_CODE::DEFAULT_FAIL, STATUS_CODE::SUCCESS_WITH_MULTIPLE_RECORDS, STATUS_CODE::SUCCESS_NORMAL]);

        // sur_near_case
        $resNear = $this->post($this->endpoint, [
            'type' => 'sur_near_case'
        ]);
        $this->assertContains($resNear['status'], [STATUS_CODE::DEFAULT_FAIL, STATUS_CODE::SUCCESS_WITH_MULTIPLE_RECORDS, STATUS_CODE::SUCCESS_NORMAL]);
    }

    /**
     * 路徑 11：實價登錄申報對應與外人管制清冊 (val_realprice_map, reg_inheritance_restriction)
     */
    public function testValRealPriceMapAndInheritanceRestriction(): void {
        // val_realprice_map
        $resVal = $this->post($this->endpoint, [
            'type' => 'val_realprice_map',
            'start_date' => '1130101',
            'end_date' => '1130131'
        ]);
        $this->assertContains($resVal['status'], [STATUS_CODE::DEFAULT_FAIL, STATUS_CODE::SUCCESS_WITH_MULTIPLE_RECORDS, STATUS_CODE::SUCCESS_NORMAL]);

        // reg_inheritance_restriction
        $resInheritance = $this->post($this->endpoint, [
            'type' => 'reg_inheritance_restriction'
        ]);
        $this->assertContains($resInheritance['status'], [STATUS_CODE::DEFAULT_FAIL, STATUS_CODE::SUCCESS_WITH_MULTIPLE_RECORDS, STATUS_CODE::SUCCESS_NORMAL]);
    }

    /**
     * 路徑 12：不支援的查詢型態與空請求防護 (type=unsupported / empty)
     */
    public function testUnsupportedTypeAndEmptyPost(): void {
        // 不支援型態
        $resUnsupported = $this->post($this->endpoint, [
            'type' => 'unsupported_prefetch_type'
        ]);
        $this->assertSame(STATUS_CODE::UNSUPPORT_FAIL, $resUnsupported['status']);
        $this->assertStringContainsString('不支援的查詢型態【unsupported_prefetch_type】', $resUnsupported['message']);

        // 空請求
        $resEmpty = $this->post($this->endpoint, []);
        $this->assertSame(STATUS_CODE::UNSUPPORT_FAIL, $resEmpty['status']);
        $this->assertStringContainsString('不支援的查詢型態【】', $resEmpty['message']);
    }
}
