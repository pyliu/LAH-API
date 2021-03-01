<?php
require_once("init.php");
require_once("System.class.php");
require_once("DynamicSQLite.class.php");
require_once("SQLiteSYSAUTH1.class.php");
require_once('SQLiteUser.class.php');
require_once("Ping.class.php");
require_once("OraDB.class.php");

class Cache {
    private const DEF_CACHE_DB = ROOT_DIR.DIRECTORY_SEPARATOR."assets".DIRECTORY_SEPARATOR."db".DIRECTORY_SEPARATOR."cache.db";
    private $config = null;
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

    private function getSystemConfig() {
        if ($this->config === null) {
            $this->config = new System();
        }
        return $this->config;
    }

    function __construct($path = self::DEF_CACHE_DB) {
        $this->db_path = $path;
        $this->init();
    }

    function __destruct() { }
    
    public function getExpireTimestamp($key) {
        // mock mode always returns now + 300 seconds (default)
        if ($this->getSystemConfig()->isMockMode()) {
            $seconds = $this->getSystemConfig()->get('MOCK_CACHE_SECONDS') ?? 300;
            return time() + $seconds;
        }
        // $val should be time() + $expire in set method
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
        $stm->bindValue(':expire', time() + $expire); // in seconds, 86400 => one day
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
        if ($this->getSystemConfig()->isMockMode()) return false;
        return time() > $this->getExpireTimestamp($key);
    }

    public function getUserNames($refresh = false) {
        $system = new System();
        $result = OraDB::queryOraUsers(false);  // get cached data in SYSAUTH1.db

        if ($system->isMockMode() === true) {
            return $result;
        } else if ($this->isExpired('user_mapping_cached_datetime') || $refresh === true) {
            $result = OraDB::queryOraUsers(true);
            try {
                $sysauth1 = new SQLiteSYSAUTH1();
                /**
                 * Also get user info from SQLite DB
                 */
                $sqlite_user = new SQLiteUser();
                $all_users = $sqlite_user->getAllUsers();
                foreach($all_users as $this_user) {
                    $user_id = trim($this_user["id"]);
                    if (empty($user_id) || $sysauth1->exists($user_id)) {
                        continue;
                    }
                    $name_filtered = preg_replace('/\d+/', "", trim($this_user["name"]));
                    $result[$user_id] = $name_filtered;
                    $tmp_row = array(
                        "USER_ID" => $user_id,
                        "USER_NAME" => $name_filtered,
                        "USER_PSW" => "",
                        "GROUP_ID" => "",
                        "VALID" => 1
                    );
                    $sysauth1->import($tmp_row);
                }
            } catch (\Throwable $th) {
                //throw $th;
                global $log;
                $log->error("取得SQLite內網使用者失敗。【".$th->getMessage()."】");
            } finally {
                $this->set('user_mapping_cached_datetime', date("Y-m-d H:i:s"), 86400);
            }
        }

        return $result;
    }
}
