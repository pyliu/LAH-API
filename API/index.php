<?php
/**
 * LAH-API Root Gateway Guard
 * 防止目錄瀏覽 (Directory Listing) 並返回安全阻擋回應
 */
http_response_code(403);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');

echo json_encode([
    'status' => 403,
    'message' => 'Forbidden: Directory listing is disabled.'
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
exit;
