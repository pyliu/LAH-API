<?php
define('ROOT_DIR', dirname(__FILE__));

date_default_timezone_set("ASIA/TAIPEI");
session_start();
// some query take long time ...
set_time_limit(0);

require_once("Logger.class.php");

$client_ip = $_SERVER["HTTP_X_FORWARDED_FOR"] ?? $_SERVER["HTTP_CLIENT_IP"] ?? $_SERVER["REMOTE_ADDR"];

// to ensure log dir exists
if (!file_exists('log') && !is_dir('log')) {
    mkdir('log');       
}

// ex: log-2019-09-16.log
$log = new Logger('log'.DIRECTORY_SEPARATOR.'log-' . date('Y-m-d') . '.log');

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
