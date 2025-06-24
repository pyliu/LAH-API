<?php
require_once(dirname(dirname(__FILE__)).DIRECTORY_SEPARATOR.'include'.DIRECTORY_SEPARATOR.'init.php');
require_once(INC_DIR.DIRECTORY_SEPARATOR.'Message.class.php');
require_once(INC_DIR.DIRECTORY_SEPARATOR.'Notification.class.php');
require_once(INC_DIR.DIRECTORY_SEPARATOR."MonitorMail.class.php");
require_once(INC_DIR.DIRECTORY_SEPARATOR."SQLiteMonitorMail.class.php");
require_once(INC_DIR.DIRECTORY_SEPARATOR."SQLiteConnectivity.class.php");
require_once(INC_DIR.DIRECTORY_SEPARATOR.'Cache.class.php');
require_once(INC_DIR.DIRECTORY_SEPARATOR."IPResolver.class.php");
require_once(INC_DIR.DIRECTORY_SEPARATOR."SQLiteRKEYN.class.php");
require_once(INC_DIR.DIRECTORY_SEPARATOR."SQLiteRKEYNALL.class.php");
require_once(INC_DIR.DIRECTORY_SEPARATOR."SQLiteSYSAUTH1.class.php");
require_once(INC_DIR.DIRECTORY_SEPARATOR."StatsSQLite.class.php");
require_once(INC_DIR.DIRECTORY_SEPARATOR."Prefetch.class.php");
require_once(INC_DIR.DIRECTORY_SEPARATOR."SQLiteOFFICES.class.php");
require_once(INC_DIR.DIRECTORY_SEPARATOR."SQLiteOFFICESSTATS.class.php");
require_once(INC_DIR.DIRECTORY_SEPARATOR."System.class.php");
require_once(INC_DIR.DIRECTORY_SEPARATOR."MOICAS.class.php");
require_once(INC_DIR.DIRECTORY_SEPARATOR."MOIADM.class.php");
require_once(INC_DIR.DIRECTORY_SEPARATOR."MOICAT.class.php");
require_once(INC_DIR.DIRECTORY_SEPARATOR."SQLiteAPConnectionHistory.class.php");

class Scheduler {
    private $tmp;
    private $tickets;
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

