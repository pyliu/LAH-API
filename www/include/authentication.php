<?php
require_once("init.php");
require_once(ROOT_DIR."/include/System.class.php");
$system = System::getInstance();
$adm_ips = $system->getRoleAdminIps();
// $client_ip is from init.php
if (!in_array($client_ip, $adm_ips)) {
    $log->warning($client_ip." tried to access the mgt system.");
    echo json_encode(array(
      "status" => STATUS_CODE::FAIL_NO_AUTHORITY,
      "data_count" => "0",
      "message" => "<(￣ ﹌ ￣)> you bad boy ... ".$client_ip
    ), 0);
    exit;
}
