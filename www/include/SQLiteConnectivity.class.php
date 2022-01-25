<?php
require_once('init.php');
require_once('SQLiteDBFactory.class.php');
require_once('Ping.class.php');

class SQLiteConnectivity {
    private $db;
    private $path;

    private function addConnectivityStatus($log_time, $tgt_ip, $tgt_port, $latency) {
        $stm = $this->db->prepare("REPLACE INTO connectivity (log_time,target_ip,status,latency) VALUES (:log_time, :target_ip, :status, :latency)");
        if ($stm === false) {
            Logger::getInstance()->warning(__METHOD__.": 準備資料庫 statement [ REPLACE INTO connectivity (log_time,target_ip,status,latency) VALUES (:log_time, :target_ip, :status, :latency) ] 失敗。($log_time, $tgt_ip:$tgt_port, $latency)");
        } else {
            $stm->bindParam(':log_time', $log_time);
            $stm->bindValue(':target_ip', $tgt_ip.":".$tgt_port);
            $stm->bindValue(':status', empty($latency) ? 'DOWN' : 'UP');
            $stm->bindValue(':latency', empty($latency) ? 1000.0 : $latency);   // default ping timeout is 1s
            if ($stm->execute() !== FALSE) {
                return true;
            } else {
                Logger::getInstance()->warning(__METHOD__.": 更新資料庫(".$this->path.")失敗。($log_time, $tgt_ip, $tgt_port, $latency)");
            }
        }

        return false;
    }

    private function pingAndSave($arr) {
        $ip = $arr['ip'];
        $port = $arr['port'];
        if (filter_var($ip, FILTER_VALIDATE_IP)) {
            $log_time = date("YmdHis");
            $ping = new Ping($ip);
            $latency = 0;
            if (empty($port)) {
                $latency = $ping->ping();
            } else {
                $ping->setPort($port);
                $latency = $ping->ping('fsockopen');
                if (empty($latency)) {
                    $latency = $ping->ping('socket');
                }
            }
            return $this->addConnectivityStatus($log_time, $ip, $port, $latency);
        }
        return false;
    }

    function __construct() {
        $this->path = SQLiteDBFactory::getConnectivityDB();
        $this->db = new SQLite3($this->path);
        $this->db->exec("PRAGMA cache_size = 100000");
        $this->db->exec("PRAGMA temp_store = MEMORY");
        $this->db->exec("BEGIN TRANSACTION");
    }

    function __destruct() {
        $this->db->exec("END TRANSACTION");
        $this->db->close();
    }

    public function removeTarget($data) {
        $sql = "DELETE FROM target WHERE ip = :bv_ip AND port = :bv_port AND name = :bv_name AND monitor = :bv_monitor";
        $stm = $this->db->prepare($sql);
        if ($stm === false) {
            Logger::getInstance()->warning(__METHOD__.": 準備資料庫 statement [ $sql ] 失敗。");
        } else {
            $stm->bindParam(":bv_ip", $data["ip"]);
            $stm->bindParam(":bv_port", $data["port"]);
            $stm->bindParam(":bv_name", $data["name"]);
            $stm->bindParam(":bv_monitor", $data["monitor"]);
            $result = $stm->execute() === FALSE ? false : true;
            if ($result) {
                Logger::getInstance()->info(__METHOD__.": 刪除監控標的成功。(".implode(', ', $data).")");
            } else {
                Logger::getInstance()->warning(__METHOD__.": 刪除監控標的失敗。");
                Logger::getInstance()->warning(print_r($data, true));
            }
            return $result;
        }
        return false;
    }

    public function replaceTarget($data) {
        $sql = "REPLACE INTO target ('ip', 'port', 'name', 'monitor', 'note') VALUES (:bv_ip, :bv_port, :bv_name, :bv_monitor, :bv_note)";
        $stm = $this->db->prepare($sql);
        if ($stm === false) {
            Logger::getInstance()->warning(__METHOD__.": 準備資料庫 statement [ $sql ] 失敗。");
        } else {
            $stm->bindParam(":bv_ip", $data["ip"]);
            $stm->bindParam(":bv_port", $data["port"]);
            $stm->bindParam(":bv_name", $data["name"]);
            $stm->bindParam(":bv_monitor", $data["monitor"]);
            $stm->bindParam(":bv_note", $data["note"]);
            $result = $stm->execute() === FALSE ? false : true;
            if ($result) {
                Logger::getInstance()->info(__METHOD__.": 更新監控標的成功。(".implode(', ', $data).")");
            } else {
                Logger::getInstance()->warning(__METHOD__.": 更新監控標的失敗。");
                Logger::getInstance()->warning(print_r($data, true));
            }
            return $result;
        }
        return false;
    }

