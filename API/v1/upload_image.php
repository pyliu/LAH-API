<?php
require_once(dirname(dirname(__FILE__)).DIRECTORY_SEPARATOR.'include'.DIRECTORY_SEPARATOR."init.php");
require_once(INC_DIR.DIRECTORY_SEPARATOR."SQLiteImage.class.php");

$status = STATUS_CODE::DEFAULT_FAIL;
$message = '已上傳';
$filename = '';
$tmp_file = '';
$base64 = '';
$converted = ['uri' => '', 'encoded' => ''];

if (isset($_FILES['file']['name']) && isset($_FILES['file']['tmp_name'])) {
    $filename = $_FILES['file']['name'];
    $valid_extensions = array("jpg", "JPG", 'jpeg', 'JPEG');
    $extension = pathinfo($filename, PATHINFO_EXTENSION);
    if (in_array($extension, $valid_extensions)) {

        $tmp_file = $_FILES['file']['tmp_name'];
        $timestamp = time();

        if (!file_exists(UPLOAD_IMG_DIR) || !is_dir(UPLOAD_IMG_DIR)) {
            @mkdir(UPLOAD_IMG_DIR, 0777, true);
        }

        $to_file = UPLOAD_IMG_DIR.DIRECTORY_SEPARATOR.$filename;
        $w = isset($_POST['width']) ? (int)$_POST['width'] : 960;
        $h = isset($_POST['height']) ? (int)$_POST['height'] : 540;
        $q = isset($_POST['quality']) ? (int)$_POST['quality'] : 75;

        $resized = resizeImage($tmp_file, $w, $h);

        if ($resized && imagejpeg($resized, $to_file, $q)) {
            $status = STATUS_CODE::SUCCESS_NORMAL;
            $message = '影像已儲存 '.$to_file;

            Logger::getInstance()->info("檔案已存放到 $to_file");
            
            $sqlite_image = new SQLiteImage();
            $inserted_id = $sqlite_image->addImage(array(
                "name" => $filename,
                "path" => $to_file,
                "note" => $_POST["note"] ?? ''
            ));
            
            if ($inserted_id === false) {
                Logger::getInstance()->error("新增一筆影像BLOB資料失敗");
            } else {
                Logger::getInstance()->info("上傳影像BLOB資料成功 ($inserted_id)");
                $converted = base64EncodedImage($to_file);
            }
        } else {
            $message = '處理失敗 '.$tmp_file.' => '.$to_file;
        }
    } else {
        $message = "檔案不是JPG";
        Logger::getInstance()->error(__FILE__.': 檔案不是JPG。 '.print_r($_FILES, true));
    }
}

if (!headers_sent()) {
    header('Content-Type: application/json; charset=utf-8');
}

echo json_encode(array(
    'status' => $status,
    'message'  => $message,
    'uri' => $converted['uri'] ?? '',
    'encoded' => $converted['encoded'] ?? ''
));
