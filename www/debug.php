<?php
require_once("./include/init.php");
require_once(ROOT_DIR."/include/SQLiteAdminActionLog.class.php");
require_once(ROOT_DIR."/include/MOISMS.class.php");
require 'vendor/autoload.php';

try {
    // $s = SQLiteAdminActionLog::getInstance();
    // $records = SQLiteAdminActionLog::getInstance()->get(time(), time() - 86400);
    // var_dump($records);

    // $moisms = new MOISMS();
    // $result = $moisms->manualSendSMS('0911XXXXXX', '手動測試');
    // $result = $moisms->manualSendBookingSMS('0911XXXXXX', 'BOOKING測試', '1140408', '083000');
    // $result = $moisms->isMA5NoExists('1140408000003');
    // $result = $moisms->setTodayMA05SMSToResend('0911XXXXXX', '手動測試');
    // $result = $moisms->setMA05SMSToResendByMA5NO('1140408000003');
    // var_dump($result);

    // $cpuInfo = getCpuInfo();
    // if (!empty($cpuInfo)) {
    //     echo "<pre>";
    //     print_r($cpuInfo);
    //     echo "</pre>" . "\n";
    // } else {
    //     echo "Unable to retrieve CPU information." . "\n";
    // }
    // SQLiteAPConnectionHistory::removeDBFiles();
} catch (Exception $ex) {
    echo 'Caught exception: ', $e->getMessage(), "\n";
} finally {
    echo "\n\nThis is the finally block.\n\n";
}