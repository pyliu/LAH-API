<?php
require_once('init.php');
require_once('SQLiteDBFactory.class.php');
require_once('IPResolver.class.php');
require_once('System.class.php');

class StatsSQLite {
    private $db;
    private $querySingleFail = array(NULL, FALSE, array());

    private function beginImmediateTransation () {
        $this->db->exec("BEGIN IMMEDIATE TRANSACTION");
    }

    private function commit () {
        $this->db->exec("COMMIT");
    }

    private function rollback () {
        $this->db->exec("ROLLBACK");
    }

    function __construct() {
        $path = SQLiteDBFactory::getLAHDB();
        $this->db = new SQLite3($path);
        $this->db->exec("PRAGMA cache_size = 100000");
        $this->db->exec("PRAGMA temp_store = MEMORY");
        // $this->db->exec("BEGIN TRANSACTION");
    }

    function __destruct() {
        // $this->db->exec("END TRANSACTION");
        $this->db->close();
    }

    public function instTotal($id, $name, $total = 0) {
        $result = false;
        try {
            if ($stm = $this->db->prepare("INSERT INTO stats ('ID', 'NAME', 'TOTAL') VALUES (:id, :name, :total)")) {
                //$stm = $this->db->prepare("INSERT INTO stats set TOTAL = :total WHERE  ID = :id");
                $stm->bindValue(':total', intval($total));
                $stm->bindParam(':id', $id);
                $stm->bindParam(':name', $name);
                $this->beginImmediateTransation();
                $result = $stm->execute() === FALSE ? false : true;
                $this->commit();
                return $result;
            }
        } catch (Exception $ex) {
            Logger::getInstance()->warning(__METHOD__.": ".$ex->getMessage());
            $this->rollback();
            return $result;
        }
        Logger::getInstance()->warning(__METHOD__.": 準備資料庫 statement [ INSERT INTO stats ('ID', 'NAME', 'TOTAL') VALUES (:id, :name, :total) ] 失敗。($id, $name, $total)");
        return $result;
    }
    /**
     * Early LAH Stats
     */
    public function getTotal($id) {
        return $this->db->querySingle("SELECT TOTAL from stats WHERE ID = '$id'");
    }

    public function updateTotal($id, $total) {
        $result = false;
        try {
            if ($stm = $this->db->prepare("UPDATE stats set TOTAL = :total WHERE  ID = :id")) {
                $stm->bindValue(':total', intval($total));
                $stm->bindParam(':id', $id);
                $this->beginImmediateTransation();
                $result = $stm->execute() === FALSE ? false : true;
                $this->commit();
                return $result;
            }
        } catch (Exception $ex) {
            Logger::getInstance()->warning(__METHOD__.": ".$ex->getMessage());
            $this->rollback();
            return $result;
        }
        Logger::getInstance()->warning(__METHOD__.": 準備資料庫 statement [ UPDATE stats set TOTAL = :total WHERE  ID = :id ] 失敗。($id, $total)");
        return $result;
        
    }

    public function addNotificationCount($count = 1) {
        $total = $this->getTotal('notification_msg_count');
        // in case the entry not exists
        if (in_array($total, $this->querySingleFail)) {
            $this->instTotal('notification_msg_count', '桃園即時通訊息傳送統計');
            $total = 0;
        }
        $total += $count;
        $ret = $this->updateTotal('notification_msg_count', $total);
        Logger::getInstance()->info(__METHOD__.": notification_msg_count 計數器+ $count ，目前值為 $total 【".($ret ? "成功" : "失敗")."】");
    }

    public function addOverdueMsgCount($count = 1) {
        $total = $this->getTotal('overdue_msg_count') + $count;
        $ret = $this->updateTotal('overdue_msg_count', $total);
        Logger::getInstance()->info(__METHOD__.": overdue_msg_count 計數器+ $count ，目前值為 $total 【".($ret ? "成功" : "失敗")."】");
    }

    public function addOverdueStatsDetail($data) {
        // $data => ["ID" => HB0000, "RECORDS" => array, "DATETIME" => 2020-03-04 08:50:23, "NOTE" => XXX]
        // overdue_stats_detail
        $result = false;
        try {
            if ($stm = $this->db->prepare("INSERT INTO overdue_stats_detail (datetime,id,count,note) VALUES (:date, :id, :count, :note)")) {
                $stm->bindParam(':date', $data["DATETIME"]);
                $stm->bindParam(':id', $data["ID"]);
                $stm->bindValue(':count', count($data["RECORDS"]));
                $stm->bindParam(':note', $data["NOTE"]);
                $this->beginImmediateTransation();
                $result = $stm->execute();
                $this->commit();
                if (!$result) {
                    Logger::getInstance()->error(__METHOD__.": 新增逾期統計詳情失敗【".$stm->getSQL()."】");
                }
                return $result;
            }
        } catch (Exception $ex) {
            Logger::getInstance()->warning(__METHOD__.": ".$ex->getMessage());
            $this->rollback();
            return $result;
        }
        Logger::getInstance()->warning(__METHOD__.": 準備資料庫 statement [ INSERT INTO overdue_stats_detail (datetime,id,count,note) VALUES (:date, :id, :count, :note) ] 失敗。(".print_r($data, true).")");
        return $result;
    }

