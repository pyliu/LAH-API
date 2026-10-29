<?php
declare(strict_types=1);

namespace Tests\Integration;

use STATUS_CODE;

class SurJsonApiTest extends BaseIntegrationTestCase {
    private string $endpoint = 'sur_json_api.php';
    private array $tempFilesToClean = [];

    protected function tearDown(): void {
        foreach ($this->tempFilesToClean as $file) {
            if (file_exists($file)) {
                @unlink($file);
            }
        }
        $this->tempFilesToClean = [];
        parent::tearDown();
    }

    private function createTempPdf(): string {
        $path = tempnam(sys_get_temp_dir(), 'test_sur_') . '.pdf';
        // Minimal valid PDF content
        file_put_contents($path, "%PDF-1.4\n1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj\n2 0 obj<</Type/Pages/Kids[3 0 R]/Count 1>>endobj\n3 0 obj<</Type/Page/MediaBox[0 0 3 3]>>endobj\nxref\n0 4\n0000000000 65535 f\n0000000009 00000 n\n0000000052 00000 n\n0000000101 00000 n\ntrailer<</Size 4/Root 1 0 R>>\nstartxref\n147\n%%EOF");
        $this->tempFilesToClean[] = $path;
        return $path;
    }

    private function createTempTxt(): string {
        $path = tempnam(sys_get_temp_dir(), 'test_sur_') . '.txt';
        file_put_contents($path, "This is not a PDF file.");
        $this->tempFilesToClean[] = $path;
        return $path;
    }

    /**
     * 測試 1：不支援的查詢型態與空請求（default 分支）
     */
    public function testUnsupportedTypeAndEmptyPost(): void {
        // 空請求
        $resEmpty = $this->post($this->endpoint, []);
        $this->assertEquals(STATUS_CODE::UNSUPPORT_FAIL, $resEmpty['status'] ?? null);
        $this->assertStringContainsString('不支援的查詢型態', $resEmpty['message'] ?? '');

        // 未知型態
        $resUnknown = $this->post($this->endpoint, ['type' => 'unsupported_sur_type']);
        $this->assertEquals(STATUS_CODE::UNSUPPORT_FAIL, $resUnknown['status'] ?? null);
        $this->assertStringContainsString('不支援的查詢型態【unsupported_sur_type】', $resUnknown['message'] ?? '');
    }

    /**
     * 測試 2：取得建物滅失追蹤發文字號資料列表 (destruction_tracking_number_list)
     */
    public function testDestructionTrackingNumberList(): void {
        $res = $this->post($this->endpoint, [
            'type' => 'destruction_tracking_number_list'
        ]);
        $this->assertArrayHasKey('status', $res);
        $this->assertEquals(STATUS_CODE::SUCCESS_NORMAL, $res['status']);
        $this->assertArrayHasKey('raw', $res);
        $this->assertIsArray($res['raw']);
        $this->assertStringContainsString('建物滅失追蹤發文字號資料', $res['message']);
    }

    /**
     * 測試 3：查詢追蹤字號是否存在 (destruction_tracking_number_exist)
     */
    public function testDestructionTrackingNumberExist(): void {
        // 查詢不存在之字號
        $resNotFound = $this->post($this->endpoint, [
            'type' => 'destruction_tracking_number_exist',
            'number' => '9999999999'
        ]);
        $this->assertArrayHasKey('status', $resNotFound);
        $this->assertContains($resNotFound['status'], [
            STATUS_CODE::DEFAULT_FAIL,
            STATUS_CODE::SUCCESS_NORMAL
        ]);
        $this->assertStringContainsString('9999999999', $resNotFound['message']);
    }

    /**
     * 測試 4：依申請日期區間取得追蹤資料 (destruction_tracking_list)
     */
    public function testDestructionTrackingList(): void {
        $res = $this->post($this->endpoint, [
            'type' => 'destruction_tracking_list',
            'tw_start' => '1130101',
            'tw_end' => '1131231'
        ]);
        $this->assertArrayHasKey('status', $res);
        $this->assertEquals(STATUS_CODE::SUCCESS_NORMAL, $res['status']);
        $this->assertArrayHasKey('raw', $res);
        $this->assertIsArray($res['raw']);
        $this->assertStringContainsString('建物滅失追蹤資料', $res['message']);
    }

    /**
     * 測試 5：新增追蹤資料之檔案驗證異常分支
     */
    public function testAddDestructionTrackingValidationErrors(): void {
        // 1. 未夾帶檔案
        $resNoFile = $this->post($this->endpoint, [
            'type' => 'add_destruction_tracking',
            'number' => '1234567890'
        ]);
        $this->assertEquals(STATUS_CODE::DEFAULT_FAIL, $resNoFile['status']);
        $this->assertStringContainsString('未知的失敗', $resNoFile['message']);

        // 2. 夾帶非 PDF 檔案
        $txtFile = $this->createTempTxt();
        $resInvalidExt = $this->post($this->endpoint, [
            'type' => 'add_destruction_tracking',
            'number' => '1234567890'
        ], [
            'file' => $txtFile
        ]);
        $this->assertEquals(STATUS_CODE::DEFAULT_FAIL, $resInvalidExt['status']);
        $this->assertStringContainsString('檔案不是PDF', $resInvalidExt['message']);
    }

