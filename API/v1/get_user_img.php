<?php
require_once(dirname(dirname(__FILE__)) . DIRECTORY_SEPARATOR . 'include' . DIRECTORY_SEPARATOR . 'init.php');

// 立即釋放 session 鎖，避免串流輸出阻塞同 session 之其他 HTTP 請求
if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}

$default_path = defined('USER_IMG_DIR') ? USER_IMG_DIR . DIRECTORY_SEPARATOR : (ROOT_DIR . DIRECTORY_SEPARATOR . "assets" . DIRECTORY_SEPARATOR . "img" . DIRECTORY_SEPARATOR . "users" . DIRECTORY_SEPARATOR);

$is_image_found = true;

// 安全性防護：移除可能之路徑穿越字元
$clean_param = function ($val) {
    return preg_replace('/[^a-zA-Z0-9_\x{4e00}-\x{9fa5}-]/u', '', (string)$val);
};

$raw_id = $_REQUEST['id'] ?? '';
$key = $clean_param($raw_id);
$full_path = $default_path . $key . '.jpg';

if (empty($key) || !file_exists($full_path)) {
    $raw_name = $_REQUEST['name'] ?? '';
    $key = $clean_param($raw_name);
    $full_path = $default_path . $key . '.jpg';
    if (empty($key) || !file_exists($full_path)) {
        $is_image_found = false;
        $is_avatar = (strpos($raw_id, '_avatar') !== false || strpos($raw_name, '_avatar') !== false);
        $full_path = $default_path . ($is_avatar ? 'not_found_avatar' . rand(1, 12) . '.jpg' : 'not_found.jpg');
        if (!file_exists($full_path) && $is_avatar) {
            $full_path = $default_path . 'not_found_avatar.jpg';
        }
    }
}

if (!file_exists($full_path)) {
    // 終極 fallback
    $full_path = defined('ASSETS_DIR') ? ASSETS_DIR . DIRECTORY_SEPARATOR . "img" . DIRECTORY_SEPARATOR . "not_found.jpg" : $full_path;
}

if (!file_exists($full_path)) {
    http_response_code(404);
    exit('Image not found');
}

if ($is_image_found) {
    $cache_duration = 604800; // 一週快取
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
