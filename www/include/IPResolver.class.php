<?php
require_once('init.php');
require_once('SQLiteUser.class.php');

define('DB_DIR', ROOT_DIR.DIRECTORY_SEPARATOR."assets".DIRECTORY_SEPARATOR."db");
define('DEF_SQLITE_DB', DB_DIR.DIRECTORY_SEPARATOR."LAH.db");
define('TEMPERATURE_SQLITE_DB', DB_DIR.DIRECTORY_SEPARATOR."Temperature.db");
define('DIMENSION_SQLITE_DB', DB_DIR.DIRECTORY_SEPARATOR."dimension.db");

abstract class IPResolver {
    private static $static_map = array(
        '220.1.35.43' => 'G08儲存',
        '220.1.35.2' => '資料庫',
        '220.1.35.123' => '本所跨域主機',
        '220.1.35.31' => '登記(1)主機',
        '220.1.35.32' => '登記(2)主機',
        '220.1.35.33' => '地價主機',
        '220.1.35.34' => '測量主機',
        '220.1.35.35' => '外掛主機',
        '220.1.35.36' => '資訊主機',
        '220.1.35.70' => '謄本主機',
        '220.1.33.5' => '局同步異動',
        '220.1.37.246' => '楊梅跨域',
        '220.1.38.30' => '蘆竹跨域',
        '220.1.34.161' => '桃園跨域',
        '220.1.36.45' => '大溪跨域',
        '220.1.39.57' => '八德跨域',
        '220.1.40.33' => '平鎮跨域',
        '220.1.41.20' => '龜山跨域'
    );

    function __construct() { }

    function __destruct() { }

    public static function resolve($ip) {
        global $log;
        if (filter_var($ip, FILTER_VALIDATE_IP)) {
            if (array_key_exists($ip, IPResolver::$static_map)) {
                return IPResolver::$static_map[$ip];
            }
            // find user by ip address
            $sqlite_user = new SQLiteUser();
            $user_data = $sqlite_user->getUserByIP($ip);
            return $user_data === false ? '' : $user_data[0]['name'];
        } else {
            $log->warning(__METHOD__.": Not a valid IP address. [$ip]");
        }
        return false;
    }
    
}
?>
