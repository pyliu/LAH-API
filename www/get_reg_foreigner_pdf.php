<?php
require_once("./include/init.php");
require_once("./include/SQLiteRegForeignerPDF.class.php");

$rfpdf = new SQLiteRegForeignerPDF();
$default_path = ROOT_DIR.DIRECTORY_SEPARATOR."assets".DIRECTORY_SEPARATOR."pdf";

$id = array_key_exists('id', $_REQUEST) ? $_REQUEST['id'] : '';
$record = $rfpdf->getOne($id);
if ($record !== false) {
    $full_path = $default_path.DIRECTORY_SEPARATOR.$record['year'].DIRECTORY_SEPARATOR.$record['number'].'_'.$record['fid'].'_'.$record['fname'].'.pdf';
    header('Content-Type: application/pdf');
    header('Content-Length: '.filesize($full_path));
    ob_clean();
    flush();
    readfile($full_path);
}
die("無法取得 $id 的PDF");
