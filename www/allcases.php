<?php
require_once("./include/init.php");
require_once("./include/Query.class.php");

$query = new Query();
$query->echoAllCasesHTML($qday);
?>
