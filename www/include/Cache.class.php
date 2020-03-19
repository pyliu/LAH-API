<?php
require_once("init.php");
define("CACHE_ROOT_DIR", ROOT_DIR.DIRECTORY_SEPARATOR."assets".DIRECTORY_SEPARATOR."cache");

class Cache {

    function __construct() { }

    function __destruct() { }
    
    public function set($key, $val) {
        if (!SYSTEM_CONFIG["MOCK_MODE"]) {
            return file_put_contents(CACHE_ROOT_DIR.DIRECTORY_SEPARATOR.$key.".cache", serialize($val));
        }
        return false;
    }

    public function get($key) {
        if (SYSTEM_CONFIG["MOCK_MODE"]) {
            return unserialize(file_get_contents(CACHE_ROOT_DIR.DIRECTORY_SEPARATOR.$key.".cache"));
        }
        return false;
    }
}
?>
