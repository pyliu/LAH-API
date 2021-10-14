<?php
require_once(dirname(dirname(__FILE__)).DIRECTORY_SEPARATOR.'include'.DIRECTORY_SEPARATOR.'init.php');
require_once(INC_DIR.DIRECTORY_SEPARATOR.'Query.class.php');
require_once(INC_DIR.DIRECTORY_SEPARATOR.'Message.class.php');
require_once(INC_DIR.DIRECTORY_SEPARATOR.'Notification.class.php');
require_once(INC_DIR.DIRECTORY_SEPARATOR.'StatsSQLite.class.php');
require_once(INC_DIR.DIRECTORY_SEPARATOR.'Temperature.class.php');
require_once(INC_DIR.DIRECTORY_SEPARATOR.'SQLiteUser.class.php');
require_once(INC_DIR.DIRECTORY_SEPARATOR.'System.class.php');
require_once(INC_DIR.DIRECTORY_SEPARATOR.'Ping.class.php');
require_once(INC_DIR.DIRECTORY_SEPARATOR.'Cache.class.php');
require_once(INC_DIR.DIRECTORY_SEPARATOR."OraDB.class.php");
require_once(INC_DIR.DIRECTORY_SEPARATOR."SQLiteSYSAUTH1.class.php");
require_once(INC_DIR.DIRECTORY_SEPARATOR."SQLiteRKEYN.class.php");

class WatchDog {
    private $stats = null;

    private $schedule = array(
        "office" => [
            'Sun' => [],
            'Mon' => ['07:30 AM' => '05:30 PM'],
            'Tue' => ['07:30 AM' => '05:30 PM'],
            'Wed' => ['07:30 AM' => '05:30 PM'],
            'Thu' => ['07:30 AM' => '05:30 PM'],
            'Fri' => ['07:30 AM' => '05:30 PM'],
            'Sat' => []
        ],
        "overdue" => [
            'Sun' => [],
            'Mon' => ['08:31 AM' => '08:46 AM', '01:31 PM' => '01:46 PM'],
            'Tue' => ['08:31 AM' => '08:46 AM', '01:31 PM' => '01:46 PM'],
            'Wed' => ['08:31 AM' => '08:46 AM', '01:31 PM' => '01:46 PM'],
            'Thu' => ['08:31 AM' => '08:46 AM', '01:31 PM' => '01:46 PM'],
            'Fri' => ['08:31 AM' => '08:46 AM', '01:31 PM' => '01:46 PM'],
            'Sat' => []
        ],
        // "temperature" => [
        //     'Sun' => [],
        //     'Mon' => ['10:30 AM' => '10:45 AM', '03:30 PM' => '03:45 PM'],
        //     'Tue' => ['10:30 AM' => '10:45 AM', '03:30 PM' => '03:45 PM'],
        //     'Wed' => ['10:30 AM' => '10:45 AM', '03:30 PM' => '03:45 PM'],
        //     'Thu' => ['10:30 AM' => '10:45 AM', '03:30 PM' => '03:45 PM'],
        //     'Fri' => ['10:30 AM' => '10:45 AM', '03:30 PM' => '03:45 PM'],
        //     'Sat' => []
        // ],
        "temperature" => [
            'Sun' => [],
            'Mon' => ['10:31 AM' => '10:46 AM'],
            'Tue' => ['10:31 AM' => '10:46 AM'],
            'Wed' => ['10:31 AM' => '10:46 AM'],
            'Thu' => ['10:31 AM' => '10:46 AM'],
            'Fri' => ['10:31 AM' => '10:46 AM'],
            'Sat' => []
        ],
        "once_a_day" => [
            'Sun' => [],
            'Mon' => ['08:46 AM' => '09:01 AM'],
            'Tue' => ['08:46 AM' => '09:01 AM'],
            'Wed' => ['08:46 AM' => '09:01 AM'],
            'Thu' => ['08:46 AM' => '09:01 AM'],
            'Fri' => ['08:46 AM' => '09:01 AM'],
            'Sat' => []
        ],
        "test" => [
            'Sun' => [],
            'Mon' => ['00:00 AM' => '11:59 PM'],
            'Tue' => ['00:00 AM' => '11:59 PM'],
            'Wed' => ['00:00 AM' => '11:59 PM'],
            'Thu' => ['00:00 AM' => '11:59 PM'],
            'Fri' => ['00:00 AM' => '11:59 PM'],
            'Sat' => []
        ]
    );

