<?php
require_once("./include/init.php");
require_once("./include/Query.class.php");

$qday = $_REQUEST["date"];
$qday = preg_replace("/\D+/", "", $qday);
if (empty($qday) || !preg_match("/^[0-9]{7}$/i", $qday)) {
  $qday = $today; // 今天
}

$query = new Query();
$query->echoAllCasesHTML($qday);
?>
