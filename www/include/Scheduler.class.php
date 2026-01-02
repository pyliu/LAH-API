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

    public function __destruct()
    {
        // 解構子 (保留擴充空間)
    }

    /**
     * 主要執行入口
     * 依序呼叫各時間粒度的排程檢查
     */
    public function do()
    {
        Logger::getInstance()->info(__METHOD__ . ": Scheduler 開始執行。");
        
        // 依照時間長度由大到小檢查，避免小週期任務搶佔資源 (可視需求調整順序)
        $this->doOneDayJobs();
        $this->doHalfDayJobs();
        $this->do8HoursJobs();
        $this->do4HoursJobs();
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
            // 任務邏輯
            if ($this->isOn($this->schedule["office_check"])) {
                $this->addOfficeCheckStatus();
            }
        });
    }

    /**
     * 執行 10 分鐘週期任務
     * - 擷取監控郵件
     * - 偵測跨所案件回寫失效問題
     */
    public function do10minsJobs(): bool
    {
        return $this->executeJob('10m', '+10 mins', function() {
            $this->fetchMonitorMail();
            // $this->fixXCaseFailures(); // 安全性考量暫時註解
            $this->findXCaseFailures();
        });
    }

    /**
     * 執行 15 分鐘週期任務
     * - 檢查系統內部連線 (Connectivity Check)
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
     * - 日誌壓縮與清理
     * - 資料庫清理 (AP連線紀錄、過期IP等)
     * - 資料匯入 (RKEYN, OFFICES, L3HWEB Users, AD Users)
     * - 資料表分析
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
            $this->syncAdUsersToLocalDB(); // [NEW] 同步 AD 使用者
            
            // 4. 資料庫優化
            $this->analyzeTables();
        });
    }

    // =========================================================================
    //  輔助方法 (Private Helper Methods)
    // =========================================================================

    /**
     * 統一處理排程執行的核心邏輯
     * @param string $ticketKey Ticket 識別鍵 (e.g., '5m')
     * @param string $nextTimeInterval 下次執行的時間間隔 (e.g., '+5 mins')
     * @param callable $callback 實際執行的任務函式
     * @return bool 是否成功執行
     */
    private function executeJob($ticketKey, $nextTimeInterval, callable $callback): bool
    {
        try {
            $ticketFile = $this->tickets[$ticketKey];
            $ticketTs = @file_get_contents($ticketFile); // 使用 @ 抑制檔案不存在的警告

            if ($ticketTs <= time()) {
                Logger::getInstance()->info(__CLASS__ . "::do{$ticketKey}Jobs: 開始執行排程。");
                
                // 更新 Ticket 為下次執行時間
                file_put_contents($ticketFile, strtotime($nextTimeInterval, time()));
                
                // 執行傳入的任務邏輯
                $callback();
                
                return true;
            }
        } catch (Exception $e) {
            Logger::getInstance()->warning(__CLASS__ . "::do{$ticketKey}Jobs: 執行失敗。");
            Logger::getInstance()->warning("錯誤訊息: " . $e->getMessage());
        }
        return false;
    }

    /**
     * 判斷當前時間是否在指定的排程時段內
     * @param array $schedule 排程設定陣列 (e.g., $this->schedule['office'])
     * @return bool
     */
    private function isOn(array $schedule): bool
    {
        $timestamp = time();
        $currentTime = (new DateTime())->setTimestamp($timestamp);
        $dayOfWeek = date('D', $timestamp); // Mon, Tue...

        if (!isset($schedule[$dayOfWeek])) {
            return false;
        }

        foreach ($schedule[$dayOfWeek] as $startTime => $endTime) {
            $st = DateTime::createFromFormat('h:i A', $startTime);
            $ed = DateTime::createFromFormat('h:i A', $endTime);

            // 檢查目前時間是否在區間內
            if (($st < $currentTime) && ($currentTime < $ed)) {
                return true;
            }
        }
        return false;
    }

    // =========================================================================
    //  具體任務實作 - 資料匯入與同步 (Data Import & Sync Tasks)
    // =========================================================================

    /**
     * 從 L3HWEB (Oracle) 匯入使用者資料
     */
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
        } else {
            Logger::getInstance()->error(__METHOD__ . ": 同步 AD 使用者失敗 (回傳 false)。");
        }
    }

    /**
     * 匯入 RKEYN 代碼檔 (Oracle -> SQLite)
     */
    private function importRKEYN()
    {
        Logger::getInstance()->info(__METHOD__ . ': 匯入RKEYN代碼檔排程啟動。');
        $sqlite_sr = new SQLiteRKEYN();
        $sqlite_sr->importFromOraDB();
    }

    /**
     * 匯入 RKEYN_ALL 代碼檔 (Oracle -> SQLite)
     */
    private function importRKEYNALL()
    {
        Logger::getInstance()->info(__METHOD__ . ': 匯入RKEYN_ALL代碼檔排程啟動。');
        $sqlite_sra = new SQLiteRKEYNALL();
        $sqlite_sra->importFromOraDB();
    }

    /**
     * 匯入 LANDIP (OFFICES) 資料 (Oracle -> SQLite)
     */
    private function importOFFICES()
    {
        Logger::getInstance()->info(__METHOD__ . ': 匯入LANDIP資料排程啟動。');
        $sqlite_so = new SQLiteOFFICES();
        $sqlite_so->importFromOraDB();
    }

    // =========================================================================
    //  具體任務實作 - 系統維護與清理 (Maintenance Tasks)
    // =========================================================================

    /**
     * 壓縮舊的 Log 檔案
     * 一週執行一次 (透過 Cache flag 控制)
     */
    private function compressLog()
    {
        $cache = Cache::getInstance();
        if ($cache->isExpired('zipLogs_flag')) {
            Logger::getInstance()->info(__METHOD__ . ": 開始壓縮LOG檔！");
            zipLogs(); // 全域 helper function
            Logger::getInstance()->info(__METHOD__ . ": 壓縮LOG檔結束！");
            $cache->set('zipLogs_flag', true, 604800); // 7天
        }
    }

    /**
     * 刪除過時的 Log 檔案
     */
    private function removeOutdatedLog()
    {
        Logger::getInstance()->info(__METHOD__ . ": 啟動刪除過時記錄檔排程。");
        Logger::getInstance()->removeOutdatedLog();
    }

    /**
     * 清除過時的動態 IP 紀錄
     */
    private function wipeOutdatedIPEntries()
    {
        Logger::getInstance()->info(__METHOD__ . ": 啟動清除過時 dynamic ip 資料排程。");
        $ipr = new IPResolver();
        $ipr->removeDynamicIPEntries(604800); // 7天
    }

    /**
     * 刪除 Prefetch Cache DB 檔案
     */
    private function removePrefetchDB()
    {
        Logger::getInstance()->info(__METHOD__ . ": 啟動刪除 Prefetch Cache DB 排程。");
        return Prefetch::removeDBFile();
    }

    /**
     * 刪除 AP 連線歷史紀錄 DB 檔案
     */
    private function removeAPConnectionHistoryDB()
    {
        Logger::getInstance()->info(__METHOD__ . ": 啟動刪除AP連線歷史紀錄DB排程。");
        return SQLiteAPConnectionHistory::removeDBFiles();
    }

    /**
     * 清除過期的監控郵件紀錄 (本地 DB 與 Mail Server)
     */
    private function wipeOutdatedMonitorMail()
    {
        $monitor = new SQLiteMonitorMail();
        $days = 30;
        $month_secs = $days * 24 * 60 * 60;
        
        Logger::getInstance()->info("啟動清除本地端過時監控郵件排程。(${days}, ${month_secs})");
        
        if ($monitor->removeOutdatedMail($month_secs)) {
            Logger::getInstance()->info(__METHOD__ . ": 移除過時的監控郵件成功。(${days}天之前)");
        } else {
            Logger::getInstance()->warning(__METHOD__ . ": 移除過時的監控郵件失敗。(${days}天之前)");
        }
        
        Logger::getInstance()->info("開始清除伺服器端過時監控郵件排程。(1個月前)");
        $imapServer = new MonitorMail();
        $imapServer->removeOutdatedMails();
    }

    /**
     * 對特定 SQLite 資料表執行 ANALYZE 優化 (目前註解中)
     */
    private function analyzeTables()
    {
        // 預留給未來需要針對特定大表做優化時使用
        // $moicas = new MOICAS();
        // $result = $moicas->analyzeMOICASTable('CRSMS');
    }

    // =========================================================================
    //  具體任務實作 - 監控與檢測 (Monitoring & Check Tasks)
    // =========================================================================

    /**
     * 執行全國地所連線狀態檢查
     * 測試各所 WebAP 是否存活，並寫入 OFFICES_STATS 資料庫
     */
    public function addOfficeCheckStatus()
    {
        try {
            $ticketTs = @file_get_contents($this->tickets['office_check']);
            $now = time();
            $offset = $now - $ticketTs;

            // 如果 Ticket 不存在或上次執行超過 15 分鐘 (900s)，則執行
            if (empty($ticketTs) || $offset > 900) {
                // 刪除可能殘留的 journal 檔，避免卡死
                @unlink(DB_DIR . DIRECTORY_SEPARATOR . "OFFICES_STATS.db-journal");
                file_put_contents($this->tickets['office_check'], $now);
                
                Logger::getInstance()->info(__METHOD__ . ": 開始進行全國地所連線測試 ... ");

                $xap_ip = System::getInstance()->getWebAPIp();
                $sqlite_so = new SQLiteOFFICES();
                $sqlite_sos = new SQLiteOFFICESSTATS();
                $sites = $sqlite_so->getAll();
                $count = 0;

                $sqlite_sos->cleanNormalRecords();
                
                foreach ($sites as $site) {
                    if ($site['ID'] === 'CB' || $site['ID'] === 'CC') {
                        continue; // 跳過已廢止的地所
                    }

                    $url = "http://$xap_ip/Land" . strtoupper($site['ID']) . "/";
                    $headers = httpHeader($url); // 全域 helper function
                    $response = trim($headers[0] ?? '');

                    // 若回傳 401 Unauthorized 代表服務存在 (只是需要驗證)，視為 UP
                    $state = ($response === 'HTTP/1.1 401 Unauthorized') ? 'UP' : 'DOWN';

                    $sqlite_sos->replace(array(
                        'id' => $site['ID'],
                        'name' => $site['NAME'],
                        'state' => $state,
                        'response' => $response,
                        'timestamp' => time(),
                    ));
                    $count++;
                }
                Logger::getInstance()->info(__METHOD__ . ": 全國地所連線測試共完成 $count 所測試。");
            } else {
                Logger::getInstance()->warning(__METHOD__ . ": 上一次連線測試仍在進行或冷卻中，略過本次檢查。");
            }
        } catch (Exception $e) {
            Logger::getInstance()->warning(__METHOD__ . ": 執行全國地所連線測試失敗。");
            Logger::getInstance()->warning(__METHOD__ . ": " . $e->getMessage());
        } finally {
            // 重置 Ticket，表示工作結束
            file_put_contents($this->tickets['office_check'], 0);
        }
    }

    /**
     * 從 Mail Server 擷取新的監控郵件
     */
    private function fetchMonitorMail()
    {
        $monitor = new SQLiteMonitorMail();
        $monitor->fetchFromMailServer();
    }

    /**
     * 偵測跨所案件回寫失效問題
     * 檢查流程：找出 XCase 中有問題的案件 ID，並發送通知
     */
    private function findXCaseFailures()
    {
        $xcase = new XCase();
        $info = $xcase->findFailureXCases();
        $found = [];
        foreach ($info as $codeArray) {
            $tmp = array_merge($found, $codeArray['foundIds']);
            $unique_array = array_unique($tmp);
            $found = array_values($unique_array);
        }
        $this->sendFindXCaseFailuresNotification($found);
    }

    /**
     * 傳送「發現回寫失效」的通知訊息
     * @param array $found 失效的案件 ID 列表
     */
    private function sendFindXCaseFailuresNotification($found)
    {
        if (empty($found)) {
            return;
        }
        
        $message = "##### ✨ 智慧監控系統已找到下列跨所案件(" . count($found) . "件)未回寫問題：\n***\n";
        $message .= "| 　 | 　 |\n";
        $message .= "| :--- | :--- |\n";
        
        // 兩兩一組製作表格
        $chunks = array_chunk($found, 2);
        foreach ($chunks as $chunk) {
            $formatted_links = [];
            foreach ($chunk as $case_id) {
                $formatted_links[] = getMDCaseLink($case_id); // 全域 helper
            }
            $col1 = $formatted_links[0] ?? ''; 
            $col2 = $formatted_links[1] ?? '';
            $message .= "| $col1 | $col2 |\n";
        }
        $message .= "\n***\n⚠ 請至 系管管理面板 / 同步登記案件 功能進行同步修正。\n\n";

        // 發送給管理員與 inf 群組
        $sqlite_user = new SQLiteUser();
        $admins = $sqlite_user->getAdmins();
        global $today;
        $title = "$today 跨所案件同步檢測";
        
        $notify = new Notification();
        
        foreach ($admins as $admin) {
            $this->addNotification($message, $admin['id'], $title);
        }
        foreach (['inf'] as $channel) {
            // 移除舊的相同標題訊息，避免洗版
            $notify->removeOutdatedMessageByTitle($channel, $title);
            $this->addNotification($message, $channel, $title);
        }
    }

    /**
     * (已停用) 嘗試自動修復回寫失效案件
     * 註：因安全性考量，目前在 do10minsJobs 中被註解掉
     */
    private function fixXCaseFailures()
    {
        $codes = array_keys(REG_CODE["本所收件"]);
        $site = System::getInstance()->getSiteCode();
        
        // 過濾掉本所收本所的案件
        $filtered_codes = array_filter($codes, function($code) use ($site) {
            return strpos($code, $site) !== 0;
        });
        
        global $this_year;
        $done = [];
        $xcase = new XCase();
        
        foreach ($filtered_codes as $code) {
            $latestNum = $xcase->getLocalDBMaxNumByWord($code);
            if ($latestNum > 0) {
                $result = false;
                $step = 10;
                do {
                    $nextNum = str_pad($latestNum + $step, 6, '0', STR_PAD_LEFT);
                    $nextCaseID = $this_year . $code . $nextNum;
                    Logger::getInstance()->info(__METHOD__ . ": 檢查 $nextCaseID 是否可以新增到本地資料庫。");
                    
                    $result = $xcase->instXCase($nextCaseID);
                    if ($result === true) {
                        $done[] = "$this_year-$code-$nextNum";
                        Logger::getInstance()->info(__METHOD__ . ": $nextCaseID 已成功新增到本地資料庫。");
                    }
                    $step += 10;
                } while ($result === true);
            }
        }
        $this->sendFixXCaseFailuresNotification($done);
    }

    /**
     * 傳送「自動修復完成」的通知
     */
    private function sendFixXCaseFailuresNotification($done)
    {
        if (empty($done)) {
            return;
        }
        $message = "##### ✨ 智慧監控系統已修復下列跨所案件(" . count($done) . "件)未回寫問題：\n***\n";
        $message .= "| 　 | 　 |\n";
        $message .= "| :--- | :--- |\n";
        
        $chunks = array_chunk($done, 2);
        foreach ($chunks as $chunk) {
            $formatted_links = [];
            foreach ($chunk as $case_id) {
                $formatted_links[] = getMDCaseLink($case_id);
            }
            $col1 = $formatted_links[0] ?? ''; 
            $col2 = $formatted_links[1] ?? '';
            $message .= "| $col1 | $col2 |\n";
        }
        
        $sqlite_user = new SQLiteUser();
        $admins = $sqlite_user->getAdmins();
        foreach ($admins as $admin) {
            $this->addNotification($message, $admin['id'], "跨所案件同步檢測");
        }
        foreach (['reg', 'inf'] as $channel) {
            $this->addNotification($message, $channel, "跨所案件同步檢測");
        }
    }

    /**
     * 發送系統通知的輔助方法
     * @param string $message 訊息內容
     * @param string $to_id 接收者 ID 或群組 (e.g., 'admin', 'inf')
     * @param string $title 標題
     * @return string|false 成功回傳 message ID，失敗回傳 false
     */
    private function addNotification($message, $to_id, $title = '系統排程訊息')
    {
        if (empty($to_id)) {
            Logger::getInstance()->warning("未指定接收者 id 下面訊息無法送出！");
            Logger::getInstance()->warning($message);
            return false;
        }
        
        $users = Cache::getInstance()->getUserNames();
        $notify = new Notification();
        
        $payload = array(
            'title' =>  $title,
            'content' => trim($message),
            'priority' => 3,
            'expire_datetime' => '',
            'sender' => '系統排程',
            'from_ip' => getLocalhostIP() // 全域 helper
        );
        
        $lastId = $notify->addMessage($to_id, $payload);
        $nameTag = rtrim("$to_id:" . ($users[$to_id] ?? ''), ":");
        
        if ($lastId === false || empty($lastId)) {
            Logger::getInstance()->warning("訊息無法送出給 $nameTag");
        } else {
            Logger::getInstance()->info("訊息($lastId)已送出給 $nameTag");
        }
        return $lastId;
    }
}
