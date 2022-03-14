<?php
require_once("./include/init.php");
require_once("./include/Query.class.php");
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
require_once("./include/SQLiteSYSAUTH1.class.php");
require_once("./include/IPResolver.class.php");
require_once("./include/Notification.class.php");
require_once("./include/SQLiteMonitorMail.class.php");

try {
    echo strtotime('+15 mins', time());
    echo '<br/>';
    echo strtotime('+15 mins', time()) - time();
    echo '<br/>';
    echo false <= time();
}
catch(Exception $e)
{
    die($e->getMessage());
}
