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
require_once("Logger.class.php");

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
