<?php
require_once("./include/init.php");
require_once("./include/OracleDB.class.php");
require_once("./include/Query.class.php");
$query = new Query();
var_dump($query->getChargeItems());
//var_dump($query->getProblematicCrossCases());
?>
