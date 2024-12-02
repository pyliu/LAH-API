<?php
require_once('init.php');
require_once('SQLiteUser.class.php');
require_once('SQLiteDBFactory.class.php');
require_once('System.class.php');

class IPResolver {
    private $db;

    private function bindParams(&$stm, &$row) {
        if ($stm === false) {
            Logger::getInstance()->error(__METHOD__.": bindUserParams because of \$stm is false.");
            return false;
        }

        $stm->bindParam(':ip', $row['ip']);
        $stm->bindParam(':added_type', $row['added_type']);
        $stm->bindParam(':entry_type', $row['entry_type']);
        $stm->bindParam(':entry_desc', $row['entry_desc']);
        $stm->bindParam(':entry_id' , $row['entry_id']);
        $stm->bindParam(':timestamp', $row['timestamp']);
        $stm->bindParam(':note', $row['note']);
        // for update
        if (!empty($row['orig_ip'])) {
            $stm->bindParam(':orig_ip', $row['orig_ip']);
        }
        if (!empty($row['orig_added_type'])) {
            $stm->bindParam(':orig_added_type', $row['orig_added_type']);
        }
        if (!empty($row['orig_entry_type'])) {
            $stm->bindParam(':orig_entry_type', $row['orig_entry_type']);
        }

        return true;
    }

    private function prepareArray(&$stmt) {
        $result = $stmt->execute();
        $return = [];
        if ($result) {
            while($row = $result->fetchArray(SQLITE3_ASSOC)) {
                $return[] = $row;
            }
        } else {
            Logger::getInstance()->warning(__CLASS__."::".__METHOD__.": execute SQL unsuccessfully.");
        }
        return $return;
    }

    function __construct() {
        $this->db = new SQLite3(SQLiteDBFactory::getIPResolverDB());
        $this->db->exec("PRAGMA cache_size = 100000");
        $this->db->exec("PRAGMA temp_store = MEMORY");
        // $this->db->exec("BEGIN TRANSACTION");
    }

    function __destruct() {
        // $this->db->exec("END TRANSACTION");
        $this->db->close();
    }

