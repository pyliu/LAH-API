<?php
require_once("init.php");
require_once("System.class.php");
require_once("DynamicSQLite.class.php");

define('CACHE_SQLITE_DB', ROOT_DIR.DIRECTORY_SEPARATOR."assets".DIRECTORY_SEPARATOR."db".DIRECTORY_SEPARATOR."cache.db");

class Cache {
    private $system;
    private $sqlite3;

    private function init() {
        $db = CACHE_SQLITE_DB;
        $sqlite = new DynamicSQLite($db);
        $sqlite->initDB();

        $table = new SQLiteTable('cache');
        $table->addField('key', 'TEXT PRIMARY KEY');
        $table->addField('value', 'TEXT');
        $table->addField('expire', 'INTEGER NOT NULL DEFAULT 864000');
        $sqlite->createTable($table);
    }

    private function getExpire($key) {
        // $val should be mktime() + $expire in set method
        $val = $this->sqlite3->querySingle("SELECT expire from cache WHERE key = '$key'");
        if (empty($val)) return 0;
        return intval($val);
    }

    function __construct() {
        $this->init();
        $this->sqlite3 = new SQLite3(CACHE_SQLITE_DB);
        $this->system = new System();
    }

    function __destruct() { }
    
    public function set($key, $val, $expire = 86400) {
        if ($this->system->isMockMode()) return false;
        $stm = $this->sqlite3->prepare("
            REPLACE INTO cache ('key', 'value', 'expire')
            VALUES (:key, :value, :expire)
        ");
        $stm->bindParam(':key', $key);
        $stm->bindValue(':value', serialize($val));
        $stm->bindValue(':expire', mktime() + $expire); // in seconds, 86400 => one day
        return $stm->execute() === FALSE ? false : true;
    }

    public function get($key) {
        $val = $this->sqlite3->querySingle("SELECT value from cache WHERE key = '$key'");
        if (empty($val)) return false;
        return unserialize($val);
    }

    public function del($key) {
        if ($this->system->isMockMode()) return false;
        $stm = $this->sqlite3->prepare("DELETE from cache WHERE key = :key");
        $stm->bindParam(':key', $key);
        return $stm->execute() === FALSE ? false : true;
    }

    public function isExpired($key) {
        return mktime() > $this->getExpire($key);
    }
}
