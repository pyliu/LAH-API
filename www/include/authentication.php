<?php
require_once("init.php");
// $client_ip is from init.php
if (!in_array($client_ip, SYSTEM_CONFIG["ADM_IPS"])) {
    $log->warning($client_ip." tried to access the mgt system.");
    die("<(￣ ﹌ ￣)> you bad boy ... ".$client_ip);
}
?>
