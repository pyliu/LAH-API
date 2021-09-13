<?php
require_once("./include/init.php");
require_once("./include/SQLiteImage.class.php");

$sqlite_image = new SQLiteImage();

$key = $_REQUEST['path'];
$blob = $sqlite_image->getImageByPath($key);
if ($blob === false) {
    $key = $_REQUEST['filename'];
    $blob = $sqlite_image->getImageByFilename($key);
}

$image_info_arr = $sqlite_image->getImageData($key);
$iana = count($image_info_arr) > 0 ? $image_info_arr[0]['iana'] : 'image/jpeg';
$size = count($image_info_arr) > 0 ? $image_info_arr[0]['size'] : strlen($blob);

header('Content-Type: '.$iana);
header('Content-Length: '.$size);
ob_clean();
flush();

echo $blob;
