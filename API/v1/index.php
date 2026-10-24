<?php
/**
 * LAH-API v1 Directory Guard
 * 防止直接瀏覽 /v1 目錄
 */
http_response_code(403);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');

echo json_encode([
    'status' => 403,
    'message' => 'Forbidden: Direct directory access to /v1 is prohibited.'
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
exit;
