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
require_once(ROOT_DIR."/include/SQLiteAPConnectionHistory.class.php");
require 'vendor/autoload.php';

try {
    $cpuInfo = getCpuInfo();
    if (!empty($cpuInfo)) {
        echo "<pre>";
        print_r($cpuInfo);
        echo "</pre>" . "\n";
    } else {
        echo "Unable to retrieve CPU information." . "\n";
    }
    // SQLiteAPConnectionHistory::removeDBFiles();
} catch (Exception $ex) {
    echo 'Caught exception: ', $e->getMessage(), "\n";
} finally {
    echo "\n\nThis is the finally block.\n\n";
}