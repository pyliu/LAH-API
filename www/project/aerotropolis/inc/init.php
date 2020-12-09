<?php
define('ROOT_DIR', dirname(dirname(__FILE__)));

date_default_timezone_set("ASIA/TAIPEI");
session_start();
// some query take long time ...
set_time_limit(0);
$client_ip = $_SERVER["HTTP_X_FORWARDED_FOR"] ?? $_SERVER["HTTP_CLIENT_IP"] ?? $_SERVER["REMOTE_ADDR"];

require_once("Logger.class.php");

define('LOG_PATH', ROOT_DIR.DIRECTORY_SEPARATOR."log");
// to ensure log dir exists
if (!file_exists(LOG_PATH) && !is_dir(LOG_PATH)) {
    mkdir(LOG_PATH);       
}

define('FILE_PATH', ROOT_DIR.DIRECTORY_SEPARATOR."assets".DIRECTORY_SEPARATOR."file");
// to ensure log dir exists
if (!file_exists(FILE_PATH) && !is_dir(FILE_PATH)) {
    mkdir(FILE_PATH);       
}

define('DB_PATH', ROOT_DIR.DIRECTORY_SEPARATOR."assets".DIRECTORY_SEPARATOR."db");
// to ensure log dir exists
if (!file_exists(DB_PATH) && !is_dir(DB_PATH)) {
    mkdir(DB_PATH);       
}

// ex: log-2019-09-16.log
$log = new Logger(ROOT_DIR.DIRECTORY_SEPARATOR.'log'.DIRECTORY_SEPARATOR.'log-' . date('Y-m-d') . '.log');

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
