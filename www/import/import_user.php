<?php
require_once(dirname(dirname(__FILE__)).DIRECTORY_SEPARATOR."include".DIRECTORY_SEPARATOR."init.php");
require_once(INC_DIR.DIRECTORY_SEPARATOR."TdocUserInfo.class.php");
require_once(INC_DIR.DIRECTORY_SEPARATOR."DocUserInfo.class.php");
require_once(INC_DIR.DIRECTORY_SEPARATOR."SQLiteUser.class.php");

// prepare EXT info
$userinfo = new DocUserInfo();
$old_all = $userinfo->getAllUsers();
$ext_map = array();
foreach ($old_all as $employee) {
    $employee = array_map('trim', $employee);
    $ext_map[$employee['USER_ID']] = empty($employee['SUB_TEL']) ? '153' : $employee['SUB_TEL'];
}

$userinfo = new TdocUserInfo();
$all = $userinfo->getAllUsers();
if ($all === false) die("Can't get tdoc users.");
// inject Sub_Tel into AP_EXT for importing
$count = count($all);
for ($i = 0; $i < $count; $i++) {
    if (empty($all[$i]["DocUserID"])) continue;
    $all[$i] = @array_map('trim', $all[$i]);
    $id = $all[$i]["DocUserID"];
    $all[$i]['AP_EXT'] = array_key_exists($id, $ext_map) ? $ext_map[$id] : '153';
}

$sqlite_user = new SQLiteUser();
for ($i = 0; $i < $count; $i++) {
    // old DB data is not clean ... Orz
    $all[$i] = array_map('trim', $all[$i]);
    if (empty($all[$i]["DocUserID"])) {
        $log->info($i.": DocUserID is empty ... skipped.");
        continue;
    }

    $this_msg = $i.": ".$all[$i]["DocUserID"]."...";
    
    $ret = $sqlite_user->import($all[$i]);
    
    $this_msg .= ($ret ? "OK ".$all[$i]['AP_EXT'] : "Failed").".";
    $log->info($this_msg);
}
$log->info("Imports users done. ($count)");
