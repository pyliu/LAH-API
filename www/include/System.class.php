<?php
require_once("init.php");
require_once('DynamicSQLite.class.php');
require_once('Ping.class.php');

define('DIMENSION_SQLITE_DB', ROOT_DIR.DIRECTORY_SEPARATOR."assets".DIRECTORY_SEPARATOR."db".DIRECTORY_SEPARATOR."dimension.db");

abstract class ROLE {
    const NORMAL = 1;
    const SUPER = 2;                    // 超級管理者
    const ADMIN = 3;                    // 管理者
    const CHIEF = 4;                    // 主管
    const RESEARCH_AND_EVALUATION = 5;  // 研考
    const GENERAL_AFFAIRS = 6;          // 總務
    const HUMAN_RESOURCE = 7;           // 人事
    const ACCOUNTING = 8;               // 會計
}

// could processed by bitwise operation
abstract class AUTHORITY {
    const NORMAL = 0;
    const SUPER = 1;
    const ADMIN = 2;
    const CHIEF = 4;
    const RESEARCH_AND_EVALUATION = 8;
    const GENERAL_AFFAIRS = 16;
    const HUMAN_RESOURCE = 32;
    const ACCOUNTING = 64;
}

class System {
    // singleton
    private static $_instance = null;

    private $sqlite3;
    private $ROLE;

    private function getRoleIps($role_id) {
        $return = [];
        if($stmt = $this->sqlite3->prepare('SELECT * FROM authority WHERE role_id = :role_id')) {
            $stmt->bindParam(':role_id', $role_id);
            $result = $stmt->execute();
            if ($result === false) return $return;
            while($row = $result->fetchArray(SQLITE3_ASSOC)) {
                if (!in_array($row['ip'], $return)) {
                    $return[] = $row['ip'];
                }
            }
        } else {
            global $log;
            $log->error(__METHOD__.": 取得 $role_id IPs 資料失敗！");
        }
        return $return;
    }

