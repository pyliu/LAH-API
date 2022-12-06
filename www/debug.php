<?php
require_once("./include/init.php");
require_once("./include/MOIPRC.class.php");
require_once("./include/LXHWEB.class.php");

try {
    echo 'TEST HA 1111201 ~ 1111231 <br/>';
    $moiprc = new MOIPRC();
    $map = $moiprc->getRealPriceMap('1111201', '1111231');
    var_dump($map);
}
catch(Exception $e)
{
    die($e->getMessage());
}
