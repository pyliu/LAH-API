<?php
require_once("./include/init.php");

try {
    $ad = new AdService();
    // $users = $ad->getMultiDepartmentUsers();
    // echo "AD multi depts users: (".count($users).")\n";
    // foreach ($users as $user) {
    //     echo "- " . print_r($user, true) . "\n\n";
    // }
    // $users = $ad->getLockedUsers();
    // echo "\n\nAD locked users: (".count($users).")\n";
    // foreach ($users as $user) {
    //     echo "- " . print_r($user, true) . "\n\n";
    // }

    // $result = $ad->getUser('HA80013183');
    // echo "HA80013183 查詢結果：\n";
    // echo "- " . print_r($result, true) . "\n\n";

    $result = $ad->getConfig();
    echo "AD Config:\n";
    echo "- " . print_r($result, true) . "\n\n";

    $suser = new SQLiteUser();
    $result = $suser->syncUserDynamicIP(86400);
    echo "Synced user dynamic IP entries:\n";
    foreach ($result as $row) {
        echo "- " . print_r($row, true) . "\n\n";
    }
} catch (Exception $ex) {
    echo 'Caught exception: ', $ex->getMessage(), "\n";
} finally {
    echo "\n\nThis is the finally block.\n\n";
}