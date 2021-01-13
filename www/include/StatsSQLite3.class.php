<?php
require_once('init.php');
require_once('DynamicSQLite.class.php');
require_once('IPResolver.class.php');
require_once('Ping.class.php');

define('DB_DIR', ROOT_DIR.DIRECTORY_SEPARATOR."assets".DIRECTORY_SEPARATOR."db");
define('DEF_SQLITE_DB', DB_DIR.DIRECTORY_SEPARATOR."LAH.db");

class StatsSQLite3 {
    private $db;

    private function getLAHDB() {
        $db_path = DEF_SQLITE_DB;
        $sqlite = new DynamicSQLite($db_path);
        $sqlite->initDB();
        $sqlite->createTableBySQL('
            CREATE TABLE IF NOT EXISTS "overdue_stats_detail" (
                "datetime"	TEXT NOT NULL,
                "id"	TEXT NOT NULL,
                "count"	NUMERIC NOT NULL DEFAULT 0,
                "note"	TEXT,
                PRIMARY KEY("id","datetime")
            )
        ');
        $sqlite->createTableBySQL('
            CREATE TABLE IF NOT EXISTS "stats" (
                "ID"	TEXT,
                "NAME"	TEXT NOT NULL,
                "TOTAL"	INTEGER NOT NULL DEFAULT 0,
                PRIMARY KEY("ID")
            )
        ');
        $sqlite->createTableBySQL('
            CREATE TABLE IF NOT EXISTS "stats_raw_data" (
                "id"	TEXT NOT NULL,
                "data"	TEXT,
                PRIMARY KEY("id")
            )
        ');
        $sqlite->createTableBySQL('
            CREATE TABLE IF NOT EXISTS "xcase_stats" (
                "datetime"	TEXT NOT NULL,
                "found"	INTEGER NOT NULL DEFAULT 0,
                "note"	TEXT,
                PRIMARY KEY("datetime")
            )
        ');
        return $db_path;
    }

    private function getAPConnStatsDB($ip_end) {
        $db_path = DB_DIR.DIRECTORY_SEPARATOR.'stats_ap_conn_AP'.$ip_end.'.db';
        $sqlite = new DynamicSQLite($db_path);
        $sqlite->initDB();
        $sqlite->createTableBySQL('
            CREATE TABLE IF NOT EXISTS "ap_conn_history" (
                "log_time"	TEXT NOT NULL,
                "ap_ip"	TEXT NOT NULL,
                "est_ip"	TEXT NOT NULL,
                "count"	INTEGER NOT NULL DEFAULT 0,
                "batch"	INTEGER NOT NULL DEFAULT 0,
                PRIMARY KEY("log_time","ap_ip","est_ip")
            )
        ');
        return $db_path;
    }

    
    private function getConnectivityDB() {
        $db_path = DB_DIR.DIRECTORY_SEPARATOR."connectivity.db";
        $sqlite = new DynamicSQLite($db_path);
        $sqlite->initDB();
        $sqlite->createTableBySQL('
            CREATE TABLE IF NOT EXISTS "connectivity" (
                "log_time"	TEXT NOT NULL,
                "target_ip"	TEXT NOT NULL,
                "status"    TEXT NOT NULL DEFAULT \'DOWN\',
                "latency"	REAL NOT NULL DEFAULT 0.0,
                PRIMARY KEY("log_time","target_ip")
            )
        ');
        $sqlite->createTableBySQL('
            CREATE TABLE IF NOT EXISTS "target" (
                "ip"	TEXT NOT NULL,
                "port"	INTEGER,
                "name"	TEXT NOT NULL,
                "monitor"	TEXT NOT NULL DEFAULT \'Y\',
                "note"	TEXT,
                PRIMARY KEY("ip")
            )
        ');
        return $db_path;
    }

    private function addConnectivityStatus($log_time, $tgt_ip, $latency) {
        global $log;
        // inst into db
        $db_path = $this->getConnectivityDB();
        $ap_db = new SQLite3($db_path);
        $stm = $ap_db->prepare("REPLACE INTO connectivity (log_time,target_ip,status,latency) VALUES (:log_time, :target_ip, :status, :latency)");
        if ($stm === false) {
            $log->warning(__METHOD__.": 準備資料庫 statement [ REPLACE INTO connectivity (log_time,target_ip,status,latency) VALUES (:log_time, :target_ip, :status, :latency) ] 失敗。($log_time, $tgt_ip, $latency)");
        } else {
            $stm->bindParam(':log_time', $log_time);
            $stm->bindParam(':target_ip', $tgt_ip);
            $stm->bindValue(':status', empty($latency) ? 'DOWN' : 'UP');
            $stm->bindValue(':latency', empty($latency) ? 1000.0 : $latency);   // default ping timeout is 1s
            if ($stm->execute() !== FALSE) {
                return true;
            } else {
                $log->warning(__METHOD__.": 更新資料庫(${db_path})失敗。($log_time, $tgt_ip, $latency)");
            }
        }

        return false;
    }

    private function pingAndSave($row) {
        $ip = $row['ip'];
        if (filter_var($ip, FILTER_VALIDATE_IP)) {
            $log_time = date("YmdHis");
            $ping = new Ping($ip);
            $latency = 0;
            if (empty($row['port'])) {
                $latency = $ping->ping();
            } else {
                $ping->setPort($row['port']);
                $latency = $ping->ping('fsockopen');
                if (empty($latency)) {
                    $latency = $ping->ping('socket');
                }
            }
            return $this->addConnectivityStatus($log_time, $ip, $latency);
        }
        return false;
    }

    function __construct() {
        $path = $this->getLAHDB();
        $this->db = new SQLite3($path);
        $this->db->exec("PRAGMA cache_size = 100000");
        $this->db->exec("PRAGMA temp_store = MEMORY");
        $this->db->exec("BEGIN TRANSACTION");
    }

    function __destruct() {
        $this->db->exec("END TRANSACTION");
        $this->db->close();
    }

    public function instTotal($id, $name, $total = 0) {
        if ($stm = $this->db->prepare("INSERT INTO stats ('ID', 'NAME', 'TOTAL') VALUES (:id, :name, :total)")) {
            //$stm = $this->db->prepare("INSERT INTO stats set TOTAL = :total WHERE  ID = :id");
            $stm->bindValue(':total', intval($total));
            $stm->bindParam(':id', $id);
            $stm->bindParam(':name', $name);
            return $stm->execute() === FALSE ? false : true;
        }
        global $log;
        $log->warning(__METHOD__.": 準備資料庫 statement [ INSERT INTO stats ('ID', 'NAME', 'TOTAL') VALUES (:id, :name, :total) ] 失敗。($id, $name, $total)");
        return false;
    }
    /**
     * Early LAH Stats
     */
    public function getTotal($id) {
        return $this->db->querySingle("SELECT TOTAL from stats WHERE ID = '$id'");
    }

    public function updateTotal($id, $total) {
        if ($stm = $this->db->prepare("UPDATE stats set TOTAL = :total WHERE  ID = :id")) {
            $stm->bindValue(':total', intval($total));
            $stm->bindParam(':id', $id);
            return $stm->execute() === FALSE ? false : true;
        }
        global $log;
        $log->warning(__METHOD__.": 準備資料庫 statement [ UPDATE stats set TOTAL = :total WHERE  ID = :id ] 失敗。($id, $total)");
        return false;
    }

    public function addOverdueMsgCount($count = 1) {
        global $log;
        $total = $this->getTotal('overdue_msg_count') + $count;
        $ret = $this->updateTotal('overdue_msg_count', $total);
        $log->info(__METHOD__.": overdue_msg_count 計數器+${count}，目前值為 ${total} 【".($ret ? "成功" : "失敗")."】");
    }

    public function addOverdueStatsDetail($data) {
        // $data => ["ID" => HB0000, "RECORDS" => array, "DATETIME" => 2020-03-04 08:50:23, "NOTE" => XXX]
        // overdue_stats_detail
        if ($stm = $this->db->prepare("INSERT INTO overdue_stats_detail (datetime,id,count,note) VALUES (:date, :id, :count, :note)")) {
            $stm->bindParam(':date', $data["DATETIME"]);
            $stm->bindParam(':id', $data["ID"]);
            $stm->bindValue(':count', count($data["RECORDS"]));
            $stm->bindParam(':note', $data["NOTE"]);
            $ret = $stm->execute();
            if (!$ret) {
                global $log;
                $log->error(__METHOD__.": 新增逾期統計詳情失敗【".$stm->getSQL()."】");
            }
            return $ret;
        }
        global $log;
        $log->warning(__METHOD__.": 準備資料庫 statement [ INSERT INTO overdue_stats_detail (datetime,id,count,note) VALUES (:date, :id, :count, :note) ] 失敗。(".print_r($data, true).")");
        return false;
    }

    public function addXcasesStats($data) {
        // $data => ["date" => "2020-03-04 10:10:10","found" => 2, "note" => XXXXXXXXX]
        // xcase_stats
        if ($stm = $this->db->prepare("INSERT INTO xcase_stats (datetime,found,note) VALUES (:date, :found, :note)")) {
            $stm->bindParam(':date', $data["date"]);
            $stm->bindParam(':found', $data["found"]);
            $stm->bindParam(':note', $data["note"]);
            $ret = $stm->execute();
            global $log;
            $log->info(__METHOD__.": 新增跨所註記遺失案件統計".($ret ? "成功" : "失敗【".$stm->getSQL()."】")."。");
            // 更新 total counter
            $total = $this->getTotal('xcase_found_count') + $data["found"];
            $ret = $this->updateTotal('xcase_found_count', $total);
            $log->info(__METHOD__.": xcase_found_count 計數器+".$data["found"]."，目前值為 ${total} 【".($ret ? "成功" : "失敗")."】");
        }
        global $log;
        $log->warning(__METHOD__.": 準備資料庫 statement [ INSERT INTO xcase_stats (datetime,found,note) VALUES (:date, :found, :note) ] 失敗。(".print_r($data, true).")");
        return false;
    }

    public function addBadSurCaseStats($data) {
        // $data => ["date" => "2020-03-04 10:10:10","found" => 2, "note" => XXXXXXXXX]
        // xcase_stats
        if ($stm = $this->db->prepare("INSERT INTO found_bad_sur_case_stats (datetime,found,note) VALUES (:date, :found, :note)")) {
            $stm->bindParam(':date', $data["date"]);
            $stm->bindParam(':found', $data["found"]);
            $stm->bindParam(':note', $data["note"]);
            $ret = $stm->execute();
            global $log;
            $log->info(__METHOD__.": 新增複丈問題案件統計".($ret ? "成功" : "失敗【".$stm->getSQL()."】")."。");
            // 更新 total counter
            $total = $this->getTotal('bad_sur_case_found_count') + $data["found"];
            $ret = $this->updateTotal('bad_sur_case_found_count', $total);
            $log->info(__METHOD__.": bad_sur_case_found_count 計數器+".$data["found"]."，目前值為 ${total} 【".($ret ? "成功" : "失敗")."】");
        }
        global $log;
        $log->warning(__METHOD__.": 準備資料庫 statement [ INSERT INTO found_bad_sur_case_stats (datetime,found,note) VALUES (:date, :found, :note) ] 失敗。(".print_r($data, true).")");
        return false;
    }

    public function addStatsRawData($id, $data) {
        // $data => php array
        // overdue_stats_detail
        if ($stm = $this->db->prepare("INSERT INTO stats_raw_data (id,data) VALUES (:id, :data)")) {
            $param = serialize($data);
            $stm->bindParam(':data', $param);
            $stm->bindParam(':id', $id);
            $ret = $stm->execute();
            if (!$ret) {
                global $log;
                $log->error(__METHOD__.": 新增統計 RAW DATA 失敗【".$id.", ".$stm->getSQL()."】");
            }
            return $ret;
        }
        global $log;
        $log->warning(__METHOD__.": 準備資料庫 statement [ INSERT INTO stats_raw_data (id,data) VALUES (:id, :data) ] 失敗。($id, ".print_r($data, true).")");
        return false;
    }

    public function removeAllStatsRawData($year_month) {
        if ($stm = $this->db->prepare("DELETE FROM stats_raw_data WHERE id LIKE '%_".$year_month."'")) {
            $ret = $stm->execute();
            if (!$ret) {
                global $log;
                $log->error(__METHOD__.": 移除統計 RAW DATA 失敗【".$year_month.", ".$stm->getSQL()."】");
            }
            return $ret;
        }
        global $log;
        $log->warning(__METHOD__.": 準備資料庫 statement [ DELETE FROM stats_raw_data WHERE id LIKE '%_".$year_month."' ] 失敗。($year_month)");
        return false;
    }

    public function removeStatsRawData($id) {
        // $data => php array
        // overdue_stats_detail
        if ($stm = $this->db->prepare("DELETE FROM stats_raw_data WHERE id = :id")) {
            $stm->bindParam(':id', $id);
            $ret = $stm->execute();
            if (!$ret) {
                global $log;
                $log->error(__METHOD__.": 移除統計 RAW DATA 失敗【".$id.", ".$stm->getSQL()."】");
            }
            return $ret;
        }
        global $log;
        $log->warning(__METHOD__.": 準備資料庫 statement [ DELETE FROM stats_raw_data WHERE id = :id ] 失敗。($id)");
        return false;
    }

    public function getStatsRawData($id) {
        $data = $this->db->querySingle("SELECT data from stats_raw_data WHERE id = '$id'");
        return empty($data) ? false : unserialize($data);
    }
    /**
     * AP connection history
     */
    public function getLatestAPConnHistory($ap_ip, $all = 'true') {
        global $log;
        $db_path = $this->getAPConnStatsDB(explode('.', $ap_ip)[3]);
        $ap_db = new SQLite3($db_path);
        // get latest batch log_time
        $latest_log_time = $ap_db->querySingle("SELECT DISTINCT log_time from ap_conn_history ORDER BY log_time DESC");
        if($stmt = $ap_db->prepare('SELECT * FROM ap_conn_history WHERE log_time = :log_time ORDER BY count DESC')) {
            $stmt->bindParam(':log_time', $latest_log_time);
            $result = $stmt->execute();
            $return = [];
            if ($result === false) return $return;
            while($row = $result->fetchArray(SQLITE3_ASSOC)) {
                if ($all == 'false' && IPResolver::isServerIP($row['est_ip'])) continue;
                // turn est_ip to user
                $name = IPResolver::resolve($row['est_ip']);
                $row['name'] = empty($name) ? $row['est_ip'] : $name;
                $return[] = $row;
            }
            return $return;
        } else {
            global $log;
            $log->error(__METHOD__.": 取得 $ap_ip 最新紀錄資料失敗！ (${db_path})");
        }
        return false;
    }

    public function getAPConnHistory($ap_ip, $count, $extend = true) {
        global $log;
        // XAP conn only store at AP123 db
        $db_path = $this->getAPConnStatsDB('123');
        $ap_db = new SQLite3($db_path);
        if($stmt = $ap_db->prepare('SELECT * FROM ap_conn_history WHERE est_ip = :ip ORDER BY log_time DESC LIMIT :limit')) {
            $stmt->bindParam(':ip', $ap_ip);
            $stmt->bindValue(':limit', $extend ? $count * 4 : $count, SQLITE3_INTEGER);
            $result = $stmt->execute();
            $return = [];
            if ($result === false) return $return;
            $skip_count = 0;
            while($row = $result->fetchArray(SQLITE3_ASSOC)) {
                // basically BE every 15s insert a record, extend means to get 1-min duration record
                if ($extend) {
                    $skip_count++;
                    if ($skip_count % 4 != 1) continue;
                }
                $return[] = $row;
            }
            return $return;
        } else {
            global $log;
            $log->error(__METHOD__.": 取得 $ap_ip 歷史紀錄資料失敗！ (${db_path})");
        }
        return false;
    }

    public function addAPConnHistory($log_time, $ap_ip, $processed) {
        global $log;
        // inst into db
        $db_path = $this->getAPConnStatsDB(explode('.', $ap_ip)[3]);
        $ap_db = new SQLite3($db_path);
        $latest_batch = $ap_db->querySingle("SELECT DISTINCT batch from ap_conn_history ORDER BY batch DESC");
        $success = 0;
        foreach ($processed as $est_ip => $count) {
            if ($stm = @$ap_db->prepare("INSERT INTO ap_conn_history (log_time,ap_ip,est_ip,count,batch) VALUES (:log_time, :ap_ip, :est_ip, :count, :batch)")) {
                $stm->bindParam(':log_time', $log_time);
                $stm->bindParam(':ap_ip', $ap_ip);
                $stm->bindParam(':est_ip', $est_ip);
                $stm->bindParam(':count', $count);
                $stm->bindValue(':batch', $latest_batch + 1);
                $retry = 0;
                while (@$stm->execute() === FALSE) {
                    if ($retry > 10) {
                        $log->warning(__METHOD__.": 更新資料庫(${db_path})失敗。($log_time, $ap_ip, $est_ip, $count)");
                        return $success;
                    }
                    $zzz_us = random_int(100000, 500000);
                    $log->warning(__METHOD__.": execute statement failed ... sleep ".$zzz_us." microseconds, retry(".++$retry.").");
                    usleep($zzz_us);
                }
                $success++;
            } else {
                global $log;
                $log->warning(__METHOD__.": 準備資料庫 statement [ INSERT INTO ap_conn_history (log_time,ap_ip,est_ip,count,batch) VALUES (:log_time, :ap_ip, :est_ip, :count, :batch) ] 失敗。($log_time, $ap_ip, $est_ip, $count)");
            }
        }

        return $success;
    }

    public function wipeAPConnHistory($ip_end) {
        global $log;
        $one_day_ago = date("YmdHis", time() - 24 * 3600);
        $ap_db = new SQLite3($this->getAPConnStatsDB($ip_end));
        if ($stm = $ap_db->prepare("DELETE FROM ap_conn_history WHERE log_time < :time")) {
            $stm->bindParam(':time', $one_day_ago, SQLITE3_TEXT);
            $ret = $stm->execute();
            if (!$ret) {
                $log->error(__METHOD__.": stats_ap_conn_AP".$ip_end.".db 移除一天前資料失敗【".$one_day_ago.", ".$ap_db->lastErrorMsg()."】");
            }
            return $ret;
        }
        global $log;
        $log->warning(__METHOD__.": 準備資料庫 statement [ DELETE FROM ap_conn_history WHERE log_time < :time ] 失敗。($ip_end)");
        return false;
    }

    public function wipeAllAPConnHistory() {
        $this->wipeAPConnHistory('31');
        $this->wipeAPConnHistory('32');
        $this->wipeAPConnHistory('33');
        $this->wipeAPConnHistory('34');
        $this->wipeAPConnHistory('35');
        $this->wipeAPConnHistory('36');
        $this->wipeAPConnHistory('70');
        $this->wipeAPConnHistory('123');
    }
    /**
     * Connectivity Status
     */
    public function getCheckingTargets() {
        global $log;
        // inst into db
        $db_path = $this->getConnectivityDB();
        $ap_db = new SQLite3($db_path);
        $stm = $ap_db->prepare("SELECT * FROM target WHERE monitor = 'Y' ORDER BY name");
        if ($stm === false) {
            $log->warning(__METHOD__.": 準備資料庫 statement [ SELECT * FROM target ] 失敗。");
        } else {
            if ($result = $stm->execute()) {
                $return = array();
                if ($result === false) return $return;
                while($row = $result->fetchArray(SQLITE3_ASSOC)) {
                    $return[$row['name']] = $row;
                }
                return $return;
            } else {
                $log->warning(__METHOD__.": 取得檢測目標列表失敗。");
            }
        }

        return false;
    }

    public function checkRegisteredConnectivity() {
        global $log;
        $tracking_targets = $this->getCheckingTargets();
        // generate the latest batch records
        foreach ($tracking_targets as $name => $row) {
            if (filter_var($row['ip'], FILTER_VALIDATE_IP)) {
                $this->pingAndSave($row);
            } else {
                $log->warning(__METHOD__.": $name:".$row['ip']." is not a valid IP address.".(empty($row['port']) ? '' : ':'.$row['port']));
            }
        }
    }

    public function checkIPConnectivity($ip = null, $port = 0) {
        global $log;
        if (filter_var($ip, FILTER_VALIDATE_IP)) {
            // single ep
            $this->pingAndSave(array('ip' => $ip, 'port' => $port));
        } else {
            $log->warning(__METHOD__.": $ip".(empty($port) ? '' : ":$port")." is not valid.");
        }
    }

    public function getConnectivityStatus($force = 'false') {
        global $log;

        if ($force === 'true') {
            // generate the latest batch records
            $this->checkRegisteredConnectivity();
        }

        $tracking_targets = $this->getCheckingTargets();
        $tracking_ips = array();
        foreach ($tracking_targets as $name => $row) {
            $tracking_ips[$name] = $row['ip'];
        }
        $return = array();
        if (empty($tracking_ips)) {
            $log->warning(__METHOD__.": tracking ip array is empty.");
        } else {
            $db_path = $this->getConnectivityDB();
            $conn_db = new SQLite3($db_path);
            $in_statement = " IN ('".implode("','", $tracking_ips)."') ";
            if($stmt = $conn_db->prepare('SELECT * FROM connectivity WHERE target_ip '.$in_statement.' ORDER BY ROWID DESC LIMIT :limit')) {
                $stmt->bindValue(':limit', count($tracking_targets), SQLITE3_INTEGER);
                $result = $stmt->execute();
                if ($result === false) return $return;
                while($row = $result->fetchArray(SQLITE3_ASSOC)) {
                    $return[] = $row;
                }
            } else {
                $log->error(__METHOD__.": 取得 connectivity 最新紀錄資料失敗！ (${db_path})");
            }
        }
        return $return;
    }

    public function getIPConnectivityStatus($ip, $force = 'false', $port = 0) {
        if ($force === 'true') {
            // generate the latest record for $ip
            $this->checkIPConnectivity($ip, $port);
        }

        $db_path = $this->getConnectivityDB();
        $conn_db = new SQLite3($db_path);
        if($stmt = $conn_db->prepare('SELECT * FROM connectivity WHERE target_ip = :ip ORDER BY ROWID DESC LIMIT :limit')) {
            $stmt->bindValue(':limit', 1, SQLITE3_INTEGER);
            $stmt->bindParam(':ip', $ip);
            $result = $stmt->execute();
            if ($result === false) return array();
            return $result->fetchArray(SQLITE3_ASSOC);
        } else {
            global $log;
            $log->error(__METHOD__.": 取得 $ip connectivity 最新紀錄資料失敗！ (${db_path})");
        }
        return false;
    }

    public function wipeConnectivityHistory() {
        global $log;
        $db_path = $this->getConnectivityDB();
        $sc_db = new SQLite3($db_path);
        if ($stm = $sc_db->prepare("DELETE FROM connectivity WHERE log_time < :time")) {
            $one_day_ago = date("YmdHis", time() - 24 * 3600);
            $stm->bindParam(':time', $one_day_ago, SQLITE3_TEXT);
            $ret = $stm->execute();
            if (!$ret) {
                $log->error(__METHOD__.": $db_path 移除一天前資料失敗【".$one_day_ago.", ".$sc_db->lastErrorMsg()."】");
            }
            return $ret;
        }
        global $log;
        $log->warning(__METHOD__.": 準備資料庫 statement [ DELETE FROM connectivity WHERE log_time < :time ] 失敗。");
        return false;
    }
}
