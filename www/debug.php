<?php
require_once("./include/init.php");
require_once("./include/Prefetch.class.php");
require_once("./include/Watchdog.class.php");
// require_once("./include/Notification.class.php");

try {
    echo "now is ".milliseconds()." ms\n";
    echo "ms date is ".timestampToDate(milliseconds())."\n";
    echo "tw date is ".timestampToDate(milliseconds(), 'TW')."\n";
    echo "now is ".time()." s\n";
    echo "sec date is ".timestampToDate(time())."\n";
    echo "tw date is ".timestampToDate(time(), 'TW')."\n";

    // $notify = new Notification();
    // $notify->removeTodayOfficeDownMessage('inf');
    // $w = new Watchdog();
    // $arr = $w->sendOfficeCheckNotification();
    // echo count($arr)."\n";

} catch(Exception $e) {
    die($e->getMessage());
}