    private function isOn($schedule) {
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

    private function compressLog() {
        $cache = Cache::getInstance();
        // compress all log when zipLogs_flag is expired
        if ($cache->isExpired('zipLogs_flag')) {
            Logger::getInstance()->info(__METHOD__.": 開始壓縮LOG檔！");
            zipLogs();
            Logger::getInstance()->info(__METHOD__.": 壓縮LOG檔結束！");
            // cache the flag for a week
            $cache->set('zipLogs_flag', true, 604800);
        }
    }

    private function importUserFromL3HWEB() {
        Logger::getInstance()->info(__METHOD__.': 匯入L3HWEB使用者資料排程啟動。');
        $sysauth1 = new SQLiteSYSAUTH1();
        $sysauth1->importFromL3HWEBDB();
    }

    private function importRKEYN() {
        Logger::getInstance()->info(__METHOD__.': 匯入RKEYN代碼檔排程啟動。');
        $sqlite_sr = new SQLiteRKEYN();
        $sqlite_sr->importFromOraDB();
    }

    private function importRKEYNALL() {
        Logger::getInstance()->info(__METHOD__.': 匯入RKEYN_ALL代碼檔排程啟動。');
        $sqlite_sra = new SQLiteRKEYNALL();
        $sqlite_sra->importFromOraDB();
    }

    private function importOFFICES() {
        Logger::getInstance()->info(__METHOD__.': 匯入LANDIP資料排程啟動。');
        $sqlite_so = new SQLiteOFFICES();
        $sqlite_so->importFromOraDB();
    }

    private function removePrefetchDB() {
        Logger::getInstance()->info(__METHOD__.": 啟動刪除 Prefetch Cache DB 排程。");
        return Prefetch::removeDBFile();
    }

    private function removeAPConnectionHistoryDB() {
        Logger::getInstance()->info(__METHOD__.": 啟動刪除AP連線歷史紀錄DB排程。");
        return SQLiteAPConnectionHistory::removeDBFiles();
    }

    private function removeOutdatedLog() {
        Logger::getInstance()->info(__METHOD__.": 啟動刪除過時記錄檔排程。");
        // Logger::getInstance()->warning(__METHOD__.": 暫時略過清除過時記錄檔排程。");
        Logger::getInstance()->removeOutdatedLog();
    }

    private function wipeOutdatedIPEntries() {
        Logger::getInstance()->info(__METHOD__.": 啟動清除過時 dynamic ip 資料排程。");
        $ipr = new IPResolver();
        $ipr->removeDynamicIPEntries(604800);   // a week
    }

    private function wipeOutdatedMonitorMail() {
        $monitor = new SQLiteMonitorMail();
        // remove mails by a month ago
        $days = 30;
        $month_secs = $days * 24 * 60 * 60;
        Logger::getInstance()->info("啟動清除本地端過時監控郵件排程。(${days}, ${month_secs})");
        if ($monitor->removeOutdatedMail($month_secs)) {
            Logger::getInstance()->info(__METHOD__.": 移除過時的監控郵件成功。(${days}天之前)");
        } else {
            Logger::getInstance()->warning(__METHOD__.": 移除過時的監控郵件失敗。(${days}天之前)");
        }
        Logger::getInstance()->info("開始清除伺服器端過時監控郵件排程。(1個月前)");
        $imapServer = new MonitorMail();
        $imapServer->removeOutdatedMails();
    }

    private function fetchMonitorMail() {
        $monitor = new SQLiteMonitorMail();
        $monitor->fetchFromMailServer();
    }

    private function analyzeTables() {
        // $moicas = new MOICAS();
        // $result = $moicas->analyzeMOICASTable('CRSMS');
        // Logger::getInstance()->info(__METHOD__.": ANALYZE MOICAS.CRSMS TABLE ".($result ? '成功' : '失敗'));
        
        // $moiadm = new MOIADM();
        // $result = $moiadm->analyzeMOIADMTable('PUBLICATION_HISTORY');
        // Logger::getInstance()->info(__METHOD__.": ANALYZE MOIADM.PUBLICATION_HISTORY TABLE ".($result ? '成功' : '失敗'));
        
        // $moicat = new MOICAT();
        // $result = $moicat->analyzeMOICATTable('RINDX');
        // Logger::getInstance()->info(__METHOD__.": ANALYZE MOICAT.RINDX TABLE ".($result ? '成功' : '失敗'));
    }

    private function fixXCaseFailures() {
        $codes = array_keys(REG_CODE["本所收件"]);
        $site = System::getInstance()->getSiteCode();
        // 過濾掉本所收本所的跨所收件碼
        $filtered_codes = array_filter($codes, function($code) use ($site) {
            // 檢查 $code 字串的開頭是否為 $site
            // strpos() 的回傳值若為 0，代表 $site 就在字串的開頭
            // 我們要保留的是「開頭不是 $site」的元素，所以條件是 !== 0
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
                    // e.g. 114HAB1017600
                    $nextCaseID = $this_year.$code.$nextNum;
                    Logger::getInstance()->info(__METHOD__.": 檢查 $nextCaseID 是否可以新增到本地資料庫。");
                    $result = $xcase->instXCase($nextCaseID);
                    if ($result === true) {
                        $done[] = "$this_year-$code-$nextNum";
                        Logger::getInstance()->info(__METHOD__.": $nextCaseID 已成功新增到本地資料庫。");
                    } else {
                        Logger::getInstance()->info(__METHOD__.": $nextCaseID 是否可以新增到本地資料庫。");
                    }
                    $step += 10;
                } while($result === true);
            }
        }
        $this->sendFixXCaseFailuresNotification($done);
    }

    private function sendFixXCaseFailuresNotification($done) {
        if (empty($done)) {
            return;
        }
        $host_ip = getLocalhostIP();
        $base_url = "http://".$host_ip.":8080/reg/case/";
        $message = "##### ✨ 智慧監控系統已修復下列跨所案件(".count($done)."件)未回寫問題：\n***\n";
        // 1. 將案件陣列 $done 每 2 個一組，分割成一個新的二維陣列 $chunks
        $chunks = array_chunk($done, 2);
        // 2. 遍歷這個包含「案件對」的新陣列
        foreach ($chunks as $chunk) {
            $formatted_links = [];
            // 3. 處理每一對(或單個)案件
            foreach ($chunk as $case_id) {
                // 將每個案件格式化為 Markdown 連結
                $formatted_links[] = "[$case_id]($base_url$case_id)";
            }
            // 4. 將同一組的連結用空格連接，並附加到主訊息中
            $message .= "1. " . implode(' ', $formatted_links) . "\n";
        }
        // send message
        $notification = new Notification();
        foreach (['reg', 'inf'] as $channel) {
            $notification->removeOutdatedMessageByTitle($channel, "跨所案件同步檢測");
            $this->addNotification($message, $channel, "跨所案件同步檢測");
        }
    }