    public function editTarget($data, $edit_ip, $edit_port) {
        $sql = "UPDATE target SET ip = :bv_ip, port = :bv_port, name = :bv_name, monitor = :bv_monitor, note = :bv_note WHERE ip = :bv_edit_ip AND port = :bv_edit_port";
        $stm = $this->db->prepare($sql);
        if ($stm === false) {
            Logger::getInstance()->warning(__METHOD__.": 準備資料庫 statement [ $sql ] 失敗。");
        } else {
            $stm->bindParam(":bv_ip", $data["ip"]);
            $stm->bindParam(":bv_port", $data["port"]);
            $stm->bindParam(":bv_name", $data["name"]);
            $stm->bindParam(":bv_monitor", $data["monitor"]);
            $stm->bindParam(":bv_note", $data["note"]);
            $stm->bindParam(":bv_edit_ip", $edit_ip);
            $stm->bindParam(":bv_edit_port", $edit_port);
            $result = $stm->execute() === FALSE ? false : true;
            if ($result) {
                Logger::getInstance()->info(__METHOD__.": 更新監控標的成功。(".implode(', ', $data).")");
            } else {
                Logger::getInstance()->warning(__METHOD__.": 更新監控標的失敗。");
                Logger::getInstance()->warning(print_r($data, true));
            }
            return $result;
        }
        return false;
    }

    public function getTargets($active = true) {
        $sql = $active ? "SELECT * FROM target WHERE monitor = 'Y' ORDER BY name" : "SELECT * FROM target ORDER BY name";
        $stm = $this->db->prepare($sql);
        if ($stm === false) {
            Logger::getInstance()->warning(__METHOD__.": 準備資料庫 statement [ $sql ] 失敗。");
        } else {
            if ($result = $stm->execute()) {
                $return = array();
                if ($result === false) return $return;
                while($row = $result->fetchArray(SQLITE3_ASSOC)) {
                    $return[] = $row;
                }
                return $return;
            } else {
                Logger::getInstance()->warning(__METHOD__.": 取得檢測目標列表失敗。");
            }
        }
        return false;
    }

    public function check() {
        $tracking_targets = $this->getTargets();
        // generate the latest batch records
        foreach ($tracking_targets as $name => $row) {
            if (filter_var($row['ip'], FILTER_VALIDATE_IP)) {
                $this->pingAndSave($row);
            } else {
                Logger::getInstance()->warning(__METHOD__.": $name:".$row['ip']." is not a valid IP address.".(empty($row['port']) ? '' : ':'.$row['port']));
            }
        }
    }

    public function checkIP($ip = null, $port = 0) {
        if (filter_var($ip, FILTER_VALIDATE_IP)) {
            // single ep
            $this->pingAndSave(array('ip' => $ip, 'port' => $port));
        } else {
            Logger::getInstance()->warning(__METHOD__.": $ip".(empty($port) ? '' : ":$port")." is not valid.");
        }
    }

    public function getStatus($force = 'false') {
        if (($force === 'true' || $force === true) && !System::getInstance()->isMockMode()) {
            // generate the latest batch records
            $this->check();
        }
        $tracking_targets = $this->getTargets();
        $tracking_ips = array();
        foreach ($tracking_targets as $name => $row) {
            $tracking_ips[$name] = $row['ip'].":".$row['port'];
        }
        $return = array();
        if (empty($tracking_ips)) {
            Logger::getInstance()->warning(__METHOD__.": tracking ip array is empty.");
        } else {
            $in_statement = " IN ('".implode("','", $tracking_ips)."') ";
            if($stmt = $this->db->prepare('SELECT * FROM connectivity WHERE target_ip '.$in_statement.' ORDER BY ROWID DESC LIMIT :limit')) {
                $stmt->bindValue(':limit', count($tracking_targets), SQLITE3_INTEGER);
                $result = $stmt->execute();
                if ($result === false) return $return;
                while($row = $result->fetchArray(SQLITE3_ASSOC)) {
                    $return[] = $row;
                }
            } else {
                Logger::getInstance()->error(__METHOD__.": 取得 connectivity 最新紀錄資料失敗！ (".$this->path.")");
            }
        }
        return $return;
    }

    public function getIPStatus($ip, $force = 'false', $port = "") {
        if ($force === 'true' && !System::getInstance()->isMockMode()) {
            // generate the latest record for $ip
            $this->checkIP($ip, $port);
        }
        if($stmt = $this->db->prepare('SELECT * FROM connectivity WHERE target_ip = :ip ORDER BY ROWID DESC LIMIT :limit')) {
            $stmt->bindValue(':limit', 1, SQLITE3_INTEGER);
            $stmt->bindValue(':ip', $ip.":".$port);
            $result = $stmt->execute();
            if ($result === false) return array();
            return $result->fetchArray(SQLITE3_ASSOC);
        } else {
            Logger::getInstance()->error(__METHOD__.": 取得 $ip connectivity 最新紀錄資料失敗！ (".$this->path.")");
        }
        return false;
    }

    public function wipeHistory($days_ago = 1) {
        if ($stm = $this->db->prepare("DELETE FROM connectivity WHERE log_time < :time")) {
            $one_day_ago = date("YmdHis", time() - $days_ago * 24 * 3600);
            $stm->bindParam(':time', $one_day_ago, SQLITE3_TEXT);
            $ret = $stm->execute();
            Logger::getInstance()->info(__METHOD__.": ".$this->path." 移除一天前資料".($ret ? "成功" : "失敗")."【".$one_day_ago.", ".$this->db->lastErrorMsg()."】");
            return $ret;
        }
        Logger::getInstance()->warning(__METHOD__.": 準備資料庫 statement [ DELETE FROM connectivity WHERE log_time < :time ] 失敗。");
        return false;
    }

}
