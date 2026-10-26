<?php
declare(strict_types=1);

namespace Tests\Integration;

use STATUS_CODE;

class AdmJsonApiTest extends BaseIntegrationTestCase {
    private string $endpoint = 'adm_json_api.php';
    private ?string $tempPdfFile = null;
    private ?string $tempTxtFile = null;
    private ?int $createdRecordId = null;

    protected function tearDown(): void {
        // 清理本機暫存測試檔案
        if ($this->tempPdfFile && file_exists($this->tempPdfFile)) {
            @unlink($this->tempPdfFile);
        }
        if ($this->tempTxtFile && file_exists($this->tempTxtFile)) {
            @unlink($this->tempTxtFile);
        }

        // 若測試中途失敗且殘留資料庫記錄，執行刪除還原
        if ($this->createdRecordId !== null) {
            $this->post($this->endpoint, [
                'type' => 'remove_reserve_pdf',
                'id' => $this->createdRecordId
            ]);
            $this->createdRecordId = null;
        }

        parent::tearDown();
    }

    private function createTempPdf(): string {
        $path = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'integ_test_' . uniqid() . '.pdf';
        file_put_contents($path, "%PDF-1.4\n1 0 obj\n<<>>\nendobj\ntrailer\n<<>>\n%%EOF");
        $this->tempPdfFile = $path;
        return $path;
    }

    private function createTempTxt(): string {
        $path = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'integ_test_' . uniqid() . '.txt';
        file_put_contents($path, 'This is a text file, not a PDF.');
        $this->tempTxtFile = $path;
        return $path;
    }

    /**
     * 路徑 1：不支援的查詢型態（default 分支）
     */
    public function testUnsupportedTypeReturnsError(): void {
        $res = $this->post($this->endpoint, [
            'type' => 'unsupported_type_xyz'
        ]);

        $this->assertResponseUnsupport($res);
        $this->assertStringContainsString('不支援的查詢型態【unsupported_type_xyz】', $res['message']);
    }

    /**
     * 路徑 2：空 type 參數（default 分支防護）
     */
    public function testEmptyTypeReturnsError(): void {
        $res = $this->post($this->endpoint, []);

        $this->assertResponseUnsupport($res);
        $this->assertStringContainsString('不支援的查詢型態', $res['message']);
    }

    /**
     * 路徑 3：取得最新收件號（get_reserve_pdf_latest_number）
     */
    public function testGetReservePdfLatestNumber(): void {
        $res = $this->post($this->endpoint, [
            'type' => 'get_reserve_pdf_latest_number'
        ]);

        $this->assertResponseSuccess($res, '取得最新檔案應用預約收件號為');
        $this->assertArrayHasKey('number', $res);
        $this->assertNotEmpty($res['number']);
    }

    /**
     * 路徑 4：查詢預約清單（reserve_pdf_list）
     */
    public function testReservePdfList(): void {
        $res = $this->post($this->endpoint, [
            'type' => 'reserve_pdf_list',
            'start_ts' => 0,
            'end_ts' => 9999999999,
            'keyword' => ''
        ]);

        $this->assertResponseSuccess($res, '筆檔案應用預約資料');
        $this->assertArrayHasKey('raw', $res);
        $this->assertIsArray($res['raw']);
        $this->assertArrayHasKey('data_count', $res);
        $this->assertSame(count($res['raw']), $res['data_count']);
    }

    /**
     * 路徑 5：查詢預約清單無匹配關鍵字（reserve_pdf_list 查無資料）
     */
    public function testReservePdfListNotFound(): void {
        $res = $this->post($this->endpoint, [
            'type' => 'reserve_pdf_list',
            'start_ts' => 0,
            'end_ts' => 9999999999,
            'keyword' => 'KEYWORD_CANNOT_BE_FOUND_' . uniqid()
        ]);

        $this->assertResponseSuccess($res, '取得 0 筆檔案應用預約資料');
        $this->assertArrayHasKey('raw', $res);
        $this->assertEmpty($res['raw']);
        $this->assertSame(0, $res['data_count']);
    }

    /**
     * 路徑 6：新增預約未上傳檔案（add_reserve_pdf 失敗分支：無檔案）
     */
    public function testAddReservePdfWithoutFile(): void {
        $res = $this->post($this->endpoint, [
            'type' => 'add_reserve_pdf',
            'number' => '9999999990'
        ]);

        $this->assertResponseFail($res, STATUS_CODE::DEFAULT_FAIL, '未知的失敗');
    }

