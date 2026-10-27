<?php
declare(strict_types=1);

namespace Tests\Integration;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use SQLiteDBFactory;
use STATUS_CODE;

class ImportUserXlsxTest extends BaseIntegrationTestCase {
    private string $endpoint = 'import_user_xlsx.php';
    private array $tempFiles = [];

    protected function tearDown(): void {
        // 清理暫存檔案
        foreach ($this->tempFiles as $file) {
            if (file_exists($file)) {
                @unlink($file);
            }
        }
        $this->tempFiles = [];

        // 清理測試產生的使用者資料
        try {
            $lahDb = SQLiteDBFactory::getLahDB();
            if ($lahDb) {
                $lahDb->exec("DELETE FROM user WHERE id LIKE 'TEST_IMPORT_%'");
            }
        } catch (\Throwable $e) {
            // 忽略非致命清理異常
        }

        parent::tearDown();
    }

    private function createXlsxFile(string $filename, array $rows): string {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray($rows, null, 'A1');
        $writer = new Xlsx($spreadsheet);

        $tempPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $filename;
        $writer->save($tempPath);
        $this->tempFiles[] = $tempPath;

        return $tempPath;
    }

    /**
     * 路徑 1：未上傳任何檔案（空 POST 請求防護）
     */
    public function testNoFileUploadedReturnsFail(): void {
        $res = $this->post($this->endpoint, []);

        $this->assertSame(STATUS_CODE::DEFAULT_FAIL, $res['status']);
        $this->assertSame('已上傳', $res['message']);
        $this->assertSame(0, $res['succeeded']);
        $this->assertSame(0, $res['failed']);
    }

    /**
     * 路徑 2：上傳非合法副檔名之檔案（副檔名檢查分支）
     */
    public function testInvalidFileExtensionReturnsError(): void {
        $tempTxt = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'invalid_ext_' . uniqid() . '.txt';
        file_put_contents($tempTxt, 'This is a text file not an Excel sheet.');
        $this->tempFiles[] = $tempTxt;

        $res = $this->post($this->endpoint, [], ['file' => $tempTxt]);

        $this->assertSame(STATUS_CODE::DEFAULT_FAIL, $res['status']);
        $this->assertSame('上傳檔案有問題。', $res['message']);
        $this->assertSame(0, $res['succeeded']);
        $this->assertSame(0, $res['failed']);
    }

    /**
     * 路徑 3：上傳 XLSX 檔案無使用者資料列（列數 <= 2 分支）
     */
    public function testEmptyXlsxDataReturnsError(): void {
        $rows = [
            ['桃園市智慧控管系統 使用者列表'],
            ['使用者代碼', '使用者姓名', '性別', '地址', '電話', '分機', '手機', '部門', '職稱', '工作', '考試', '教育程度', '報到日期', '離職日期', 'IP', '生日']
        ];
        $xlsxPath = $this->createXlsxFile('empty_users_' . uniqid() . '.xlsx', $rows);

        $res = $this->post($this->endpoint, [], ['file' => $xlsxPath]);

        $this->assertSame(STATUS_CODE::DEFAULT_FAIL, $res['status']);
        $this->assertSame('上傳檔案無資料。', $res['message']);
        $this->assertSame(0, $res['succeeded']);
        $this->assertSame(0, $res['failed']);
    }

    /**
     * 路徑 4：標準格式 XLSX 上傳成功匯入（含系統標題列、表頭列與使用者資料）
     */
    public function testSuccessfulImportWithStandardFormat(): void {
        $id1 = 'TEST_IMPORT_001_' . rand(100, 999);
        $id2 = 'TEST_IMPORT_002_' . rand(100, 999);

        $rows = [
            ['桃園市智慧控管系統 使用者列表'],
            ['使用者代碼', '使用者姓名', '性別', '地址', '電話', '分機', '手機', '部門', '職稱', '工作', '考試', '教育程度', '報到日期', '離職日期', 'IP', '生日'],
            [$id1, '測試員甲', '男', '桃園市桃園區縣府路1號', '03-3322101', '101', '0912345678', '資訊課', '管理師', '系統維護', '高考', '大學', '110/01/01', '', '192.168.1.101', '080/01/01'],
            [$id2, '測試員乙', '女', '桃園市中壢區中北路200號', '03-4567890', '102', '0987654321', '登記課', '課員', '審查作業', '普考', '碩士', '111/02/01', '', '192.168.1.102', '085/05/05']
        ];
        $xlsxPath = $this->createXlsxFile('standard_users_' . uniqid() . '.xlsx', $rows);

        $res = $this->post($this->endpoint, [], ['file' => $xlsxPath]);

        $this->assertSame(STATUS_CODE::SUCCESS_NORMAL, $res['status']);
        $this->assertSame(2, $res['succeeded']);
        $this->assertSame(0, $res['failed']);
        $this->assertStringContainsString('已匯入 2 筆使用者資料', $res['message']);
    }

    /**
     * 路徑 5：無標題列、直接以表頭開頭之 XLSX 成功匯入相容性
     */
    public function testImportWithDirectHeaderRow(): void {
        $id3 = 'TEST_IMPORT_003_' . rand(100, 999);

        $rows = [
            ['使用者代碼', '使用者姓名', '性別', '地址', '電話', '分機', '手機', '部門', '職稱', '工作', '考試', '教育程度', '報到日期', '離職日期', 'IP', '生日'],
            [$id3, '測試員丙', '男', '桃園市八德區福國街', '03-3681234', '103', '0911222333', '測量課', '技士', '外業測量', '特考', '專科', '112/03/01', '', '192.168.1.103', '088/08/08'],
            // 補充第三列以滿足 count - 2 > 0 條件
            ['TEST_IMPORT_PADDING', '填充筆數', '0', '', '', '', '', '', '', '', '', '', '', '', '', '']
        ];
        $xlsxPath = $this->createXlsxFile('direct_header_' . uniqid() . '.xlsx', $rows);

        $res = $this->post($this->endpoint, [], ['file' => $xlsxPath]);

        $this->assertSame(STATUS_CODE::SUCCESS_NORMAL, $res['status']);
        $this->assertGreaterThanOrEqual(1, $res['succeeded']);
    }

    /**
     * 路徑 6：使用者 ID 為空之錯誤資料列計入 failed 計數
     */
    public function testImportWithFailedRowsCount(): void {
        $idValid = 'TEST_IMPORT_004_' . rand(100, 999);

        $rows = [
            ['桃園市智慧控管系統 使用者列表'],
            ['使用者代碼', '使用者姓名', '性別', '地址', '電話', '分機', '手機', '部門', '職稱', '工作', '考試', '教育程度', '報到日期', '離職日期', 'IP', '生日'],
            [$idValid, '合法測試員', '男', '地址', '電話', '分機', '手機', '部門', '職稱', '工作', '考試', '教育', '1100101', '', '192.168.1.1', '0800101'],
            ['', '無ID之非法資料列', '女', '地址', '電話', '分機', '手機', '部門', '職稱', '工作', '考試', '教育', '1100101', '', '192.168.1.2', '0800102']
        ];
        $xlsxPath = $this->createXlsxFile('has_failed_rows_' . uniqid() . '.xlsx', $rows);

        $res = $this->post($this->endpoint, [], ['file' => $xlsxPath]);

        $this->assertSame(STATUS_CODE::SUCCESS_NORMAL, $res['status']);
        $this->assertSame(1, $res['succeeded']);
        $this->assertSame(1, $res['failed']);
        $this->assertStringContainsString('失敗: 1', $res['message']);
    }
}
