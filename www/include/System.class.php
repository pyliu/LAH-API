<?php
require_once("init.php");
define('DIMENSION_SQLITE_DB', ROOT_DIR.DIRECTORY_SEPARATOR."assets".DIRECTORY_SEPARATOR."db".DIRECTORY_SEPARATOR."dimension.db");

class System {
    private $sqlite3;

    private function addLoopIpToSuper() {
        $super_array = unserialize($this->get('ROLE_SUPER_IPS'));
        if (!in_array('127.0.0.1', $super_array)) {
            $super_array[] = '127.0.0.1';
            $stm = $this->sqlite3->prepare("
                REPLACE INTO config ('key', 'value')
                VALUES (:key, :value)
            ");
            $stm->bindValue(':key', 'ROLE_SUPER_IPS');
            $stm->bindValue(':value', serialize($super_array));
            return $stm->execute() === FALSE ? false : true;
        }
        return false;
    }

    private function turnOnMock() {
        $stm = $this->sqlite3->prepare("
            REPLACE INTO config ('key', 'value')
            VALUES (:key, :value)
        ");
        $stm->bindValue(':key', 'MOCK_MODE');
        $stm->bindValue(':value', 'true');
        return $stm->execute() === FALSE ? false : true;
    }

    private function turnOffMock() {
        $stm = $this->sqlite3->prepare("
            REPLACE INTO config ('key', 'value')
            VALUES (:key, :value)
        ");
        $stm->bindValue(':key', 'MOCK_MODE');
        $stm->bindValue(':value', 'false');
        return $stm->execute() === FALSE ? false : true;
    }

    private function addMockSuperUser() {
        $stm = $this->sqlite3->prepare("
            REPLACE INTO user ('id', 'name', 'sex', 'addr', 'tel', 'ext', 'cell', 'unit', 'title', 'work', 'exam', 'education', 'onboard_date', 'offboard_date', 'ip', 'pw_hash', 'authority', 'birthday')
            VALUES (:id, :name, :sex, :addr, :tel, :ext, :cell, :unit, :title, :work, :exam, :education, :onboard_date, :offboard_date, :ip, '827ddd09eba5fdaee4639f30c5b8715d', :authority, :birthday)
        ");
        $stm->bindValue(':id', 'HBAMIN');
        $stm->bindValue(':name', '系統管理員');
        $stm->bindValue(':sex', 1);
        $stm->bindValue(':addr', '虛構的世界');
        $stm->bindValue(':tel', '034917647', SQLITE3_TEXT);
        $stm->bindValue(':ext', '153', SQLITE3_TEXT); // 總機 153
        $stm->bindValue(':cell', '0912345678', SQLITE3_TEXT);
        $stm->bindValue(':unit', '庶務二課');
        $stm->bindValue(':title', '雜役');
        $stm->bindValue(':work', '打怪');
        $stm->bindValue(':exam', '109年邦頭特考三級');
        $stm->bindValue(':education', '國立台北科技大學資訊工程研究所');
        $stm->bindValue(':birthday', '066/05/23');
        $stm->bindValue(':onboard_date', '107/10/31');
        $stm->bindValue(':offboard_date', '');
        $stm->bindValue(':ip', '127.0.0.1');
        // $stm->bindValue(':pw_hash', '827ddd09eba5fdaee4639f30c5b8715d');    // HB default
        $authority = AUTHORITY::SUPER | AUTHORITY::ADMIN;
        $stm->bindParam(':authority', $authority);
        return $stm->execute() === FALSE ? false : true;
    }

    private function removeMockSuperUser() {
        $stm = $this->sqlite3->prepare("DELETE from user WHERE id = :id");
        $stm->bindValue(':id', 'HBAMIN');
        return $stm->execute() === FALSE ? false : true;
    }

    function __construct() { $this->sqlite3 = new SQLite3(DIMENSION_SQLITE_DB); }

    function __destruct() { unset($this->sqlite3); }

    public function isMockMode() {
        global $client_ip;
        if ($client_ip == '127.0.0.1') return true;
        return $this->get('MOCK_MODE') == 'true';
    }
    
    public function enableMockMode() {
        $this->addLoopIpToSuper();
        $this->addMockSuperUser();
        return $this->turnOnMock();
    }
    
    public function disableMockMode() {
        return $this->turnOffMock();
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
?>
