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

try {
    // $stdout = system("dir", $ret); 
    // echo iconv('BIG5', 'UTF-8', print_r($stdout, true)); 
    // echo iconv('BIG5', 'UTF-8', print_r($ret, true)); 
    pingDomain('220.1.35.84');
}
catch(Exception $e)
{
    die($e->getMessage());
}
// echo date("Ymdhis", strtotime("-10 minutes"));
// echo serialize(array( ));
