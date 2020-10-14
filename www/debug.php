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
// $stats_sqlite3 = new StatsSQLite3();
// print_r($stats_sqlite3->getLatestAPConnHistory('220.1.35.123'));
    // $to_ping='220.1.35.2';
    // $count=1;
    // $psize=1;
    // echo "正在執行php ping命令，請等待...\n<br><br>";
    // ob_clean();
    // flush();
    //     echo "<pre>";
    //     exec("ping  $to_ping", $list);
    //     for($i=0;$i<count($list);$i++){
    //         print $list[$i]."\n";
    //     }
    //     echo "</pre>";
    //     ob_clean();
    //     flush();
    $stdout = system("dir", $ret); 
    echo iconv('BIG5', 'UTF-8', print_r($stdout, true)); 
    echo iconv('BIG5', 'UTF-8', print_r($ret, true)); 
}
catch(Exception $e)
{
    die($e->getMessage());
}
// echo date("Ymdhis", strtotime("-10 minutes"));
// echo serialize(array( ));
?>
