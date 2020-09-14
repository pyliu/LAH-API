<?php
require_once(dirname(dirname(__FILE__)).DIRECTORY_SEPARATOR."include".DIRECTORY_SEPARATOR."init.php");
require_once(INC_DIR.DIRECTORY_SEPARATOR."UserInfo.class.php");
require_once(INC_DIR.DIRECTORY_SEPARATOR."SQLiteUser.class.php");

$userinfo = new UserInfo();
$all = $userinfo->getAllUsers();
$count = count($all);
$sqlite_user = new SQLiteUser();
for ($i = 0; $i < $count; $i++) {
    $sqlite_user->import($all[$i]);
}
$log->info("Imports users done. ($count)");
?>
