<?php
require_once('init.php');

class SQLiteAPConnectionHistory {
    public static function removeDBFiles() {
        Logger::getInstance()->info(__METHOD__.": 清除 AP Connection History DB 檔案 ... ");
        $dir = DB_DIR . DIRECTORY_SEPARATOR;
        $pattern = 'stats_ap_conn_AP*';
        $files = glob($dir . $pattern);
        foreach ($files as $file) {
            if (is_file($file)) {
                if (unlink($file)) {
                    Logger::getInstance()->info(__METHOD__.": Deleted: $file");
                } else {
                    Logger::getInstance()->warning(__METHOD__.": Failed to delete: $file");
                }
            }
        }
        return false;
    }

    public static function cleanOneDayAgoAll() {
        $postfixs = System::getInstance()->getWebAPPostfix();
        foreach ($postfixs as $postfix) {
            $ap = new SQLiteAPConnectionHistory($postfix);
            $ap->cleanDaysAgo(1);
        }
    }

    private $ap_ip;
    private $db;

    private function beginImmediateTransaction() {
        $this->db->exec("BEGIN IMMEDIATE TRANSACTION");
    }

    private function commit() {
        $this->db->exec("COMMIT");
    }

    private function rollback() {
        $this->db->exec("ROLLBACK");
    }

	function __construct($ip) {
        $this->ap_ip = $ip;
        $path = SQLiteDBFactory::getAPConnectionHistoryDB($ip);
        $this->db = new SQLite3($path);
        $this->db->exec("PRAGMA cache_size = 100000");
        $this->db->exec("PRAGMA temp_store = MEMORY");
    }

	function __destruct() {
        $this->db->close();
    }

    public function getLatest($all = 'true') {
        // get latest batch log_time
        $latest_log_time = $this->db->querySingle("SELECT DISTINCT log_time from ap_conn_history ORDER BY log_time DESC");
        if($stmt = $this->db->prepare('SELECT * FROM ap_conn_history WHERE log_time = :log_time ORDER BY count DESC')) {
            $stmt->bindParam(':log_time', $latest_log_time);
            $result = $stmt->execute();
            $return = [];
            if ($result === false) return $return;
            $ipr = new IPResolver();
            while($row = $result->fetchArray(SQLITE3_ASSOC)) {
                if ($all == 'false' && $ipr->isServerIP($row['est_ip'])) continue;
                // turn est_ip to user
                $name = $ipr->resolve($row['est_ip']);
                $row['name'] = empty($name) ? $row['est_ip'] : $name;
                $return[] = $row;
            }
            return $return;
        } else {
            Logger::getInstance()->error(__METHOD__.": 取得 $this->ap_ip 最新紀錄資料失敗！");
        }
        return false;
    }

    public function get($est_ip, $count, $extend = true) {
        if($stmt = $this->db->prepare('SELECT * FROM ap_conn_history WHERE est_ip = :ip ORDER BY log_time DESC LIMIT :limit')) {
            $stmt->bindParam(':ip', $est_ip);
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
            Logger::getInstance()->error(__METHOD__.": 取得 $est_ip 歷史紀錄資料失敗！ (${db_path})");
        }
        return false;
    }

    public function add($log_time, $processed) {
        $success = 0;
        try {
            $latest_batch = $this->db->querySingle("SELECT DISTINCT batch from ap_conn_history ORDER BY batch DESC");
            foreach ($processed as $est_ip => $count) {
                $this->beginImmediateTransaction();
                if ($stm = @$this->db->prepare("INSERT INTO ap_conn_history (log_time,ap_ip,est_ip,count,batch) VALUES (:log_time, :ap_ip, :est_ip, :count, :batch)")) {
                    $stm->bindParam(':log_time', $log_time);
                    $stm->bindParam(':ap_ip', $this->ap_ip);
                    $stm->bindParam(':est_ip', $est_ip);
                    $stm->bindParam(':count', $count);
                    $stm->bindValue(':batch', $latest_batch + 1);

                    $retry = 0;
                    while (@$stm->execute() === FALSE) {
                        $this->commit();
                        if ($retry > 2) {
                            Logger::getInstance()->warning(__METHOD__.": 更新 $this->ap_ip 資料庫失敗。($log_time, $est_ip, $count)");
                            return $success;
                        }
                        $zzz_us = random_int(300000, 500000) * pow(2, $retry);
                        ++$retry;
                        Logger::getInstance()->warning(__METHOD__.": 嘗試新增 $this->ap_ip AP 歷史資料失敗 ".number_format($zzz_us / 1000000, 3)." 秒後重試(".$retry.")。");
                        usleep($zzz_us);
                        $this->beginImmediateTransaction();
                    }
                    $this->commit();
                    $success++;
                } else {
                    Logger::getInstance()->warning(__METHOD__.": 準備資料庫 statement [ INSERT INTO ap_conn_history (log_time,ap_ip,est_ip,count,batch) VALUES (:log_time, :ap_ip, :est_ip, :count, :batch) ] 失敗。($log_time, $this->ap_ip, $est_ip, $count)");
                }
            }
        } catch (Exception $ex) {
            Logger::getInstance()->warning(__METHOD__.": ".$ex->getMessage());
            $this->rollback();
        }
        return $success;
    }

    public function cleanDaysAgo($days = 1) {
        $one_day_ago = date("YmdHis", time() - $days * 24 * 3600);
        $result = false;
        try {
            if ($stm = $this->db->prepare("DELETE FROM ap_conn_history WHERE log_time < :time")) {
                $stm->bindParam(':time', $one_day_ago, SQLITE3_TEXT);
                $this->beginImmediateTransaction();
                $result = $stm->execute();
                $this->commit();
                if (!$result) {
                    Logger::getInstance()->error(__METHOD__.": 移除一天前資料失敗【".$one_day_ago.", ".$this->db->lastErrorMsg()."】");
                }
                Logger::getInstance()->info(__METHOD__.": $this->ap_ip 移除一天前連線資料成功。");
                return $result;
            }
        } catch (Exception $ex) {
            Logger::getInstance()->warning(__METHOD__.": ".$ex->getMessage());
            $this->rollback();
            return $result;
        }
        Logger::getInstance()->warning(__METHOD__.": 準備資料庫 statement [ DELETE FROM ap_conn_history WHERE log_time < :time ] 失敗。($this->ap_ip)");
        return $result;
    }
}