    public function addIpEntry($post) {
        try {
            $stm = $this->db->prepare("
                REPLACE INTO IPResolver ('ip', 'added_type', 'entry_type', 'entry_desc', 'entry_id', 'timestamp', 'note')
                VALUES (:ip, :added_type, :entry_type, :entry_desc, :entry_id, :timestamp, :note)
            ");
            $this->db->exec("BEGIN IMMEDIATE TRANSACTION");
            $result = false;
            if ($this->bindParams($stm, $post)) {
                $result = $stm->execute() === FALSE ? false : true;
                // SQLite 的設計初衷並非為了高並行寫入動作，所以我實作重試機制以減低寫入失敗的情形
                $retry = 0;
                while ($result === false && $retry < 5) {
                    // like TCP congestion retry delay ... 
                    $zzz_us = random_int(100000, 500000) * pow(2, $retry);
                    Logger::getInstance()->warning(__METHOD__.": ".$post['ip']." 寫入 IPResolver 失敗 ".number_format($zzz_us / 1000000, 3)." 秒後重試。(".($retry + 1).")");
                    usleep($zzz_us);
                    $result = $stm->execute() === FALSE ? false : true;
                    $retry++;
                }
            }
            
            // Execute COMMIT/ROLLBACK will end the transaction
            $this->db->exec("COMMIT");
            return $result;
        } catch (Exception $e) {
            $this->db->exec("ROLLBACK");
            Logger::getInstance()->error(__METHOD__.': '.$e->getMessage());
        }
        return false;
    }

    public function editIpEntry($post) {
        $result = $this->removeIpEntry(array(
            'ip' => $post['orig_ip'],
            'added_type' => $post['orig_added_type'],
            'entry_type' => $post['orig_entry_type']
        ));
        if ($result) {
            return $this->addIpEntry($post);
        }
        return false;
    }

    public function removeIpEntry($post) {
        try {
            $sql = "DELETE FROM IPResolver WHERE ip = '".$post['ip']."' AND added_type = '".$post['added_type']."' AND entry_type = '".$post['entry_type']."'";
            $result = false;
            if ($stm = $this->db->prepare($sql)) {
                $this->db->exec("BEGIN IMMEDIATE TRANSACTION");

                $result = $stm->execute() === FALSE ? false : true;
                // SQLite 的設計初衷並非為了高並行寫入動作，所以我實作重試機制以減低寫入失敗的情形
                $retry = 0;
                while ($result === false && $retry < 5) {
                    // like TCP congestion retry delay ... 
                    $zzz_us = random_int(100000, 500000) * pow(2, $retry);
                    Logger::getInstance()->warning(__METHOD__.": ".$post['ip']." 寫入 IPResolver 失敗 ".number_format($zzz_us / 1000000, 3)." 秒後重試。(".($retry + 1).")");
                    usleep($zzz_us);
                    $retry++;
                    $result = $stm->execute() === FALSE ? false : true;
                }
            }
            // Execute COMMIT/ROLLBACK will end the transaction
            $this->db->exec("COMMIT");
            return $result;
        } catch (Exception $e) {
            $this->db->exec("ROLLBACK");
            Logger::getInstance()->error(__METHOD__.': '.$e->getMessage());
        }
        Logger::getInstance()->warning(__METHOD__.": 無法執行 「".$sql."」 SQL描述。");
        return false;
    }
    
    public function getIPEntry($ip, $threadhold = 31556926) {
        /**
         * default get entry within a year
         * a year: 31556926
         * a month: 2629743
         * a week: 604800
         **/
        $now = time();
        $ondemand = $now - $threadhold;
        if($stmt = $this->db->prepare("SELECT * FROM IPResolver WHERE timestamp > :bv_ondemand AND ip = :bv_ip")) {
            $stmt->bindParam(':bv_ondemand', $ondemand);
            $stmt->bindParam(':bv_ip', $ip);
            return $this->prepareArray($stmt);
        } else {
            Logger::getInstance()->warning(__METHOD__.": 無法執行「SELECT * FROM IPResolver WHERE timestamp > $ondemand AND ip = '$ip'」SQL描述。");
        }
        return array();
    }

    public function getIPEntries($threadhold = 31556926) {
        /**
         * default get entry within a year
         * a year: 31556926
         * a month: 2629743
         * a week: 604800
         **/
        $now = time();
        $ondemand = $now - $threadhold;
        if($stmt = $this->db->prepare("SELECT * FROM IPResolver WHERE timestamp > :bv_ondemand OR added_type <> 'DYNAMIC' ORDER BY timestamp DESC")) {
            $stmt->bindParam(':bv_ondemand', $ondemand);
            return $this->prepareArray($stmt);
        } else {
            Logger::getInstance()->warning(__METHOD__.": 無法執行「SELECT * FROM IPResolver WHERE timestamp > $ondemand OR added_type <> 'DYNAMIC' ORDER BY timestamp DESC」SQL描述。");
        }
        return array();
    }

    public function getDynamicIPEntries($threadhold = 2629743) {
        /**
         * default get entry within a year
         * a year: 31556926
         * a month: 2629743
         * a week: 604800
         **/
        $now = time();
        $ondemand = $now - $threadhold;
        if($stmt = $this->db->prepare("SELECT * FROM IPResolver WHERE timestamp > :bv_ondemand AND added_type = 'DYNAMIC' ORDER BY timestamp DESC")) {
            $stmt->bindParam(':bv_ondemand', $ondemand);
            return $this->prepareArray($stmt);
        } else {
            Logger::getInstance()->warning(__METHOD__.": 無法執行「SELECT * FROM IPResolver WHERE timestamp > $ondemand AND added_type = 'DYNAMIC' ORDER BY timestamp DESC」SQL描述。");
        }
        return array();
    }

    public function removeDynamicIPEntries($threadhold = 604800) {
        /**
         * default remove entries within a week
         * a year: 31556926
         * a month: 2629743
         * a week: 604800
         **/
        $now = time();
        $ondemand = $now - $threadhold;
        if($stmt = $this->db->prepare("DELETE FROM IPResolver WHERE timestamp < :bv_ondemand AND added_type = 'DYNAMIC'")) {
            $stmt->bindParam(':bv_ondemand', $ondemand);
            return $stmt->execute() === FALSE ? false : true;
        } else {
            Logger::getInstance()->warning(__METHOD__.": 無法執行「DELETE FROM IPResolver WHERE timestamp < :bv_ondemand AND added_type = 'DYNAMIC'」SQL描述。");
        }
        return false;
    }
    
    public function getStaticIPEntries() {
        if($stmt = $this->db->prepare("SELECT * FROM IPResolver WHERE added_type = 'STATIC' ORDER BY timestamp DESC")) {
            return $this->prepareArray($stmt);
        } else {
            Logger::getInstance()->warning(__METHOD__.": 無法執行「SELECT * FROM IPResolver WHERE added_type = 'STATIC' ORDER BY timestamp DESC」SQL描述。");
        }
        return array();
    }

    public static function packUserData($data) {
        $unit = IPResolver::parseUnit($data['note']);
        // Logger::getInstance()->info(__METHOD__.': 打包找到的資料 ('.$data['entry_id'].', '.$data['entry_desc'].', '.$unit.', '.$data['ip'].')');
        $site_code = System::getInstance()->getSiteCode();
        $auto_admin = $data['entry_id'] === $site_code.'ADMIN';
        return array(
            'id' => $data['entry_id'],
            'name' => $data['entry_desc'],
            'unit' => $unit,
            'ip' => $data['ip'],
            'sex' => 0,
            'addr' => '',
            'tel' => '',
            'ext' => '',
            'cell' => '',
            'title' => $auto_admin ? '系管人員' : '',
            'work' => $auto_admin ? '智慧控管系統管理' : '',
            'exam' => '',
            'education' => $auto_admin ? '北科大資工所' : '',
            'onboard_date' => $auto_admin ? '110/06/01' : '',
            'offboard_date' => '',
            'birthday' => $auto_admin ? '066/05/23' : '',
            'authority' => $data['authority'] ?? 0
        );
    }

    public static function parseUnit($note) {
        $unit = '未分配';
        if (!empty($note)) {
            $key = explode(' ', $note)[1];
            switch ($key) {
                case 'inf':
                    $unit = '資訊課';
                    break;
                case 'reg':
                    $unit = '登記課';
                    break;
                case 'adm':
                    $unit = '行政課';
                    break;
                case 'sur':
                    $unit = '測量課';
                    break;
                case 'val':
                    $unit = '地價課';
                    break;
                case 'acc':
                    $unit = '會計室';
                    break;
                case 'hr':
                    $unit = '人事室';
                    break;
                case 'supervisor':
                    $unit = '主任室';
                    break;
            }
        }
        return $unit;
    }

    public function resolve($ip) {
        if (filter_var($ip, FILTER_VALIDATE_IP)) {
            // query IPResolver table
            if($stmt = $this->db->prepare("SELECT * FROM IPResolver WHERE ip = :ip")) {
                $stmt->bindParam(':ip', $ip);
                $result = $stmt->execute();
                $row = $result->fetchArray(SQLITE3_ASSOC);
                if (is_array($row) && !empty($row['entry_desc'])) {
                    return $row['entry_desc'];
                } else {
                    // Logger::getInstance()->warning(__METHOD__.": 找不到 $ip 對應資料。(IPResolver table, IPResolver.db)");
                }
            }

            // find user by ip address via previous user table in dimension.db
            $sqlite_user = new SQLiteUser();
            $user_data = $sqlite_user->getUserByIP($ip);
            if (array_key_exists(0, $user_data)) {
                return $user_data[0]['name'];
            } else {
                // Logger::getInstance()->warning(__METHOD__.": 找不到 $ip 對應資料。(user table, dimension.db)");
            }

            return '';
        } else if ($ip !== 'JBOSS_CPU_USAGE') {
            Logger::getInstance()->warning(__METHOD__.": ${ip}不是一個正確的IPv4位址。");
        }
        return false;
    }

    public function isServerIP($ip) {
        $ret = $this->db->querySingle("SELECT ip FROM IPResolver WHERE added_type = 'STATIC' AND entry_type = 'SERVER' AND ip = '".trim($ip)."'");
        return !empty($ret);
    }
    
}