    /**
     * 路徑 7：新增預約上傳非 PDF 檔案（add_reserve_pdf 失敗分支：檔案不是PDF）
     */
    public function testAddReservePdfWithNonPdfFile(): void {
        $txtPath = $this->createTempTxt();

        $res = $this->post(
            $this->endpoint,
            [
                'type' => 'add_reserve_pdf',
                'number' => '9999999991'
            ],
            [
                'file' => $txtPath
            ]
        );

        $this->assertResponseFail($res, STATUS_CODE::DEFAULT_FAIL, '檔案不是PDF');
    }

    /**
     * 路徑 8：編輯不存在之預約（edit_reserve_pdf 失敗分支：資料庫查無資料）
     */
    public function testEditReservePdfNotFound(): void {
        $res = $this->post($this->endpoint, [
            'type' => 'edit_reserve_pdf',
            'id' => 999999999,
            'number' => '9999999999',
            'pid' => 'A123456789',
            'pname' => '測試無此人',
            'note' => '測試不存在',
            'createtime' => time(),
            'endtime' => time() + 86400
        ]);

        $this->assertResponseFail($res, STATUS_CODE::FAIL_NOT_FOUND, '資料庫無法找到資料');
    }

    /**
     * 路徑 9：刪除不存在之收件編號（驗證 SQLite DELETE 執行成功邊界行為）
     */
    public function testRemoveReservePdfNonExistentId(): void {
        $res = $this->post($this->endpoint, [
            'type' => 'remove_reserve_pdf',
            'id' => 999999999
        ]);

        $this->assertResponseSuccess($res, '已刪除檔案預約PDF資料 (999999999)');
    }

    /**
     * 路徑 10：完整端到端生命週期測試（新增 PDF 成功 -> 編輯欄位 -> 置換 PDF -> 刪除成功）
     */
    public function testReservePdfFullLifecycle(): void {
        $testNumber = '999' . str_pad((string)random_int(100000, 999999), 7, '0', STR_PAD_LEFT);
        $pdfPath1 = $this->createTempPdf();

        // 1. 新增預約資料並上傳 PDF
        $now = time();
        $addRes = $this->post(
            $this->endpoint,
            [
                'type' => 'add_reserve_pdf',
                'number' => $testNumber,
                'pid' => 'T123456789',
                'pname' => '整合測試員',
                'note' => '端到端自動化整合測試',
                'createtime' => $now,
                'endtime' => $now + 86400 * 30
            ],
            [
                'file' => $pdfPath1
            ]
        );

        $this->assertResponseSuccess($addRes, '已新增資料並儲存PDF完成');
        $this->assertArrayHasKey('payload', $addRes);
        $this->assertArrayHasKey('id', $addRes['payload']);
        $recordId = (int)$addRes['payload']['id'];
        $this->assertGreaterThan(0, $recordId);
        $this->createdRecordId = $recordId;

        // 2. 編輯預約資料（僅文字欄位，無置換檔案）
        $editRes1 = $this->post($this->endpoint, [
            'type' => 'edit_reserve_pdf',
            'id' => $recordId,
            'number' => $testNumber,
            'pid' => 'T123456789',
            'pname' => '整合測試員(已更新)',
            'note' => '端到端測試更新說明',
            'createtime' => $now,
            'endtime' => $now + 86400 * 60
        ]);

        $this->assertResponseSuccess($editRes1, "資料庫資料已更新($recordId)");

        // 3. 編輯預約資料並置換 PDF 檔案
        $pdfPath2 = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'integ_replace_' . uniqid() . '.pdf';
        file_put_contents($pdfPath2, "%PDF-1.4\n%Replacement PDF test\n%%EOF");
        try {
            $editRes2 = $this->post(
                $this->endpoint,
                [
                    'type' => 'edit_reserve_pdf',
                    'id' => $recordId,
                    'number' => $testNumber,
                    'pid' => 'T123456789',
                    'pname' => '整合測試員(置換PDF)',
                    'note' => '已置換PDF內容',
                    'createtime' => $now,
                    'endtime' => $now + 86400 * 60
                ],
                [
                    'file' => $pdfPath2
                ]
            );

            $this->assertResponseSuccess($editRes2, 'PDF檔案置換成功');
        } finally {
            if (file_exists($pdfPath2)) {
                @unlink($pdfPath2);
            }
        }

        // 4. 刪除預約資料（同時清除 PDF 檔案）
        $removeRes = $this->post($this->endpoint, [
            'type' => 'remove_reserve_pdf',
            'id' => $recordId
        ]);

        $this->assertResponseSuccess($removeRes, "已刪除檔案預約PDF資料 ($recordId)");
        $this->createdRecordId = null; // 標記已刪除完成
    }
}
