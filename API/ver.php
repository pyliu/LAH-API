<?php
define('LAH_LIGHTWEIGHT_BRIDGE_PROBE', true);
require_once __DIR__ . DIRECTORY_SEPARATOR . 'include' . DIRECTORY_SEPARATOR . 'OraDB.class.php';

header('Content-Type: application/json; charset=utf-8');
echo json_encode([
    'status' => 1,
    'channel' => 'LAH-API',
    'php_version' => PHP_VERSION,
    'sapi' => PHP_SAPI,
    'extensions' => [
        'pdo_sqlite' => extension_loaded('pdo_sqlite'),
        'sqlite3'    => extension_loaded('sqlite3'),
        'intl'       => extension_loaded('intl'),
        'curl'       => extension_loaded('curl'),
        'gd'         => extension_loaded('gd'),
        'oci8'       => extension_loaded('oci8'),
        'odbc'       => extension_loaded('odbc'),
    ],
    'oci8_bridge' => OraDB::probeBridgeStatus(),
    'timestamp' => date('Y-m-d H:i:s')
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
