<?php
ini_set("display_errors", 0);
require_once("./include/init.php");
require_once("./include/Query.class.php");
require_once("./include/Message.class.php");
require_once("./include/StatsOracle.class.php");
require_once("./include/Logger.class.php");
require_once("./include/TdocUserInfo.class.php");
require_once("./include/api/FileAPICommandFactory.class.php");
require_once("./include/Watchdog.class.php");

require_once(ROOT_DIR."/include/StatsOracle.class.php");


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

// $db = new SQLite3(DEF_SQLITE_DB);
// $now = $db->querySingle("select TOTAL from stats WHERE ID = 'overdue_msg_count'", true);
// var_dump($now["TOTAL"]);
// var_dump($db->query("SELECT * FROM stats"));
// $db->close();

// $db = new Stats();
// $db->addOverdueMsgCount(123);

// $fact = new FileAPICommandFactory();
//var_dump($msg->getMessageByUser("220.1.35.48"));
//var_dump(sqlsrv_errors());

// $d1=new DateTime("2012-07-08 11:14:15.638276");
// $d2=new DateTime("2012-07-08 11:14:05.889342");
// $diff=$d2->diff($d1);
// print_r( $diff->s ) ;
// print_r( $diff ) ;

// $result = array(
//     array("text" => "text", "count" => 4),
//     array("text" => "text2", "count" => 3),
//     array("text" => "text3", "count" => 9)
// );
// echo array_reduce($result, function($carry, $item) { return $carry += $item['count']; }, 10);

// echo "\n\n";

// echo (date("Y") - 1911)."".date("m");

// $stats = new StatsOracle();
// $result = $stats->getRegCaseCount('10904');
// echo (__METHOD__.": 取得 ".count($result)." 筆資料。\n<br />");
//     echoJSONResponse("取得 ".count($result)." 筆資料。", STATUS_CODE::SUCCESS_NORMAL, array(
//         "data_count" => count($result),
//         "raw" => $result
//     ));
// $db = new PDO("odbc:driver={microsoft access driver (*.mdb)};dbq=".realpath("\\220.1.35.69\personnel\ATT2000.MDB")) or die("Connect Error");
// var_dump($db);
// $rs = $db->query('select * from web');
// print "<pre>";
// print_r($rs->fetchAll());
// print "</pre>";

// require_once('vendor/autoload.php');

// use PhpOffice\PhpSpreadsheet\Spreadsheet;
// use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
// use PhpOffice\PhpSpreadsheet\IOFactory;

// $spreadsheet = IOFactory::load('test.xlsx');

// $worksheet = $spreadsheet->getActiveSheet();

// $worksheet->getCell('A1')->setValue('套用樣板測試');
// $worksheet->getCell('B2')->setValue('B2');
// $worksheet->getCell('C3')->setValue('C3');

// // $writer = new Xlsx($spreadsheet);
// // $writer->save('exports/hello world.xlsx');
// //header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
// ob_end_clean();
// header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
// header('Content-Disposition: attachment;filename="'.$today.'.xlsx"');
// header('Cache-Control: max-age=0');
// ob_end_clean();

// $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
// $writer->save('php://output');

echo date("Ymdhis", strtotime("-10 minutes"));

?>
