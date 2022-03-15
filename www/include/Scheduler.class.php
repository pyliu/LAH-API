<?php
require_once(dirname(dirname(__FILE__)).DIRECTORY_SEPARATOR.'include'.DIRECTORY_SEPARATOR.'init.php');
require_once(INC_DIR.DIRECTORY_SEPARATOR.'Message.class.php');
require_once(INC_DIR.DIRECTORY_SEPARATOR.'Notification.class.php');
require_once(INC_DIR.DIRECTORY_SEPARATOR."SQLiteMonitorMail.class.php");
require_once(INC_DIR.DIRECTORY_SEPARATOR."SQLiteConnectivity.class.php");
require_once(INC_DIR.DIRECTORY_SEPARATOR.'Cache.class.php');
require_once(INC_DIR.DIRECTORY_SEPARATOR."IPResolver.class.php");
require_once(INC_DIR.DIRECTORY_SEPARATOR."SQLiteRKEYN.class.php");
require_once(INC_DIR.DIRECTORY_SEPARATOR."SQLiteRKEYNALL.class.php");
require_once(INC_DIR.DIRECTORY_SEPARATOR."SQLiteSYSAUTH1.class.php");
require_once(INC_DIR.DIRECTORY_SEPARATOR."StatsSQLite.class.php");

class Scheduler {
    private $tmp;
    private $tickets;

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

    private function wipeOutdatedLog() {
        Logger::getInstance()->info(__METHOD__.": 啟動清除過時記錄檔排程。");
        Logger::getInstance()->removeOutdatedLog();
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
        Logger::getInstance()->info("啟動清除過時監控郵件排程。(${days}, ${month_secs})");
        if ($monitor->removeOutdatedMail($month_secs)) {
            Logger::getInstance()->info(__METHOD__.": 移除過時的監控郵件成功。(${days}天之前)");
        } else {
            Logger::getInstance()->warning(__METHOD__.": 移除過時的監控郵件失敗。(${days}天之前)");
        }
    }

    private function fetchMonitorMail() {
        $monitor = new SQLiteMonitorMail();
        $monitor->fetchFromMailServer();
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
            '24h' => $this->tmp.DIRECTORY_SEPARATOR.'LAH-24hours.ts'
        );
    }
    function __destruct() {}

    public function do() {
        Logger::getInstance()->info(__METHOD__.": Scheduler 開始執行。");
        $this->do15minsJobs();
        $this->do30minsJobs();
        $this->do1HourJobs();
        $this->do4HoursJobs();
        $this->do8HoursJobs();
        $this->doHalfDayJobs();
        $this->doOneDayJobs();
        Logger::getInstance()->info(__METHOD__.": Scheduler 執行完成。");
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
                /**
                 * 擷取監控郵件
                 */
                $this->fetchMonitorMail();
            } else {
                Logger::getInstance()->info(__METHOD__.": 每15分鐘的排程將於 ".date("Y-m-d H:i:s", $ticketTs)." 後執行。");
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
                Logger::getInstance()->info(__METHOD__.": 每30分鐘的排程將於 ".date("Y-m-d H:i:s", $ticketTs)." 後執行。");
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
                Logger::getInstance()->info(__METHOD__.": 每小時的排程將於 ".date("Y-m-d H:i:s", $ticketTs)." 後執行。");
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
                Logger::getInstance()->info(__METHOD__.": 每4小時的排程將於 ".date("Y-m-d H:i:s", $ticketTs)." 後執行。");
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
                Logger::getInstance()->info(__METHOD__.": 每8小時的排程將於 ".date("Y-m-d H:i:s", $ticketTs)." 後執行。");
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
                Logger::getInstance()->info(__METHOD__.": 每12小時的排程將於 ".date("Y-m-d H:i:s", $ticketTs)." 後執行。");
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
                // clean AP stats data one day ago
                $stats = new StatsSQLite();
                $stats->wipeAllAPConnHistory();
                // clean connectivity stats data one day ago
                $conn = new SQLiteConnectivity();
                $conn->wipeHistory(1);
                // $this->notifyTemperatureRegistration();
                $this->wipeOutdatedIPEntries();
                $this->wipeOutdatedMonitorMail();
                $this->wipeOutdatedLog();
                /**
                 * 匯入WEB DB固定資料
                 */
                $this->importRKEYN();
                $this->importRKEYNALL();
                $this->importUserFromL3HWEB();
            } else {
                Logger::getInstance()->info(__METHOD__.": 每24小時的排程將於 ".date("Y-m-d H:i:s", $ticketTs)." 後執行。");
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