    public function addXcasesStats($data) {
        // $data => ["date" => "2020-03-04 10:10:10","found" => 2, "note" => XXXXXXXXX]
        // xcase_stats
        $result = false;
        try {
            if ($stm = $this->db->prepare("INSERT INTO xcase_stats (datetime,found,note) VALUES (:date, :found, :note)")) {
                $stm->bindParam(':date', $data["date"]);
                $stm->bindParam(':found', $data["found"]);
                $stm->bindParam(':note', $data["note"]);
                $this->beginImmediateTransation();
                $result = $stm->execute();
                $this->commit();
                Logger::getInstance()->info(__METHOD__.": 新增跨所註記遺失案件統計".($result ? "成功" : "失敗【".$stm->getSQL()."】")."。");
                // 更新 total counter
                $total = $this->getTotal('xcase_found_count') + $data["found"];
                $this->updateTotal('xcase_found_count', $total);
                Logger::getInstance()->info(__METHOD__.": xcase_found_count 計數器+".$data["found"]."，目前值為 ${total} 【".($ret ? "成功" : "失敗")."】");
                return $result;
            }
        } catch (Exception $ex) {
            Logger::getInstance()->warning(__METHOD__.": ".$ex->getMessage());
            $this->rollback();
            return $result;
        }
        Logger::getInstance()->warning(__METHOD__.": 準備資料庫 statement [ INSERT INTO xcase_stats (datetime,found,note) VALUES (:date, :found, :note) ] 失敗。(".print_r($data, true).")");
        return $result;
    }

    public function addBadSurCaseStats($data) {
        // $data => ["date" => "2020-03-04 10:10:10","found" => 2, "note" => XXXXXXXXX]
        // xcase_stats
        $result = false;
        try {
            if ($stm = $this->db->prepare("INSERT INTO found_bad_sur_case_stats (datetime,found,note) VALUES (:date, :found, :note)")) {
                $stm->bindParam(':date', $data["date"]);
                $stm->bindParam(':found', $data["found"]);
                $stm->bindParam(':note', $data["note"]);
                $this->beginImmediateTransation();
                $result = $stm->execute();
                $this->commit();
                Logger::getInstance()->info(__METHOD__.": 新增複丈問題案件統計".($result ? "成功" : "失敗【".$stm->getSQL()."】")."。");
                // 更新 total counter
                $total = $this->getTotal('bad_sur_case_found_count') + $data["found"];
                $ret = $this->updateTotal('bad_sur_case_found_count', $total);
                Logger::getInstance()->info(__METHOD__.": bad_sur_case_found_count 計數器+".$data["found"]."，目前值為 $total 【".($ret ? "成功" : "失敗")."】");
                return $result;
            }
        } catch (Exception $ex) {
            Logger::getInstance()->warning(__METHOD__.": ".$ex->getMessage());
            $this->rollback();
            return $result;
        }
        Logger::getInstance()->warning(__METHOD__.": 準備資料庫 statement [ INSERT INTO found_bad_sur_case_stats (datetime,found,note) VALUES (:date, :found, :note) ] 失敗。(".print_r($data, true).")");
        return $result;
    }

    public function addStatsRawData($id, $data) {
        // $data => php array
        // overdue_stats_detail
        $result = false;
        try {
            if ($stm = $this->db->prepare("INSERT INTO stats_raw_data (id,data) VALUES (:id, :data)")) {
                $param = serialize($data);
                $stm->bindParam(':data', $param);
                $stm->bindParam(':id', $id);
                $this->beginImmediateTransation();
                $result = $stm->execute();
                $this->commit();
                if (!$result) {
                    Logger::getInstance()->error(__METHOD__.": 新增統計 RAW DATA 失敗【".$id.", ".$stm->getSQL()."】");
                }
                return $result;
            }
        } catch (Exception $ex) {
            Logger::getInstance()->warning(__METHOD__.": ".$ex->getMessage());
            $this->rollback();
            return $result;
        }
        Logger::getInstance()->warning(__METHOD__.": 準備資料庫 statement [ INSERT INTO stats_raw_data (id,data) VALUES (:id, :data) ] 失敗。($id, ".print_r($data, true).")");
        return $result;
    }

    public function removeAllStatsRawData($year_month) {
        $result = false;
        try {
            if ($stm = $this->db->prepare("DELETE FROM stats_raw_data WHERE id LIKE '%_".$year_month."'")) {
                $this->beginImmediateTransation();
                $result = $stm->execute();
                $this->commit();
                if (!$result) {
                    Logger::getInstance()->error(__METHOD__.": 移除統計 RAW DATA 失敗【".$year_month.", ".$stm->getSQL()."】");
                }
                return $result;
            }
        } catch (Exception $ex) {
            Logger::getInstance()->warning(__METHOD__.": ".$ex->getMessage());
            $this->rollback();
            return $result;
        }
        Logger::getInstance()->warning(__METHOD__.": 準備資料庫 statement [ DELETE FROM stats_raw_data WHERE id LIKE '%_".$year_month."' ] 失敗。($year_month)");
        return $result;
    }

    public function removeStatsRawData($id) {
        // $data => php array
        // overdue_stats_detail
        $result = false;
        try {
            if ($stm = $this->db->prepare("DELETE FROM stats_raw_data WHERE id = :id")) {
                $stm->bindParam(':id', $id);
                $this->beginImmediateTransation();
                $result = $stm->execute();
                $this->commit();
                if (!$result) {
                    Logger::getInstance()->error(__METHOD__.": 移除統計 RAW DATA 失敗【".$id.", ".$stm->getSQL()."】");
                }
                return $result;
            }
        } catch (Exception $ex) {
            Logger::getInstance()->warning(__METHOD__.": ".$ex->getMessage());
            $this->rollback();
            return $result;
        }
        Logger::getInstance()->warning(__METHOD__.": 準備資料庫 statement [ DELETE FROM stats_raw_data WHERE id = :id ] 失敗。($id)");
        return $result;
    }

    public function getStatsRawData($id) {
        $data = $this->db->querySingle("SELECT data from stats_raw_data WHERE id = '$id'");
        return empty($data) ? false : unserialize($data);
    }
}
