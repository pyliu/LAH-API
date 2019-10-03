<?php
require_once("./include/init.php");
require_once("./include/Query.class.php");
require_once("./include/Message.class.php");
require_once("./include/Logger.class.php");
//echo date("H") . date("i", strtotime("1 min")) . date("s", strtotime("1 second"))."<br/>";

//$xkey = (random_int(1, 255) * date("H") * date("i", strtotime("1 min")) * date("s", strtotime("1 second"))) % 65535;
//echo $xkey;
/*
$msg = new Messsage();
echo $msg->getXKey();
var_dump($msg->sendMessage("test", "220.1.35.48"));
*/
var_dump(getTdocUserInfo("hb0541"));

//$ms_db = new MSDB();
//var_dump(print_r($ms_db->fetchAll("SELECT TOP 10 * FROM Message"), true));
?>
