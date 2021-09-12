<?php
require_once(dirname(dirname(__FILE__)).DIRECTORY_SEPARATOR.'include'.DIRECTORY_SEPARATOR."init.php");

$status = STATUS_CODE::DEFAULT_FAIL;
$message = '已上傳';
$filename = '';
$tmp_file = '';

if (isset($_FILES['file']['name']) && isset($_FILES['file']['tmp_name'])) {
    $filename = $_FILES['file']['name'];
    $valid_extensions = array("jpg", "JPG", 'jpeg', 'JPEG');
    $extension = pathinfo($filename, PATHINFO_EXTENSION);
    if (in_array($extension, $valid_extensions)) {
        $tmp_file = $_FILES['file']['tmp_name'];
        $user_id = $_POST['id'];
        $user_name = $_POST['name'];
        $avatar = $_POST['avatar'] === "true";

        // use ID as the file name
        $to_filename = "${user_id}".($avatar ? "_avatar" : "").".${extension}";
        $to_file = USER_IMG_DIR.DIRECTORY_SEPARATOR.$to_filename;
        $resized = $avatar ? resizeImage($tmp_file, 256, 256) : resizeImage($tmp_file);
        if (imagejpeg($resized, $to_file, 90)) {
            $status = STATUS_CODE::SUCCESS_NORMAL;
            $message = '已儲存 '.$filename.' => '.$to_filename;
            // also use name as the file name
            $from = $to_file;
            $to_filename = "${user_name}".($avatar ? "_avatar" : "").".${extension}";
            $to_file = USER_IMG_DIR.DIRECTORY_SEPARATOR.$to_filename;
            if(!copy($from, $to_file)){
                $message = '複製失敗 '.$from.' => '.$to_file;
            }
        } else {
            $message = '處理失敗 '.$tmp_file.' => '.$to_file;
        }
    } else {
        $message = "檔案不是JPG";
        Logger::getInstance()->error('檔案不是JPG。 '.print_r($_FILES, true));
    }
}

echo json_encode(array(
    'status' => $status,
    'message'  => $message
));
