<?php
error_reporting(E_ALL & ~E_WARNING & ~E_NOTICE);
date_default_timezone_set("ASIA/TAIPEI");
// https://stackoverflow.com/questions/60157086/php-samesite-session-problem-session-doesnt-work
// To fix the cross site cookies error on browswer
session_set_cookie_params(["SameSite" => "None"]); //none, lax, strict
session_set_cookie_params(["Secure" => "false"]); //false, true
session_set_cookie_params(["HttpOnly" => "true"]); //false, true
session_start();
// some query take long time ...
set_time_limit(0);

require_once("GlobalConstants.inc.php");
require_once("GlobalFunctions.inc.php");

/**
 * 當 PHP 嘗試使用一個尚未載入的類別時，這個自動載入器會被觸發。
 * 它會根據類別名稱，直接在 'include/' 資料夾中尋找對應的檔案。
 */
spl_autoload_register(function (string $class_name) {
    // 定義所有類別檔案的基礎目錄。
    // __DIR__ 在這裡指向 'includes/' 資料夾。
    $base_dir = __DIR__ . DIRECTORY_SEPARATOR;

    // 直接將類別名稱轉換為檔案路徑。
    // 例如：'IPResolver' 變成 'IPResolver.class.php'
    // 然後與 $base_dir 結合：'include/IPResolver.class.php'
    $file = $base_dir . $class_name . '.class.php';

    // 檢查這個檔案是否存在。
    if (file_exists($file)) {
        // 如果檔案存在，就使用 require_once 載入它。
        require_once $file;
    } else {
        Logger::getInstance()->error($file.'不存在，無法自動導入'.$class_name);
    }
});
// autoload composer classes
require_once(__DIR__ .DIRECTORY_SEPARATOR.'..'.DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR.'autoload.php');

$client_ip = $_SERVER["HTTP_X_FORWARDED_FOR"] ?? $_SERVER["HTTP_CLIENT_IP"] ?? $_SERVER["REMOTE_ADDR"] ?? getLocalhostIP();

// to ensure exports dir exists
if (!file_exists(EXPORT_DIR) && !is_dir(EXPORT_DIR)) {
    mkdir(EXPORT_DIR);       
}

// to ensure import dir exists
if (!file_exists(IMPORT_DIR) && !is_dir(IMPORT_DIR)) {
    mkdir(IMPORT_DIR);       
} 

// to ensure log dir exists
if (!file_exists(LOG_DIR) && !is_dir(LOG_DIR)) {
    mkdir(LOG_DIR);       
}

// to ensure upload image dir exists
if (!file_exists(UPLOAD_IMG_DIR) && !is_dir(UPLOAD_IMG_DIR)) {
    mkdir(UPLOAD_IMG_DIR);       
}

// to ensure user image dir exists
if (!file_exists(USER_IMG_DIR) && !is_dir(USER_IMG_DIR)) {
    mkdir(USER_IMG_DIR);       
}

// to ensure pdf dir exists
if (!file_exists(UPLOAD_PDF_DIR) && !is_dir(UPLOAD_PDF_DIR)) {
    mkdir(UPLOAD_PDF_DIR);       
}

// to ensure reserve pdf dir exists
if (!file_exists(UPLOAD_RESERVE_PDF_DIR) && !is_dir(UPLOAD_RESERVE_PDF_DIR)) {
    mkdir(UPLOAD_RESERVE_PDF_DIR);       
}

// to ensure reserve pdf dir exists
if (!file_exists(UPLOAD_SUR_DESTRUCTION_TRACKING_PDF_DIR) && !is_dir(UPLOAD_SUR_DESTRUCTION_TRACKING_PDF_DIR)) {
    mkdir(UPLOAD_SUR_DESTRUCTION_TRACKING_PDF_DIR);       
}

set_exception_handler(function(Throwable $e) {
    Logger::getInstance()->error($e->getMessage());
});

$tmp_date = timestampToDate(time(), 'TW');
$parts = explode(' ', $tmp_date);
$date_parts = explode('-', $parts[0]);
$time_parts = explode(':', $parts[1]);
// ex. 113
$this_year = $date_parts[0];
// ex. 1130415
$today = implode('', $date_parts);