    private function addNotification($message, $to_id, $title = '系統排程訊息') {
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
            'from_ip' => getLocalhostIP()
        );
        $skip_announcement_convertion = true;
        $lastId = $notify->addMessage($to_id, $payload, $skip_announcement_convertion);
        $nameTag = rtrim("$to_id:".$users[$to_id], ":");
        if ($lastId === false || empty($lastId)) {
            Logger::getInstance()->warning("訊息無法送出給 $nameTag");
        } else {
            Logger::getInstance()->info("訊息($lastId)已送出給 $nameTag");
        }
        return $lastId;
    }

    function __construct() {
        $this->tmp = sys_get_temp_dir();
        $this->tickets = array(
            '5m' => $this->tmp.DIRECTORY_SEPARATOR.'LAH-5mins.ts',
            '10m' => $this->tmp.DIRECTORY_SEPARATOR.'LAH-10mins.ts',
            '15m' => $this->tmp.DIRECTORY_SEPARATOR.'LAH-15mins.ts',
            '30m' => $this->tmp.DIRECTORY_SEPARATOR.'LAH-30mins.ts',
            '1h' => $this->tmp.DIRECTORY_SEPARATOR.'LAH-1hour.ts',
            '2h' => $this->tmp.DIRECTORY_SEPARATOR.'LAH-2hours.ts',
            '4h' => $this->tmp.DIRECTORY_SEPARATOR.'LAH-4hours.ts',
            '8h' => $this->tmp.DIRECTORY_SEPARATOR.'LAH-8hours.ts',
            '12h' => $this->tmp.DIRECTORY_SEPARATOR.'LAH-12hours.ts',
            '24h' => $this->tmp.DIRECTORY_SEPARATOR.'LAH-24hours.ts',
            'office_check' => $this->tmp.DIRECTORY_SEPARATOR.'LAH-office-check.ts'
        );
    }
    function __destruct() {}

    public function addOfficeCheckStatus() {
        try {
            $ticketTs = file_get_contents($this->tickets['office_check']);
            $now = time();
            $offset = $now - $ticketTs;
            if (empty($ticketTs) || $offset > 900) {
                // prevent hanging
                @unlink(DB_DIR.DIRECTORY_SEPARATOR."OFFICES_STATS.db-journal");
                file_put_contents($this->tickets['office_check'], $now);
                Logger::getInstance()->info(__METHOD__.": 開始進行全國地所連線測試 ... ");

                $xap_ip = System::getInstance()->getWebAPIp();
                $sqlite_so = new SQLiteOFFICES();
                $sqlite_sos = new SQLiteOFFICESSTATS();
                $sites = $sqlite_so->getAll();
                $count = 0;
                // clear normal stats records previously
                $sqlite_sos->cleanNormalRecords();
                foreach ($sites as $site) {
                    // skip out of date sites
                    if ($site['ID'] === 'CB' || $site['ID'] === 'CC') {
                        continue;
                    }
                    // Logger::getInstance()->info(__METHOD__.": 檢測".$site['ID']." ".$site['ALIAS']." ".$site['NAME']."。");
                    $url = "http://$xap_ip/Land".strtoupper($site['ID'])."/";
                    // Logger::getInstance()->info(__METHOD__.": url:$url");
                    $headers = httpHeader($url);
                    $response = trim($headers[0]);
                    // Logger::getInstance()->info(__METHOD__.": header: $response");
                    $sqlite_sos->replace(array(
                        'id' => $site['ID'],
                        'name' => $site['NAME'],
                        // if service available, HTTP response code will return 401
                        'state' => $response === 'HTTP/1.1 401 Unauthorized' ? 'UP' : 'DOWN',
                        'response' => $response,
                        'timestamp' => time(),
                    ));
                    // Logger::getInstance()->info(__METHOD__.": timestamp: ".time());
                    $count++;
                }
                Logger::getInstance()->info(__METHOD__.": 全國地所連線測試共完成 $count 所測試。");
            } else {
                Logger::getInstance()->warning(__METHOD__.": 上一次全國地所連線測試工作仍在進行中，略過本次檢查。");
            }
        } catch (Exception $e) {
            Logger::getInstance()->warning(__METHOD__.": 執行全國地所連線測試失敗。");
            Logger::getInstance()->warning(__METHOD__.": ".$e->getMessage());
        } finally {
            // finished 
            file_put_contents($this->tickets['office_check'], 0);
        }
    }

    public function do() {
        Logger::getInstance()->info(__METHOD__.": Scheduler 開始執行。");
        $this->doOneDayJobs();
        $this->doHalfDayJobs();
        $this->do8HoursJobs();
        $this->do4HoursJobs();
        $this->do1HourJobs();
        $this->do30minsJobs();
        $this->do15minsJobs();
        $this->do10minsJobs();
        $this->do5minsJobs();
        Logger::getInstance()->info(__METHOD__.": Scheduler 執行完成。");
    }

    public function do5minsJobs () {
        try {
            $ticketTs = file_get_contents($this->tickets['5m']);
            if ($ticketTs <= time()) {
                Logger::getInstance()->info(__METHOD__.": 開始執行每5分鐘的排程。");
                // place next timestamp to the tmp ticket file 
                file_put_contents($this->tickets['5m'], strtotime('+5 mins', time()));
                // check all offices connectivity during office hours
                if ($this->isOn($this->schedule["office_check"])) {
                    $this->addOfficeCheckStatus();
                }
            } else {
                // Logger::getInstance()->info(__METHOD__.": 每5分鐘的排程將於 ".date("Y-m-d H:i:s", $ticketTs)." 後執行。");
            }
            return true;
        } catch (Exception $e) {
            Logger::getInstance()->warning(__METHOD__.": 執行每5分鐘的排程失敗。");
            Logger::getInstance()->warning(__METHOD__.": ".$e->getMessage());
        } finally {
        }
        return false;
    }

    public function do10minsJobs () {
        try {
            
            $ticketTs = file_get_contents($this->tickets['10m']);
            if ($ticketTs <= time()) {
                Logger::getInstance()->info(__METHOD__.": 開始執行每10分鐘的排程。");
                // place next timestamp to the tmp ticket file 
                file_put_contents($this->tickets['10m'], strtotime('+10 mins', time()));
                /**
                 * 擷取監控郵件
                 */
                $this->fetchMonitorMail();
                /**
                 * 避免回寫失效問題
                 */
                $this->fixXCaseFailures();
            } else {
                // Logger::getInstance()->info(__METHOD__.": 每10分鐘的排程將於 ".date("Y-m-d H:i:s", $ticketTs)." 後執行。");
            }
            return true;
        } catch (Exception $e) {
            Logger::getInstance()->warning(__METHOD__.": 執行每10分鐘的排程失敗。");
            Logger::getInstance()->warning(__METHOD__.": ".$e->getMessage());
        } finally {
        }
        return false;
    }

    public function do15minsJobs () {
        try {
            $ticketTs = file_get_contents($this->tickets['15m']);
            if ($ticketTs <= time()) {
                Logger::getInstance()->info(__METHOD__.": 開始執行每15分鐘的排程。");
                // place next timestamp to the tmp ticket file 
                file_put_contents($this->tickets['15m'], strtotime('+15 mins', time()));
                // check systems connectivity
                $conn = new SQLiteConnectivity();
                $conn->check();
            } else {
                // Logger::getInstance()->info(__METHOD__.": 每15分鐘的排程將於 ".date("Y-m-d H:i:s", $ticketTs)." 後執行。");
            }
            return true;
        } catch (Exception $e) {
            Logger::getInstance()->warning(__METHOD__.": 執行每15分鐘的排程失敗。");
            Logger::getInstance()->warning(__METHOD__.": ".$e->getMessage());
        } finally {
        }
        return false;
    }

    public function do30minsJobs () {
        try {
            $ticketTs = file_get_contents($this->tickets['30m']);
            if ($ticketTs <= time()) {
                Logger::getInstance()->info(__METHOD__.": 開始執行每30分鐘的排程。");
                // place next timestamp to the tmp ticket file 
                file_put_contents($this->tickets['30m'], strtotime('+30 mins', time()));
                // job execution below ...
            } else {
                // Logger::getInstance()->info(__METHOD__.": 每30分鐘的排程將於 ".date("Y-m-d H:i:s", $ticketTs)." 後執行。");
            }
            return true;
        } catch (Exception $e) {
            Logger::getInstance()->warning(__METHOD__.": 執行每30分鐘的排程失敗。");
            Logger::getInstance()->warning(__METHOD__.": ".$e->getMessage());
        } finally {
        }
        return false;
    }

    public function do1HourJobs () {
        try {
            $ticketTs = file_get_contents($this->tickets['1h']);
            if ($ticketTs <= time()) {
                Logger::getInstance()->info(__METHOD__.": 開始執行每小時的排程。");
                // place next timestamp to the tmp ticket file 
                file_put_contents($this->tickets['1h'], strtotime('+60 mins', time()));
                // job execution below ...
            } else {
                // Logger::getInstance()->info(__METHOD__.": 每小時的排程將於 ".date("Y-m-d H:i:s", $ticketTs)." 後執行。");
            }
            return true;
        } catch (Exception $e) {
            Logger::getInstance()->warning(__METHOD__.": 執行每小時的排程失敗。");
            Logger::getInstance()->warning(__METHOD__.": ".$e->getMessage());
        } finally {
        }
        return false;
    }

    public function do4HoursJobs () {
        try {
            $ticketTs = file_get_contents($this->tickets['4h']);
            if ($ticketTs <= time()) {
                Logger::getInstance()->info(__METHOD__.": 開始執行每4小時的排程。");
                // place next timestamp to the tmp ticket file 
                file_put_contents($this->tickets['4h'], strtotime('+240 mins', time()));
                // job execution below ...
            } else {
                // Logger::getInstance()->info(__METHOD__.": 每4小時的排程將於 ".date("Y-m-d H:i:s", $ticketTs)." 後執行。");
            }
            return true;
        } catch (Exception $e) {
            Logger::getInstance()->warning(__METHOD__.": 執行每4小時的排程失敗。");
            Logger::getInstance()->warning(__METHOD__.": ".$e->getMessage());
        } finally {
        }
        return false;
    }
    
    public function do8HoursJobs () {
        try {
            $ticketTs = file_get_contents($this->tickets['8h']);
            if ($ticketTs <= time()) {
                Logger::getInstance()->info(__METHOD__.": 開始執行每8小時的排程。");
                // place next timestamp to the tmp ticket file 
                file_put_contents($this->tickets['8h'], strtotime('+480 mins', time()));
                // job execution below ...
            } else {
                // Logger::getInstance()->info(__METHOD__.": 每8小時的排程將於 ".date("Y-m-d H:i:s", $ticketTs)." 後執行。");
            }
            return true;
        } catch (Exception $e) {
            Logger::getInstance()->warning(__METHOD__.": 執行每8小時的排程失敗。");
            Logger::getInstance()->warning(__METHOD__.": ".$e->getMessage());
        } finally {
        }
        return false;
    }
    
    public function doHalfDayJobs () {
        try {
            $ticketTs = file_get_contents($this->tickets['12h']);
            if ($ticketTs <= time()) {
                Logger::getInstance()->info(__METHOD__.": 開始執行每12小時的排程。");
                // place next timestamp to the tmp ticket file 
                file_put_contents($this->tickets['12h'], strtotime('+720 mins', time()));
                // job execution below ...
            } else {
                // Logger::getInstance()->info(__METHOD__.": 每12小時的排程將於 ".date("Y-m-d H:i:s", $ticketTs)." 後執行。");
            }
            return true;
        } catch (Exception $e) {
            Logger::getInstance()->warning(__METHOD__.": 執行每12小時的排程失敗。");
            Logger::getInstance()->warning(__METHOD__.": ".$e->getMessage());
        } finally {
        }
        return false;
    }
    
    public function doOneDayJobs () {
        try {
            $ticketTs = file_get_contents($this->tickets['24h']);
            if ($ticketTs <= time()) {
                Logger::getInstance()->info(__METHOD__.": 開始執行每24小時的排程。");
                // place next timestamp to the tmp ticket file 
                file_put_contents($this->tickets['24h'], strtotime('+1440 mins', time()));
                // job execution below ...
                // compress other days log
                $this->compressLog();
                // clean all AP history data one day ago
                SQLiteAPConnectionHistory::cleanOneDayAgoAll();
                // clean connectivity stats data one day ago
                $conn = new SQLiteConnectivity();
                $conn->wipeHistory(1);
                // $this->notifyTemperatureRegistration();
                $this->wipeOutdatedIPEntries();
                /**
                 * 移除過期的監控郵件
                 */
                $this->wipeOutdatedMonitorMail();
                $this->removeOutdatedLog();
                // remove cached prefetch data once a day
                $this->removePrefetchDB();
                // remove cached AP connection history data once a day
                $this->removeAPConnectionHistoryDB();
                /**
                 * 匯入WEB DB固定資料
                 */
                $this->importRKEYN();
                $this->importRKEYNALL();
                $this->importOFFICES();
                $this->importUserFromL3HWEB();
                $this->analyzeTables();
            } else {
                // Logger::getInstance()->info(__METHOD__.": 每24小時的排程將於 ".date("Y-m-d H:i:s", $ticketTs)." 後執行。");
            }
            return true;
        } catch (Exception $e) {
            Logger::getInstance()->warning(__METHOD__.": 執行每24小時的排程失敗。");
            Logger::getInstance()->warning(__METHOD__.": ".$e->getMessage());
        } finally {
        }
        return false;
    }
}
