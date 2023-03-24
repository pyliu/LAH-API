<?php
require_once("./include/init.php");
require_once("./include/Prefetch.class.php");

try {
    // echo 'TEST wipeExpiredData <br/>';
    // $result = Prefetch::wipeExpiredData();
    // var_dump($result);
} catch(Exception $e) {
    die($e->getMessage());
}
