<?php
require_once(dirname(dirname(__FILE__)).DIRECTORY_SEPARATOR."include".DIRECTORY_SEPARATOR."init.php");
require_once(ROOT_DIR.DIRECTORY_SEPARATOR."include".DIRECTORY_SEPARATOR."UserInfo.class.php");

$userinfo = new UserInfo();
$all = $userinfo->getAllUsers();
$count = count($all);
for ($i = 0; $i < $count; $i++) {
    $userinfo->import($all[$i]);
}
$log->info("Imports users done. ($count)");
?>
