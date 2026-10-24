<?php
declare(strict_types=1);

error_reporting(E_ALL & ~E_WARNING & ~E_NOTICE & ~E_DEPRECATED & ~E_USER_DEPRECATED);
date_default_timezone_set("ASIA/TAIPEI");

// Force MOCK mode in test environment
putenv("MOCK=true");
$_ENV['MOCK'] = 'true';
$_SERVER['MOCK'] = 'true';

// Isolated test SQLite database
define('DIMENSION_SQLITE_DB', sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'lah_test_dimension.db');

$api_dir = dirname(__DIR__);
$inc_dir = $api_dir . DIRECTORY_SEPARATOR . 'include';
$api_cmd_dir = $inc_dir . DIRECTORY_SEPARATOR . 'api';
$legacy_inc = dirname($api_dir) . DIRECTORY_SEPARATOR . 'www' . DIRECTORY_SEPARATOR . 'include';

set_include_path(
    $inc_dir . PATH_SEPARATOR .
    $api_cmd_dir . PATH_SEPARATOR .
    $legacy_inc . PATH_SEPARATOR .
    get_include_path()
);

// Autoload composer
$vendor_autoload = $api_dir . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';
if (file_exists($vendor_autoload)) {
    require_once $vendor_autoload;
}

// Core constants and functions
require_once $inc_dir . DIRECTORY_SEPARATOR . 'GlobalConstants.inc.php';
require_once $inc_dir . DIRECTORY_SEPARATOR . 'GlobalFunctions.inc.php';

// Class autoloader for include/ and include/api/
spl_autoload_register(function (string $class_name) use ($inc_dir, $api_cmd_dir, $legacy_inc) {
    $paths = [
        $inc_dir . DIRECTORY_SEPARATOR . $class_name . '.class.php',
        $api_cmd_dir . DIRECTORY_SEPARATOR . $class_name . '.class.php',
        $legacy_inc . DIRECTORY_SEPARATOR . $class_name . '.class.php'
    ];

    foreach ($paths as $file) {
        if (file_exists($file)) {
            require_once $file;
            return;
        }
    }
});
