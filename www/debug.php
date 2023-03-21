<?php
require_once("./include/init.php");
require_once("./include/Prefetch.class.php");
require_once("./include/MOICAD.class.php");

try {
    // echo 'TEST wipeExpiredData <br/>';
    // $result = Prefetch::wipeExpiredData();
    // var_dump($result);
    $moicad = new MOICAD();
    $raw = $moicad->getInheritanceRestrictionRecords();
    var_dump($raw);
} catch(Exception $e) {
    die($e->getMessage());
}
