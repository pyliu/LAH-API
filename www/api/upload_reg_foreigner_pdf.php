<?php
require_once(dirname(dirname(__FILE__)).DIRECTORY_SEPARATOR.'include'.DIRECTORY_SEPARATOR."init.php");
require_once(INC_DIR.DIRECTORY_SEPARATOR."SQLiteRegForeignerPDF.class.php");

$status = STATUS_CODE::DEFAULT_FAIL;
$message = '未知的失敗';
$filename = '';
$tmp_file = '';

Logger::getInstance()->info("收到上傳登記外國人PDF請求");

if (isset($_FILES['file']['name']) && isset($_FILES['file']['tmp_name'])) {
    $filename = $_FILES['file']['name'];
    $extension = pathinfo($filename, PATHINFO_EXTENSION);
    if (strtoupper($extension) === 'PDF') {
        $tmp_file = $_FILES['file']['tmp_name'];
        $timestamp = time();

        $year = $_POST['year'];
        $number = $_POST['number'];
        $fid = $_POST['fid'];
        $fname = $_POST['fname'];
        $note = $_POST['note'];

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
            $status = STATUS_CODE::SUCCESS_NORMAL;
            $message = "已新增儲存完成";
            $filename = $to_file;
        } else {    
            $message = "無法移動上傳檔案 $tmp_file → $to_file";
            Logger::getInstance()->error(__FILE__.': '.$message);
        }


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
