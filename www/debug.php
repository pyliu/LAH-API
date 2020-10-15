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
require_once("./include/StatsSQLite3.class.php");
require_once("./include/Ping.class.php");

try {
    pingDomain('220.1.35.84');
    $ping = new Ping('220.2.33.50');
    $latency = $ping->ping();
}
catch(Exception $e)
{
    die($e->getMessage());
}
$stats = new StatsSQLite3();
print_r($stats->getConnectivityStatus());

// echo date("Ymdhis", strtotime("-10 minutes"));
// echo serialize(array( ));
