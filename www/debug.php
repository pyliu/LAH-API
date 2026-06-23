<?php
require_once("./include/init.php");

try {
    // $ad = new AdService();
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

    // $result = $ad->getConfig();
    // echo "AD Config:\n";
    // echo "- " . print_r($result, true) . "\n\n";

    // $suser = new SQLiteUser();
    // $result = $suser->syncUserDynamicIP(86400);
    // echo "Synced user dynamic IP entries:\n";
    // foreach ($result as $row) {
    //     echo "- " . print_r($row, true) . "\n\n";
    // }

    // $moicas = new MOICAS();
    // $rows = $moicas->getCounterPrinterMap();
    // echo "- " . print_r($rows, true) . "\n\n";
    // $moisms = new MOISMS();
    // $moisms->resendMOIADMSMSFailureRecordsByDate($tw_date);
    // $rows = $moisms->getMOIADMSMSLOGFailureRecordsByDate('1150331');
    // echo print_r(REG_CODE, true) . "\n\n";
    $parser = new DGXLandCaseParser();
    // 測試案例一：混合輸入
    $testInput1 = "幫我查 南投桃園 第190號，還有 1200";
    $result1 = $parser->parse($testInput1);

    // 測試案例二：多筆純數字繼承
    $testInput2 = "HA85 1200 1300 1400";
    $result2 = $parser->parse($testInput2);

    echo json_encode(array(
        'case_1_input' => $testInput1,
        'case_1_output' => $result1,
        'case_2_input' => $testInput2,
        'case_2_output' => $result2
    ), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    
} catch (Exception $ex) {
    echo 'Caught exception: ', $ex->getMessage(), "\n";
} finally {
    echo "\n\nThis is the finally block.\n\n";
}