<?php
error_reporting(E_ALL & ~E_WARNING & ~E_NOTICE);
date_default_timezone_set("ASIA/TAIPEI");
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

// ex: log-2019-09-16.log
$log = Logger::getInstance(LOG_DIR.DIRECTORY_SEPARATOR.'log-' . date('Y-m-d') . '.log');

set_exception_handler(function(Throwable $e) {
    global $log;
    $log->error($e->getMessage());
});

$tw_date = new Datetime("now");
$tw_date->modify("-1911 year");
$this_year = ltrim($tw_date->format("Y"), "0");	// ex: 108
$today = ltrim($tw_date->format("Ymd"), "0");	// ex: 1080325
$tw_date->modify("-1 week");
$week_ago = ltrim($tw_date->format("Ymd"), "0");	// ex: 1080318
