<?php
require_once("init.php");
define('DIMENSION_SQLITE_DB', ROOT_DIR.DIRECTORY_SEPARATOR."assets".DIRECTORY_SEPARATOR."db".DIRECTORY_SEPARATOR."dimension.db");

class System {
    private $sqlite3;

    function __construct() { $this->sqlite3 = new SQLite3(DIMENSION_SQLITE_DB); }

    function __destruct() { unset($this->sqlite3); }

    public function isMockMode() {
        return $this->get('MOCK_MODE') == 'true';
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

    public function getMSDocDatabaseConnection() {
        return unserialize($this->get('MS_DOC_DB_CONN'));
    }

    public function getAuthority($ip) {
        return array(
            "isAdmin" => in_array($ip, $this->getRoleAdminIps()),
            "isChief" => in_array($ip, $this->getRoleChiefIps()),
            "isSuper" => in_array($ip, $this->getRoleSuperIps()),
            "isRAE"   => in_array($ip, $this->getRoleRAEIps()),
            "isGA"    => in_array($ip, $this->getRoleGAIps())
        )
    }

    public function get($key) {
        return $this->sqlite3->querySingle("SELECT value from config WHERE key = '$key'");
    }
}
?>