    private $overdue_cfg = array(
        "REG_CHIEF_ID" => "HA10021802",
        "SUBSCRIBER" => array(
            "192.168.13.96",    // pyliu
            "192.168.13.100",   // #501
            "192.168.13.98",    // #502
            "192.168.13.168"    // #506
        )
    );

    private function isOfficeHours() {
        Logger::getInstance()->info("檢查是否處於上班時間 ... ");
        $result = $this->isOn($this->schedule["office"]);
        Logger::getInstance()->info('現在是'.($result ? "上班" : "下班")."時段。");
        return $result;
    }

    private function isOverdueCheckNeeded() {
        
        Logger::getInstance()->info("檢查是否需要執行逾期案件檢查 ... ");
        $result = $this->isOn($this->schedule["overdue"]);
        Logger::getInstance()->info('現在是'.($result ? "啟動" : "非啟動")."時段。");
        return $result;
    }

    private function isTemperatureNotifyNeeded() {
        
        Logger::getInstance()->info("檢查是否需要體溫通知 ... ");
        $result = $this->isOn($this->schedule["temperature"]);
        Logger::getInstance()->info('現在是'.($result ? "啟動" : "非啟動")."時段。");
        return $result;
    }

    private function checkCrossSiteData() {
        if ($this->isOn($this->schedule["once_a_day"])) {
            $query = new Query();
            // check reg case missing RM99~RM101 data
            Logger::getInstance()->info('開始跨所註記遺失檢查 ... ');
            $rows = $query->getProblematicCrossCases();
            if (!empty($rows)) {
                Logger::getInstance()->warning('找到'.count($rows).'件跨所註記遺失！');
                $case_ids = [];
                foreach ($rows as $row) {
                    $case_ids[] = '🔴 '.$row['RM01'].'-'.$row['RM02'].'-'.$row['RM03'];
                    Logger::getInstance()->warning('🔴 '.$row['RM01'].'-'.$row['RM02'].'-'.$row['RM03']);
                }
                
                $host_ip = getLocalhostIP();
                $content = "⚠️地政系統目前找到下列跨所註記遺失案件:<br/><br/>".implode(" <br/> ", $case_ids)."<br/><br/>請前往 👉 [系管面板](http://$host_ip/dashboard.html) 執行檢查功能並修正。";
                $sqlite_user = new SQLiteUser();
                $notify = new Notification();
                $admins = $sqlite_user->getAdmins();
                foreach ($admins as $admin) {
                    $lastId = $notify->addMessage($admin['id'], array(
                        'title' => 'dontcare',
                        'content' => trim($content),
                        'priority' => 3,
                        'expire_datetime' => '',
                        'sender' => '系統排程',
                        'from_ip' => $host_ip
                    ));
                    echo '新增「跨所註記遺失」通知訊息至 '.$admin['id'].' 頻道。 ('.($lastId === false ? '失敗' : '成功').')';
                }
                
                $this->stats->addXcasesStats(array(
                    "date" => date("Y-m-d H:i:s"),
                    "found" => count($rows),
                    "note" => $content
                ));
            }
            Logger::getInstance()->info('跨所註記遺失檢查結束。');
        } else {
            Logger::getInstance()->warning('不在啟動區間「once_a_day」，略過跨所註記遺失檢查。');
        }
    }

    private function findDelayRegCases() {
        
        if (!$this->isOverdueCheckNeeded()) {
            Logger::getInstance()->warning(__METHOD__.": 非設定時間內，跳過逾期案件檢測。");
            return false;
        }
        $query = new Query();
        // check reg case missing RM99~RM101 data
        Logger::getInstance()->info('開始查詢15天內逾期登記案件 ... ');

        $rows = $query->queryOverdueCasesIn15Days();
        if (!empty($rows)) {
            Logger::getInstance()->info('15天內找到'.count($rows).'件逾期登記案件。');
            $cache = Cache::getInstance();
            $users = $cache->getUserNames();
            $case_records = [];
            foreach ($rows as $row) {
                $this_msg = $row['RM01'].'-'.$row['RM02'].'-'.$row['RM03'].' '.REG_REASON[$row['RM09']].' '.($users[$row['RM45']] ?? $row['RM45']);
                $case_records[$row['RM45']][] = $this_msg;
                $case_records["ALL"][] = $this_msg;
                //Logger::getInstance()->info("找到逾期案件：${this_msg}");
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
                    "NOTE" => array_key_exists($ID, $users) ? $users[$ID] : ''
                ));
                $stats++;
            }
            
