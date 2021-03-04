<?php
require_once("init.php");
require_once("System.class.php");
require_once("DynamicSQLite.class.php");
require_once("SQLiteSYSAUTH1.class.php");
require_once('SQLiteUser.class.php');
require_once("Ping.class.php");
require_once("OraDB.class.php");
require_once("Ping.class.php");
// require_once("LXHWEB.class.php");

class Cache {
    // singleton
    private static $_instance = null;

    private const DEF_CACHE_DB = ROOT_DIR.DIRECTORY_SEPARATOR."assets".DIRECTORY_SEPARATOR."db".DIRECTORY_SEPARATOR."cache.db";
    private $sqlite3 = null;
    private $db_path = self::DEF_CACHE_DB;

    private function init() {
        if (!file_exists($this->db_path)) {
            $sqlite = new DynamicSQLite($this->db_path);
            $sqlite->initDB();
            $table = new SQLiteTable('cache');
            $table->addField('key', 'TEXT PRIMARY KEY');
            $table->addField('value', 'TEXT');
            $table->addField('expire', 'INTEGER NOT NULL DEFAULT 864000');
            $sqlite->createTable($table);
        }
    }

    private function getSqliteDB() {
        if ($this->sqlite3 === null) {
            $this->sqlite3 = new SQLite3($this->db_path);
        }
        return $this->sqlite3;
    }

    // private because of singleton
    private function __construct($path = self::DEF_CACHE_DB) {
        $this->db_path = $path;
        $this->init();
    }

    // private because of singleton
    private function __clone() { }

    function __destruct() { }
    
    // singleton
    public static function getInstance($path = self::DEF_CACHE_DB) {
        if (!(self::$_instance instanceof Cache)) {
            self::$_instance = new Cache($path);
        }
        return self::$_instance;
    }

    public function getExpireTimestamp($key) {
        // mock mode always returns now + 300 seconds (default)
        if (System::getInstance()->isMockMode()) {
            $seconds = System::getInstance()->get('MOCK_CACHE_SECONDS') ?? 300;
            return time() + $seconds;
        }
        // $val should be time() + $expire in set method
        $val = $this->getSqliteDB()->querySingle("SELECT expire from cache WHERE key = '$key'");
        if (empty($val)) return 0;
        return intval($val);
    }

    public function set($key, $val, $expire = 86400) {
        if (System::getInstance()->isMockMode()) return false;
        $stm = $this->getSqliteDB()->prepare("
            REPLACE INTO cache ('key', 'value', 'expire')
            VALUES (:key, :value, :expire)
        ");
        $stm->bindParam(':key', $key);
        $stm->bindValue(':value', serialize($val));
        $stm->bindValue(':expire', time() + $expire); // in seconds, 86400 => one day
        return $stm->execute() === FALSE ? false : true;
    }

    public function get($key) {
        $val = $this->getSqliteDB()->querySingle("SELECT value from cache WHERE key = '$key'");
        if (empty($val)) return false;
        return unserialize($val);
    }

    public function del($key) {
        if (System::getInstance()->isMockMode()) return false;
        $stm = $this->getSqliteDB()->prepare("DELETE from cache WHERE key = :key");
        $stm->bindParam(':key', $key);
        return $stm->execute() === FALSE ? false : true;
    }

    public function isExpired($key) {
        if (System::getInstance()->isMockMode()) return false;
        return time() > $this->getExpireTimestamp($key);
    }

    public function getUserNames() {
        $sysauth1 = new SQLiteSYSAUTH1();
        return $sysauth1->getUserDictionary();
    }
}
