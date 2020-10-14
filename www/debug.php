<?php
require_once("./include/init.php");
require_once("./include/Query.class.php");
require_once("./include/Message.class.php");
require_once("./include/StatsOracle.class.php");
require_once("./include/Logger.class.php");
require_once("./include/TdocUserInfo.class.php");
require_once("./include/api/FileAPICommandFactory.class.php");
require_once("./include/Watchdog.class.php");
require_once("./include/StatsOracle.class.php");
require_once("./include/SQLiteUser.class.php");
require_once("./include/System.class.php");
require_once("./include/Temperature.class.php");
require_once("./include/StatsSQLite3.class.php");

// $stats_sqlite3 = new StatsSQLite3();
// print_r($stats_sqlite3->getLatestAPConnHistory('220.1.35.123'));
try {
    $database = new PDO('sqlite:assets/db/test.db');
    $database->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $sTable = 'CREATE TABLE IF NOT EXISTS '.$table->name.' (';
    $sFields;
    foreach($table->fields as $f) {
        if($sFields != '')
        $sFields .= ',';
        $sFields .= $f->name.' '.$f->type;
    }
    $sFields .= ')';
    $sTable .= $sFields;
            
    $database->exec($sTable);
}
catch(PDOException $e)
{
    die($e->getMessage());
}
// echo date("Ymdhis", strtotime("-10 minutes"));
// echo serialize(array( ));
?>
