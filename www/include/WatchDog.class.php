<?php
require_once(dirname(dirname(__FILE__)).'/include/init.php');
require_once(ROOT_DIR.'/include/Query.class.php');
require_once(ROOT_DIR.'/include/Message.class.php');
require_once(ROOT_DIR.'/include/Stats.class.php');
require_once(ROOT_DIR.'/include/Temperature.class.php');
require_once(ROOT_DIR.'/include/UserInfo.class.php');

class WatchDog {
    
    private $stats = null;
    private $schedule = array(
        "office" => [
            'Sun' => [],
            'Mon' => ['08:00 AM' => '05:00 PM'],
            'Tue' => ['08:00 AM' => '05:00 PM'],
            'Wed' => ['08:00 AM' => '05:00 PM'],
            'Thu' => ['08:00 AM' => '05:00 PM'],
            'Fri' => ['08:00 AM' => '05:00 PM'],
            'Sat' => []
        ],
        "overdue" => [
            'Sun' => [],
            'Mon' => ['08:30 AM' => '08:45 AM', '01:30 PM' => '01:45 PM'],
            'Tue' => ['08:30 AM' => '08:45 AM', '01:30 PM' => '01:45 PM'],
            'Wed' => ['08:30 AM' => '08:45 AM', '01:30 PM' => '01:45 PM'],
            'Thu' => ['08:30 AM' => '08:45 AM', '01:30 PM' => '01:45 PM'],
            'Fri' => ['08:30 AM' => '08:45 AM', '01:30 PM' => '01:45 PM'],
            'Sat' => []
        ],
        "temperature" => [
            'Sun' => [],
            'Mon' => ['08:15 AM' => '08:30 AM', '09:15 AM' => '09:30 AM', '01:15 PM' => '01:30 PM', '02:15 PM' => '02:30 PM'],
            'Tue' => ['08:15 AM' => '08:30 AM', '09:15 AM' => '09:30 AM', '01:15 PM' => '01:30 PM', '02:15 PM' => '02:30 PM'],
            'Wed' => ['08:15 AM' => '08:30 AM', '09:15 AM' => '09:30 AM', '01:15 PM' => '01:30 PM', '02:15 PM' => '02:30 PM'],
            'Thu' => ['08:15 AM' => '08:30 AM', '09:15 AM' => '09:30 AM', '01:15 PM' => '01:30 PM', '02:15 PM' => '02:30 PM'],
            'Fri' => ['08:15 AM' => '08:30 AM', '09:15 AM' => '09:30 AM', '01:15 PM' => '01:30 PM', '02:15 PM' => '02:30 PM'],
            'Sat' => []
        ]
    );
    private $overdue_cfg = array(
        "REG_CHIEF_ID" => "HB1214",
        "SUBSCRIBER" => array(
            "220.1.35.48",  // pyliu
            "220.1.35.106"  // INF Chief
        )
    );

    private function isOfficeHours() {
        global $log;
        $log->info("檢查是否處於上班時間 ... ");
        $result = $this->isOn($this->schedule["office"]);
        $log->info('現在是'.($result ? "上班" : "下班")."時段。");
        return $result;
    }

    private function isOverdueCheckNeeded() {
        global $log;
        $log->info("檢查是否需要執行逾期案件檢查 ... ");
        $result = $this->isOn($this->schedule["overdue"]);
        $log->info('現在是'.($result ? "啟動" : "非啟動")."時段。");
        return $result;
    }

    private function isTemperatureNotifyNeeded() {
        global $log;
        $log->info("檢查是否需要體溫通知 ... ");
        $result = $this->isOn($this->schedule["temperature"]);
        $log->info('現在是'.($result ? "啟動" : "非啟動")."時段。");
        return $result;
    }

