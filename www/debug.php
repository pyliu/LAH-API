<?php
require_once("./include/init.php");
require_once("./include/Query.class.php");
require_once("./include/Message.class.php");
require_once("./include/StatsOracle.class.php");
require_once("./include/Logger.class.php");
require_once("./include/TdocUserInfo.class.php");
require_once("./include/api/FileAPICommandFactory.class.php");
require_once("./include/Watchdog.class.php");
require_once("./include/StatsOracle.class.php");
require_once("./include/SQLiteUser.class.php");
require_once("./include/System.class.php");
require_once("./include/Temperature.class.php");
require_once("./include/StatsSQLite.class.php");
require_once("./include/Ping.class.php");
require_once("./include/BKHXWEB.class.php");
require_once("./include/Checklist.class.php");
require_once("./include/SQLiteSYSAUTH1.class.php");
require_once("./include/IPResolver.class.php");
require_once("./include/Notification.class.php");
require_once("./include/SQLiteMonitorMail.class.php");

try {
    // $cl = new Checklist();
    // $cl->debug();
    // $today = new Datetime("now");
    // $today = ltrim($today->format("Y/m/d"), "0");	// ex: 2021/01/21
    // echo $today;
    // $files = array_diff(scandir("assets/img/poster"), array('..', '.'));
    // echo print_r($files, true);
    // echo '<br/><br/>';
    // echo print_r(preg_replace("/^(桃園所|中壢所|大溪所|楊梅所|蘆竹所|八德所|平鎮所|龜山所|桃園|中壢|大溪|楊梅|蘆竹|八德|平鎮|龜山)/i", '', '桃園湖百松'), true);
    
    // $host_ip = getLocalhostIP();
    // $content = "⚠️地政系統目前找到下列跨所註記遺失案件:<br/><br/>".implode("<br/>", array(
    //     '🔴 110-HBA1-111111', '🔴 110-HCA1-222222'
    // ))."<br/><br/>請前往 👉 [系管面板](http://$host_ip/dashboard.html) 執行檢查功能並修正。";
    // $sqlite_user = new SQLiteUser();
    // $notify = new Notification();
    // $admins = $sqlite_user->getAdmins();
    // foreach ($admins as $admin) {
    //     if ($admin['id'] === 'HA10013859') {
    //         $lastId = $notify->addMessage($admin['id'], array(
    //             'title' => 'dontcare',
    //             'content' => trim($content),
    //             'priority' => 3,
    //             'expire_datetime' => '',
    //             'sender' => '系統排程',
    //             'from_ip' => $host_ip
    //         ));
    //         echo '新增「跨所註記遺失」通知訊息至 '.$admin['id'].' 頻道。 ('.($lastId === false ? '失敗' : '成功').')';
    //     }
    // }
    // Create PhpImap\Mailbox instance for all further actions
    // $monitor = new SQLiteMonitorMail();
    // print($monitor->removeOutdatedMail());
    // $watchdog = new WatchDog();
	// $done = $watchdog->do();
}
catch(Exception $e)
{
    die($e->getMessage());
}
