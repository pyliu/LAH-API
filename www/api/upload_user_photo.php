<?php
require_once(dirname(dirname(__FILE__)).DIRECTORY_SEPARATOR.'include'.DIRECTORY_SEPARATOR."init.php");

$message = '上傳中';
$filename = '';
$tmp_file = '';
$user_processed = 0;

$log->info(print_r($_FILES, true));

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
        /* expect data format:
            [0] => Array (
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
            [1] => array( ... ),
            ...
        */
        $len = count($sheetData);
        $log->info(print_r($sheetData, true));
        if ($len > 0) {
            // if (preg_match("/歸戶號/m", $sheetData[0][0])) {
            //     $header = array_shift($sheetData);
            //     $log->info('偵測到表頭 => '.str_replace("\n", ' ', print_r($header, true)));
            // }
            // $db = new LandDataDB();
            // $clean_old_data = false;
            // foreach ($sheetData as $row) {
            //     $household = trim($row[0]);
            //     $pids = explode(strpos($row[1], ',') === false ? '、' : ',', $row[1]);
            //     $pnames = explode(strpos($row[2], ',') === false ? '、' : ',', $row[2]);
            //     $numbers = explode(strpos($row[4], ',') === false ? '、' : ',', $row[4]);

            //     if (!$clean_old_data) {
            //         $keyword = mb_convert_encoding($household, 'UTF-8', 'BIG5')[0];
            //         $log->info('清除所有舊歸戶資料。('.$keyword.'%)');
            //         $db->removePeopleMapping($keyword);
            //         $clean_old_data = true;
            //     }
            //     $sub_count = 0;
            //     // each land number adds to the person 
            //     foreach ($numbers as $number) {
            //         $number = trim(trim($number, " =\t\n\r\0\x0B"), " \"\t\n\r\0\x0B");
            //         if (empty($number)) {
            //             continue;
            //         }
            //         for ($i = 0; $i < count($pids); $i++) {
            //             $pid = trim(trim($pids[$i], " =\t\n\r\0\x0B"), " \"\t\n\r\0\x0B");
            //             $pname = trim(trim($pnames[$i], " =\t\n\r\0\x0B"), " \"\t\n\r\0\x0B");
            //             if (empty($pid) || empty($pname)) {
            //                 continue;
            //             }
            //             // to fix the $pid and $pname order is wrong
            //             if (preg_match("/[*a-zA-Z\d]+/m", $pname)) {
            //                 // $log->info("adding $household ,$pname, $pid, $number");
            //                 $db->addPeopleMapping($household, $pname, $pid, $number);
            //             } else {
            //                 // $log->info("adding $household, $pid, $pname, $number");
            //                 $db->addPeopleMapping($household, $pid, $pname, $number);
            //             }
            //             $sub_count++;
            //         }
            //     }
            //     $log->info("$household 新增 $sub_count 筆資料。");
            //     $_SESSTION['people_processed']++;
            // }
            // $log->info('已匯入 '.$_SESSTION['people_processed'].' 筆歸戶資料。');
        } else {
            $log->error('上傳檔案無資料。'.print_r($sheetData, true));
        }
    } else {
        $log->error('上傳檔案有問題。'.print_r($_FILES, true));
    }
}

echo json_encode(array(
    'message'  => $message,
    'name' => $filename,
    'path'   => $tmp_file,
    'count' => $user_processed
));
