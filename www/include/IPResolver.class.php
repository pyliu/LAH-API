<?php
require_once('init.php');
require_once('SQLiteUser.class.php');
require_once('SQLiteDBFactory.class.php');

class IPResolver {
    private static $server_map = array(
        '220.1.34.43' => 'G08儲存',
        '220.1.34.3' => 'AD主機',
        '220.1.34.212' => 'PS謄本主機',
        '220.1.34.211' => '建物平面圖同步主機',
        '220.1.34.214' => 'PS權狀主機',
        '220.1.34.204' => '次AD主機',
        '220.1.34.205' => 'AP1登記(1)',
        '220.1.34.206' => 'AP2登記(2)',
        '220.1.34.156' => 'AP8地價',
        '220.1.34.207' => 'AP3測量(1)',
        '220.1.34.62' => 'AP6測量(2)',
        '220.1.34.118' => 'AP15工作站',
        '220.1.34.161' => 'AP61跨域',
        '220.1.34.60' => 'AP14外掛',
        '220.1.34.50' => '資料庫HA-MASTER',
        '220.1.34.51' => '資料庫HA-SLAVE',
        '220.1.33.5' => '局同步異動',
        '220.5.61.33' => '內政部主機'
    );
    private static $remote_eps = array(
        '220.1.34.2' => '資料庫',
        '220.1.33.71' => '地政局',
        '220.1.35.123' => '中壢跨域',
        '220.1.37.246' => '楊梅跨域',
        '220.1.38.30' => '蘆竹跨域',
        '220.1.34.161' => '桃園跨域',
        '220.1.36.45' => '大溪跨域',
        '220.1.39.57' => '八德跨域',
        '220.1.40.33' => '平鎮跨域',
        '220.1.41.20' => '龜山跨域',
        '220.2.33.85' => '自強櫃台',
        '220.2.33.84' => '自強審查',
        '220.2.33.89' => '普義櫃台1',
        '220.2.33.90' => '普義審查',
        '220.2.33.93' => '普義櫃台2',
        '220.2.33.44' => '觀音櫃台',
        '220.2.33.43' => '觀音審查'
    );

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

        return true;
    }

    private function prepareArray(&$stmt) {
        $result = $stmt->execute();
        $return = [];
        while($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $return[] = $row;
        }
        return $return;
    }

    function __construct() {
        $this->db = new SQLite3(SQLiteDBFactory::getIPResolverDB());
        $this->db->exec("PRAGMA cache_size = 100000");
        $this->db->exec("PRAGMA temp_store = MEMORY");
        $this->db->exec("BEGIN TRANSACTION");
    }

    function __destruct() {
        $this->db->exec("END TRANSACTION");
        $this->db->close();
    }

    public function addIpEntry($post) {
        $stm = $this->db->prepare("
            REPLACE INTO IPResolver ('ip', 'added_type', 'entry_type', 'entry_desc', 'entry_id', 'timestamp', 'note')
            VALUES (:ip, :added_type, :entry_type, :entry_desc, :entry_id, :timestamp, :note)
        ");
        if ($this->bindParams($stm, $post)) {
            return $stm->execute() === FALSE ? false : true;
        }
        return false;
    }

    public function removeIpEntry($post) {
        if ($stm = $this->db->prepare("
            DELETE * FROM IPResolver WHERE ip = :bv_ip AND added_type = :bv_added_type AND entry_type = :bv_entry_type
        ")) {
            
            $stm->bindParam(':bv_ip', $post['ip']);
            $stm->bindParam(':bv_added_type', $post['added_type']);
            $stm->bindParam(':bv_entry_type', $post['entry_type']);
            return $stm->execute() === FALSE ? false : true;
        }
        Logger::getInstance()->warning(__METHOD__.": 無法執行 DELETE FROM IPResolver WHERE ip = '".$post['ip']."' AND added_type = '".$post['added_type']."' AND entry_type = '".$post['entry_type']."' SQL描述。");
        return false;
    }
    
    public function getIPEntry($ip, $threadhold = 31556926) {
        // default get entry within a year
        $now = time();
        $month_ago = $now - 2629743;
        $year_ago = $now - 31556926;
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
        // default get entry within a year
        $now = time();
        $month_ago = $now - 2629743;
        $year_ago = $now - 31556926;
        $ondemand = $now - $threadhold;
        if($stmt = $this->db->prepare("SELECT * FROM IPResolver WHERE timestamp > :bv_ondemand OR added_type <> 'DYNAMIC'")) {
            $stmt->bindParam(':bv_ondemand', $ondemand);
            return $this->prepareArray($stmt);
        } else {
            Logger::getInstance()->warning(__METHOD__.": 無法執行「SELECT * FROM IPResolver WHERE timestamp > $ondemand OR added_type <> 'DYNAMIC'」SQL描述。");
        }
        return array();
    }

    public static function packUserData($data) {
        $unit = IPResolver::parseUnit($data['note']);
        Logger::getInstance()->info(__METHOD__.': 打包找到的資料 ('.$data['entry_id'].', '.$data['entry_desc'].', '.$unit.', '.$data['ip'].')');
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
            'title' => '',
            'work' => '',
            'exam' => '',
            'education' => '',
            'onboard_date' => '110/06/01',
            'offboard_date' => '',
            'birthday' => '066/05/23'
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

    public static function resolve($ip) {
        if (filter_var($ip, FILTER_VALIDATE_IP)) {
            // query IPResolver table
            $db = new SQLite3(SQLiteDBFactory::getIPResolverDB());
            if($stmt = $db->prepare("SELECT * FROM IPResolver WHERE ip = :ip")) {
                $stmt->bindParam(':ip', $ip);
                $result = $stmt->execute();
                $row = $result->fetchArray(SQLITE3_ASSOC);
                if (is_array($row)) {
                    return $row['entry_desc'];
                } else {
                    Logger::getInstance()->warning(__METHOD__.": 找不到 $ip 對應資料。(IPResolver table, IPResolver.db)");
                }
            }

            // find user by ip address via previous user table in dimension.db
            $sqlite_user = new SQLiteUser();
            $user_data = $sqlite_user->getUserByIP($ip);
            if (array_key_exists(0, $user_data)) {
                return $user_data[0]['name'];
            } else {
                Logger::getInstance()->warning(__METHOD__.": 找不到 $ip 對應資料。(user table, dimension.db)");
            }

            // find hard coded entry
            if (array_key_exists($ip, IPResolver::$server_map)) {
                return IPResolver::$server_map[$ip];
            } else if (array_key_exists($ip, IPResolver::$remote_eps)) {
                return IPResolver::$remote_eps[$ip];
            }

            return '';
        } else {
            Logger::getInstance()->warning(__METHOD__.": Not a valid IP address. [$ip]");
        }
        return false;
    }

    public static function isServerIP($ip) {
        return array_key_exists($ip, IPResolver::$server_map);
    }
    
}
