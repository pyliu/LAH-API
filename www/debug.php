<?php
require_once("./include/init.php");
require_once("./include/Query.class.php");
require_once("./include/MSDB.class.php");

$ms_db = new MSDB();
var_dump(print_r($ms_db->fetchAll("SELECT TOP 10 * FROM dbo.Message")));

/*
$sock = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
$msg = "This is a test";
$len = strlen($msg);
echo socket_sendto($sock, $msg, $len, 0, '220.1.35.48', 12345);
socket_close($sock);
*/
?>
