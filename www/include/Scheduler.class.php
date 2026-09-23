<?php

// 載入系統初始化檔案 (包含 Autoloader)
require_once(dirname(dirname(__FILE__)) . DIRECTORY_SEPARATOR . 'include' . DIRECTORY_SEPARATOR . 'init.php');

/**
 * Class Scheduler
 * 系統排程器
 * * 負責執行系統各類週期性任務，包括資料同步、日誌維護、系統監控與報表檢查。
 * 使用檔案 (Ticks) 來記錄上次執行時間，避免重複執行。
 */
class Scheduler
{
    /** @var string 暫存目錄路徑 */
    private $tmp;

    /** @var array 紀錄各排程上次執行時間的檔案路徑對映表 */
    private $tickets;

    /** @var array 定義特定任務的執行時段 (如上班時間) */
    private $schedule = array(
        "office" => [
            'Sun' => [],
            'Mon' => ['07:30 AM' => '05:30 PM'],
            'Tue' => ['07:30 AM' => '05:30 PM'],
            'Wed' => ['07:30 AM' => '05:30 PM'],
            'Thu' => ['07:30 AM' => '05:30 PM'],
            'Fri' => ['07:30 AM' => '05:30 PM'],
            'Sat' => ['07:30 AM' => '05:30 PM']
        ],
        "office_check" => [
            'Sun' => [],
            'Mon' => ['08:00 AM' => '05:00 PM'],
            'Tue' => ['08:00 AM' => '05:00 PM'],
            'Wed' => ['08:00 AM' => '05:00 PM'],
            'Thu' => ['08:00 AM' => '05:00 PM'],
            'Fri' => ['08:00 AM' => '05:00 PM'],
            'Sat' => ['08:00 AM' => '05:00 PM']
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

    /**
     * 建構子：初始化暫存路徑與 Ticket 檔案位置
     */
    public function __construct()
    {
        $this->tmp = sys_get_temp_dir();
        // 定義各時間粒度的 Ticket 檔案，用於記錄「下一次可執行的時間戳記」
        $this->tickets = array(
            '5m'  => $this->tmp . DIRECTORY_SEPARATOR . 'LAH-5mins.ts',
            '10m' => $this->tmp . DIRECTORY_SEPARATOR . 'LAH-10mins.ts',
            '15m' => $this->tmp . DIRECTORY_SEPARATOR . 'LAH-15mins.ts',
            '30m' => $this->tmp . DIRECTORY_SEPARATOR . 'LAH-30mins.ts',
            '1h'  => $this->tmp . DIRECTORY_SEPARATOR . 'LAH-1hour.ts',
            '2h'  => $this->tmp . DIRECTORY_SEPARATOR . 'LAH-2hours.ts',
            '4h'  => $this->tmp . DIRECTORY_SEPARATOR . 'LAH-4hours.ts',
            '8h'  => $this->tmp . DIRECTORY_SEPARATOR . 'LAH-8hours.ts',
            '12h' => $this->tmp . DIRECTORY_SEPARATOR . 'LAH-12hours.ts',
            '24h' => $this->tmp . DIRECTORY_SEPARATOR . 'LAH-24hours.ts',
            'office_check' => $this->tmp . DIRECTORY_SEPARATOR . 'LAH-office-check.ts'
        );
    }

    public function __destruct() {}

    /**
     * 主要執行入口
     * 依序呼叫各時間粒度的排程檢查
     */
    public function do()
    {
        Logger::getInstance()->info(__METHOD__ . ": Scheduler 開始執行。");
        
        // 依照時間長度由大到小檢查
        $this->doOneDayJobs();
        $this->doHalfDayJobs();
        $this->do8HoursJobs();
        $this->do4HoursJobs();
        $this->do2HoursJobs(); // 檢查 2 小時排程
        $this->do1HourJobs();
        $this->do30minsJobs();
        $this->do15minsJobs();
        $this->do10minsJobs();
        $this->do5minsJobs();
        
        Logger::getInstance()->info(__METHOD__ . ": Scheduler 執行完成。");
    }

    // =========================================================================
    //  排程週期檢查方法 (Public Schedule Methods)
    // =========================================================================

    /**
     * 執行 5 分鐘週期任務
     * - 檢查全國地所連線狀態 (僅上班時間)
     */
    public function do5minsJobs(): bool
    {
        return $this->executeJob('5m', '+5 mins', function() {
            if ($this->isOn($this->schedule["office_check"])) {
                $this->addOfficeCheckStatus();
            }
        });
    }

    /**
     * 執行 10 分鐘週期任務
     */
    public function do10minsJobs(): bool
    {
        return $this->executeJob('10m', '+10 mins', function() {
            $this->fetchMonitorMail();
            $this->findXCaseFailures();
        });
    }

    /**
     * 執行 15 分鐘週期任務
     */
    public function do15minsJobs(): bool
    {
        return $this->executeJob('15m', '+15 mins', function() {
            $conn = new SQLiteConnectivity();
            $conn->check();
        });
    }

    /**
     * 執行 30 分鐘週期任務
     */
    public function do30minsJobs(): bool
    {
        return $this->executeJob('30m', '+30 mins', function() {
            // 目前無任務
        });
    }

    /**
     * 執行 1 小時週期任務
     */
    public function do1HourJobs(): bool
    {
        return $this->executeJob('1h', '+60 mins', function() {
            // 目前無任務
        });
    }

    /**
     * 執行 2 小時週期任務
     */
    public function do2HoursJobs(): bool
    {
        return $this->executeJob('2h', '+120 mins', function() {
            // 預留給中週期任務
        });
    }

    /**
     * 執行 4 小時週期任務
     */
    public function do4HoursJobs(): bool
    {
        return $this->executeJob('4h', '+240 mins', function() {
            // 目前無任務
        });
    }

    /**
     * 執行 8 小時週期任務
     */
    public function do8HoursJobs(): bool
    {
        return $this->executeJob('8h', '+480 mins', function() {
            // 目前無任務
        });
    }

    /**
     * 執行 12 小時週期任務
     */
    public function doHalfDayJobs(): bool
    {
        return $this->executeJob('12h', '+720 mins', function() {
            // 目前無任務
        });
    }

    /**
     * 執行 24 小時週期任務 (每日維護)
     */
    public function doOneDayJobs(): bool
    {
        return $this->executeJob('24h', '+1440 mins', function() {
            // 1. 日誌與歷史資料清理
            $this->compressLog();
            SQLiteAPConnectionHistory::cleanOneDayAgoAll();
            
            $conn = new SQLiteConnectivity();
            $conn->wipeHistory(1);
            
            $this->wipeOutdatedIPEntries();
            $this->wipeOutdatedMonitorMail();
            $this->removeOutdatedLog();
            
            // 2. 清除快取資料庫
            $this->removePrefetchDB();
            $this->removeAPConnectionHistoryDB();
            
            // 3. 匯入/同步外部資料
            $this->importRKEYN();
            $this->importRKEYNALL();
            $this->importOFFICES();
            $this->importUserFromL3HWEB();
            $this->syncAdUsersToLocalDB(); 
            $this->syncUserIPs();          // 同步使用者動態 IP (86400s = 1day)
        });
    }

    // =========================================================================
    //  輔助方法 (Private Helper Methods)
    // =========================================================================

    private function executeJob($ticketKey, $nextTimeInterval, callable $callback): bool
    {
        try {
            $ticketFile = $this->tickets[$ticketKey];
            $ticketTs = @file_get_contents($ticketFile);

            if ($ticketTs <= time()) {
                Logger::getInstance()->info(__CLASS__ . "::do{$ticketKey}Jobs: 開始執行排程。");
                file_put_contents($ticketFile, strtotime($nextTimeInterval, time()));
                $callback();
                return true;
            }
        } catch (Exception $e) {
            Logger::getInstance()->warning(__CLASS__ . "::do{$ticketKey}Jobs: 執行失敗。");
            Logger::getInstance()->warning("錯誤訊息: " . $e->getMessage());
        }
        return false;
    }

    private function isOn(array $schedule): bool
    {
        $timestamp = time();
        $currentTime = (new DateTime())->setTimestamp($timestamp);
        $dayOfWeek = date('D', $timestamp);

        if (!isset($schedule[$dayOfWeek])) {
            return false;
        }

        foreach ($schedule[$dayOfWeek] as $startTime => $endTime) {
            $st = DateTime::createFromFormat('h:i A', $startTime);
            $ed = DateTime::createFromFormat('h:i A', $endTime);
            if (($st < $currentTime) && ($currentTime < $ed)) {
                return true;
            }
        }
        return false;
    }

    // =========================================================================
    //  具體任務實作 - 資料匯入與同步 (Data Import & Sync Tasks)
    // =========================================================================

    private function importUserFromL3HWEB()
    {
        Logger::getInstance()->info(__METHOD__ . ': 匯入L3HWEB使用者資料排程啟動。');
        $sysauth1 = new SQLiteSYSAUTH1();
        $sysauth1->importFromL3HWEBDB();
    }

    /**
     * 從 AD (Active Directory) 同步使用者至本地 SQLite
     * - 會呼叫 AdService 取得最新清單
     * - 若本地無資料則新增，有資料則更新
     * - 若 AD 有效名單無此人但本地在職，則設為離職
     * - [新功能] 同步完成後將報告傳送至 inf 頻道並清除舊訊息
     */
    private function syncAdUsersToLocalDB()
    {
        Logger::getInstance()->info(__METHOD__ . ': 同步 AD 使用者至 SQLite 排程啟動。');
        $sqlite_user = new SQLiteUser();
        // syncAdUsers() 不帶參數時，內部會自動 new AdService() 去抓取資料
        $stats = $sqlite_user->syncAdUsers();
        
        if ($stats !== false) {
            $msg = sprintf(
                "同步完成。新增: %d, 更新: %d, 跳過: %d, 失敗: %d, 離職: %d",
                $stats['added'], $stats['updated'], $stats['skipped'], $stats['failed'], $stats['offboarded']
            );
            Logger::getInstance()->info(__METHOD__ . ": $msg");

            // 準備發送報告訊息
            $title = "智慧控管系統 AD 使用者同步報告";
            $report = "智慧控管系統 👤 AD 使用者同步完成報告：\n***\n";
            $report .= "- **新增人員**: " . $stats['added'] . "\n";
            $report .= "- **姓名更新**: " . $stats['updated'] . "\n";
            $report .= "- **設為離職**: " . $stats['offboarded'] . "\n";
            $report .= "- **跳過(無異動)**: " . $stats['skipped'] . "\n";
            $report .= "- **處理失敗**: " . $stats['failed'] . "\n";
            $report .= "\n***\n同步時間: " . date('Y-m-d H:i:s');

            // 清除舊報告並發送新報告
            $concernedChannel = 'inf';
            $this->removeNotificationByTitle($title, $concernedChannel);
            $this->addNotification($report, $concernedChannel, $title);

        } else {
            Logger::getInstance()->error(__METHOD__ . ": 同步 AD 使用者失敗。");
        }
    }

    /**
     * 同步使用者動態 IP 資料
     * - 利用 IPResolver 記錄的動態 IP 來更新本地使用者資料
     * - 處理自動更新
     * - 若發生衝突，直接發送通知給受影響之使用者
     */
    private function syncUserIPs()
    {
        Logger::getInstance()->info(__METHOD__ . ': 啟動使用者動態 IP 同步排程。');
        $sqlite_user = new SQLiteUser();
        // 執行 24 小時內 (86400s) 的動態 IP 同步分析
        $result = $sqlite_user->syncUserDynamicIP(86400);

        $auto_count = count($result['auto_updated'] ?? []);
        $conflict_count = count($result['conflicts'] ?? []);

        Logger::getInstance()->info(__METHOD__ . ": 同步作業結束。自動更新: $auto_count 人，偵測到衝突: $conflict_count 人。");

        // 若有衝突，逐一發送系統通知給該使用者
        // if ($conflict_count > 0) {
        //     foreach ($result['conflicts'] as $conflict) {
        //         $uid = $conflict['id'];
        //         $uname = $conflict['name'];
        //         $currentIp = $conflict['currentIp'];

        //         $message = "🛰️ 智慧監控系統偵測到您有多個 IP 紀錄存在：\n***\n";
        //         $message .= "您好 **{$uname}**，系統偵測到您的電腦目前使用的 IP 與主機紀錄 [{$currentIp}] 不符，且發現多個可能的候選 IP，無法自動完成同步。\n\n";
        //         $message .= "***\n⚠ 請聯繫資訊人員至「員工管理」頁面進行手動確認與更新。";

        //         $title = "您的 IP 同步衝突提醒";
        //         // 移除 $uid 頻道的舊衝突訊息
        //         $this->removeNotificationByTitle($title, $uid);
        //         // 發送個人化通知給該使用者
        //         $this->addNotification($message, $uid, $title);
                
        //         Logger::getInstance()->info(__METHOD__ . ": 已對使用者 {$uid} ({$uname}) 發送衝突提醒。");
        //     }
        // }
    }

    private function importRKEYN()
    {
        Logger::getInstance()->info(__METHOD__ . ': 匯入RKEYN代碼檔排程啟動。');
        $sqlite_sr = new SQLiteRKEYN();
        $sqlite_sr->importFromOraDB();
    }

    private function importRKEYNALL()
    {
        Logger::getInstance()->info(__METHOD__ . ': 匯入RKEYN_ALL代碼檔排程啟動。');
        $sqlite_sra = new SQLiteRKEYNALL();
        $sqlite_sra->importFromOraDB();
    }

    private function importOFFICES()
    {
        Logger::getInstance()->info(__METHOD__ . ': 匯入LANDIP資料排程啟動。');
        $sqlite_so = new SQLiteOFFICES();
        $sqlite_so->importFromOraDB();
    }

    // =========================================================================
    //  具體任務實作 - 系統維護與清理 (Maintenance Tasks)
    // =========================================================================

    private function compressLog()
    {
        $cache = Cache::getInstance();
        if ($cache->isExpired('zipLogs_flag')) {
            Logger::getInstance()->info(__METHOD__ . ": 開始壓縮LOG檔！");
            zipLogs();
            Logger::getInstance()->info(__METHOD__ . ": 壓縮LOG檔結束！");
            $cache->set('zipLogs_flag', true, 604800);
        }
    }

    private function removeOutdatedLog()
    {
        Logger::getInstance()->info(__METHOD__ . ": 啟動刪除過時記錄檔排程。");
        Logger::getInstance()->removeOutdatedLog();
    }

    private function wipeOutdatedIPEntries()
    {
        Logger::getInstance()->info(__METHOD__ . ": 啟動清除過時 dynamic ip 資料排程。");
        $ipr = new IPResolver();
        $ipr->removeDynamicIPEntries(604800);
    }

    private function removePrefetchDB()
    {
        Logger::getInstance()->info(__METHOD__ . ": 啟動刪除 Prefetch Cache DB 排程。");
        return Prefetch::removeDBFile();
    }

    private function removeAPConnectionHistoryDB()
    {
        Logger::getInstance()->info(__METHOD__ . ": 啟動刪除AP連線歷史紀錄DB排程。");
        return SQLiteAPConnectionHistory::removeDBFiles();
    }

    private function wipeOutdatedMonitorMail()
    {
        $monitor = new SQLiteMonitorMail();
        $days = 30;
        $month_secs = $days * 24 * 60 * 60;
        Logger::getInstance()->info("啟動清除本地端過時監控郵件排程。(${days}天)");
        $monitor->removeOutdatedMail($month_secs);
        
        $imapServer = new MonitorMail();
        $imapServer->removeOutdatedMails();
    }

    private function analyzeTables() {}

    // =========================================================================
    //  具體任務實作 - 監控與檢測 (Monitoring & Check Tasks)
    // =========================================================================

    public function addOfficeCheckStatus()
    {
        try {
            $ticketTs = @file_get_contents($this->tickets['office_check']);
            $now = time();
            if (empty($ticketTs) || ($now - $ticketTs) > 900) {
                @unlink(DB_DIR . DIRECTORY_SEPARATOR . "OFFICES_STATS.db-journal");
                file_put_contents($this->tickets['office_check'], $now);
                
                $xap_ip = System::getInstance()->getWebAPIp();
                $sqlite_so = new SQLiteOFFICES();
                $sqlite_sos = new SQLiteOFFICESSTATS();
                $sites = $sqlite_so->getAll();
                $count = 0;

                $sqlite_sos->cleanNormalRecords();
                foreach ($sites as $site) {
                    if ($site['ID'] === 'CB' || $site['ID'] === 'CC') continue;
                    $url = "http://$xap_ip/Land" . strtoupper($site['ID']) . "/";
                    $headers = httpHeader($url);
                    $response = trim($headers[0] ?? '');
                    $state = ($response === 'HTTP/1.1 401 Unauthorized') ? 'UP' : 'DOWN';
                    $sqlite_sos->replace(array(
                        'id' => $site['ID'], 'name' => $site['NAME'], 'state' => $state,
                        'response' => $response, 'timestamp' => time(),
                    ));
                    $count++;
                }
                Logger::getInstance()->info(__METHOD__ . ": 全國地所連線測試完成 ($count 所)。");
            }
        } catch (Exception $e) {
            Logger::getInstance()->warning(__METHOD__ . ": 執行失敗: " . $e->getMessage());
        } finally {
            file_put_contents($this->tickets['office_check'], 0);
        }
    }

    private function fetchMonitorMail()
    {
        $monitor = new SQLiteMonitorMail();
        $monitor->fetchFromMailServer();
    }

    private function findXCaseFailures()
    {
        if (!$this->isOn($this->schedule["office_check"])) {
            Logger::getInstance()->info(__METHOD__ . ": 跨所案件檢測僅於上班時間執行，跳過本次排程。");
            return;
        }
        $xcase = new XCase();
        $info = $xcase->findFailureXCases();
        $found = [];
        foreach ($info as $codeArray) {
            $found = array_values(array_unique(array_merge($found, $codeArray['foundIds'])));
        }
        $this->sendFindXCaseFailuresNotification($found);
    }

    private function sendFindXCaseFailuresNotification($found)
    {
        if (empty($found)) return;
        $message = "⚠ 智慧監控系統已找到跨所案件未回寫問題(" . count($found) . "件)：\n***\n";
        $message .= "| 　 | 　 |\n| :--- | :--- |\n";
        $chunks = array_chunk($found, 2);
        foreach ($chunks as $chunk) {
            $col1 = getMDCaseLink($chunk[0] ?? '');
            $col2 = getMDCaseLink($chunk[1] ?? '');
            $message .= "| $col1 | $col2 |\n";
        }
        $message .= "\n***\n⚠ 請至管理面板進行同步修正。";

        $sqlite_user = new SQLiteUser();
        $admins = $sqlite_user->getAdmins();
        global $today;
        $title = "$today 跨所案件同步檢測";
        foreach ($admins as $admin) {
            $this->addNotification($message, $admin['id'], $title);
        }
        $this->removeNotificationByTitle($title, 'inf');
        $this->addNotification($message, 'inf', $title);
    }

    /**
     * 根據標題移除通知訊息
     * @param string $title 訊息標題
     * @param string $to_id 接收者 ID 或群組
     * @return bool
     */
    private function removeNotificationByTitle($title, $to_id)
    {
        if (empty($to_id)) return false;
        $notify = new Notification();
        $removed = $notify->removeOutdatedMessageByTitle($to_id, $title);
        if ($removed) Logger::getInstance()->info("\"$title\" 訊息已從 $to_id 刪除");
        return $removed;
    }

    /**
     * 發送系統通知的輔助方法
     * @param string $message 訊息內容
     * @param string $to_id 接收者 ID 或群組
     * @param string $title 標題
     * @return string|false 成功回傳 message ID
     */
    private function addNotification($message, $to_id, $title = '系統排程訊息')
    {
        if (empty($to_id)) return false;
        $notify = new Notification();
        $payload = array(
            'title' => $title, 
            'content' => trim($message), 
            'priority' => 3,
            'expire_datetime' => '', 
            'sender' => '系統排程', 
            'from_ip' => getLocalhostIP()
        );
        $lastId = $notify->addMessage($to_id, $payload);
        if ($lastId) Logger::getInstance()->info("訊息($lastId)已送出給 $to_id");
        return $lastId;
    }
}