            $this->stats->addOverdueMsgCount($stats);
        }
        Logger::getInstance()->info('查詢近15天逾期登記案件完成。');
        return true;
    }

    private function sendOverdueMessage($to_id, $case_records) {
        
        $chief_id = $this->overdue_cfg["REG_CHIEF_ID"];
        $host_ip = getLocalhostIP();
        $cache = Cache::getInstance();
        $users = $cache->getUserNames();
        $msg = new Message();
        $url = "http://${host_ip}/overdue_reg_cases.html";
        if ($to_id != "ALL") {
            $url .= "?ID=${to_id}";
        }
        $content = "目前有 ".count($case_records)." 件逾期案件(近15天".(count($case_records) > 4 ? "，僅顯示前4筆" : "")."):\r\n\r\n".implode("\r\n", array_slice($case_records, 0, 4))."\r\n...\r\n\r\n請用 CHROME 瀏覽器前往 ${url}\r\n查看詳細列表。";
        if ($to_id == "ALL") {
            $title = "15天內逾期案件(全部)通知";
            $sn = $msg->sysSend($title, $content, $chief_id, 14399);  // 14399 secs => +3 hours 59 mins 59 secs
            Logger::getInstance()->info("${title}訊息(${sn})已送出給 ${chief_id} 。 (".$users[$chief_id].")");
            // send all cases notice to subscribers
            foreach ($this->overdue_cfg["SUBSCRIBER"] as $subscriber_ip) {
                $sn = $msg->send($title, $content, $subscriber_ip, 'now', 14399);
                Logger::getInstance()->info("${title}訊息(${sn})已送出給 ${subscriber_ip} 。 (訂閱者)");
            }
        } else {
            $this_user = $users[$to_id];
            $title = "15天內逾期案件(${this_user})通知";
            $sn = $msg->sysSend($title, $content, $to_id, 14399);
            if ($sn == -1) {
                Logger::getInstance()->warning("${title}訊息無法送出給 ${to_id} 。 (".$this_user.", $sn)");
            } else {
                Logger::getInstance()->info("${title}訊息(${sn})已送出給 ${to_id} 。 (".$this_user.")");
            }
        }
    }

    public function notifyTemperatureRegistration() {
        
        if (!$this->isTemperatureNotifyNeeded()) {
            Logger::getInstance()->warning(__METHOD__.": 非設定時間內，跳過體溫通知排程。");
            return false;
        }
        // get all on-board users
        $sqlite_user = new SQLiteUser();
        $onboard_users = $sqlite_user->getOnboardUsers();
        //check if they checked their temperature
        $temperature = new Temperature();
        $AMPM = date('A');
        foreach ($onboard_users as $idx => $user) {
            $user_id = $user['id'];
            $record = $temperature->getAMPMTemperatures($user_id, $AMPM);
            // only no record should be notified
            if (empty($record)) {
                $this->sendTemperatureMessage($user);
            }
        }
    }

    private function sendTemperatureMessage($user) {
        
        $to_id = trim($user['id']);
        $to_name = $user['name'];
        $AMPM = date('A');
        $host_ip = getLocalhostIP();
        $msg = new Message();
        $url = "http://${host_ip}/temperature.html?id=${to_id}";
        $content = "$to_name 您好\r\n\r\n系統偵測您於今日 $AMPM 尚未登記體溫！\r\n\r\n請用 CHROME 瀏覽器前往 ${url} 進行登記。";
        $title = "體溫登記通知";
        $sn = $msg->sysSend($title, $content, $to_id, 840); // 14 mins == 840 secs
        if ($sn == -1) {
            Logger::getInstance()->warning("${title} 訊息無法送出給 ${to_id}。($to_name, $sn)");
        } else {
            Logger::getInstance()->info("${title} 訊息(${sn})已送出給 ${to_id}。($to_name)");
        }
    }

    private function compressLog() {
        if (php_sapi_name() != "cli") {
            
            $cache = Cache::getInstance();
            // compress all log when zipLogs_flag is expired
            if ($cache->isExpired('zipLogs_flag')) {
                Logger::getInstance()->info("開始壓縮LOG檔！");
                zipLogs();
                Logger::getInstance()->info("壓縮LOG檔結束！");
                // cache the flag for a week
                $cache->set('zipLogs_flag', true, 604800);
            }
        }
    }

    private function findProblematicSURCases() {
        
        if ($this->isOn($this->schedule["once_a_day"])) {
            // 找已結案但卻又延期複丈之案件
            $q = new Query();
            $results = $q->getSurProblematicCases();
            if (count($results) > 0) {
                $this->sendProblematicSURCasesMessage($results);
            } else {
                Logger::getInstance()->info(__METHOD__.": 無已結案卻延期複丈之測量案件。");
            }
        }
    }

    private function sendProblematicSURCasesMessage(&$results) {
        
        $host_ip = getLocalhostIP();
        $cache = Cache::getInstance();
        $users = $cache->getUserNames();
        $msg = new Message();

        $case_ids = array();
        $msg_prefix = $msg_content = "系統目前找到下列已結案之測量案件但是狀態卻是「延期複丈」:\r\n\r\n";
        foreach ($results as $result) {
            $case_id = $result['MM01'].'-'.$result['MM02'].'-'.$result['MM03'];
            $case_ids[] = $case_id;

            // notify corresponding operator as well
            $to_id = trim($result['MD04']); // 測量員ID
            $this_user = $users[$to_id];
            if (!empty($this_user)) {
                $title = "有問題的延期複丈案件(${this_user})通知";
                $msg_content = $msg_prefix.$case_id."\r\n\r\n請確認該案件狀態以免案件逾期。\r\n如有需要請填寫「電腦問題處理單」交由資訊課協助修正。";
                $sn = $msg->sysSend($title, $msg_content, $to_id, 85500);   // 85500 = 86400 - 15 * 60 (one day - 15 mins)
                if ($sn == -1) {
                    Logger::getInstance()->warning("「${title}」訊息無法送出給 ${to_id} 。 (".$this_user.", $sn)");
                } else {
                    Logger::getInstance()->info("「${title}」訊息(${sn})已送出給 ${to_id} 。 (".$this_user.")");
                }
            }
        }

        $system = System::getInstance();
        $adm_ips = $system->getRoleAdminIps();
        $content = "系統目前找到下列已結案之測量案件但是狀態卻是「延期複丈」:\r\n\r\n".implode("\r\n", $case_ids)."\r\n\r\n請前往 http://$host_ip/dashboard.html 執行複丈案件查詢功能並修正。";
        foreach ($adm_ips as $adm_ip) {
            if ($adm_ip == '::1') {
                continue;
            }
            $sn = $msg->send('複丈問題案件通知', $content, $adm_ip, 840);   // 840 secs => +14 mins
            Logger::getInstance()->info("訊息已送出(${sn})給 ${adm_ip}");
        }

        $this->stats->addBadSurCaseStats(array(
            "date" => date("Y-m-d H:i:s"),
            "found" => count($case_ids),
            "note" => $content
        ));
    }

    private function importUserFromL3HWEB() {
        
        if ($this->isOn($this->schedule["once_a_day"])) {
            Logger::getInstance()->info(__METHOD__.': 匯入L3HWEB使用者資料排程啟動。');
            $sysauth1 = new SQLiteSYSAUTH1();
            $sysauth1->importFromL3HWEBDB();
            return true;
        }

        return false;
    }

    private function importRKEYN() {
        if ($this->isOn($this->schedule["once_a_day"])) {
            Logger::getInstance()->info(__METHOD__.': 匯入RKEYN代碼檔排程啟動。');
            $sqlite_sr = new SQLiteRKEYN();
            $sqlite_sr->importFromOraDB();
            return true;
        }
        return false;
    }

    private function importRKEYNALL() {
        if ($this->isOn($this->schedule["once_a_day"])) {
            Logger::getInstance()->info(__METHOD__.': 匯入RKEYN_ALL代碼檔排程啟動。');
            $sqlite_sra = new SQLiteRKEYNALL();
            $sqlite_sra->importFromOraDB();
            return true;
        }
        return false;
    }

    function __construct() { $this->stats = new StatsSQLite(); }
    function __destruct() { $this->stats = null; }

    public function do() {
        if ($this->isOfficeHours()) {
            $this->checkCrossSiteData();
            // $this->findDelayRegCases();
            // $this->findProblematicSURCases();
            $this->compressLog();
            // clean AP stats data one day ago
            $this->stats->wipeAllAPConnHistory();
            $this->stats->checkRegisteredConnectivity();
            // clean connectivity stats data one day ago
            $this->stats->wipeConnectivityHistory();
            // $this->notifyTemperatureRegistration();
            /**
             * 匯入WEB DB固定資料
             */
            $this->importRKEYN();
            $this->importRKEYNALL();
            $this->importUserFromL3HWEB();
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
