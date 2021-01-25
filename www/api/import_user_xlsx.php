<?php
require_once(dirname(dirname(__FILE__)).DIRECTORY_SEPARATOR.'include'.DIRECTORY_SEPARATOR."init.php");
require_once(INC_DIR.DIRECTORY_SEPARATOR.'SQLiteUser.class.php');
require_once(ROOT_DIR.DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR.'autoload.php');

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\IOFactory;

$status = STATUS_CODE::DEFAULT_FAIL;
$message = '已上傳';
$filename = '';
$tmp_file = '';
$succeeded = 0;
$failed = 0;

if (isset($_FILES['file']['name']) && isset($_FILES['file']['tmp_name'])) {
    $filename = $_FILES['file']['name'];
    $valid_extensions = array("xlsx", "XLSX");
    $extension = pathinfo($filename, PATHINFO_EXTENSION);
    if (in_array($extension, $valid_extensions)) {
        $tmp_file = $_FILES['file']['tmp_name'];
        // prerequisite is ready
        $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($tmp_file);
        /*
            public function toArray(
                $nullValue = null,
                $calculateFormulas = true,
                $formatData = true,
                $returnCellRef = false
            ) { }
            @param mixed $nullValue — Value returned in the array entry if a cell doesn't exist
            @param bool $calculateFormulas — Should formulas be calculated?
            @param bool $formatData — Should formatting be applied to cell values?
            @param bool $returnCellRef
            False - Return a simple array of rows and columns indexed by number counting from zero True - Return rows and columns indexed by their actual row and column IDs
            @return array
        */
        $sheetData = $spreadsheet->getActiveSheet()->toArray(null, false, false, false);
        /* expect data format: (first 2 rows are the title and header)
            [2] => Array (
                [0] => 使用者代碼
                [1] => 使用者姓名
                [2] => 性別
                [3] => 地址
                [4] => 電話
                [5] => 分機
                [6] => 手機
                [7] => 部門
                [8] => 職稱
                [9] => 工作
                [10] => 考試
                [11] => 教育程度
                [12] => 報到日期
                [13] => 離職日期
                [14] => IP
                [15] => 生日
            ),
            [3] => array( ... ),
            ...
        */
        $len = count($sheetData) - 2;
        if ($len > 0) {
            if (preg_match("/桃園市智慧管控系統/m", $sheetData[0][0])) {
                $title_row = array_shift($sheetData);
                $log->info('偵測到標題 => '.str_replace("\n", ' ', print_r($title_row, true)));
            }
            if (preg_match("/使用者代碼/m", $sheetData[0][0])) {
                $header_row = array_shift($sheetData);
                $log->info('偵測到表頭 => '.str_replace("\n", ' ', print_r($header_row, true)));
            }
            $sqlite_user = new SQLiteUser();
            foreach ($sheetData as $row) {
                if ($sqlite_user->importXlsxUser($row)) {
                    $succeeded++;
                } else {
                    $failed++;
                    $log->warning("新增/更新使用者失敗。(".print_r($row, true).")");
                }
            }
            $status = STATUS_CODE::SUCCESS_NORMAL;
            $message = '已匯入 '.$succeeded.' 筆使用者資料(失敗: '.$failed.')。';
            $log->info($message);
        } else {
            $message = '上傳檔案無資料。';
            $log->error($message.print_r($sheetData, true));
        }
    } else {
        $message = '上傳檔案有問題。';
        $log->error($message.print_r($_FILES, true));
    }
}

echo json_encode(array(
    'status' => $status,
    'message'  => $message,
    'succeeded' => $succeeded,
    'failed' => $failed
));
