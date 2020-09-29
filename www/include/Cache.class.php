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
    
    public function set($key, $val, $expire = 864000) {
        if ($this->system->isMockMode()) return false;
        $stm = $this->sqlite3->prepare("
            REPLACE INTO cache ('key', 'value', 'expire')
            VALUES (:key, :value, :expire)
        ");
        $stm->bindParam(':key', $key);
        $stm->bindParam(':value', serialize($val));
        $stm->bindParam(':expire', $expire);
        return $stm->execute() === FALSE ? false : true;
    }

    public function get($key) {
        $val = $this->sqlite3->querySingle("SELECT value from cache WHERE key = '$key'");
        if (empty($val)) return false;
        return unserialize($val);
    }

    
    public function getExpire($key) {
        $val = $this->sqlite3->querySingle("SELECT expire from cache WHERE key = '$key'");
        if (empty($val)) return false;
        return intval($val);
    }
}
?>
