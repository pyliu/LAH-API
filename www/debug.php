<?php
require_once("./include/init.php");
require_once("./include/Query.class.php");
require_once("./include/MSDB.class.php");
require_once("./include/Logger.class.php");

$ms_db = new MSDB();
var_dump(print_r($ms_db->fetchAll("SELECT TOP 10 * FROM dbo.Message")));

$log = new Katzgrau\KLogger\Logger('c:/AppServ/www', Psr\Log\LogLevel::INFO);
$log->info('Returned a million search results'); //Prints to the log file
$log->error('Oh dear.'); //Prints to the log file
$log->debug('x = 5'); //Prints nothing due to current severity threshhold
?>
