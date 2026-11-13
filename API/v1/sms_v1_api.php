<?php
require_once(dirname(dirname(__FILE__)) . DIRECTORY_SEPARATOR . 'include' . DIRECTORY_SEPARATOR . 'init.php');
require_once(INC_DIR . DIRECTORY_SEPARATOR . 'SmsV1Router.class.php');

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$path = $_SERVER['PATH_INFO'] ?? '';
if ($path === '') {
    $path = $_SERVER['REQUEST_URI'] ?? '';
}
$query = $_GET;
$rawBody = @file_get_contents('php://input');
if (!is_string($rawBody)) {
    $rawBody = '';
}

$router = new SmsV1Router();
$result = $router->dispatch($method, $path, $query, $rawBody);

if (!headers_sent()) {
    http_response_code($result['status']);
    header('Content-Type: application/json; charset=utf-8');
}
echo json_encode($result['body'], JSON_UNESCAPED_UNICODE);
