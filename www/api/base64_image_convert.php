<?php
require_once(dirname(dirname(__FILE__)).DIRECTORY_SEPARATOR.'include'.DIRECTORY_SEPARATOR."init.php");

$status = STATUS_CODE::DEFAULT_FAIL;
$message = '已上傳';
$filename = '';
$tmp_file = '';
$base64 = '';

if (isset($_FILES['file']['name']) && isset($_FILES['file']['tmp_name'])) {
    $filename = $_FILES['file']['name'];
    $valid_extensions = array("jpg", "JPG", 'jpeg', 'JPEG');
    $extension = pathinfo($filename, PATHINFO_EXTENSION);
    if (in_array($extension, $valid_extensions)) {

        $tmp_file = $_FILES['file']['tmp_name'];
        $timestamp = time();
        $to_file = UPLOAD_IMG_DIR.DIRECTORY_SEPARATOR.'tmp_base64.jpg';
        $w = $_POST['width'] ?? 320;
        $h = $_POST['height'] ?? 240;
        $q = $_POST['quality'] ?? 75;

        $resized = resizeImage($tmp_file, $w, $h);

        if (imagejpeg($resized, $to_file, $q)) {
            $status = STATUS_CODE::SUCCESS_NORMAL;
            $message = '暫存影像已儲存到 '.$to_file;
            Logger::getInstance()->info($message);
            $converted = base64EncodedImage($to_file);
        } else {
            $message = '處理失敗 '.$tmp_file.' => '.$to_file;
        }
    } else {
        $message = "檔案不是JPG";
        Logger::getInstance()->error(__FILE__.': 檔案不是JPG。 '.print_r($_FILES, true));
    }
}

echo json_encode(array(
    'status' => $status,
    'message'  => $message,
    'uri' => $converted['uri'],
    'encoded' => $converted['encoded']
));