<?php
require_once("init.php");
require_once("System.class.php");
require_once("DynamicSQLite.class.php");

class Cache {
    private const CACHE_SQLITE_DB = ROOT_DIR.DIRECTORY_SEPARATOR."assets".DIRECTORY_SEPARATOR."db".DIRECTORY_SEPARATOR."cache.db";
    private $config = null;
    private $sqlite3 = null;

    private function init($path) {
        if (!file_exists($path)) {
            $sqlite = new DynamicSQLite($path);
            $sqlite->initDB();
            $table = new SQLiteTable('cache');
            $table->addField('key', 'TEXT PRIMARY KEY');
            $table->addField('value', 'TEXT');
            $table->addField('expire', 'INTEGER NOT NULL DEFAULT 864000');
            $sqlite->createTable($table);
        }
    }

    private function getSqliteDB($path = self::CACHE_SQLITE_DB) {
        if ($this->sqlite3 === null) {
            $this->sqlite3 = new SQLite3($path);
        }
        return $this->sqlite3;
    }

    private function getSystemConfig() {
        if ($this->config === null) {
            $this->config = new System();
        }
        return $this->config;
    }

    function __construct($path = self::CACHE_SQLITE_DB) {
        $this->init($path);
    }

    function __destruct() { }
    
    public function getExpireTimestamp($key) {
        // $val should be mktime() + $expire in set method
        $val = $this->getSqliteDB()->querySingle("SELECT expire from cache WHERE key = '$key'");
        if (empty($val)) return 0;
        return intval($val);
    }

    public function set($key, $val, $expire = 86400) {
        if ($this->getSystemConfig()->isMockMode()) return false;
        $stm = $this->getSqliteDB()->prepare("
            REPLACE INTO cache ('key', 'value', 'expire')
            VALUES (:key, :value, :expire)
        ");
        $stm->bindParam(':key', $key);
        $stm->bindValue(':value', serialize($val));
        $stm->bindValue(':expire', mktime() + $expire); // in seconds, 86400 => one day
        return $stm->execute() === FALSE ? false : true;
    }

    public function get($key) {
        $val = $this->getSqliteDB()->querySingle("SELECT value from cache WHERE key = '$key'");
        if (empty($val)) return false;
        return unserialize($val);
    }

    public function del($key) {
        if ($this->getSystemConfig()->isMockMode()) return false;
        $stm = $this->getSqliteDB()->prepare("DELETE from cache WHERE key = :key");
        $stm->bindParam(':key', $key);
        return $stm->execute() === FALSE ? false : true;
    }

    public function isExpired($key) {
        return mktime() > $this->getExpireTimestamp($key);
    }
}
