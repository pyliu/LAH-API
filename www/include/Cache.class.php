<?php
require_once("init.php");
require_once(ROOT_DIR."/include/System.class.php");
define('CACHE_SQLITE_DB', ROOT_DIR.DIRECTORY_SEPARATOR."assets".DIRECTORY_SEPARATOR."db".DIRECTORY_SEPARATOR."cache.db");

class Cache {
    private $system;
    private $sqlite3;

    function __construct() {
        $this->sqlite3 = new SQLite3(CACHE_SQLITE_DB);
        $this->system = new System();
    }

    function __destruct() { }
    
    public function set($key, $val) {
        if ($this->system->isMockMode()) return false;
        $stm = $this->sqlite3->prepare("
            REPLACE INTO cache ('key', 'value')
            VALUES (:key, :value)
        ");
        $stm->bindParam(':key', $key);
        $stm->bindParam(':value', $val);
        return $stm->execute() === FALSE ? false : true;
    }

    public function get($key) {
        $val = $this->sqlite3->querySingle("SELECT value from cache WHERE key = '$key'");
        if (empty($val)) return false;
        return unserialize($val);
    }
}
?>
