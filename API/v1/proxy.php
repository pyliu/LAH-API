<?php
$legacy_proxy = dirname(dirname(dirname(__FILE__))) . DIRECTORY_SEPARATOR . 'www' . DIRECTORY_SEPARATOR . 'api' . DIRECTORY_SEPARATOR . 'proxy.php';
$parent_www_proxy = dirname(dirname(__FILE__)) . DIRECTORY_SEPARATOR . 'www' . DIRECTORY_SEPARATOR . 'api' . DIRECTORY_SEPARATOR . 'proxy.php';
if (is_file($legacy_proxy)) {
    require_once($legacy_proxy);
} elseif (is_file($parent_www_proxy)) {
    require_once($parent_www_proxy);
} else {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(array('status' => 0, 'message' => 'Proxy handler not found'));
}
