<?php
require_once("./include/init.php");
require_once("./include/MonitorMail.class.php");
require_once("./include/SQLiteMonitorMail.class.php");
require_once("./include/Ping.class.php");
require_once("./include/SQLiteOFFICESSTATS.class.php");
require_once("./include/StatsSQLite.class.php");
require_once("./include/System.class.php");
require_once("./include/MOIADM.class.php");
require_once("./include/XCase.class.php");
require_once("./include/SQLiteSurDestructionTracking.class.php");
require 'vendor/autoload.php';

try {
    // echo "now is ".milliseconds()." ms\n";
    // echo "ms date is ".timestampToDate(milliseconds())."\n";
    // echo "tw date is ".timestampToDate(milliseconds(), 'TW')."\n";
    // echo "now is ".time()." s\n";
    // echo "sec date is ".timestampToDate(time())."\n";
    // echo "tw date is ".timestampToDate(time(), 'TW')."\n";
    
    // echo "\$this_year now is " . $this_year . "\n";
    // echo "\$today now is " . $today . "\n";

    // $q = new XCase();
    // $arr = $moicas->getCUSMMByDate('1130530', '1130531');
    // $l3_crcld = $q->getXCaseCRCLD("113HBA177830");
    // var_dump($l3_crcld);
    // $l3_crcrd = $q->getXCaseCRCRD($l3_crcld);
    // var_dump($l3_crcrd);
    // $result = $q->syncXCaseFixData("113HBA177830");
    $c = new SQLiteSurDestructionTracking();
    $result = $c->searchByOverdue();
    var_dump($result);
    $result = $c->searchByConcerned();
    var_dump($result);
} catch(Exception $e) {
    die($e->getMessage());
} finally {
}
