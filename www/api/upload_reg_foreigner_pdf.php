<?php
require_once(dirname(dirname(__FILE__)).DIRECTORY_SEPARATOR.'include'.DIRECTORY_SEPARATOR."init.php");
require_once(INC_DIR.DIRECTORY_SEPARATOR."SQLiteRegForeignerPDF.class.php");

$status = STATUS_CODE::DEFAULT_FAIL;
$message = '未知的失敗';
$filename = '';
$tmp_file = '';
$payload = array();

Logger::getInstance()->info("收到上傳登記外國人PDF請求");

if (isset($_FILES['file']['name']) && isset($_FILES['file']['tmp_name'])) {
    $filename = $_FILES['file']['name'];
    $extension = pathinfo($filename, PATHINFO_EXTENSION);
    if (strtoupper($extension) === 'PDF') {
        $tmp_file = $_FILES['file']['tmp_name'];

        $payload['year'] = $year = $_POST['year'];
        $payload['number'] = $number = $_POST['number'];
        $payload['fid'] = $fid = $_POST['fid'];
        $payload['fname'] = $fname = $_POST['fname'];
        $payload['note'] = $note = $_POST['note'];

        // make sure the parent dir has been created
        $parent_dir = UPLOAD_PDF_DIR.DIRECTORY_SEPARATOR.$year;
        if (!file_exists($parent_dir) || !is_dir($parent_dir)) {
            Logger::getInstance()->info("建立 $parent_dir ...");
            @mkdir($parent_dir, 0777, true);
        }
        
        $to_file = $parent_dir.DIRECTORY_SEPARATOR.$number."_".$fid."_".$fname.".".$extension;
        $moved = move_uploaded_file($tmp_file, $to_file);
        if ($moved) {
            // cont. to add database record ...
            $sqlite_pdf = new SQLiteRegForeignerPDF();
            $row_id = $sqlite_pdf->add($_POST);
            $status = $row_id !== false ? STATUS_CODE::SUCCESS_NORMAL : STATUS_CODE::FAIL_DB_ERROR;
            $message = $status === STATUS_CODE::SUCCESS_NORMAL ? "已新增資料並儲存PDF完成" : "於資料庫新增資料失敗";
            $payload['file'] = $to_file;
        } else {    
            $message = "無法移動上傳檔案 $tmp_file → $to_file";
            Logger::getInstance()->error(__FILE__.': '.$message);
        }
    } else {
        $message = "檔案不是PDF";
        Logger::getInstance()->error(__FILE__.': 檔案不是PDF。 '.print_r($_FILES, true));
    }
}

echo json_encode(array(
    'status' => $status,
    'message'  => $message,
    'payload' => $payload
));
