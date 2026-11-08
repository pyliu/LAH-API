<?php
/**
 * 跨機監控透明代理端點 (Cross-Host Monitoring Transparent Proxy)
 * 相容 PHP 7.3+ 與 PHP 8.5，嚴格鎖定 l05proxy、smsproxy、srmas-weathermap 白名單目標。
 */
error_reporting(E_ALL & ~E_WARNING & ~E_NOTICE);
set_time_limit(130);

header("Access-Control-Allow-Origin: *");
header("Cache-Control: no-cache, no-store, must-revalidate");

function loadProxyEnvConfig() {
    $env = array();
    $root_candidates = array(
        dirname(dirname(dirname(__FILE__))),
        dirname(dirname(dirname(dirname(__FILE__))))
    );
    $env_files = array();
    foreach ($root_candidates as $root) {
        $env_files[] = $root . DIRECTORY_SEPARATOR . 'www' . DIRECTORY_SEPARATOR . '.env';
        $env_files[] = $root . DIRECTORY_SEPARATOR . 'LAH-API' . DIRECTORY_SEPARATOR . '.env';
        $env_files[] = $root . DIRECTORY_SEPARATOR . 'LAH-FE' . DIRECTORY_SEPARATOR . '.env';
        $env_files[] = $root . DIRECTORY_SEPARATOR . 'LAH-NUXTJS' . DIRECTORY_SEPARATOR . '.env';
    }
    foreach ($env_files as $file) {
        if (!is_file($file) || !is_readable($file)) {
            continue;
        }
        $lines = @file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (!is_array($lines)) {
            continue;
        }
        foreach ($lines as $line) {
            $trimmed = trim($line);
            if ($trimmed === '' || strpos($trimmed, '#') === 0) {
                continue;
            }
            $pos = strpos($trimmed, '=');
            if ($pos === false) {
                continue;
            }
            $key = trim(substr($trimmed, 0, $pos));
            $val = trim(substr($trimmed, $pos + 1));
            $val = trim($val, "\"'");
            if ($key !== '') {
                $env[$key] = $val;
            }
        }
    }
    return $env;
}

$target = isset($_GET['_target']) ? trim($_GET['_target']) : '';
$sub_path = isset($_GET['_path']) ? ltrim($_GET['_path'], '/') : '';

if ($target === '' && isset($_SERVER['REQUEST_URI'])) {
    if (preg_match('#^/(l05proxy|smsproxy|srmas-weathermap)(?:/(.*))?$#i', parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), $m)) {
        $target = strtolower($m[1]);
        $sub_path = isset($m[2]) ? $m[2] : '';
    }
}

$env = loadProxyEnvConfig();
$protocol = isset($env['PROTOCOL']) && $env['PROTOCOL'] !== '' ? $env['PROTOCOL'] : 'http';
$base_url = '';
$timeout = 30;
$verify_ssl = false;

switch (strtolower($target)) {
    case 'l05proxy':
        $ip = isset($env['MONITOR_HOST_L05_IP']) ? $env['MONITOR_HOST_L05_IP'] : '';
        $port = isset($env['MONITOR_HOST_L05_PORT']) ? $env['MONITOR_HOST_L05_PORT'] : '8082';
        if ($ip !== '') {
            $base_url = $protocol . '://' . $ip . ':' . $port;
        }
        break;
    case 'smsproxy':
        $ip = isset($env['MONITOR_HOST_SMS_IP']) ? $env['MONITOR_HOST_SMS_IP'] : '';
        $port = isset($env['MONITOR_HOST_SMS_PORT']) ? $env['MONITOR_HOST_SMS_PORT'] : '8082';
        if ($ip !== '') {
            $base_url = $protocol . '://' . $ip . ':' . $port;
        }
        $timeout = 120;
        break;
    case 'srmas-weathermap':
        $host = isset($env['SRMAS_HOST']) ? $env['SRMAS_HOST'] : '';
        if ($host !== '') {
            $base_url = 'https://' . $host;
        }
        $verify_ssl = false;
        break;
    default:
        http_response_code(403);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(array('status' => 0, 'message' => 'Forbidden proxy target'));
        exit;
}

if ($base_url === '') {
    http_response_code(502);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(array('status' => 0, 'message' => 'Proxy target host is not configured in .env'));
    exit;
}

$query_params = $_GET;
unset($query_params['_target'], $query_params['_path']);
$query_str = http_build_query($query_params);

$remote_url = rtrim($base_url, '/') . '/' . $sub_path;
if ($query_str !== '') {
    $remote_url .= (strpos($remote_url, '?') === false ? '?' : '&') . $query_str;
}

$ch = curl_init($remote_url);
$method = isset($_SERVER['REQUEST_METHOD']) ? strtoupper($_SERVER['REQUEST_METHOD']) : 'GET';
curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HEADER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, $verify_ssl);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, $verify_ssl ? 2 : 0);

$req_headers = array();
if (isset($_SERVER['CONTENT_TYPE']) && $_SERVER['CONTENT_TYPE'] !== '') {
    $req_headers[] = 'Content-Type: ' . $_SERVER['CONTENT_TYPE'];
}
if (isset($_SERVER['HTTP_ACCEPT']) && $_SERVER['HTTP_ACCEPT'] !== '') {
    $req_headers[] = 'Accept: ' . $_SERVER['HTTP_ACCEPT'];
}
if (!empty($req_headers)) {
    curl_setopt($ch, CURLOPT_HTTPHEADER, $req_headers);
}

if ($method === 'POST' || $method === 'PUT' || $method === 'PATCH') {
    $raw_body = file_get_contents('php://input');
    curl_setopt($ch, CURLOPT_POSTFIELDS, $raw_body);
}

$response = curl_exec($ch);
if ($response === false) {
    $err = curl_error($ch);
    curl_close($ch);
    http_response_code(502);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(array('status' => 0, 'message' => 'Bad Gateway: ' . $err));
    exit;
}

$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
$content_type = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
curl_close($ch);

$body = substr($response, $header_size);
http_response_code($http_code > 0 ? $http_code : 200);
if ($content_type) {
    header('Content-Type: ' . $content_type);
}
echo $body;
