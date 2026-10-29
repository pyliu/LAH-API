<?php
require_once(dirname(dirname(__FILE__)) . DIRECTORY_SEPARATOR . 'include' . DIRECTORY_SEPARATOR . 'init.php');

if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}

$tracking = new SQLiteSurDestructionTracking();
$default_path = defined('UPLOAD_SUR_DESTRUCTION_TRACKING_PDF_DIR') ? UPLOAD_SUR_DESTRUCTION_TRACKING_PDF_DIR : (ROOT_DIR . DIRECTORY_SEPARATOR . "assets" . DIRECTORY_SEPARATOR . "pdf" . DIRECTORY_SEPARATOR . "sur_destruction_tracking");

$id = preg_replace('/[^a-zA-Z0-9_-]/', '', (string)($_REQUEST['id'] ?? ''));
if (empty($id)) {
    http_response_code(400);
    exit("缺少 ID 參數");
}

$record = $tracking->getOne($id);
if ($record !== false) {
    $filename = ($record['apply_date'] ?? '') . '_' . ($record['section_code'] ?? '') . '_' . ($record['land_number'] ?? '') . '_' . ($record['building_number'] ?? '') . '.pdf';
    $full_path = $default_path . DIRECTORY_SEPARATOR . $filename;
    if (file_exists($full_path)) {
        header('Content-Type: application/pdf');
        header('Content-Length: ' . filesize($full_path));
        header('Content-Disposition: inline; filename="' . basename($filename) . '"');
        if (ob_get_level()) {
            ob_end_clean();
        }
        flush();
        readfile($full_path);
        exit;
    }
}

http_response_code(404);
exit("無法取得 $id 的測量銷毀清冊 PDF 檔案");
