<?php
require_once('include/init.php');
require_once('include/Query.class.php');
require_once('include/Message.class.php');

$query = new Query();

$_SERVER["REQUEST_URI"] = $_SERVER["REQUEST_URI"] ?? DIRECTORY_SEPARATOR.basename(__FILE__);
$_SERVER["REMOTE_ADDR"] = $_SERVER["REMOTE_ADDR"] ?? getLocalhostIP();

// check reg case missing RM99~RM101 data
$log->info('開始跨所註記遺失檢查 ... ');
$rows = $query->getProblematicCrossCases();
if (!empty($rows)) {
    $log->warning('找到'.count($rows).'件跨所註記遺失！');
    $case_ids = [];
    foreach ($rows as $row) {
        $case_ids[] = $row['RM01'].'-'.$row['RM02'].'-'.$row['RM03'];
        $log->warning($row['RM01'].'-'.$row['RM02'].'-'.$row['RM03']);
    }
    
    $msg = new Message();
    $content = "系統目前找到下列跨所註記遺失案件:\r\n\r\n".implode("\r\n", $case_ids)."\r\n\r\n請前往 http://$host_ip/watch_dog.php 修正。";
    foreach (SYSTEM_CONFIG['ADM_IPS'] as $adm_ip) {
        if ($adm_ip == '::1') {
            continue;
        }
        $sn = $msg->send('跨所案件註記遺失通知', $content, $adm_ip);
        $log->info("訊息已送出(${sn})給 ${adm_ip}");
    }
}
$log->info('跨所註記遺失檢查結束。');
?>
