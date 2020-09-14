<?php
require_once(dirname(dirname(__FILE__)).DIRECTORY_SEPARATOR."include".DIRECTORY_SEPARATOR."init.php");
require_once(INC_DIR.DIRECTORY_SEPARATOR."TdocUserInfo.class.php");
require_once(INC_DIR.DIRECTORY_SEPARATOR."SQLiteUser.class.php");

$userinfo = new TdocUserInfo();
$all = $userinfo->getAllUsers();
if ($all === false) die("Return results is false.");

$count = count($all);
$sqlite_user = new SQLiteUser();
for ($i = 0; $i < $count; $i++) {
    // old DB data is not clean ... Orz
    $all[$i] = array_map('trim', $all[$i]);
    if (empty($all[$i]["DocUserID"])) {
        echo $i.": DocUserID is empty ... skipped.<br/>";
        ob_flush();
        flush();
        continue;
    }
    echo $i.": ".$all[$i]["DocUserID"]."...";
    ob_flush();
    flush();
    $ret = $sqlite_user->import($all[$i]);
    echo ($ret ? "OK" : "Failed").".<br/>";
}
$log->info("Imports users done. ($count)");
echo "Imports users done. ($count)";
?>