    /**
     * 測試 6：完整生命週期（新增、字號查驗、編輯、狀態切換、置換PDF、查無更新與刪除）
     */
    public function testDestructionTrackingFullLifecycle(): void {
        $uniqueNum = (string)random_int(100000, 999999);
        $pdfFile = $this->createTempPdf();

        // 1. 新增一筆追蹤紀錄 (add_destruction_tracking)
        $resAdd = $this->post($this->endpoint, [
            'type' => 'add_destruction_tracking',
            'number' => $uniqueNum,
            'apply_date' => '1131001',
            'section_code' => '0001',
            'land_number' => '01000000',
            'building_number' => '00200000',
            'issue_date' => '1131015',
            'address' => '桃園市復興路一段1號',
            'occupancy_permit' => '使字第001號',
            'construction_permit' => '建字第002號',
            'note' => '整合測試備註',
            'done' => 'false'
        ], [
            'file' => $pdfFile
        ]);

        $this->assertEquals(STATUS_CODE::SUCCESS_NORMAL, $resAdd['status']);
        $this->assertStringContainsString('已新增資料並儲存PDF完成', $resAdd['message']);
        $rowId = $resAdd['payload']['id'] ?? null;
        $this->assertNotEmpty($rowId, '新增成功後應回傳資料庫 id');

        // 2. 驗證字號已存在 (destruction_tracking_number_exist)
        $resExist = $this->post($this->endpoint, [
            'type' => 'destruction_tracking_number_exist',
            'number' => $uniqueNum
        ]);
        $this->assertEquals(STATUS_CODE::SUCCESS_NORMAL, $resExist['status']);
        $this->assertStringContainsString('已建立', $resExist['message']);

        // 3. 切換管制與解除列管狀態 (switch_done_destruction_tracking)
        // 設為解除列管
        $resDoneTrue = $this->post($this->endpoint, [
            'type' => 'switch_done_destruction_tracking',
            'id' => $rowId,
            'done' => 'true'
        ]);
        $this->assertEquals(STATUS_CODE::SUCCESS_NORMAL, $resDoneTrue['status']);
        $this->assertStringContainsString("已設定解除列管 ($rowId)", $resDoneTrue['message']);

        // 設為管制中
        $resDoneFalse = $this->post($this->endpoint, [
            'type' => 'switch_done_destruction_tracking',
            'id' => $rowId,
            'done' => 'false'
        ]);
        $this->assertEquals(STATUS_CODE::SUCCESS_NORMAL, $resDoneFalse['status']);
        $this->assertStringContainsString("已設定管制 ($rowId)", $resDoneFalse['message']);

        // 4. 編輯資料並置換新 PDF (edit_destruction_tracking)
        $newPdfFile = $this->createTempPdf();
        $resEdit = $this->post($this->endpoint, [
            'type' => 'edit_destruction_tracking',
            'id' => $rowId,
            'number' => $uniqueNum,
            'apply_date' => '1131001',
            'section_code' => '0001',
            'land_number' => '01000000',
            'building_number' => '00200000',
            'issue_date' => '1131015',
            'address' => '桃園市復興路一段99號 (已修改)',
            'occupancy_permit' => '使字第001號',
            'construction_permit' => '建字第002號',
            'note' => '整合測試已修改',
            'done' => 'true'
        ], [
            'file' => $newPdfFile
        ]);
        $this->assertEquals(STATUS_CODE::SUCCESS_NORMAL, $resEdit['status']);
        $this->assertStringContainsString("資料庫資料已更新($rowId)", $resEdit['message']);

        // 5. 編輯不存在之 ID (FAIL_NOT_FOUND)
        $resEditNotFound = $this->post($this->endpoint, [
            'type' => 'edit_destruction_tracking',
            'id' => 999999999
        ]);
        $this->assertEquals(STATUS_CODE::FAIL_NOT_FOUND, $resEditNotFound['status']);
        $this->assertStringContainsString('資料庫無法找到資料', $resEditNotFound['message']);

        // 6. 刪除該筆建物滅失追蹤資料 (remove_destruction_tracking)
        $resRemove = $this->post($this->endpoint, [
            'type' => 'remove_destruction_tracking',
            'id' => $rowId
        ]);
        $this->assertEquals(STATUS_CODE::SUCCESS_NORMAL, $resRemove['status']);
        $this->assertStringContainsString("已刪除建物滅失追蹤PDF資料 ($rowId)", $resRemove['message']);
    }
}
