<?php
declare(strict_types=1);

namespace Tests\Integration;

use STATUS_CODE;
use SQLiteRegAddressUndisclosed;
use SQLiteRegPropertyAlert;

class RegJsonApiTest extends BaseIntegrationTestCase {
    private string $endpoint = 'reg_json_api.php';
    private ?string $tempPdfFile = null;
    private ?string $tempTxtFile = null;
    private ?int $createdPdfId = null;
    private ?string $createdPkey = null;
    private ?int $createdUndisclosedId = null;
    private ?int $createdAlertId = null;

    protected function tearDown(): void {
        if ($this->tempPdfFile && file_exists($this->tempPdfFile)) {
            @unlink($this->tempPdfFile);
        }
        if ($this->tempTxtFile && file_exists($this->tempTxtFile)) {
            @unlink($this->tempTxtFile);
        }

        // 清理殘留資料
        if ($this->createdPdfId !== null) {
            $this->post($this->endpoint, [
                'type' => 'remove_foreigner_pdf',
                'id' => $this->createdPdfId
            ]);
            $this->createdPdfId = null;
        }

        if ($this->createdPkey !== null) {
            $this->post($this->endpoint, [
                'type' => 'remove_foreigner_data',
                'pkey' => $this->createdPkey
            ]);
            $this->createdPkey = null;
        }

        if ($this->createdUndisclosedId !== null) {
            $this->post($this->endpoint, [
                'type' => 'remove_address_undisclosed',
                'id' => $this->createdUndisclosedId
            ]);
            $this->createdUndisclosedId = null;
        }

        if ($this->createdAlertId !== null) {
            $this->post($this->endpoint, [
                'type' => 'remove_property_alert',
                'id' => $this->createdAlertId
            ]);
            $this->createdAlertId = null;
        }

        parent::tearDown();
    }

    private function createTempPdf(): string {
        $path = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'integ_test_reg_' . uniqid() . '.pdf';
        file_put_contents($path, "%PDF-1.4\n1 0 obj\n<<>>\nendobj\ntrailer\n<<>>\n%%EOF");
        $this->tempPdfFile = $path;
        return $path;
    }

    private function createTempTxt(): string {
        $path = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'integ_test_reg_' . uniqid() . '.txt';
        file_put_contents($path, 'This is a text file, not a PDF.');
        $this->tempTxtFile = $path;
        return $path;
    }

    /**
     * 測試 1：不支援的查詢型態與空請求（default 分支）
     */
    public function testUnsupportedTypeAndEmptyPost(): void {
        $res = $this->post($this->endpoint, []);
        $this->assertEquals(STATUS_CODE::UNSUPPORT_FAIL, $res['status'] ?? null);
        $this->assertStringContainsString('不支援的查詢型態', $res['message'] ?? '');

        $resUnknown = $this->post($this->endpoint, ['type' => 'unknown_query_type_xyz']);
        $this->assertEquals(STATUS_CODE::UNSUPPORT_FAIL, $resUnknown['status'] ?? null);
        $this->assertStringContainsString('不支援的查詢型態【unknown_query_type_xyz】', $resUnknown['message'] ?? '');
    }

    /**
     * 測試 2：外國人 PDF 清單查詢 (foreigner_pdf_list)
     */
    public function testForeignerPdfList(): void {
        $res = $this->post($this->endpoint, [
            'type' => 'foreigner_pdf_list',
            'start_ts' => 0,
            'end_ts' => time(),
            'keyword' => ''
        ]);
        $this->assertEquals(STATUS_CODE::SUCCESS_NORMAL, $res['status'] ?? null);
        $this->assertArrayHasKey('raw', $res);
        $this->assertIsArray($res['raw']);
    }

    /**
     * 測試 3：新增外國人 PDF 無夾帶檔案邊界
     */
    public function testAddForeignerPdfWithoutFile(): void {
        $res = $this->post($this->endpoint, [
            'type' => 'add_foreigner_pdf',
            'year' => '113',
            'number' => '000001',
            'fid' => 'F123456789',
            'fname' => '測試外國人',
            'note' => '無檔案測試'
        ]);
        $this->assertEquals(STATUS_CODE::DEFAULT_FAIL, $res['status'] ?? null);
    }

