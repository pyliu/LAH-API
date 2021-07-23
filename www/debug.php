<?php
require_once("./include/init.php");
require_once("./include/Query.class.php");
require_once("./include/Message.class.php");
require_once("./include/StatsOracle.class.php");
require_once("./include/Logger.class.php");
require_once("./include/TdocUserInfo.class.php");
require_once("./include/api/FileAPICommandFactory.class.php");
require_once("./include/Watchdog.class.php");
require_once("./include/StatsOracle.class.php");
require_once("./include/SQLiteUser.class.php");
require_once("./include/System.class.php");
require_once("./include/Temperature.class.php");
require_once("./include/StatsSQLite.class.php");
require_once("./include/Ping.class.php");
require_once("./include/BKHXWEB.class.php");
require_once("./include/Checklist.class.php");
require_once("./include/SQLiteRegFixCaseStore.class.php");
require_once("./include/SQLiteCaseCode.class.php");

try {
    // $cl = new Checklist();
    // $cl->debug();
    $today = new Datetime("now");
    $today = ltrim($today->format("Y/m/d"), "0");	// ex: 2021/01/21
    echo $today;
    // $files = array_diff(scandir("assets/img/poster"), array('..', '.'));
    // echo print_r($files, true);
    echo '<br/><br/>';

    echo ord('A');
    
    echo '<br/><br/>';

    $scc = new SQLiteCaseCode();
    $scc->importFromOraDB();
    
}
catch(Exception $e)
{
    die($e->getMessage());
}
