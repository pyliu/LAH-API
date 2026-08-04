<?php
header('Content-Type: application/json; charset=utf-8');

// 允許跨網域請求 (如果需要的話)
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit; // Handle preflight
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// 接收 JSON 資料
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!isset($data['pin'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing PIN']);
    exit;
}

// 驗證 PIN 碼
$envFile = __DIR__ . '/.env';
$secretPin = '8888'; // Default fallback
if (file_exists($envFile)) {
    $envVars = parse_ini_file($envFile);
    if (isset($envVars['SECRET_PIN'])) {
        $secretPin = trim($envVars['SECRET_PIN']);
    }
}

if ($data['pin'] !== $secretPin) {
    http_response_code(403);
    echo json_encode(['error' => '密碼錯誤，拒絕存取']);
    exit;
}

// 如果只是單純驗證密碼
if (isset($data['action']) && $data['action'] === 'verify') {
    echo json_encode(['success' => true]);
    exit;
}

if (!isset($data['category']) || !isset($data['chapter']) || !isset($data['content'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing parameters']);
    exit;
}

$category = $data['category'];
$chapter = $data['chapter'];
$content = $data['content'];

// 安全性檢查：防止目錄穿越 (Directory Traversal)
if (preg_match('/[^a-zA-Z0-9\x{4e00}-\x{9fa5}_]/u', $category)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid category']);
    exit;
}

if (!is_numeric($chapter)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid chapter']);
    exit;
}

$filePath = __DIR__ . '/assets/txt/' . $category . '/' . $chapter . '.txt';

// 檢查檔案是否存在
if (!file_exists($filePath)) {
    http_response_code(404);
    echo json_encode(['error' => 'File not found: ' . $filePath]);
    exit;
}

// 寫入檔案
$result = file_put_contents($filePath, $content);

if ($result !== false) {
    echo json_encode(['success' => true]);
} else {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to write file']);
}
?>
