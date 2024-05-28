<?php
require_once("./include/init.php");
require_once("./include/MonitorMail.class.php");
require_once("./include/SQLiteMonitorMail.class.php");
require_once("./include/Ping.class.php");
require_once("./include/SQLiteOFFICESSTATS.class.php");
require_once("./include/StatsSQLite.class.php");
require_once("./include/System.class.php");
require 'vendor/autoload.php';

try {
    echo "now is ".milliseconds()." ms\n";
    echo "ms date is ".timestampToDate(milliseconds())."\n";
    echo "tw date is ".timestampToDate(milliseconds(), 'TW')."\n";
    echo "now is ".time()." s\n";
    echo "sec date is ".timestampToDate(time())."\n";
    echo "tw date is ".timestampToDate(time(), 'TW')."\n";
    
    echo "\$this_year now is " . $this_year . "\n";
    echo "\$today now is " . $today . "\n";

    $sqlite = new StatsSQLite();
    System::getInstance()->
    $arr = $sqlite->getLatestAPConnHistory();
    var_dump($arr);
} catch(Exception $e) {
    die($e->getMessage());
} finally {
}
