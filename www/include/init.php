<?php
date_default_timezone_set("ASIA/TAIPEI");
session_start();
// some query take long time ...
set_time_limit(0);

require_once("config/Config.inc.php");
require_once("GlobalConstants.inc.php");
require_once("GlobalFunctions.inc.php");
require_once("Logger.class.php");

$client_ip = $_SERVER["HTTP_X_FORWARDED_FOR"] ?? $_SERVER["HTTP_CLIENT_IP"] ?? $_SERVER["REMOTE_ADDR"] ?? getLocalhostIP();

// to ensure logs dir exists
$logs_folder = ROOT_DIR.DIRECTORY_SEPARATOR.'logs';
if (!file_exists($logs_folder) && !is_dir($logs_folder)) {
    mkdir($logs_folder);       
} 

$today_ad = date('Y-m-d');  // ex: 2019-09-16
$log = new Logger(ROOT_DIR.'/logs/log-' . $today_ad . '.log');

if (php_sapi_name() != "cli") {
    // compress all log every monday
    if (date("w") == "1" && !isset($_SESSION["LOG_COMPRESSION_DONE"])) {
        $log->info("今天星期一，開始壓縮LOG檔！");
        zipLogs();
        $_SESSION["LOG_COMPRESSION_DONE"] = true;
        $log->info("壓縮LOG檔結束！");
    }
}

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
?>
