<?php
require_once("MSDB.class.php");

class Messsage {
    private $jungli_in_db;

    private function getXKey() : int {
        return (random_int(1, 255) * date("H") * date("i", strtotime("1 min")) * date("s", strtotime("1 second"))) % 65535;
    }

    private function getUserInfo($name_or_id) {
        $tdoc_db = new MSDB(array(
            "MS_DB_UID" => SYSTEM_CONFIG["MS_TDOC_DB_UID"],
            "MS_DB_PWD" => SYSTEM_CONFIG["MS_TDOC_DB_PWD"],
            "MS_DB_DATABASE" => SYSTEM_CONFIG["MS_TDOC_DB_DATABASE"],
            "MS_DB_SVR" => SYSTEM_CONFIG["MS_TDOC_DB_SVR"],
            "MS_DB_CHARSET" => SYSTEM_CONFIG["MS_TDOC_DB_CHARSET"]
        ));
        if (preg_match("/^HB[0-9]+$/i", $name_or_id)) {
            return $tdoc_db->fetchAll("SELECT * FROM AP_USER WHERE DocUserID LIKE '%${name_or_id}%'");
        }
        // query by name may have multiple records
        return $tdoc_db->fetchAll("SELECT * FROM AP_USER WHERE AP_USER_NAME LIKE '%${name_or_id}%'");
    }

    function __construct() {
        $this->jungli_in_db = new MSDB();
    }

    function __destruct() {
        unset($this->jungli_in_db);
        $this->jungli_in_db = null;
    }
    
    public function sendMessage($content, $target) : bool {
        return true;
    }
}
?>
