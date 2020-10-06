<?php
require_once('init.php');
require_once('IPResolver.class.php');

define('DB_DIR', ROOT_DIR.DIRECTORY_SEPARATOR."assets".DIRECTORY_SEPARATOR."db");
define('DEF_SQLITE_DB', DB_DIR.DIRECTORY_SEPARATOR."LAH.db");
define('TEMPERATURE_SQLITE_DB', DB_DIR.DIRECTORY_SEPARATOR."Temperature.db");
define('DIMENSION_SQLITE_DB', DB_DIR.DIRECTORY_SEPARATOR."dimension.db");

class StatsSQLite3 {
    private $db;

    function __construct($db = DEF_SQLITE_DB) {
        $this->db = new SQLite3($db);
    }

    function __destruct() { $this->db->close(); }

    public function instTotal($id, $name, $total = 0) {
        $stm = $this->db->prepare("INSERT INTO stats ('ID', 'NAME', 'TOTAL') VALUES (:id, :name, :total)");
        //$stm = $this->db->prepare("INSERT INTO stats set TOTAL = :total WHERE  ID = :id");
        $stm->bindValue(':total', intval($total));
        $stm->bindParam(':id', $id);
        $stm->bindParam(':name', $name);
        return $stm->execute() === FALSE ? false : true;
    }
    /**
     * Stats
     */
    public function getTotal($id) {
        return $this->db->querySingle("SELECT TOTAL from stats WHERE ID = '$id'");
    }

    public function updateTotal($id, $total) {
        $stm = $this->db->prepare("UPDATE stats set TOTAL = :total WHERE  ID = :id");
        $stm->bindValue(':total', intval($total));
        $stm->bindParam(':id', $id);
        return $stm->execute() === FALSE ? false : true;
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
        $stm = $this->db->prepare("INSERT INTO overdue_stats_detail (datetime,id,count,note) VALUES (:date, :id, :count, :note)");
        $stm->bindParam(':date', $data["DATETIME"]);
        $stm->bindParam(':id', $data["ID"]);
        $stm->bindValue(':count', count($data["RECORDS"]));
        $stm->bindParam(':note', $data["NOTE"]);
        $ret = $stm->execute();
        if (!$ret) {
            global $log;
            $log->error(__METHOD__.": 新增逾期統計詳情失敗【".$stm->getSQL()."】");
        }
    }

    public function addXcasesStats($data) {
        // $data => ["date" => "2020-03-04 10:10:10","found" => 2, "note" => XXXXXXXXX]
        // xcase_stats
        $stm = $this->db->prepare("INSERT INTO xcase_stats (datetime,found,note) VALUES (:date, :found, :note)");
        $stm->bindParam(':date', $data["date"]);
        $stm->bindParam(':found', $data["found"]);
        $stm->bindParam(':note', $data["note"]);
        $ret = $stm->execute();
        global $log;
        $log->info(__METHOD__.": 新增跨所註記遺失案件統計".($ret ? "成功" : "失敗【".$stm->getSQL()."】")."。");
        // 更新 total counter
        $total = $this->getTotal('xcase_found_count') + $data["found"];
        $ret = $this->updateTotal('xcase_found_count', $total);
        $log->info(__METHOD__.":xcase_found_count 計數器+".$data["found"]."，目前值為 ${total} 【".($ret ? "成功" : "失敗")."】");

    }

