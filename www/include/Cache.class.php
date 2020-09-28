<?php
require_once("init.php");
require_once(ROOT_DIR."/include/System.class.php");

define("CACHE_ROOT_DIR", ROOT_DIR.DIRECTORY_SEPARATOR."assets".DIRECTORY_SEPARATOR."cache");

class Cache {
    private $system;

    function __construct() { $this->system = new System(); }

    function __destruct() { }
    
    public function set($key, $val) {
        if (!$this->system->isMockMode()) {
            return file_put_contents(CACHE_ROOT_DIR.DIRECTORY_SEPARATOR.$key.".cache", serialize($val));
        }
        return false;
    }

    public function get($key) {
        return unserialize(file_get_contents(CACHE_ROOT_DIR.DIRECTORY_SEPARATOR.$key.".cache"));
    }
}
?>
