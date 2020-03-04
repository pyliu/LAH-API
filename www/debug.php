<?php
require_once("./include/init.php");
require_once("./include/Query.class.php");
require_once("./include/Message.class.php");
require_once("./include/Logger.class.php");
require_once("./include/Stats.class.php");
//echo date("H") . date("i", strtotime("1 min")) . date("s", strtotime("1 second"))."<br/>";

//$xkey = (random_int(1, 255) * date("H") * date("i", strtotime("1 min")) * date("s", strtotime("1 second"))) % 65535;
//echo $xkey;

        /*$tdoc_db = new MSDB(array(
            "MS_DB_UID" => SYSTEM_CONFIG["MS_TDOC_DB_UID"],
            "MS_DB_PWD" => SYSTEM_CONFIG["MS_TDOC_DB_PWD"],
            "MS_DB_DATABASE" => SYSTEM_CONFIG["MS_TDOC_DB_DATABASE"],
            "MS_DB_SVR" => SYSTEM_CONFIG["MS_TDOC_DB_SVR"],
            "MS_DB_CHARSET" => SYSTEM_CONFIG["MS_TDOC_DB_CHARSET"]
        ));
        $users_results = $tdoc_db->fetchAll("SELECT * FROM AP_USER WHERE AP_OFF_JOB <> 'Y'");
        var_dump($users_results);
        var_dump($tdoc_db->getLastQuery());
        */
//var_dump(getTdocUserInfo("hb0541"));
/*
$ms_db = new MSDB();
var_dump(print_r($ms_db->fetch("select top 1 sn from Message order by sn desc"), true));

*/
/*
$query = new Query();
$rows = $query->queryOverdueCasesIn15Days();
echo "<p>".count($rows)."</p>";
echo "<br />";
echo str_replace("\n", "<br />", print_r($rows, true));
*/
// $overdueSchedule = [
//     'Sun' => [],
//     'Mon' => ['08:00 AM' => '08:15 AM', '13:00 PM' => '13:15 PM'],
//     'Tue' => ['08:00 AM' => '08:15 AM', '13:00 PM' => '13:15 PM'],
//     'Wed' => ['08:00 AM' => '08:15 AM', '13:00 PM' => '13:15 PM'],
//     'Thu' => ['08:00 AM' => '08:15 AM', '13:00 PM' => '13:15 PM'],
//     'Fri' => ['08:00 AM' => '08:15 AM', '13:00 PM' => '13:15 PM'],
//     'Sat' => []
// ];
// $dog = new Watchdog();
// echo $dog->isOn($overdueSchedule);

$db = new SQLite3(DEF_SQLITE_DB);
$now = $db->querySingle("select TOTAL from stats WHERE ID = 'overdue_msg_count'", true);
var_dump($now["TOTAL"]);
var_dump($db->query("SELECT * FROM stats"));
$db->close();

$db = new Stats();
$db->addOverdueMsgCount(123);
?>