    public function addStatsRawData($id, $data) {
        // $data => php array
        // overdue_stats_detail
        $stm = $this->db->prepare("INSERT INTO stats_raw_data (id,data) VALUES (:id, :data)");
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

    public function removeStatsRawData($id) {
        // $data => php array
        // overdue_stats_detail
        $stm = $this->db->prepare("DELETE FROM stats_raw_data WHERE id = :id");
        $stm->bindParam(':id', $id);
        $ret = $stm->execute();
        if (!$ret) {
            global $log;
            $log->error(__METHOD__.": 移除統計 RAW DATA 失敗【".$id.", ".$stm->getSQL()."】");
        }
        return $ret;
    }

    public function getStatsRawData($id) {
        $data = $this->db->querySingle("SELECT data from stats_raw_data WHERE id = '$id'");
        return empty($data) ? false : unserialize($data);
    }
    /**
     * AP connection count
     */
    public function getLatestAPConnHistory($ap_ip, $all = 'true') {
        global $log;
        $db_path = DB_DIR.DIRECTORY_SEPARATOR.'stats_ap_conn_AP'.explode('.', $ap_ip)[3].'.db';
        $ap_db = new SQLite3($db_path);
        // get latest batch log_time
        $latest_log_time = $ap_db->querySingle("SELECT DISTINCT log_time from ap_conn_history ORDER BY log_time DESC");
        if($stmt = $ap_db->prepare('SELECT * FROM ap_conn_history WHERE log_time = :log_time ORDER BY count DESC')) {
            $stmt->bindParam(':log_time', $latest_log_time);
            $result = $stmt->execute();
            $return = [];
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

    public function addAPConnHistory($log_time, $ap_ip, $records) {
        global $log;
        // clean data ... 
        $processed = array();
        foreach ($records as $record) {
            $pair = explode(',',  $record);
            $count = $pair[0];
            $est_ip = $pair[1];
            if (empty($est_ip) || empty($count)) {
                $log->warning("IP或是記數為空值，將略過此筆紀錄。($est_ip, $count)");
                continue;
            }
            if (array_key_exists($est_ip, $processed)) {
                $processed[$est_ip] += $count;
            } else {
                $processed[$est_ip] = $count;
            }
        }
        // inst into db
        $db_path = DB_DIR.DIRECTORY_SEPARATOR.'stats_ap_conn_AP'.explode('.', $ap_ip)[3].'.db';
        $ap_db = new SQLite3($db_path);
        $latest_batch = $ap_db->querySingle("SELECT DISTINCT batch from ap_conn_history ORDER BY batch DESC");
        $success = 0;
        foreach ($processed as $est_ip => $count) {
            $retry = 0;
            $stm = $ap_db->prepare("INSERT INTO ap_conn_history (log_time,ap_ip,est_ip,count,batch) VALUES (:log_time, :ap_ip, :est_ip, :count, :batch)");
            while ($stm === false) {
                if ($retry > 3) return $success;
                usleep(random_int(100000, 500000));
                $stm = $ap_db->prepare("INSERT INTO ap_conn_history (log_time,ap_ip,est_ip,count,batch) VALUES (:log_time, :ap_ip, :est_ip, :count, :batch)");
                $retry++;
            }
            $stm->bindParam(':log_time', $log_time);
            $stm->bindParam(':ap_ip', $ap_ip);
            $stm->bindParam(':est_ip', $est_ip);
            $stm->bindParam(':count', $count);
            $stm->bindValue(':batch', $latest_batch + 1);
            if ($stm->execute() === FALSE) {
                $log->warning(__METHOD__.": 更新資料庫(${db_path})失敗。($log_time, $ap_ip, $est_ip, $count)");
            } else {
                $success++;
            }
        }
        return $success;
    }

    public function wipeAPConnHistory($ip_end) {
        global $log;
        $one_day_ago = date("YmdHis", time() - 24 * 3600);
        $ap_db = new SQLite3(DB_DIR.DIRECTORY_SEPARATOR.'stats_ap_conn_AP'.$ip_end.'.db');
        $stm = $ap_db->prepare("DELETE FROM ap_conn_history WHERE log_time < :time");
        $stm->bindParam(':time', $one_day_ago, SQLITE3_TEXT);
        $ret = $stm->execute();
        if (!$ret) {
            $log->error(__METHOD__.": stats_ap_conn_AP".$ip_end.".db 移除一天前資料失敗【".$one_day_ago.", ".$this->db->lastErrorMsg()."】");
        }
        return $ret;
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

    public function addAPConnection($log_time, $ip, $site, $count) {
        // $post_data => ["log_time" => '20200904094300', "ip" => '220.1.35.123', "site" => 'HB', "count" => '10']
        $stm = $this->db->prepare("INSERT INTO ap_connection (log_time,ip,site,count) VALUES (:log_time, :ip, :site, :count)");
        $stm->bindParam(':log_time', $log_time);
        $stm->bindParam(':ip', $ip);
        $stm->bindParam(':site', $site);
        $stm->bindValue(':count', $count);
        return $stm->execute();
    }

    public function wipeAPConnection() {
        global $log;
        $one_day_ago = date("YmdHis", time() - 24 * 3600);
        $stm = $this->db->prepare("DELETE FROM ap_connection WHERE log_time < :time");
        $stm->bindParam(':time', $one_day_ago, SQLITE3_TEXT);
        $ret = $stm->execute();
        if (!$ret) {
            $log->error(__METHOD__.": 移除一天前資料失敗【".$one_day_ago.", ".$this->db->lastErrorMsg()."】");
        }
        return $ret;
    }

    public function getLastestAPConnection($count = 11) {
        if($stmt = $this->db->prepare('SELECT * FROM ap_connection ORDER BY log_time DESC LIMIT :limit')) {
            $stmt->bindValue(':limit', $count, SQLITE3_INTEGER);
            $result = $stmt->execute();
            $return = [];
            while($row = $result->fetchArray(SQLITE3_ASSOC)) {
                $return[] = $row;
            }
            return $return;
        } else {
            global $log;
            $log->error(__METHOD__.": 取得最新 $count 筆失敗！");
        }
        return false;
    }

    public function getAPConnectionHXHistory($site, $count, $extend = true) {
        if($stmt = $this->db->prepare('SELECT * FROM ap_connection WHERE site = :site ORDER BY log_time DESC LIMIT :limit')) {
            $stmt->bindParam(':site', $site, SQLITE3_TEXT);
            $stmt->bindValue(':limit', $extend ? $count * 4 : $count, SQLITE3_INTEGER);
            $result = $stmt->execute();
            $return = [];

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
            $log->error(__METHOD__.": 取得${site}歷史資料失敗！");
        }
        return false;
    }
    
}
?>
