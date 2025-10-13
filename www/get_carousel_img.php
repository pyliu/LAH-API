<?php
require_once(__DIR__."/include/init.php");
require_once(__DIR__."/include/System.class.php");

$system = System::getInstance();
// $default_path = ROOT_DIR.DIRECTORY_SEPARATOR."assets".DIRECTORY_SEPARATOR."img".DIRECTORY_SEPARATOR."users".DIRECTORY_SEPARATOR; // This line seems unused in the original code
$default_path = "";
$fallback_path = "assets\\img\\poster\\";
$not_found_path = "assets\\img\\not_found.jpg";

$is_file_found = true; // 預設為 true，如果找不到檔案則設為 false

$key = array_key_exists('file', $_REQUEST) ? $_REQUEST['file'] : '';
$full_path = $default_path.$key;
if (!file_exists($full_path)) {
    $full_path = $fallback_path.$key;
    if (!file_exists($full_path)) {
        $is_file_found = false; // 找不到指定檔案，設定為 false
        $full_path = $not_found_path;
    }
}

// 只有在找到真實的檔案時，才設定快取 headers
if ($is_file_found) {
    // --- 快取設定開始 ---

    // 設定快取時間為一天 (24 * 60 * 60 = 86400 秒)
    $cache_duration = 86400;
    $expires_time = time() + $cache_duration;

    // Cache-Control Header
    header('Cache-Control: public, max-age=' . $cache_duration);

    // Expires Header
    header('Expires: ' . gmdate('D, d M Y H:i:s', $expires_time) . ' GMT');

    // Last-Modified Header
    if (file_exists($full_path)) {
        header('Last-Modified: ' . gmdate('D, d M Y H:i:s', filemtime($full_path)) . ' GMT');
    }

    // --- 快取設定結束 ---
}


$finfo = finfo_open(FILEINFO_MIME_TYPE);
$contentType = finfo_file($finfo, $full_path);
finfo_close($finfo);
header('Content-Type: ' . $contentType);
header('Content-Length: '.filesize($full_path));
ob_clean();
flush();
readfile($full_path);
