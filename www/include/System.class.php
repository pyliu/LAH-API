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

    public function getMSDocDatabaseConnection() {
        return unserialize($this->get('MS_DOC_DB_CONN'));
    }

    public function get($key) {
        return $this->sqlite3->querySingle("SELECT value from config WHERE key = '$key'");
    }
}
?>
