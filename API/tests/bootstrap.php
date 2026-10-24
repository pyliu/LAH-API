<?php
declare(strict_types=1);

error_reporting(E_ALL & ~E_WARNING & ~E_NOTICE & ~E_DEPRECATED & ~E_USER_DEPRECATED);
date_default_timezone_set("ASIA/TAIPEI");

// Force MOCK mode in test environment
putenv("MOCK=true");
$_ENV['MOCK'] = 'true';
$_SERVER['MOCK'] = 'true';

// Isolated test SQLite database directory
$test_sqlite_dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'lah_test_db';
if (!is_dir($test_sqlite_dir)) {
    @mkdir($test_sqlite_dir, 0777, true);
}
define('DIMENSION_SQLITE_DB', $test_sqlite_dir . DIRECTORY_SEPARATOR . 'lah_test_dimension.db');
define('TEMPERATURE_SQLITE_DB', $test_sqlite_dir . DIRECTORY_SEPARATOR . 'Temperature.db');

// IMAP extension fallback constants for PHP 8.5
$imap_constants = [
    'OP_DEBUG' => 1,
    'OP_READONLY' => 2,
    'OP_ANONYMOUS' => 4,
    'OP_SHORTCACHE' => 8,
    'OP_SILENT' => 16,
    'OP_PROTOTYPE' => 32,
    'OP_HALFOPEN' => 64,
    'OP_SECURE' => 256,
    'CL_EXPUNGE' => 32768,
    'CP_UID' => 1,
    'CP_MOVE' => 2,
    'SE_UID' => 1,
    'SE_FREE' => 2,
    'SE_NOPREFETCH' => 4,
    'SO_FREE' => 8,
    'SO_NOSERVER' => 16,
    'SA_MESSAGES' => 1,
    'SA_RECENT' => 2,
    'SA_UNSEEN' => 4,
    'SA_UIDNEXT' => 8,
    'SA_UIDVALIDITY' => 16,
    'SA_ALL' => 31,
];
foreach ($imap_constants as $name => $val) {
    if (!defined($name)) {
        define($name, $val);
    }
}

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
require_once $inc_dir . DIRECTORY_SEPARATOR . 'SQLiteDBFactory.class.php';
SQLiteDBFactory::setDbFolder($test_sqlite_dir);

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