    private function checkCrossSiteData() {
        global $log;
        $query = new Query();
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
            
            $host_ip = getLocalhostIP();
            $msg = new Message();
            $content = "系統目前找到下列跨所註記遺失案件:\r\n\r\n".implode("\r\n", $case_ids)."\r\n\r\n請前往 http://$host_ip/watchdog.html 執行檢查功能並修正。";
            foreach (SYSTEM_CONFIG['ADM_IPS'] as $adm_ip) {
                if ($adm_ip == '::1') {
                    continue;
                }
                $sn = $msg->send('跨所案件註記遺失通知', $content, $adm_ip, 840);   // 840 secs => +14 mins
                $log->info("訊息已送出(${sn})給 ${adm_ip}");
            }
            $this->stats->addXcasesStats(array(
                "date" => date("Y-m-d H:i:s"),
                "found" => count($rows),
                "note" => $content
            ));
        }
        $log->info('跨所註記遺失檢查結束。');
    }

    private function findDelayRegCases() {
        global $log;
        if (!$this->isOverdueCheckNeeded()) {
            $log->warning(__METHOD__.": 非設定時間內，跳過逾期案件檢測。");
            return false;
        }
        $query = new Query();
        // check reg case missing RM99~RM101 data
        $log->info('開始查詢15天內逾期登記案件 ... ');

        $rows = $query->queryOverdueCasesIn15Days();
        if (!empty($rows)) {
            $log->info('15天內找到'.count($rows).'件逾期登記案件。');
            $users = GetDBUserMapping();
            $case_records = [];
            foreach ($rows as $row) {
                $this_msg = $row['RM01'].'-'.$row['RM02'].'-'.$row['RM03'].' '.REG_REASON[$row['RM09']].' '.($users[$row['RM45']] ?? $row['RM45']);
                $case_records[$row['RM45']][] = $this_msg;
                $case_records["ALL"][] = $this_msg;
                //$log->info("找到逾期案件：${this_msg}");
            }
            
            // send to the reviewer
            $stats = 0;
            $date = date('Y-m-d H:i:s');
            foreach ($case_records as $ID => $records) {
                $this->sendOverdueMessage($ID, $records);
                $this->stats->addOverdueStatsDetail(array(
                    "ID" => $ID,
                    "RECORDS" => $records,
                    "DATETIME" => $date,
                    "NOTE" => $users[$ID]
                ));
                $stats++;
            }
            
            $this->stats->addOverdueMsgCount($stats);
        }
        $log->info('查詢近15天逾期登記案件完成。');
        return true;
    }

    private function sendOverdueMessage($to_id, $case_records) {
        global $log;
        $chief_id = $this->overdue_cfg["REG_CHIEF_ID"];
        $host_ip = getLocalhostIP();
        $users = GetDBUserMapping();
        $msg = new Message();
        $url = "http://${host_ip}/overdue_reg_cases.html";
        if ($to_id != "ALL") {
            $url .= "?ID=${to_id}";
        }
        $content = "目前有 ".count($case_records)." 件逾期案件(近15天".(count($case_records) > 4 ? "，僅顯示前4筆" : "")."):\r\n\r\n".implode("\r\n", array_slice($case_records, 0, 4))."\r\n...\r\n\r\n請用 CHROME 瀏覽器前往 ${url}\r\n查看詳細列表。";
        if ($to_id == "ALL") {
            $title = "15天內逾期案件(全部)通知";
            $sn = $msg->sysSend($title, $content, $chief_id, 14399);  // 14399 secs => +3 hours 59 mins 59 secs
            $log->info("${title}訊息(${sn})已送出給 ${chief_id} 。 (".$users[$chief_id].")");
            // send all cases notice to subscribers
            foreach ($this->overdue_cfg["SUBSCRIBER"] as $subscriber_ip) {
                $sn = $msg->send($title, $content, $subscriber_ip, 14399);
                $log->info("${title}訊息(${sn})已送出給 ${subscriber_ip} 。 (訂閱者)");
            }
        } else {
            $this_user = $users[$to_id];
            $title = "15天內逾期案件(${this_user})通知";
            $sn = $msg->sysSend($title, $content, $to_id, 14399);
            if ($sn == -1) {
                $log->warning("${title}訊息無法送出給 ${to_id} 。 (".$this_user.", $sn)");
            } else {
                $log->info("${title}訊息(${sn})已送出給 ${to_id} 。 (".$this_user.")");
            }
        }
    }

    public function notifyTemperatureRegistration() {
        global $log;
        if (!$this->isTemperatureNotifyNeeded()) {
            $log->warning(__METHOD__.": 非設定時間內，跳過體溫通知排程。");
            return false;
        }
        // get all on-board users
        $userinfo = new UserInfo();
        $onboard_users = $userinfo->getOnBoardUsers();
        //check if they checked their temperature
        $temperature = new Temperature();
        $AMPM = date('A');
        foreach ($onboard_users as $idx => $user) {
            $user_id = $user['DocUserID'];
            $record = $temperature->getAMPMTemperatures($user_id, $AMPM);
            // only no record should be notified
            if (empty($record)) {
                $this->sendTemperatureMessage($user);
            }
        }
    }

    private function sendTemperatureMessage($user) {
        global $log;
        $to_id = trim($user['DocUserID']);
        $to_name = $user['AP_USER_NAME'];
        $AMPM = date('A');
        $host_ip = getLocalhostIP();
        $msg = new Message();
        $url = "http://${host_ip}/temperature.html?id=${to_id}";
        $content = "$to_name 您好\r\n\r\n系統偵測您於今日 $AMPM 尚未登記體溫！\r\n\r\n請用 CHROME 瀏覽器前往 ${url} 進行登記。";
        $title = "體溫登記通知";
        $sn = $msg->sysSend($title, $content, $to_id, 840); // 14 mins == 840 secs
        if ($sn == -1) {
            $log->warning("${title} 訊息無法送出給 ${to_id}。($to_name, $sn)");
        } else {
            $log->info("${title} 訊息(${sn})已送出給 ${to_id}。($to_name)");
        }
    }

    function __construct() {
        $this->stats = new Stats();
    }

    function __destruct() { }

    public function do() {
        if ($this->isOfficeHours()) {
            $this->checkCrossSiteData();
            $this->findDelayRegCases();
            $this->notifyTemperatureRegistration();
            return true;
        }
        return false;
    }
    
    public function isOn($schedule) {
        // current or user supplied UNIX timestamp
        $timestamp = time();
        // default status
        $status = false;
        // get current time object
        $currentTime = (new DateTime())->setTimestamp($timestamp);
        // loop through time ranges for current day
        foreach ($schedule[date('D', $timestamp)] as $startTime => $endTime) {
            // create time objects from start/end times
            $st = DateTime::createFromFormat('h:i A', $startTime);
            $ed = DateTime::createFromFormat('h:i A', $endTime);

            // check if current time is within a range
            if (($st < $currentTime) && ($currentTime < $ed)) {
                $status = true;
                break;
            }
        }
        return $status;
    }
}
?>
