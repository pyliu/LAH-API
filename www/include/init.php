<?php
date_default_timezone_set("ASIA/TAIPEI");
session_start();
// some query take long time ...
set_time_limit(0);

require_once("GlobalConstants.inc.php");
require_once("GlobalFunctions.inc.php");
require_once("Logger.class.php");

$client_ip = $_SERVER["HTTP_X_FORWARDED_FOR"] ?? $_SERVER["HTTP_CLIENT_IP"] ?? $_SERVER["REMOTE_ADDR"] ?? getLocalhostIP();

// to ensure exports dir exists
if (!file_exists(EXPORTS_DIR) && !is_dir(EXPORTS_DIR)) {
    mkdir(EXPORTS_DIR);       
} 

// to ensure logs dir exists
$logs_folder = ROOT_DIR.'logs';
if (!file_exists(LOGS_DIR) && !is_dir(LOGS_DIR)) {
    mkdir(LOGS_DIR);       
} 

$today_ad = date('Y-m-d');  // ex: 2019-09-16
$log = new Logger(LOGS_DIR.DIRECTORY_SEPARATOR.'log-' . $today_ad . '.log');

$tw_date = new Datetime("now");
$tw_date->modify("-1911 year");
$this_year = ltrim($tw_date->format("Y"), "0");	// ex: 108
$today = ltrim($tw_date->format("Ymd"), "0");	// ex: 1080325
$tw_date->modify("-1 week");
$week_ago = ltrim($tw_date->format("Ymd"), "0");	// ex: 1080318

set_exception_handler(function(Throwable $e) {
    global $log;
    $log->error($e->getMessage());
});
