<?php
require_once("./include/init.php");
require_once("./include/Query.class.php");
require_once("./include/MSDB.class.php");

//$query = new Query();
//var_dump($query->getPrcCaseAll("108HDB1011970"));
//var_dump($query->getProblematicCrossCases());
$ms_db = new MSDB();
var_dump($ms_db->isConnected());
?>
