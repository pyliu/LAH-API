<?php
require_once("init.php");
require_once('DynamicSQLite.class.php');

define('DIMENSION_SQLITE_DB', ROOT_DIR.DIRECTORY_SEPARATOR."assets".DIRECTORY_SEPARATOR."db".DIRECTORY_SEPARATOR."dimension.db");

class System {
    private $sqlite3;

    private function addLoopIPsAuthority() {
        $ret = false;
        
        $super_array = unserialize($this->get('ROLE_SUPER_IPS'));
        if (!in_array('127.0.0.1', $super_array)) {
            $super_array[] = '127.0.0.1';
            $stm = $this->sqlite3->prepare("
                REPLACE INTO config ('key', 'value')
                VALUES (:key, :value)
            ");
            $stm->bindValue(':key', 'ROLE_SUPER_IPS');
            $stm->bindValue(':value', serialize($super_array));
            $ret = $stm->execute() === FALSE ? false : true;
        }
        
        $adm_array = unserialize($this->get('ROLE_ADM_IPS'));
        if (!in_array('::1', $adm_array)) {
            $adm_array[] = '::1';
            $stm = $this->sqlite3->prepare("
                REPLACE INTO config ('key', 'value')
                VALUES (:key, :value)
            ");
            $stm->bindValue(':key', 'ROLE_ADM_IPS');
            $stm->bindValue(':value', serialize($adm_array));
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
        $stm = $this->sqlite3->prepare("
            REPLACE INTO user ('id', 'name', 'sex', 'addr', 'tel', 'ext', 'cell', 'unit', 'title', 'work', 'exam', 'education', 'onboard_date', 'offboard_date', 'ip', 'pw_hash', 'authority', 'birthday')
            VALUES (:id, :name, :sex, :addr, :tel, :ext, :cell, :unit, :title, :work, :exam, :education, :onboard_date, :offboard_date, :ip, '827ddd09eba5fdaee4639f30c5b8715d', :authority, :birthday)
        ");
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

    private function addWatchdogUser() {
        $stm = $this->sqlite3->prepare("
            REPLACE INTO user ('id', 'name', 'sex', 'addr', 'tel', 'ext', 'cell', 'unit', 'title', 'work', 'exam', 'education', 'onboard_date', 'offboard_date', 'ip', 'pw_hash', 'authority', 'birthday')
            VALUES (:id, :name, :sex, :addr, :tel, :ext, :cell, :unit, :title, :work, :exam, :education, :onboard_date, :offboard_date, :ip, '827ddd09eba5fdaee4639f30c5b8715d', :authority, :birthday)
        ");
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

    private function removeSuperUser() {
        $stm = $this->sqlite3->prepare("DELETE from user WHERE id = :id");
        $stm->bindValue(':id', 'HBSUPER');
        return $stm->execute() === FALSE ? false : true;
    }

    private function removeWatchdogUser() {
        $stm = $this->sqlite3->prepare("DELETE from user WHERE id = :id");
        $stm->bindValue(':id', 'HBWATCHDOG');
        return $stm->execute() === FALSE ? false : true;
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
        return $db_path;
    }

    function __construct() {
        $db_path = $this->getDimensionDB();
        $this->sqlite3 = new SQLite3($db_path);
    }

    function __destruct() { unset($this->sqlite3); }

    public function isKeyValid($key) {
        return $key == $this->get('API_KEY');
    }

    public function isMockMode() {
        global $client_ip;
        // if ($client_ip == '127.0.0.1') return true;
        return $this->get('ENABLE_MOCK_MODE') == 'true';
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
        $stm = $this->sqlite3->prepare("
            REPLACE INTO config ('key', 'value')
            VALUES (:key, :value)
        ");
        $stm->bindValue(':key', 'ENABLE_MSSQL_CONN');
        $stm->bindValue(':value', $flag ? 'true' : 'false');
        return $stm->execute() === FALSE ? false : true;
    }
    
    public function isOfficeHoursEnable() {
        return $this->get('ENABLE_OFFICE_HOURS') !== 'false';
    }

    public function setOfficeHoursEnable($flag) {
        $stm = $this->sqlite3->prepare("
            REPLACE INTO config ('key', 'value')
            VALUES (:key, :value)
        ");
        $stm->bindValue(':key', 'ENABLE_OFFICE_HOURS');
        $stm->bindValue(':value', $flag ? 'true' : 'false');
        return $stm->execute() === FALSE ? false : true;
    }

    public function getUserPhotoFolderPath() {
        return rtrim($this->get('USER_PHOTO_FOLDER'), "\\");
    }

    public function getRoleAdminIps() {
        return unserialize($this->get('ROLE_ADM_IPS'));
    }

    public function getRoleChiefIps() {
        return unserialize($this->get('ROLE_CHIEF_IPS'));
    }

    public function getRoleSuperIps() {
        return unserialize($this->get('ROLE_SUPER_IPS'));
    }

    public function getRoleRAEIps() {
        return unserialize($this->get('ROLE_RAE_IPS'));
    }

    public function getRoleGAIps() {
        return unserialize($this->get('ROLE_GA_IPS'));
    }

    public function getAuthority($ip) {
        return array(
            "isAdmin" => in_array($ip, $this->getRoleAdminIps()),
            "isChief" => in_array($ip, $this->getRoleChiefIps()),
            "isSuper" => in_array($ip, $this->getRoleSuperIps()),
            "isRAE"   => in_array($ip, $this->getRoleRAEIps()),
            "isGA"    => in_array($ip, $this->getRoleGAIps())
        );
    }

    public function get($key) {
        return $this->sqlite3->querySingle("SELECT value from config WHERE key = '$key'");
    }
}