    private function addLoopIPsAuthority() {
        $ret = false;
        
        $super_array = $this->getRoleSuperIps();
        if (!in_array('127.0.0.1', $super_array)) {
            $super_array[] = '127.0.0.1';
            $stm = $this->sqlite3->prepare("
                REPLACE INTO authority ('role_id', 'ip')
                VALUES (:role_id, :ip)
            ");
            $stm->bindValue(':role_id', ROLE::SUPER);
            $stm->bindValue(':ip', '127.0.0.1');
            $ret = $stm->execute() === FALSE ? false : true;
        }
        
        $adm_array = $this->getRoleAdminIps();
        if (!in_array('::1', $adm_array)) {
            $adm_array[] = '::1';
            $stm = $this->sqlite3->prepare("
                REPLACE INTO authority ('role_id', 'ip')
                VALUES (:role_id, :ip)
            ");
            $stm->bindValue(':role_id', ROLE::ADMIN);
            $stm->bindValue(':ip', '::1');
            $ret = $stm->execute() === FALSE ? false : true;
        }

        return $ret;
    }

    private function setMockMode($flag) {
        $stm = $this->sqlite3->prepare("
            REPLACE INTO config ('key', 'value')
            VALUES (:key, :value)
        ");
        $stm->bindValue(':key', 'ENABLE_MOCK_MODE');
        $stm->bindValue(':value', $flag ? 'true' : 'false');
        return $stm->execute() === FALSE ? false : true;
    }

    private function addSuperUser() {
        if ($stm = $this->sqlite3->prepare("
            REPLACE INTO user ('id', 'name', 'sex', 'addr', 'tel', 'ext', 'cell', 'unit', 'title', 'work', 'exam', 'education', 'onboard_date', 'offboard_date', 'ip', 'pw_hash', 'authority', 'birthday')
            VALUES (:id, :name, :sex, :addr, :tel, :ext, :cell, :unit, :title, :work, :exam, :education, :onboard_date, :offboard_date, :ip, '827ddd09eba5fdaee4639f30c5b8715d', :authority, :birthday)
        ")) {
            $stm->bindValue(':id', 'HBSUPER');
            $stm->bindValue(':name', '開發人員');
            $stm->bindValue(':sex', 1);
            $stm->bindValue(':addr', '虛構的世界');
            $stm->bindValue(':tel', '034917647', SQLITE3_TEXT);
            $stm->bindValue(':ext', '503', SQLITE3_TEXT); // 總機 153
            $stm->bindValue(':cell', '0912345678', SQLITE3_TEXT);
            $stm->bindValue(':unit', '庶務一課');
            $stm->bindValue(':title', '雜役工');
            $stm->bindValue(':work', '打怪');
            $stm->bindValue(':exam', '109年邦頭特考三級');
            $stm->bindValue(':education', '國立台北科技大學資訊工程所');
            $stm->bindValue(':birthday', '066/05/23');
            $stm->bindValue(':onboard_date', '107/10/31');
            $stm->bindValue(':offboard_date', '');
            $stm->bindValue(':ip', '127.0.0.1');
            // $stm->bindValue(':pw_hash', '827ddd09eba5fdaee4639f30c5b8715d');    // HB default
            $authority = AUTHORITY::SUPER;
            $stm->bindParam(':authority', $authority);
            return $stm->execute() === FALSE ? false : true;
        }
        global $log;
        $log->warning(__METHOD__.": 準備資料庫 statement [ 
            REPLACE INTO user ('id', 'name', 'sex', 'addr', 'tel', 'ext', 'cell', 'unit', 'title', 'work', 'exam', 'education', 'onboard_date', 'offboard_date', 'ip', 'pw_hash', 'authority', 'birthday')
            VALUES (:id, :name, :sex, :addr, :tel, :ext, :cell, :unit, :title, :work, :exam, :education, :onboard_date, :offboard_date, :ip, '827ddd09eba5fdaee4639f30c5b8715d', :authority, :birthday
        ) ] 失敗。");
        return false;
    }

    private function addWatchdogUser() {
        if ($stm = $this->sqlite3->prepare("
            REPLACE INTO user ('id', 'name', 'sex', 'addr', 'tel', 'ext', 'cell', 'unit', 'title', 'work', 'exam', 'education', 'onboard_date', 'offboard_date', 'ip', 'pw_hash', 'authority', 'birthday')
            VALUES (:id, :name, :sex, :addr, :tel, :ext, :cell, :unit, :title, :work, :exam, :education, :onboard_date, :offboard_date, :ip, '827ddd09eba5fdaee4639f30c5b8715d', :authority, :birthday)
        ")) {
            $stm->bindValue(':id', 'HBWATCHDOG');
            $stm->bindValue(':name', '看門狗');
            $stm->bindValue(':sex', 0);
            $stm->bindValue(':addr', '虛構的世界');
            $stm->bindValue(':tel', '034917647', SQLITE3_TEXT);
            $stm->bindValue(':ext', '153', SQLITE3_TEXT); // 總機 153
            $stm->bindValue(':cell', '0912345678', SQLITE3_TEXT);
            $stm->bindValue(':unit', '庶務二課');
            $stm->bindValue(':title', '看門狗');
            $stm->bindValue(':work', '定時工');
            $stm->bindValue(':exam', '109年邦頭特考四級');
            $stm->bindValue(':education', '國立台北科技大學資訊工程研究所');
            $stm->bindValue(':birthday', '066/05/23');
            $stm->bindValue(':onboard_date', '107/10/31');
            $stm->bindValue(':offboard_date', '');
            $stm->bindValue(':ip', '::1');
            // $stm->bindValue(':pw_hash', '827ddd09eba5fdaee4639f30c5b8715d');    // HB default
            $authority = AUTHORITY::ADMIN;
            $stm->bindParam(':authority', $authority);
            return $stm->execute() === FALSE ? false : true;
        }
        global $log;
        $log->warning(__METHOD__.": 準備資料庫 statement [ 
            REPLACE INTO user ('id', 'name', 'sex', 'addr', 'tel', 'ext', 'cell', 'unit', 'title', 'work', 'exam', 'education', 'onboard_date', 'offboard_date', 'ip', 'pw_hash', 'authority', 'birthday')
            VALUES (:id, :name, :sex, :addr, :tel, :ext, :cell, :unit, :title, :work, :exam, :education, :onboard_date, :offboard_date, :ip, '827ddd09eba5fdaee4639f30c5b8715d', :authority, :birthday)
        ] 失敗。");
        return false;
    }

    private function removeSuperUser() {
        if ($stm = $this->sqlite3->prepare("DELETE from user WHERE id = :id")) {
            $stm->bindValue(':id', 'HBSUPER');
            return $stm->execute() === FALSE ? false : true;
        }
        global $log;
        $log->warning(__METHOD__.": 準備資料庫 statement [ DELETE from user WHERE id = :id ] 失敗。(HBSUPER)");
        return false;
    }

    private function removeWatchdogUser() {
        if ($stm = $this->sqlite3->prepare("DELETE from user WHERE id = :id")) {
            $stm->bindValue(':id', 'HBWATCHDOG');
            return $stm->execute() === FALSE ? false : true;
        }
        global $log;
        $log->warning(__METHOD__.": 準備資料庫 statement [ DELETE from user WHERE id = :id ] 失敗。(HBWATCHDOG");
        return false;
    }

    private function getDimensionDB() {
        $db_path = DIMENSION_SQLITE_DB;
        $sqlite = new DynamicSQLite($db_path);
        $sqlite->initDB();
        $sqlite->createTableBySQL('
            CREATE TABLE IF NOT EXISTS "config" (
                "key"	TEXT NOT NULL,
                "value"	TEXT,
                PRIMARY KEY("key")
            )
        ');
        $sqlite->createTableBySQL('
            CREATE TABLE IF NOT EXISTS "authority" (
                "role_id"	INTEGER NOT NULL DEFAULT 0,
                "ip"	TEXT NOT NULL DEFAULT \'192.168.xx.xx\',
                PRIMARY KEY("role_id","ip")
            )
        ');
        $sqlite->createTableBySQL('
            CREATE TABLE IF NOT EXISTS "role" (
                "id"	INTEGER NOT NULL,
                "e_name"	TEXT NOT NULL,
                "name"	TEXT NOT NULL,
                "authority"	INTEGER NOT NULL DEFAULT 0,
                PRIMARY KEY("id" AUTOINCREMENT)
            )
        ');
        $sqlite->createTableBySQL('
            CREATE TABLE IF NOT EXISTS "user" (
                "id"	TEXT NOT NULL,
                "name"	TEXT NOT NULL,
                "sex"	INTEGER NOT NULL DEFAULT 0,
                "addr"	TEXT,
                "tel"	TEXT,
                "ext"	NUMERIC NOT NULL DEFAULT 153,
                "cell"	TEXT,
                "unit"	TEXT NOT NULL DEFAULT \'行政課\',
                "title"	TEXT,
                "work"	TEXT,
                "exam"	TEXT,
                "education"	TEXT,
                "onboard_date"	TEXT,
                "offboard_date"	TEXT,
                "ip"	TEXT NOT NULL DEFAULT \'192.168.xx.xx\',
                "pw_hash"	TEXT NOT NULL DEFAULT \'827ddd09eba5fdaee4639f30c5b8715d\',
                "authority"	INTEGER NOT NULL DEFAULT 0,
                "birthday"	TEXT,
                PRIMARY KEY("id")
            )
        ');
        $sqlite->createTableBySQL('
            CREATE TABLE IF NOT EXISTS "unit" (
                "id"	INTEGER NOT NULL DEFAULT 0,
                "name"	TEXT NOT NULL,
                PRIMARY KEY("id" AUTOINCREMENT)
            )
        ');
        return $db_path;
    }

    // private because of singleton
    private function __construct() {
        $db_path = $this->getDimensionDB();
        $this->sqlite3 = new SQLite3($db_path);
    }

    // private because of singleton
    private function __clone() { }

    function __destruct() { unset($this->sqlite3); }

    // singleton
    public static function getInstance() {
        if (!(self::$_instance instanceof System)) {
            self::$_instance = new System();
        }
        return self::$_instance;
    }

    public function ping($ip, $port = 0, $timeout = 1, $ttl = 255){
        if ($this->isMockMode()) {
            return 87;
        }
        $ping = new Ping($ip, $timeout, $ttl);
        $port = intval($port);
        if ($port < 1 || $port > 65535) {
            $latency = $ping->ping();
        } else {
            $ping->setPort($port);
            $latency = $ping->ping('fsockopen');
        }
        return $latency;
    }

    public function isDBReachable() {
        $db_ip = $this->getOraTargetDBIP();
        $db_port = $this->getOraTargetDBPort();
        $latency = $this->ping($db_ip, $db_port, 1, 255);
        return !($latency > 999 || $latency == '');
    }

    public function isKeyValid($key) {
        return $key == $this->get('API_KEY');
    }

    public function isMockMode() {
        // global $client_ip;
        // if ($client_ip == '127.0.0.1') return true;
        return $this->get('ENABLE_MOCK_MODE') === 'true';
    }
    
    public function enableMockMode() {
        $this->addLoopIPsAuthority();
        $this->addSuperUser();
        $this->addWatchdogUser();
        return $this->setMockMode(true);
    }
    
    public function disableMockMode() {
        return $this->setMockMode(false);
    }
    
    public function isMSSQLEnable() {
        return $this->get('ENABLE_MSSQL_CONN') !== 'false';
    }

    public function setMSSQLConnection($flag) {
        if ($stm = $this->sqlite3->prepare("
            REPLACE INTO config ('key', 'value')
            VALUES (:key, :value)
        ")) {
            $stm->bindValue(':key', 'ENABLE_MSSQL_CONN');
            $stm->bindValue(':value', $flag ? 'true' : 'false');
            return $stm->execute() === FALSE ? false : true;
        }
        global $log;
        $log->warning(__METHOD__.": 準備資料庫 statement [ 
            REPLACE INTO config ('key', 'value')
            VALUES (:key, :value)
        ] 失敗。($flag)");
        return false;
    }
    
    public function isOfficeHoursEnable() {
        return $this->get('ENABLE_OFFICE_HOURS') !== 'false';
    }

    public function setOfficeHoursEnable($flag) {
        if ($stm = $this->sqlite3->prepare("
            REPLACE INTO config ('key', 'value')
            VALUES (:key, :value)
        ")) {
            $stm->bindValue(':key', 'ENABLE_OFFICE_HOURS');
            $stm->bindValue(':value', $flag ? 'true' : 'false');
            return $stm->execute() === FALSE ? false : true;
        }
        global $log;
        $log->warning(__METHOD__.": 準備資料庫 statement [ 
            REPLACE INTO config ('key', 'value')
            VALUES (:key, :value)
        ] 失敗。($flag)");
        return false;
    }

    public function isAvatarEnable() {
        return $this->get('ENABLE_AVATAR') === 'true';
    }

    public function setAvatarEnable($flag) {
        if ($stm = $this->sqlite3->prepare("
            REPLACE INTO config ('key', 'value')
            VALUES (:key, :value)
        ")) {
            $stm->bindValue(':key', 'ENABLE_AVATAR');
            $stm->bindValue(':value', $flag ? 'true' : 'false');
            return $stm->execute() === FALSE ? false : true;
        }
        global $log;
        $log->warning(__METHOD__.": 準備資料庫 statement [ 
            REPLACE INTO config ('key', 'value')
            VALUES (:key, :value)
        ] 失敗。($flag)");
        return false;
    }

    public function getUserPhotoFolderPath() {
        return rtrim($this->get('USER_PHOTO_FOLDER'), "\\");
    }

    public function getRoleAdminIps() {
        $array = $this->getRoleIps(ROLE::ADMIN);
        if (!in_array('127.0.0.1', $array)) {
            $array[] = '127.0.0.1';
        }
        if (!in_array('::1', $array)) {
            $array[] = '::1';
        }
        return $array;
    }

    public function getRoleChiefIps() {
        return $this->getRoleIps(ROLE::CHIEF);
    }

    public function getRoleSuperIps() {
        return $this->getRoleIps(ROLE::SUPER);
    }

    public function getRoleRAEIps() {
        return $this->getRoleIps(ROLE::RESEARCH_AND_EVALUATION);
    }

    public function getRoleGAIps() {
        return $this->getRoleIps(ROLE::GENERAL_AFFAIRS);
    }

    public function getRoleHRIps() {
        return $this->getRoleIps(ROLE::HUMAN_RESOURCE);
    }

    public function getRoleAccountingIps() {
        return $this->getRoleIps(ROLE::ACCOUNTING);
    }

    public function getAuthority($ip) {
        return array(
            "isAdmin" => in_array($ip, $this->getRoleAdminIps()),
            "isChief" => in_array($ip, $this->getRoleChiefIps()),
            "isSuper" => in_array($ip, $this->getRoleSuperIps()),
            "isRAE"   => in_array($ip, $this->getRoleRAEIps()),
            "isGA"    => in_array($ip, $this->getRoleGAIps()),
            "isHR"    => in_array($ip, $this->getRoleHRIps()),
            "isAccounting" => in_array($ip, $this->getRoleAccountingIps())
        );
    }

    public function removeAuthority($user) {
        $role_id = $user['role_id'];
        $ip = $user['role_ip'];
        global $log;
        if (empty($role_id)) {
            $log->warning(__METHOD__.": role_id could not be empty. $role_id");
            return false;
        }
        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            $log->warning(__METHOD__.": $ip is not a valid IP address.");
            return false;
        }
        if ($stm = $this->sqlite3->prepare("DELETE from authority WHERE role_id = :role_id AND ip = :ip")) {
            $stm->bindParam(':role_id', $role_id);
            $stm->bindParam(':ip', $ip);
            return $stm->execute() === FALSE ? false : true;
        }
        $log->warning(__METHOD__.": 準備資料庫 statement [ DELETE from authority WHERE role_id = :role_id AND ip = :ip ] 失敗。($role_id, $ip)");
        return false;
    }

    public function addAuthority($role_id, $ip) {
        global $log;
        if (empty($role_id)) {
            $log->warning(__METHOD__.": role_id could not be empty. $role_id");
            return false;
        }
        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            $log->warning(__METHOD__.": $ip is not a valid IP address.");
            return false;
        }
        if ($stm = $this->sqlite3->prepare("
            REPLACE INTO authority ('role_id', 'ip')
            VALUES (:role_id, :ip)
        ")) {
            $stm->bindParam(':role_id', $role_id);
            $stm->bindParam(':ip', $ip);
            return $stm->execute() === FALSE ? false : true;
        }
        $log->warning(__METHOD__.": 準備資料庫 statement [ REPLACE INTO authority ('role_id', 'ip') VALUES (:role_id, :ip) ] 失敗。($role_id, $ip)");
        return false;
    }

    public function getOraTargetDBIP() {
        $target = $this->get('ORA_DB_TARGET');
        switch ($target) {
            case 'HXT':
            case 'TEST':
                return $this->get('ORA_DB_HXT_IP');
            case 'BACKUP':
                return $this->get('ORA_DB_BACKUP_IP');
            default:
                return $this->get('ORA_DB_HXWEB_IP');
        }
    }

    public function getOraTargetDBPort() {
        $target = $this->get('ORA_DB_TARGET');
        switch ($target) {
            case 'HXT':
            case 'TEST':
                return $this->get('ORA_DB_HXT_PORT');
            case 'BACKUP':
                return $this->get('ORA_DB_BACKUP_PORT');
            default:
                return $this->get('ORA_DB_HXWEB_PORT');
        }
    }

    public function getOraConnectTarget() {
        $target = $this->get('ORA_DB_TARGET');
        switch ($target) {
            case 'HXT':
            case 'TEST':
                return 'TEST';
            case 'BACKUP':
                return 'BACKUP';
            default:
                return 'MAIN';
        }
    }

    public function getOraMainDBConnStr() {
        $site = strtoupper($this->get('SITE'));
        $ip = $this->get('ORA_DB_HXWEB_IP');
        $port = $this->get('ORA_DB_HXWEB_PORT');
        return "(DESCRIPTION=(ADDRESS_LIST=(ADDRESS=(PROTOCOL=TCP)(HOST=${ip})(PORT=${port})))(CONNECT_DATA=(SERVICE_NAME=${site}WEB)))";
    }

    public function getOraBackupDBConnStr() {
        $site = strtoupper($this->get('SITE'));
        $ip = $this->get('ORA_DB_BACKUP_IP');
        $port = $this->get('ORA_DB_BACKUP_PORT');
        return "(DESCRIPTION=(ADDRESS_LIST=(ADDRESS=(PROTOCOL=TCP)(HOST=${ip})(PORT=${port})))(CONNECT_DATA=(SERVICE_NAME=${site}WEB)))";
    }

    public function getOraTestDBConnStr() {
        $site = strtoupper($this->get('SITE'));
        $ip = $this->get('ORA_DB_HXT_IP');
        $port = $this->get('ORA_DB_HXT_PORT');
        return "(DESCRIPTION=(ADDRESS_LIST=(ADDRESS=(PROTOCOL=TCP)(HOST=${ip})(PORT=${port})))(CONNECT_DATA=(SERVICE_NAME=${site}WEBT)))";
    }

    public function getOraL3hwebDBConnStr() {
        $ip = $this->get('ORA_DB_L3HWEB_IP');
        $port = $this->get('ORA_DB_L3HWEB_PORT');
        return "(DESCRIPTION=(ADDRESS_LIST=(ADDRESS=(PROTOCOL=TCP)(HOST=${ip})(PORT=${port})))(CONNECT_DATA=(SERVICE_NAME=L3HWEB)))";
    }

    public function getOraL3hwebL1DBConnStr() {
        $ip = $this->get('ORA_DB_L3HWEB_IP');
        $port = $this->get('ORA_DB_L3HWEB_PORT');
        return "(DESCRIPTION=(ADDRESS_LIST=(ADDRESS=(PROTOCOL=TCP)(HOST=${ip})(PORT=${port})))(CONNECT_DATA=(SERVICE_NAME=L1HWEB)))";
    }

    public function getOraL1hwebDBConnStr() {
        $ip = $this->get('ORA_DB_L1HWEB_IP');
        $port = $this->get('ORA_DB_L1HWEB_PORT');
        return "(DESCRIPTION=(ADDRESS_LIST=(ADDRESS=(PROTOCOL=TCP)(HOST=${ip})(PORT=${port})))(CONNECT_DATA=(SERVICE_NAME=L1HWEB)))";
    }

    public function getOraL2hwebDBConnStr() {
        $ip = $this->get('ORA_DB_L2HWEB_IP');
        $port = $this->get('ORA_DB_L2HWEB_PORT');
        return "(DESCRIPTION=(ADDRESS_LIST=(ADDRESS=(PROTOCOL=TCP)(HOST=${ip})(PORT=${port})))(CONNECT_DATA=(SERVICE_NAME=L2HWEB)))";
    }
    
    public function getLXHWEBConfigs() {
        $configs = $this->getConfigs();
        return array(
            'ORA_DB_L1HWEB_IP' => $configs['ORA_DB_L1HWEB_IP'],
            'ORA_DB_L1HWEB_PORT' => $configs['ORA_DB_L1HWEB_PORT'],
            'ORA_DB_L2HWEB_IP' => $configs['ORA_DB_L2HWEB_IP'],
            'ORA_DB_L2HWEB_PORT' => $configs['ORA_DB_L2HWEB_PORT'],
            'ORA_DB_L3HWEB_IP' => $configs['ORA_DB_L3HWEB_IP'],
            'ORA_DB_L3HWEB_PORT' => $configs['ORA_DB_L3HWEB_PORT'],
            'PING_INTERVAL_SECONDS' => $configs['PING_INTERVAL_SECONDS']
        );
    }

    public function getConfigs() {
        if($stmt = $this->sqlite3->prepare('SELECT * FROM config WHERE 1=1')) {
            $result = $stmt->execute();
            $return = [];
            if ($result === false) return $return;
            while($row = $result->fetchArray(SQLITE3_ASSOC)) {
                $return[$row['key']] = $row['value'];
            }
            return $return;
        } else {
            global $log;
            $log->error(__METHOD__.": 取得 system config 資料失敗！");
        }
        return false;
    }

    public function updateConfigs($configs) {
        global $log;
        $success = 0;
        $error = 0;
        foreach ($configs as $key => $value) {
            // $stmt = $this->sqlite3->prepare('UPDATE config SET value = :value WHERE key = :key');
            $stmt = $this->sqlite3->prepare("REPLACE INTO config ('key', 'value') VALUES (:key, :value)");
            if($stmt) {
                $stmt->bindParam(':key', $key);
                $stmt->bindParam(':value', $value);
                $result = $stmt->execute() === FALSE ? false : true;
                if ($result) {
                    $success++;
                } else {
                    $error++;
                }
            } else {
                global $log;
                $log->error(__METHOD__.": 準備更新SQL失敗！ [ UPDATE config SET value = :value WHERE key = :key ] ".print_r($pair, true));
            }
        }
        return $success;
    }

    public function get($key) {
        return $this->sqlite3->querySingle("SELECT value from config WHERE key = '$key'");
    }
}
