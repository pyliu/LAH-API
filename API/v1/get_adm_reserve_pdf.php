<?php
require_once(dirname(dirname(__FILE__)) . DIRECTORY_SEPARATOR . 'include' . DIRECTORY_SEPARATOR . 'init.php');

if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}

$arfpdf = new SQLiteAdmReserveFilePDF();
$default_path = defined('UPLOAD_RESERVE_PDF_DIR') ? UPLOAD_RESERVE_PDF_DIR : (ROOT_DIR . DIRECTORY_SEPARATOR . "assets" . DIRECTORY_SEPARATOR . "pdf" . DIRECTORY_SEPARATOR . "adm_reserve_file");

$number = preg_replace('/[^a-zA-Z0-9_-]/', '', (string)($_REQUEST['number'] ?? ''));
if (empty($number)) {
    http_response_code(400);
    exit("缺少公文號碼參數");
}

$record = $arfpdf->getOneByNumber($number);
if ($record !== false) {
    $full_path = $default_path . DIRECTORY_SEPARATOR . $number . '.pdf';
    if (file_exists($full_path)) {
        header('Content-Type: application/pdf');
        header('Content-Length: ' . filesize($full_path));
        header('Content-Disposition: inline; filename="' . $number . '.pdf"');
        if (ob_get_level()) {
            ob_end_clean();
        }
        flush();
        readfile($full_path);
        exit;
    }
}

http_response_code(404);
exit("無法取得 $number 的PDF檔案");
