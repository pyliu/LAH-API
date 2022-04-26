<?php
require_once("./include/init.php");
require_once("./include/Scheduler.class.php");

try {
    echo strtotime('+15 mins', time());
    echo '<br/>';
    echo strtotime('+1440 mins', time()) - time();
    echo '<br/>';
    echo false <= time();
    // $sd = new Scheduler();
    // $sd->do();
    echo '<br/>';
    echo  mb_check_encoding();
    
}
catch(Exception $e)
{
    die($e->getMessage());
}
