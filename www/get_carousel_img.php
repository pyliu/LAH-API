<?php
require_once(__DIR__."/include/init.php");
require_once(__DIR__."/include/System.class.php");

$system = System::getInstance();
$default_path = ROOT_DIR.DIRECTORY_SEPARATOR."assets".DIRECTORY_SEPARATOR."img".DIRECTORY_SEPARATOR."users".DIRECTORY_SEPARATOR;
$default_path = "";
$fallback_path = "assets\\img\\poster\\";
$not_found_path = "assets\\img\\not_found.jpg";

$key = array_key_exists('file', $_REQUEST) ? $_REQUEST['file'] : '';
$full_path = $default_path.$key;
if (!file_exists($full_path)) {
    $full_path = $fallback_path.$key;
    if (!file_exists($full_path)) {
        $full_path = $not_found_path;
    }
}

$finfo = finfo_open(FILEINFO_MIME_TYPE);
$contentType = finfo_file($finfo, $full_path);
finfo_close($finfo);
header('Content-Type: ' . $contentType);
header('Content-Length: '.filesize($full_path));
ob_clean();
flush();
readfile($full_path);
