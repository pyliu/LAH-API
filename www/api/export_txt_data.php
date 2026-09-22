<?php
require_once(dirname(dirname(__FILE__)).DIRECTORY_SEPARATOR."include".DIRECTORY_SEPARATOR."init.php");

$code = isset($_GET["code"]) ? trim($_GET["code"]) : '';
$filename = isset($_GET["filename"]) ? basename(trim($_GET["filename"])) : '';

$exp_folder = EXPORT_DIR.DIRECTORY_SEPARATOR;

// 1. 若未帶 filename，嘗試自 session 取得
if (empty($filename) && !empty($code) && isset($_SESSION[$code])) {
    $filename = basename($_SESSION[$code]);
}

// 2. 若仍無 filename 但有 code，嘗試在 export 目錄尋找最新產製的對應代碼檔案
if (empty($filename) && !empty($code)) {
    $matched = glob($exp_folder . "*_{$code}_*.txt");
    if (!empty($matched)) {
        usort($matched, function($a, $b) {
            return filemtime($b) - filemtime($a);
        });
        $filename = basename($matched[0]);
    }
}

$target = !empty($filename) ? $exp_folder . $filename : '';

if (!empty($target) && file_exists($target) && is_file($target)) {
    $filesize = filesize($target);
    $encoded_filename = rawurlencode($filename);
    $fallback_filename = !empty($code) ? "{$code}.txt" : "export.txt";

    if (ob_get_level()) {
        ob_end_clean();
    }

    header('Content-Description: File Transfer');
    header('Content-Type: application/octet-stream');
    header("Content-Disposition: attachment; filename=\"{$fallback_filename}\"; filename*=UTF-8''{$encoded_filename}");
    header('Content-Length: ' . $filesize);
    header('Expires: 0');
    header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
    header('Pragma: public');

    readfile($target);
    exit;
} else {
    Logger::getInstance()->error("Export file not found: {$filename} (code: {$code})");
    header("HTTP/1.1 404 Not Found");
    die("找不到可供下載的檔案 (檔案名稱: {$filename}，代碼: {$code})。");
}
