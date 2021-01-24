<?php
require_once(dirname(dirname(__FILE__)).DIRECTORY_SEPARATOR.'include'.DIRECTORY_SEPARATOR."init.php");

$status = STATUS_CODE::DEFAULT_FAIL;
$message = '已上傳';
$filename = '';
$tmp_file = '';

if (isset($_FILES['file']['name']) && isset($_FILES['file']['tmp_name'])) {
    $filename = $_FILES['file']['name'];
    $valid_extensions = array("jpg", "JPG");
    $extension = pathinfo($filename, PATHINFO_EXTENSION);
    if (in_array($extension, $valid_extensions)) {
        $tmp_file = $_FILES['file']['tmp_name'];
        $user_id = $_POST['id'];
        $user_name = $_POST['name'];
        $avatar = $_POST['avatar'] === "true";

        // use ID as the file name
        $to_filename = "${user_id}".($avatar ? "_avatar" : "").".${extension}";
        $to_file = USER_IMG_DIR.DIRECTORY_SEPARATOR.$to_filename;
        if(copy($tmp_file, $to_file)){
            $status = STATUS_CODE::SUCCESS_NORMAL;
            $message = '已儲存 '.$filename.' => '.$to_filename;
        } else {
            $message = '複製失敗 '.$tmp_file.' => '.$to_file;
        }

        // also use name as the file name
        $to_filename = "${user_name}".($avatar ? "_avatar" : "").".${extension}";
        $to_file = USER_IMG_DIR.DIRECTORY_SEPARATOR.$to_filename;
        if(copy($tmp_file, $to_file)){
            $message .= '\n已儲存 '.$filename.' => '.$to_filename;
        } else {
            $message .= '\n複製失敗 '.$tmp_file.' => '.$to_file;
        }
    } else {
        $message = "檔案不是JPG";
        $log->error('檔案不是JPG。 '.print_r($_FILES, true));
    }
}

echo json_encode(array(
    'status' => $status,
    'message'  => $message
));
