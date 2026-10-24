<?php
http_response_code(403);
header('Content-Type: application/json; charset=utf-8');
echo json_encode(['status' => 403, 'message' => 'Forbidden: Direct access to internal include directory is denied.'], JSON_UNESCAPED_UNICODE);
exit;