    /**
     * 測試 4：新增外國人 PDF 夾帶非 PDF 檔案格式錯誤
     */
    public function testAddForeignerPdfWithNonPdfFile(): void {
        $txtPath = $this->createTempTxt();
        $res = $this->post($this->endpoint, [
            'type' => 'add_foreigner_pdf',
            'year' => '113',
            'number' => '000001',
            'fid' => 'F123456789',
            'fname' => '測試外國人',
            'note' => '文字檔測試'
        ], [
            'file' => $txtPath
        ]);
        $this->assertEquals(STATUS_CODE::DEFAULT_FAIL, $res['status'] ?? null);
        $this->assertStringContainsString('檔案不是PDF', $res['message'] ?? '');
    }

    /**
     * 測試 5：外國人 PDF 完整生命週期 (add -> edit -> remove)
     */
    public function testForeignerPdfLifecycle(): void {
        $pdfPath = $this->createTempPdf();
        $testNumber = (string)rand(100000, 999999);
        $testFid = 'F' . rand(10000000, 99999999);

        // 1. 新增 PDF
        $resAdd = $this->post($this->endpoint, [
            'type' => 'add_foreigner_pdf',
            'year' => '113',
            'number' => $testNumber,
            'fid' => $testFid,
            'fname' => '生命週期外國人',
            'note' => '整合測試新增'
        ], [
            'file' => $pdfPath
        ]);
        $this->assertEquals(STATUS_CODE::SUCCESS_NORMAL, $resAdd['status'] ?? null);
        $this->assertStringContainsString('已新增資料並儲存PDF完成', $resAdd['message'] ?? '');

        $rowId = $resAdd['payload']['id'] ?? null;
        $this->assertNotNull($rowId);
        $this->createdPdfId = (int)$rowId;

        // 2. 編輯 PDF (無效 ID)
        $resEditNotFound = $this->post($this->endpoint, [
            'type' => 'edit_foreigner_pdf',
            'id' => 9999999,
            'year' => '113',
            'old_year' => '113',
            'number' => $testNumber,
            'fid' => $testFid,
            'fname' => '生命週期外國人',
            'note' => '更新測試'
        ]);
        $this->assertEquals(STATUS_CODE::FAIL_NOT_FOUND, $resEditNotFound['status'] ?? null);

        // 3. 編輯 PDF (有效 ID)
        $resEdit = $this->post($this->endpoint, [
            'type' => 'edit_foreigner_pdf',
            'id' => $rowId,
            'year' => '113',
            'old_year' => '113',
            'number' => $testNumber,
            'fid' => $testFid,
            'fname' => '生命週期外國人改名',
            'note' => '整合測試已修改'
        ]);
        $this->assertEquals(STATUS_CODE::SUCCESS_NORMAL, $resEdit['status'] ?? null);
        $this->assertStringContainsString('資料庫資料已更新', $resEdit['message'] ?? '');

        // 4. 刪除 PDF
        $resRemove = $this->post($this->endpoint, [
            'type' => 'remove_foreigner_pdf',
            'id' => $rowId
        ]);
        $this->assertEquals(STATUS_CODE::SUCCESS_NORMAL, $resRemove['status'] ?? null);
        $this->assertStringContainsString('已刪除外國人PDF資料', $resRemove['message'] ?? '');
        $this->createdPdfId = null;
    }

