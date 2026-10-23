<?php

// 載入系統初始化檔案 (包含 Autoloader)
require_once(dirname(dirname(__FILE__)) . DIRECTORY_SEPARATOR . 'include' . DIRECTORY_SEPARATOR . 'init.php');
require_once(dirname(__FILE__) . DIRECTORY_SEPARATOR . 'MOICAS.class.php');
require_once(dirname(__FILE__) . DIRECTORY_SEPARATOR . 'RegSmsPolicy.class.php');

/**
 * Class Scheduler
 *
 * 【固定時間週期排程與維護器 (Scheduler)】
 *
 * 核心定位：
 * - 專注於「固定時間排程」的系統週期維護與批次作業 (Fixed-interval Maintenance & Batch Sync)。
 * - 24 小時全天候定時運作，不受限於上下班時間，專門處理大資料量同步、歷史清理與系統保養。
 *
 * 主要職責：
 * 1. 週期排程執行：基於檔案票券 (Tickets, 如 5m, 10m, 15m, 1h, 24h) 精確控制每次執行的間隔時間。
 * 2. 系統維護清理：壓縮舊 Log 檔案、刪除過時日誌、清理 AP 連線歷史紀錄、清除動態 IP 快取。
 * 3. 跨系統大資料同步：AD 帳號同步 (LDAP/AD)、Oracle 代碼檔匯入 (RKEYN/LANDIP/L3HWEB)。
 * 4. 每日離線比對：住址隱匿與地籍異動即時通簡訊發送紀錄掃描 (checkRegSmsDeliveryStatus)。
 * 5. 健全容錯與通報：每個任務均受 safeRun 隔離保護，若有失敗自動彙整發送警報至 inf 頻道。
 *
 * 與 Watchdog 的職責區隔：
 * - Scheduler：專注全天候固定週期的「系統背景維護 + 批次資料同步與比對」。
 * - Watchdog：專注上班時間的「即時業務異常監控 + 主動發送訊息通知」。
 *
 * 使用範例 (Usage Examples):
 *
 * 1. 外部排程常規觸發 (由 Linux Crontab 或 Windows Task Scheduler 每 5 分鐘呼叫):
 *    POST http://localhost/api/schedule_json_api.php
 *    Payload: type=regular
 *
 * 2. 手動觸發特定週期 (如手動執行每日維護):
 *    POST http://localhost/api/schedule_json_api.php
 *    Payload: type=24h
 *
 * 3. 程式碼內部手動調用:
 *    ```php
 *    $scheduler = new Scheduler();
 *    // 自動檢查所有週期的 Ticket 並依序執行到期任務
 *    $scheduler->do();
 *
 *    // 或直接強制執行特定週期
 *    $scheduler->doOneDayJobs();
 *    ```
 *
 * 4. 擴充每日維護任務 (在 doOneDayJobs() 的 $tasks 陣列中加入):
 *    ```php
 *    $tasks = [
 *        // 既有維護任務...
 *        '日誌檔壓縮 (compressLog)' => function() { $this->compressLog(); },
 *        // 新增每日批次維護任務
 *        '每日資料庫備份與優化'     => function() { $this->backupAndOptimizeDatabase(); }
 *    ];
 *    ```
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
        $jobs = [
            '24h' => function() { $this->doOneDayJobs(); },
            '12h' => function() { $this->doHalfDayJobs(); },
            '8h'  => function() { $this->do8HoursJobs(); },
            '4h'  => function() { $this->do4HoursJobs(); },
            '2h'  => function() { $this->do2HoursJobs(); },
            '1h'  => function() { $this->do1HourJobs(); },
            '30m' => function() { $this->do30minsJobs(); },
            '15m' => function() { $this->do15minsJobs(); },
            '10m' => function() { $this->do10minsJobs(); },
            '5m'  => function() { $this->do5minsJobs(); },
        ];

        foreach ($jobs as $jobName => $jobCallable) {
            try {
                $jobCallable();
            } catch (Throwable $e) {
                Logger::getInstance()->error(__METHOD__ . ": 週期 {$jobName} 排程執行發生未捕獲錯誤: " . $e->getMessage() . "\n" . $e->getTraceAsString());
            }
        }
        
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
            $this->safeRun('接收監控郵件 (fetchMonitorMail)', function() { $this->fetchMonitorMail(); });
            $this->safeRun('跨所案件檢測 (findXCaseFailures)', function() { $this->findXCaseFailures(); });
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
            $startTime = microtime(true);
            $failedTasks = [];
            $successCount = 0;

            // 每日長時間任務清單 (1. 日誌清理 2. 快取刪除 3. 資料匯入與同步)
            $tasks = [
                '日誌檔壓縮 (compressLog)' => function() { $this->compressLog(); },
                '清理AP連線歷史 (SQLiteAPConnectionHistory::cleanOneDayAgoAll)' => function() { SQLiteAPConnectionHistory::cleanOneDayAgoAll(); },
                '清除全國連線紀錄歷史 (SQLiteConnectivity::wipeHistory)' => function() {
                    $conn = new SQLiteConnectivity();
                    $conn->wipeHistory(1);
                },
                '清除過時動態IP (wipeOutdatedIPEntries)' => function() { $this->wipeOutdatedIPEntries(); },
                '清除過時監控郵件 (wipeOutdatedMonitorMail)' => function() { $this->wipeOutdatedMonitorMail(); },
                '刪除過時記錄檔 (removeOutdatedLog)' => function() { $this->removeOutdatedLog(); },
                '刪除Prefetch快取資料庫 (removePrefetchDB)' => function() { $this->removePrefetchDB(); },
                '刪除AP連線歷史紀錄DB (removeAPConnectionHistoryDB)' => function() { $this->removeAPConnectionHistoryDB(); },
                '匯入RKEYN代碼檔 (importRKEYN)' => function() { $this->importRKEYN(); },
                '匯入RKEYN_ALL代碼檔 (importRKEYNALL)' => function() { $this->importRKEYNALL(); },
                '匯入LANDIP資料 (importOFFICES)' => function() { $this->importOFFICES(); },
                '匯入L3HWEB使用者資料 (importUserFromL3HWEB)' => function() { $this->importUserFromL3HWEB(); },
                '同步AD使用者至本地DB (syncAdUsersToLocalDB)' => function() { $this->syncAdUsersToLocalDB(); },
                '同步使用者動態IP (syncUserIPs)' => function() { $this->syncUserIPs(); },
                '住址隱匿與異動即時通簡訊發送紀錄檢查 (checkRegSmsDeliveryStatus)' => function() { $this->checkRegSmsDeliveryStatus(); },
            ];

            foreach ($tasks as $taskName => $taskCallable) {
                if ($this->safeRun($taskName, $taskCallable, $failedTasks)) {
                    $successCount++;
                }
            }

            $failCount = count($failedTasks);
            $totalElapsed = round(microtime(true) - $startTime, 4);

            $summaryMsg = sprintf(
                __CLASS__ . "::doOneDayJobs 執行完成。總計: %d, 成功: %d, 失敗: %d, 總耗時: %ss",
                count($tasks),
                $successCount,
                $failCount,
                $totalElapsed
            );

            if ($failCount > 0) {
                Logger::getInstance()->warning($summaryMsg);

                // 發送推播通知至 inf 頻道
                $title = "每日維護排程異常提醒";
                $report = "⚠ 智慧監控系統每日維護排程 (doOneDayJobs) 執行部分失敗：\n***\n";
                $report .= "- **總任務數**: " . count($tasks) . "\n";
                $report .= "- **成功**: {$successCount}\n";
                $report .= "- **失敗**: {$failCount}\n\n";
                $report .= "**失敗任務清單：**\n";
                foreach ($failedTasks as $f) {
                    $report .= "- ❌ **{$f['name']}** ({$f['elapsed']}s): {$f['message']}\n";
                }
                $report .= "\n***\n⚠ 請至主機查看排程記錄檔以取得完整 Stack Trace。\n執行時間: " . date('Y-m-d H:i:s');

                $concernedChannel = 'inf';
                $this->removeNotificationByTitle($title, $concernedChannel);
                $this->addNotification($report, $concernedChannel, $title);
            } else {
                Logger::getInstance()->info($summaryMsg);
            }
        });
    }

    // =========================================================================
    //  輔助方法 (Private Helper Methods)
    // =========================================================================

    /**
     * 安全執行單一子任務，發生例外或錯誤時詳細記錄 LOG，不影響後續任務執行
     *
     * @param string $taskName 任務名稱/描述
     * @param callable $callback 執行的閉包函式
     * @param array &$failedTasks 收集失敗任務資訊以供後續統計與推播
     * @return bool 成功回傳 true，失敗回傳 false
     */
    private function safeRun(string $taskName, callable $callback, array &$failedTasks = []): bool
    {
        $startTime = microtime(true);
        try {
            $callback();
            return true;
        } catch (Throwable $t) {
            $elapsed = round(microtime(true) - $startTime, 4);
            $errClass = get_class($t);
            $errCode = $t->getCode();
            $errMsg = $t->getMessage();
            $errFile = $t->getFile();
            $errLine = $t->getLine();
            $errTrace = $t->getTraceAsString();

            $logMessage = sprintf(
                "[%s] 執行失敗 (耗時: %ss) - %s (Code: %s): %s 在 %s:%d\nStack Trace:\n%s",
                $taskName,
                $elapsed,
                $errClass,
                $errCode,
                $errMsg,
                $errFile,
                $errLine,
                $errTrace
            );
            Logger::getInstance()->error(__CLASS__ . "::safeRun: " . $logMessage);

            $failedTasks[] = [
                'name' => $taskName,
                'class' => $errClass,
                'message' => $errMsg,
                'file' => $errFile,
                'line' => $errLine,
                'elapsed' => $elapsed
            ];
            return false;
        }
    }

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
        } catch (Throwable $e) {
            Logger::getInstance()->error(__CLASS__ . "::do{$ticketKey}Jobs: 執行失敗。");
            Logger::getInstance()->error("錯誤訊息: " . $e->getMessage() . "\nStack Trace:\n" . $e->getTraceAsString());
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
        Logger::getInstance()->info("啟動清除本地端過時監控郵件排程。({$days}天)");
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
     * 每日掃描住址隱匿與地籍異動即時通案件簡訊發送紀錄，並於未發送時發送即時通提醒
     * 1. 取得有填寫手機且簡訊狀態未發送/失敗 (sms_status NOT IN (1, 3)) 的資料
     * 2. 依最早已收件日批次查詢 Oracle 簡訊紀錄比對，命中成功者更新 sms_status = 1
     * 3. 持續 3 天以上無發送紀錄者，依 receiver 分組彙整提醒承辦人員 (若無 receiver 則發至 reg 頻道)
     * 4. 超過 7 天無發送紀錄者，發送警示通知至 reg (登記課) 頻道
     */
    public function checkRegSmsDeliveryStatus()
    {
        Logger::getInstance()->info(__METHOD__ . ": 開始執行住址隱匿與地籍異動即時通簡訊發送紀錄掃描...");

        $undisclosedDB = new SQLiteRegAddressUndisclosed();
        $propertyAlertDB = new SQLiteRegPropertyAlert();

        $undisclosedPending = $undisclosedDB->getPendingSmsRecords() ?: [];
        $propertyAlertPending = $propertyAlertDB->getPendingSmsRecords() ?: [];

        $allPending = [];
        foreach ($undisclosedPending as $row) {
            $row['biz_type'] = 'undisclosed';
            $row['biz_title'] = '住址隱匿';
            $allPending[] = $row;
        }
        foreach ($propertyAlertPending as $row) {
            $row['biz_type'] = 'property_alert';
            $row['biz_title'] = '地籍異動即時通';
            $allPending[] = $row;
        }

        $totalPendingCount = count($allPending);
        if ($totalPendingCount === 0) {
            Logger::getInstance()->info(__METHOD__ . ": 無待比對之簡訊收件紀錄。");
            return;
        }

        Logger::getInstance()->info(__METHOD__ . ": 共有 {$totalPendingCount} 筆待比對之案件 (住址隱匿: " . count($undisclosedPending) . ", 異動即時通: " . count($propertyAlertPending) . ")。");

        // 計算最早收件日期
        $minCreatetime = time();
        foreach ($allPending as $item) {
            if (!empty($item['createtime']) && $item['createtime'] < $minCreatetime) {
                $minCreatetime = (int)$item['createtime'];
            }
        }

        // 轉為民國年月日字串 (YYYMMDD)
        $rocYearStart = (int)date('Y', $minCreatetime) - 1911;
        $st = sprintf("%03d%s", $rocYearStart, date('md', $minCreatetime));

        $rocYearEnd = (int)date('Y') - 1911;
        $ed = sprintf("%03d%s", $rocYearEnd, date('md'));

        Logger::getInstance()->info(__METHOD__ . ": 檢索 Oracle 簡訊紀錄區間 {$st} ~ {$ed}...");

        $moisms = new MOISMS();
        $moiadm_rows = $moisms->getMOIADMSMSLogRecordsByDate($st, $ed) ?: [];
        $ma05_rows   = $moisms->getMOICASSMS_MA05RecordsByDate($st, $ed) ?: [];
        $ma04_rows   = $moisms->getMOICASSMS_MA04RecordsByDate($st, $ed) ?: [];
        $sms98_rows  = $moisms->getSMS98LOG_SMSRecordsByDate($st, $ed) ?: [];

        $allSmsLogs = array_merge($moiadm_rows, $ma05_rows, $ma04_rows, $sms98_rows);
        Logger::getInstance()->info(__METHOD__ . ": 自 Oracle 取得簡訊紀錄共 " . count($allSmsLogs) . " 筆。");

        // 預先整理成功的簡訊紀錄以便快速比對
        $validSmsList = [];
        foreach ($allSmsLogs as $sms) {
            $smsResult = strtoupper(trim($sms['SMS_RESULT'] ?? ''));
            if ($smsResult !== 'S' && $smsResult !== 'OK' && strpos($smsResult, 'OK') === false) {
                continue;
            }

            $rawCell = preg_replace('/[^0-9]/', '', $sms['SMS_CELL'] ?? '');
            if (empty($rawCell)) {
                continue;
            }

            // 解析發送時間
            $rawDate = preg_replace('/[^0-9]/', '', $sms['SMS_DATE'] ?? '');
            $rawTime = preg_replace('/[^0-9]/', '', $sms['SMS_TIME'] ?? '');
            $rawTime = str_pad(substr($rawTime, 0, 6), 6, '0', STR_PAD_RIGHT);

            $smsTs = 0;
            $timeStr = '';
            if (strlen($rawDate) >= 6) {
                $rYearLen = strlen($rawDate) - 4;
                $rYear = (int)substr($rawDate, 0, $rYearLen);
                $adY = $rYear + 1911;
                $mM = substr($rawDate, $rYearLen, 2);
                $dD = substr($rawDate, $rYearLen + 2, 2);
                $hH = substr($rawTime, 0, 2);
                $iI = substr($rawTime, 2, 2);
                $sS = substr($rawTime, 4, 2);
                $smsTs = strtotime("$adY-$mM-$dD $hH:$iI:$sS") ?: 0;
                $fmtYear = str_pad((string)$rYear, 3, '0', STR_PAD_LEFT);
                $timeStr = "{$fmtYear}/{$mM}/{$dD} {$hH}:{$iI}:{$sS}";
            }

            $validSmsList[] = [
                'cell' => $rawCell,
                'type' => $sms['SMS_TYPE'] ?? '',
                'content' => $sms['SMS_CONTENT'] ?? '',
                'timestamp' => $smsTs,
                'time_str' => $timeStr
            ];
        }

        $matchedCount = 0;
        $unmatched3Days = [];
        $unmatched7Days = [];
        $now = time();
        $moicas = new MOICAS();

        foreach ($allPending as &$item) {
            $cleanCell = preg_replace('/[^0-9]/', '', $item['cellphone'] ?? '');
            $caseCreatetime = (int)($item['createtime'] ?? $now);
            $latestMatchedSms = null;

            if (!empty($cleanCell)) {
                foreach ($validSmsList as $validSms) {
                    if ($validSms['cell'] !== $cleanCell) {
                        continue;
                    }

                    // 寬限 10 分鐘時鐘漂移
                    if ($validSms['timestamp'] > 0 && $validSms['timestamp'] < ($caseCreatetime - 600)) {
                        continue;
                    }

                    // 寬鬆業務型態判定
                    $bizMatch = false;
                    if ($item['biz_type'] === 'property_alert') {
                        if (
                            mb_strpos($validSms['type'], '地籍異動') !== false ||
                            mb_strpos($validSms['content'], '異動即時通') !== false ||
                            mb_strpos($validSms['content'], '地籍異動') !== false
                        ) {
                            $bizMatch = true;
                        }
                    } elseif ($item['biz_type'] === 'undisclosed') {
                        if (
                            mb_strpos($validSms['type'], '住址隱匿') !== false ||
                            mb_strpos($validSms['content'], '隱匿') !== false
                        ) {
                            $bizMatch = true;
                        }
                    }

                    if ($bizMatch) {
                        if ($latestMatchedSms === null || $validSms['timestamp'] >= $latestMatchedSms['timestamp']) {
                            $latestMatchedSms = $validSms;
                        }
                    }
                }
            }

            if ($latestMatchedSms !== null) {
                $newNote = RegSmsPolicy::appendSmsToNote($item['note'] ?? '', $latestMatchedSms['time_str'], $latestMatchedSms['content']);
                // 成功比對到發送成功簡訊
                if ($item['biz_type'] === 'undisclosed') {
                    $undisclosedDB->updateSmsStatus($item['id'], 1, $newNote);
                } else {
                    $propertyAlertDB->updateSmsStatus($item['id'], 1, $newNote);
                }
                $matchedCount++;
                Logger::getInstance()->info(__METHOD__ . ": [{$item['biz_title']}] ID: {$item['id']} 手機: {$cleanCell} 比對簡訊成功，已更新為發送成功 (sms_status=1) 並寫入備註。");
            } else {
                // 未比對到成功簡訊，檢查是否為隨案案件 (receiving_type = 1)
                $isAttached = ((int)($item['receiving_type'] ?? 0) === 1);
                $baseTimestamp = $caseCreatetime;
                $item['is_case_based'] = false;
                $item['close_date'] = '';

                if ($isAttached) {
                    $caseno = trim($item['receiving_caseno'] ?? '');
                    $caseInfo = !empty($caseno) ? $moicas->getCaseCloseInfo($caseno) : false;

                    if ($caseInfo === false) {
                        Logger::getInstance()->warning(__METHOD__ . ": [{$item['biz_title']}] ID: {$item['id']} 隨案主案案號({$caseno})查無主案或案號異常，回退以收件日計算天數。");
                    } else {
                        if (!empty($caseInfo['is_cancelled'])) {
                            // 主案為撤回(B)或駁回(D)：地政主機不會發送簡訊，自動更新為忽略(3)
                            $cancelTypeStr = ($caseInfo['rm31'] === 'B') ? '撤回' : '駁回';
                            $timeStr = date('Y/m/d H:i:s');
                            $statusNote = "隨案主案({$caseno})已{$cancelTypeStr}，系統自動調整為忽略。";
                            $newNote = RegSmsPolicy::appendSmsToNote($item['note'] ?? '', $timeStr, $statusNote);
                            if ($item['biz_type'] === 'undisclosed') {
                                $undisclosedDB->updateSmsStatus($item['id'], RegSmsPolicy::SMS_IGNORED, $newNote);
                            } else {
                                $propertyAlertDB->updateSmsStatus($item['id'], RegSmsPolicy::SMS_IGNORED, $newNote);
                            }
                            Logger::getInstance()->info(__METHOD__ . ": [{$item['biz_title']}] ID: {$item['id']} 隨案主案({$caseno})已{$cancelTypeStr}，已更新為忽略 (sms_status=3) 並註記備註。");
                            continue;
                        }

                        if (empty($caseInfo['is_closed'])) {
                            // 主案尚未結案：簡訊須等主案結案後送出，暫不發送提醒
                            Logger::getInstance()->info(__METHOD__ . ": [{$item['biz_title']}] ID: {$item['id']} 隨案主案({$caseno})尚未結案(RM31: {$caseInfo['rm31']})，暫不發送未發送簡訊提醒。");
                            continue;
                        }

                        // 已結案：改以結案日(RM58_1/RM58_2)起算經過天數
                        $baseTimestamp = !empty($caseInfo['close_timestamp']) ? $caseInfo['close_timestamp'] : $caseCreatetime;
                        $item['is_case_based'] = true;
                        $item['close_date'] = $caseInfo['close_date'] ?? '';
                    }
                }

                // 計算經過天數
                $elapsedDays = floor(($now - $baseTimestamp) / 86400);
                $item['elapsed_days'] = $elapsedDays;

                if ($elapsedDays >= 3) {
                    $receiver = trim($item['receiver'] ?? '');
                    $target = empty($receiver) ? 'reg' : $receiver;
                    $unmatched3Days[$target][] = $item;
                }

                if ($elapsedDays >= 7) {
                    $unmatched7Days[] = $item;
                }
            }
        }
        unset($item);

        $host_ip = getLocalhostIP();
        $undisclosedUrl = "http://{$host_ip}:8080/reg/undisclosed";
        $propertyAlertUrl = "http://{$host_ip}:8080/reg/propertyalert";
        $userNames = Cache::getInstance()->getUserNames() ?: [];

        // 滿 3 天提醒：依收件同仁彙整通知
        foreach ($unmatched3Days as $receiver => $cases) {
            $caseCount = count($cases);
            $msg = "📢 簡訊未發送逾 3 天提醒 ({$caseCount}件)\r\n***\r\n";
            foreach ($cases as $c) {
                $bizShort = ($c['biz_type'] === 'undisclosed') ? '隱匿' : '異動';
                $cDays = $c['elapsed_days'] ?? floor(($now - (int)$c['createtime']) / 86400);
                $daysDisplay = !empty($c['is_case_based']) ? "結案{$cDays}天" : "{$cDays}天";
                $isAttached = ((int)($c['receiving_type'] ?? 0) === 1);
                $no = $isAttached ? (!empty($c['receiving_caseno']) ? $c['receiving_caseno'] : '-') : (!empty($c['serial_no']) ? $c['serial_no'] : ($c['receiving_caseno'] ?? '-'));
                $applicant = trim($c['applicant'] ?? '');
                $msg .= "• [{$bizShort}] {$no} {$applicant} ({$daysDisplay})\r\n";
            }
            $msg .= "\r\n***\r\n👉 前往 [住址隱匿]({$undisclosedUrl}) ／ [異動即時通]({$propertyAlertUrl}) 確認或設為「忽略」。";

            $title = "住址隱匿與異動即時通未發送簡訊提醒";
            $this->removeNotificationByTitle($title, $receiver);
            $this->addNotification($msg, $receiver, $title);
        }

        // 滿 7 天超期警示：發送至 reg (登記課) 頻道
        if (!empty($unmatched7Days)) {
            $caseCount = count($unmatched7Days);
            $regAlertMsg = "⚠ 簡訊未發送逾 7 天警示 ({$caseCount}件)\r\n***\r\n";
            foreach ($unmatched7Days as $c) {
                $bizShort = ($c['biz_type'] === 'undisclosed') ? '隱匿' : '異動';
                $cDays = $c['elapsed_days'] ?? floor(($now - (int)$c['createtime']) / 86400);
                $daysDisplay = !empty($c['is_case_based']) ? "結案{$cDays}天" : "{$cDays}天";
                $isAttached = ((int)($c['receiving_type'] ?? 0) === 1);
                $no = $isAttached ? (!empty($c['receiving_caseno']) ? $c['receiving_caseno'] : '-') : (!empty($c['serial_no']) ? $c['serial_no'] : ($c['receiving_caseno'] ?? '-'));
                $applicant = trim($c['applicant'] ?? '');
                $recId = trim($c['receiver'] ?? '');
                $recName = !empty($recId) ? ($userNames[$recId] ?? $recId) : '未填寫';
                $regAlertMsg .= "• [{$bizShort}] {$no} {$applicant}｜{$recName} ({$daysDisplay})\r\n";
            }
            $regAlertMsg .= "\r\n***\r\n👉 前往 [住址隱匿]({$undisclosedUrl}) ／ [異動即時通]({$propertyAlertUrl}) 確認狀態。";

            $title = "住址隱匿與異動即時通超過7天未發送簡訊警示";
            $this->removeNotificationByTitle($title, 'inf');
            $this->removeNotificationByTitle($title, 'reg');
            $this->addNotification($regAlertMsg, 'reg', $title);
        }

        Logger::getInstance()->info(sprintf(
            __METHOD__ . ": 簡訊比對與掃描完成。待比對: %d 件, 成功比對: %d 件, 滿3天提醒: %d 人, 滿7天超期: %d 件。",
            $totalPendingCount,
            $matchedCount,
            count($unmatched3Days),
            count($unmatched7Days)
        ));
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
