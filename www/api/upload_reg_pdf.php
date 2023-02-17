<?php
require_once(dirname(dirname(__FILE__)).DIRECTORY_SEPARATOR.'include'.DIRECTORY_SEPARATOR."init.php");
require_once(INC_DIR.DIRECTORY_SEPARATOR."SQLiteRegForeignerPDF.class.php");

$status = STATUS_CODE::DEFAULT_FAIL;
$message = '已上傳';
$filename = '';
$tmp_file = '';

if (isset($_FILES['file']['name']) && isset($_FILES['file']['tmp_name'])) {
    $filename = $_FILES['file']['name'];
    $valid_extensions = array("pdf", "PDF");
    $extension = pathinfo($filename, PATHINFO_EXTENSION);
    if (in_array($extension, $valid_extensions)) {
        $tmp_file = $_FILES['file']['tmp_name'];
        $timestamp = time();
        $to_file = UPLOAD_PDF_DIR.DIRECTORY_SEPARATOR.$filename;

        $sqlite_pdf = new SQLiteRegForeignerPDF();

        // $resized = resizeImage($tmp_file, $w, $h);

        // if (imagejpeg($resized, $to_file, $q)) {
        //     $status = STATUS_CODE::SUCCESS_NORMAL;
        //     $message = '影像已儲存 '.$to_file;

        //     Logger::getInstance()->info("檔案已存放到 $to_file");
            
        //     $sqlite_image = new SQLiteImage();
        //     $inserted_id = $sqlite_image->addImage(array(
        //         "name" => $filename,
        //         "path" => $to_file,
        //         "note" => $_POST["note"]
        //     ));
            
        //     if ($inserted_id === false) {
        //         Logger::getInstance()->error("新增一筆影像BLOB資料失敗");
        //     } else {
        //         Logger::getInstance()->info("上傳影像BLOB資料成功 ($inserted_id)");
        //         $converted = base64EncodedImage($to_file);
        //     }
        // } else {
        //     $message = '處理失敗 '.$tmp_file.' => '.$to_file;
        // }
    } else {
        $message = "檔案不是PDF";
        Logger::getInstance()->error(__FILE__.': 檔案不是PDF。 '.print_r($_FILES, true));
    }
}

echo json_encode(array(
    'status' => $status,
    'message'  => $message,
    'filename' => $filename
));