    /**
     * 測試 6：外國人管制資料生命週期 (add -> edit -> remove)
     */
    public function testForeignerDataLifecycle(): void {
        $testPkey = 'TEST_REG_F_' . uniqid();
        $this->createdPkey = $testPkey;

        $data = [
            'pkey' => $testPkey,
            'nation' => 'USA',
            'reg_date' => '1130101',
            'reg_caseno' => '113HB01000100',
            'transfer_date' => '',
            'transfer_caseno' => '',
            'transfer_local_date' => '',
            'transfer_local_principle' => '',
            'restore_local_date' => '',
            'use_partition' => '01',
            'logout' => '',
            'control' => 'Y',
            'note' => '測試管制'
        ];

        // 1. 新增外國人管制資料
        $resAdd = $this->post($this->endpoint, [
            'type' => 'add_foreigner_data',
            'data' => $data
        ]);
        $this->assertEquals(STATUS_CODE::SUCCESS_NORMAL, $resAdd['status'] ?? null);
        $this->assertStringContainsString('已更新外國人管制資料', $resAdd['message'] ?? '');

        // 2. 編輯外國人管制資料
        $data['note'] = '測試已更新備註';
        $resEdit = $this->post($this->endpoint, [
            'type' => 'edit_foreigner_data',
            'data' => $data
        ]);
        $this->assertEquals(STATUS_CODE::SUCCESS_NORMAL, $resEdit['status'] ?? null);

        // 3. 刪除外國人管制資料
        $resRemove = $this->post($this->endpoint, [
            'type' => 'remove_foreigner_data',
            'pkey' => $testPkey
        ]);
        $this->assertEquals(STATUS_CODE::SUCCESS_NORMAL, $resRemove['status'] ?? null);
        $this->assertStringContainsString('已刪除外國人管制資料', $resRemove['message'] ?? '');
        $this->createdPkey = null;
    }

    /**
     * 測試 7：住址隱匿資料生命週期 (list -> add -> edit -> remove)
     */
    public function testAddressUndisclosedLifecycle(): void {
        // 1. 查詢清單
        $resList = $this->post($this->endpoint, [
            'type' => 'address_undisclosed_list',
            'start_ts' => 0,
            'end_ts' => time(),
            'keyword' => ''
        ]);
        $this->assertEquals(STATUS_CODE::SUCCESS_NORMAL, $resList['status'] ?? null);
        $this->assertArrayHasKey('raw', $resList);

        // 2. 新增住址隱匿
        $testData = [
            'applicant' => '測試隱匿申請人',
            'receiving_type' => 1,
            'receiving_caseno' => '113HB01000100',
            'cellphone' => '0912345678',
            'sms_status' => 0,
            'receiver' => '測試代理人',
            'note' => '整合測試新增'
        ];
        $resAdd = $this->post($this->endpoint, [
            'type' => 'add_address_undisclosed',
            'data' => $testData
        ]);
        $this->assertEquals(STATUS_CODE::SUCCESS_NORMAL, $resAdd['status'] ?? null);

        // 透過 DB 取得剛新增的 ID 進行後續測試與清理
        $db = new SQLiteRegAddressUndisclosed();
        $latest = $db->search(0, time(), '測試隱匿申請人');
        $id = !empty($latest) ? (int)$latest[0]['id'] : null;

        if ($id !== null) {
            $this->createdUndisclosedId = $id;

            // 3. 編輯 (無效 ID)
            $resEditNotFound = $this->post($this->endpoint, [
                'type' => 'edit_address_undisclosed',
                'id' => 9999999,
                'data' => $testData
            ]);
            $this->assertEquals(STATUS_CODE::FAIL_NOT_FOUND, $resEditNotFound['status'] ?? null);

            // 4. 編輯 (有效 ID)
            $testData['applicant'] = '測試隱匿申請人已修改';
            $resEdit = $this->post($this->endpoint, [
                'type' => 'edit_address_undisclosed',
                'id' => $id,
                'data' => $testData
            ]);
            $this->assertEquals(STATUS_CODE::SUCCESS_NORMAL, $resEdit['status'] ?? null);

            // 5. 刪除
            $resRemove = $this->post($this->endpoint, [
                'type' => 'remove_address_undisclosed',
                'id' => $id
            ]);
            $this->assertEquals(STATUS_CODE::SUCCESS_NORMAL, $resRemove['status'] ?? null);
            $this->createdUndisclosedId = null;
        }
    }

    /**
     * 測試 8：案件相關人員查詢 (get_case_applicants) 格式檢核與查詢
     */
    public function testGetCaseApplicants(): void {
        // 格式錯誤：未達 13 碼
        $resInvalid = $this->post($this->endpoint, [
            'type' => 'get_case_applicants',
            'caseno' => '113-123'
        ]);
        $this->assertEquals(STATUS_CODE::FAIL_NOT_VALID_SERVER, $resInvalid['status'] ?? null);
        $this->assertStringContainsString('案件號格式錯誤', $resInvalid['message'] ?? '');

        // 格式正確：13 碼
        $resValid = $this->post($this->endpoint, [
            'type' => 'get_case_applicants',
            'caseno' => '113-HB01-000100'
        ]);
        $this->assertContains($resValid['status'] ?? null, [
            STATUS_CODE::SUCCESS_NORMAL,
            STATUS_CODE::SUCCESS_WITH_NO_RECORD,
            STATUS_CODE::FAIL_NOT_FOUND
        ]);
        $this->assertArrayHasKey('applicants', $resValid);
    }

