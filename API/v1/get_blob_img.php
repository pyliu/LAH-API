<?php
require_once(dirname(dirname(__FILE__)) . DIRECTORY_SEPARATOR . 'include' . DIRECTORY_SEPARATOR . 'init.php');

if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}

$sqlite_image = new SQLiteImage();

$path = $_REQUEST['path'] ?? '';
$blob = false;
$key = '';

if (!empty($path)) {
    $key = $path;
    $blob = $sqlite_image->getImageByPath($path);
}

if ($blob === false && !empty($_REQUEST['filename'])) {
    $key = $_REQUEST['filename'];
    $blob = $sqlite_image->getImageByFilename($key);
}

if ($blob === false || empty($blob)) {
    // 找不到時回傳 404 或 not_found 圖片
    $not_found_path = defined('ASSETS_DIR') ? ASSETS_DIR . DIRECTORY_SEPARATOR . "img" . DIRECTORY_SEPARATOR . "not_found.jpg" : "";
    if (!empty($not_found_path) && file_exists($not_found_path)) {
        header('Content-Type: image/jpeg');
        header('Content-Length: ' . filesize($not_found_path));
        if (ob_get_level()) { ob_end_clean(); }
        flush();
        readfile($not_found_path);
        exit;
    }
    http_response_code(404);
    exit('Image not found');
}

$image_info_arr = $sqlite_image->getImageData($key);
$iana = !empty($image_info_arr[0]['iana']) ? $image_info_arr[0]['iana'] : 'image/jpeg';
$size = !empty($image_info_arr[0]['size']) ? $image_info_arr[0]['size'] : strlen($blob);

header('Content-Type: ' . $iana);
header('Content-Length: ' . $size);
header('Cache-Control: public, max-age=86400');

if (ob_get_level()) {
    ob_end_clean();
}
flush();

echo $blob;
exit;
