<?php
require_once("./include/init.php");
require_once("./include/Query.class.php");
require_once("./include/Message.class.php");
require_once("./include/Logger.class.php");
//echo date("H") . date("i", strtotime("1 min")) . date("s", strtotime("1 second"))."<br/>";

//$xkey = (random_int(1, 255) * date("H") * date("i", strtotime("1 min")) * date("s", strtotime("1 second"))) % 65535;
//echo $xkey;

$msg = new Messsage();
var_dump($msg->sendMessage("中文", "test content 我是中文", "HB0541"));
var_dump(sqlsrv_errors());

//var_dump(getTdocUserInfo("hb0541"));
/*
$ms_db = new MSDB();
var_dump(print_r($ms_db->fetch("select top 1 sn from Message order by sn desc"), true));

echo date("Y-m-d 23:59:59");
*/
?>
