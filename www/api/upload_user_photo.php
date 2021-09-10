<?php
require_once(dirname(dirname(__FILE__)).DIRECTORY_SEPARATOR.'include'.DIRECTORY_SEPARATOR."init.php");

$status = STATUS_CODE::DEFAULT_FAIL;
$message = '已上傳';
$filename = '';
$tmp_file = '';

function resizeImage($filename, $max_width = 1920, $max_height = 1080) {
    list($orig_width, $orig_height) = getimagesize($filename);
    $width = $orig_width;
    $height = $orig_height;
    //原圖比設定縮圖高還高
    if ($height > $max_height) {
        //先依照比例縮放寬
        $width = ($max_height / $height) * $width;
        //指定高為縮圖高
        $height = $max_height;
    }
    //如果目前的寬還是比設定縮圖寬還寬
    if ($width > $max_width) {
        //把高也依照比例再縮放
        $height = ($max_width / $width) * $height;
        //指定寬為縮圖寬
        $width = $max_width;
    }
    //建立空白畫布
    $image_p = imagecreatetruecolor($width, $height);
    //把圖片載入
    $image = imagecreatefromjpeg($filename);
    //複製並重新取樣
    imagecopyresampled($image_p, $image, 0, 0, 0, 0,
        $width, $height, $orig_width, $orig_height);
    //清除檔案描述子
    imagedestroy($image);
    return $image_p;
}

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
