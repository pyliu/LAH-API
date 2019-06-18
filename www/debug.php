<?php
require_once("./include/init.php");
require_once("./include/Query.class.php");
require_once("./include/PrcAllCasesData.class.php");

$query = new Query();
//var_dump($query->getPrcCaseAll("108HDB1011970"));

$rows = $query->getPrcCaseAll("108HDB1011970");
$data = new PrcAllCasesData($rows);
//var_dump($data->getTableHtml());
echo json_encode(array(
    "status" => STATUS_CODE::SUCCESS_NORMAL,
    "data_count" => count($rows),
    "html" => $data->getTableHtml()
), 0);
//var_dump($query->getProblematicCrossCases());
?>
