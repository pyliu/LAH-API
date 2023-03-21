<?php
require_once("./include/init.php");
require_once("./include/Prefetch.class.php");
require_once("./include/StatsOracle.class.php");

try {
    // echo 'TEST wipeExpiredData <br/>';
    // $result = Prefetch::wipeExpiredData();
    // var_dump($result);
    $stats = new StatsOracle();
    $raw = $stats->getRegaCount('1120321');
    var_dump($raw);
}
catch(Exception $e)
{
    die($e->getMessage());
}
