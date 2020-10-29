<?php
require_once(dirname(dirname(__FILE__)).DIRECTORY_SEPARATOR.'include'.DIRECTORY_SEPARATOR.'init.php');
require_once(INC_DIR.DIRECTORY_SEPARATOR.'WatchDog.class.php');

$log->info("TASK [watchdog] 監控啟動");
$watchdog = new WatchDog();
$done = $watchdog->do();
if ($done) {
    $log->info("TASK [watchdog] 檢查完成");
} else {
    $log->warning("TASK [watchdog] 非上班時段停止執行。");
}