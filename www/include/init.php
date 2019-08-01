<?
require_once("Config.inc.php");
require_once("GlobalConstants.inc.php");
require_once("GlobalFunctions.inc.php");

// some query take long time ...
set_time_limit(0);

$client_ip = $_SERVER["REMOTE_ADDR"];
if (!empty($_SERVER["HTTP_CLIENT_IP"])) {
    $client_ip = $_SERVER["HTTP_CLIENT_IP"];
} elseif (!empty($_SERVER["HTTP_X_FORWARDED_FOR"])) {
    $client_ip = $_SERVER["HTTP_X_FORWARDED_FOR"];
}

if (!in_array($client_ip, SYSTEM_CONFIG["ADM_IPS"])) {
    die("<(￣ ﹌ ￣)> ".$client_ip);
}


date_default_timezone_set("ASIA/TAIPEI");
session_start();

$tw_date = new Datetime("now");
$tw_date->modify("-1911 year");
$this_year = ltrim($tw_date->format("Y"), "0");	// ex: 108
$today = ltrim($tw_date->format("Ymd"), "0");	// ex: 1080325
$tw_date->modify("-1 week");
$week_ago = ltrim($tw_date->format("Ymd"), "0");	// ex: 1080318

$qday = $_REQUEST["date"];
$qday = preg_replace("/\D+/", "", $qday);
if (empty($qday) || !ereg("^[0-9]{7}$", $qday)) {
  $qday = (date("Y")-1911).date("md"); // 今天
}
?>
