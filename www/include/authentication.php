<?php
require_once("init.php");
// $client_ip is from init.php
if (!in_array($client_ip, SYSTEM_CONFIG["ADM_IPS"])) {
    $log->warning($client_ip." tried to access the mgt system.");
    echo json_encode(array(
		"status" => STATUS_CODE::FAIL_NO_AUTHORITY,
		"data_count" => "0",
		"message" => "<(￣ ﹌ ￣)> you bad boy ... ".$client_ip
    ), 0);
    exit;
}
?>
