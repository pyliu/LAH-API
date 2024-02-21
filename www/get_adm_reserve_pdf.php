<?php
require_once("./include/init.php");
require_once("./include/SQLiteAdmReserveFilePDF.class.php");

$arfpdf = new SQLiteAdmReserveFilePDF();
$default_path = ROOT_DIR.DIRECTORY_SEPARATOR."assets".DIRECTORY_SEPARATOR."pdf".DIRECTORY_SEPARATOR."adm_reserve_file";

$number = array_key_exists('number', $_REQUEST) ? $_REQUEST['number'] : '';
$record = $arfpdf->getOneByNumber($number);
if ($record !== false) {
    $full_path = $default_path.DIRECTORY_SEPARATOR.$number.'.pdf';
    header('Content-Type: application/pdf');
    header('Content-Length: '.filesize($full_path));
    ob_clean();
    flush();
    readfile($full_path);
}
die("無法取得 $number 的PDF");