    /**
     * 測試 9：地籍異動即時通生命週期 (list -> add -> edit -> remove)
     */
    public function testPropertyAlertLifecycle(): void {
        // 1. 查詢清單
        $resList = $this->post($this->endpoint, [
            'type' => 'property_alert_list',
            'start_ts' => 0,
            'end_ts' => time(),
            'keyword' => ''
        ]);
        $this->assertEquals(STATUS_CODE::SUCCESS_NORMAL, $resList['status'] ?? null);
        $this->assertArrayHasKey('raw', $resList);

        // 2. 新增即時通資料
        $testData = [
            'applicant' => '測試即時通申請人',
            'receiving_type' => 1,
            'receiving_caseno' => '113HB01000100',
            'cellphone' => '0988776655',
            'sms_status' => 0,
            'receiver' => '測試代理人',
            'note' => '即時通測試'
        ];
        $resAdd = $this->post($this->endpoint, [
            'type' => 'add_property_alert',
            'data' => $testData
        ]);
        $this->assertEquals(STATUS_CODE::SUCCESS_NORMAL, $resAdd['status'] ?? null);

        // 透過 DB 取得 ID 進行後續測試與清理
        $db = new SQLiteRegPropertyAlert();
        $latest = $db->search(0, time(), '測試即時通申請人');
        $id = !empty($latest) ? (int)$latest[0]['id'] : null;

        if ($id !== null) {
            $this->createdAlertId = $id;

            // 3. 編輯 (無效 ID)
            $resEditNotFound = $this->post($this->endpoint, [
                'type' => 'edit_property_alert',
                'id' => 9999999,
                'data' => $testData
            ]);
            $this->assertEquals(STATUS_CODE::FAIL_NOT_FOUND, $resEditNotFound['status'] ?? null);

            // 4. 編輯 (有效 ID)
            $testData['applicant'] = '測試即時通申請人已修改';
            $resEdit = $this->post($this->endpoint, [
                'type' => 'edit_property_alert',
                'id' => $id,
                'data' => $testData
            ]);
            $this->assertEquals(STATUS_CODE::SUCCESS_NORMAL, $resEdit['status'] ?? null);

            // 5. 刪除
            $resRemove = $this->post($this->endpoint, [
                'type' => 'remove_property_alert',
                'id' => $id
            ]);
            $this->assertEquals(STATUS_CODE::SUCCESS_NORMAL, $resRemove['status'] ?? null);
            $this->createdAlertId = null;
        }
    }

    /**
     * 測試 10：案件簡訊狀態查詢 (check_reg_sms_status)
     */
    public function testCheckRegSmsStatus(): void {
        // 不支援的業務類別
        $resUnknown = $this->post($this->endpoint, [
            'type' => 'check_reg_sms_status',
            'biz_type' => 'invalid_biz_type',
            'id' => '1'
        ]);
        $this->assertEquals(STATUS_CODE::DEFAULT_FAIL, $resUnknown['status'] ?? null);
        $this->assertStringContainsString('不支援的業務類別', $resUnknown['message'] ?? '');

        // 住址隱匿 (無效 ID 404)
        $resUndisclosed = $this->post($this->endpoint, [
            'type' => 'check_reg_sms_status',
            'biz_type' => 'undisclosed',
            'id' => '9999999'
        ]);
        $this->assertEquals(STATUS_CODE::FAIL_NOT_FOUND, $resUndisclosed['status'] ?? null);

        // 即時通 (無效 ID 404)
        $resAlert = $this->post($this->endpoint, [
            'type' => 'check_reg_sms_status',
            'biz_type' => 'property_alert',
            'id' => '9999999'
        ]);
        $this->assertEquals(STATUS_CODE::FAIL_NOT_FOUND, $resAlert['status'] ?? null);
    }
}
