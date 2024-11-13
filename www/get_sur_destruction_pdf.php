<?php
require_once("./include/init.php");
require_once("./include/SQLiteSurDestructionTracking.class.php");

$tracking = new SQLiteSurDestructionTracking();
$default_path = ROOT_DIR.DIRECTORY_SEPARATOR."assets".DIRECTORY_SEPARATOR."pdf".DIRECTORY_SEPARATOR."sur_destruction_tracking";

$id = array_key_exists('id', $_REQUEST) ? $_REQUEST['id'] : '';
$record = $tracking->getOne($id);
if ($record !== false) {
    $filename = $record['apply_date'].'_'.$record['section_code'].'_'.$record['land_number'].'_'.$record['building_number'];
    $full_path = $default_path.DIRECTORY_SEPARATOR.$filename.'.pdf';
    header('Content-Type: application/pdf');
    header('Content-Length: '.filesize($full_path));
    ob_clean();
    flush();
    readfile($full_path);
}
die("無法取得 $id 的PDF");
