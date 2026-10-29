<?php
require_once(dirname(dirname(__FILE__)) . DIRECTORY_SEPARATOR . 'include' . DIRECTORY_SEPARATOR . 'init.php');

if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}

$rfpdf = new SQLiteRegForeignerPDF();
$default_path = defined('UPLOAD_PDF_DIR') ? UPLOAD_PDF_DIR : (ROOT_DIR . DIRECTORY_SEPARATOR . "assets" . DIRECTORY_SEPARATOR . "pdf");

$id = preg_replace('/[^a-zA-Z0-9_-]/', '', (string)($_REQUEST['id'] ?? ''));
if (empty($id)) {
    http_response_code(400);
    exit("缺少 ID 參數");
}

$record = $rfpdf->getOne($id);
if ($record !== false && !empty($record['year']) && !empty($record['number'])) {
    $filename = $record['number'] . '_' . ($record['fid'] ?? '') . '_' . ($record['fname'] ?? '') . '.pdf';
    $full_path = $default_path . DIRECTORY_SEPARATOR . $record['year'] . DIRECTORY_SEPARATOR . $filename;
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
exit("無法取得 $id 的外國人地權 PDF 檔案");
