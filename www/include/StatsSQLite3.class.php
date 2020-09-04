<?php
require_once('init.php');

define('DEF_SQLITE_DB', ROOT_DIR.DIRECTORY_SEPARATOR."assets".DIRECTORY_SEPARATOR."db".DIRECTORY_SEPARATOR."LAH.db");
define('TEMPERATURE_SQLITE_DB', ROOT_DIR.DIRECTORY_SEPARATOR."assets".DIRECTORY_SEPARATOR."db".DIRECTORY_SEPARATOR."Temperature.db");
define('AP_SQLITE_DB', ROOT_DIR.DIRECTORY_SEPARATOR."assets".DIRECTORY_SEPARATOR."db".DIRECTORY_SEPARATOR."Temperature.db");
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
     * AP connection
     */
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
        $one_hour_ago = date("YmdHis", time() - 3600);

        // $log->info("60分鐘前時間：$ten_mins_ago");

        $stm = $this->db->prepare("DELETE FROM ap_connection WHERE log_time < :time");
        $stm->bindParam(':time', $one_hour_ago);
        $ret = $stm->execute();
        if ($ret) {
            // $deleted_count = $this->db->changes();
            // if ($deleted_count > 0) $log->info("成功刪除過期 $deleted_count 筆 ap_connection 統計紀錄。");
        } else {
            $log->error(__METHOD__.": 移除60分鐘前資料失敗【".$one_hour_ago.", ".$this->db->lastErrorMsg()."】");
        }
        return $ret;
    }
    
}
?>
