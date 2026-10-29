<?php
require_once(dirname(dirname(__FILE__)) . DIRECTORY_SEPARATOR . 'include' . DIRECTORY_SEPARATOR . 'init.php');

if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}

$clean_param = function ($val) {
    return basename(str_replace(['\\', '/'], DIRECTORY_SEPARATOR, (string)$val));
};

$raw_file = $_REQUEST['file'] ?? '';
$key = $clean_param($raw_file);

$poster_dir = defined('POSTER_IMG_DIR') ? POSTER_IMG_DIR . DIRECTORY_SEPARATOR : (ROOT_DIR . DIRECTORY_SEPARATOR . "assets" . DIRECTORY_SEPARATOR . "img" . DIRECTORY_SEPARATOR . "poster" . DIRECTORY_SEPARATOR);
$assets_dir = defined('ASSETS_DIR') ? ASSETS_DIR . DIRECTORY_SEPARATOR : (ROOT_DIR . DIRECTORY_SEPARATOR . "assets" . DIRECTORY_SEPARATOR);
$not_found_path = $assets_dir . "img" . DIRECTORY_SEPARATOR . "not_found.jpg";

$is_file_found = true;
$full_path = $poster_dir . $key;

if (empty($key) || !file_exists($full_path)) {
    // 嘗試從 ROOT_DIR 相對路徑尋找
    $fallback_path = ROOT_DIR . DIRECTORY_SEPARATOR . $key;
    if (!empty($key) && file_exists($fallback_path)) {
        $full_path = $fallback_path;
    } else {
        $is_file_found = false;
        $full_path = $not_found_path;
    }
}

if (!file_exists($full_path)) {
    http_response_code(404);
    exit('File not found');
}

if ($is_file_found) {
    $cache_duration = 86400; // 一天
    $expires_time = time() + $cache_duration;
    header('Cache-Control: public, max-age=' . $cache_duration);
    header('Expires: ' . gmdate('D, d M Y H:i:s', $expires_time) . ' GMT');
    if (file_exists($full_path)) {
        header('Last-Modified: ' . gmdate('D, d M Y H:i:s', filemtime($full_path)) . ' GMT');
    }
}

$contentType = 'image/jpeg';
if (function_exists('finfo_open')) {
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $detected = finfo_file($finfo, $full_path);
    finfo_close($finfo);
    if (!empty($detected)) {
        $contentType = $detected;
    }
}

header('Content-Type: ' . $contentType);
header('Content-Length: ' . filesize($full_path));

if (ob_get_level()) {
    ob_end_clean();
}
flush();
readfile($full_path);
exit;
