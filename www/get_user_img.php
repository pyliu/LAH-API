<?php
require_once("./include/init.php");
require_once("./include/System.class.php");

$system = System::getInstance();
$default_path = ROOT_DIR.DIRECTORY_SEPARATOR."assets".DIRECTORY_SEPARATOR."img".DIRECTORY_SEPARATOR."users".DIRECTORY_SEPARATOR;
$fallback_path = rtrim($system->getUserPhotoFolderPath());

$key = array_key_exists('id', $_REQUEST) ? $_REQUEST['id'] : '';
// $full_path = $default_path.$key.'.jpg';
$full_path = $default_path.$key.'.jpg';
if (!file_exists($full_path)) {
    $key = $_REQUEST["name"];
    $full_path = $default_path.$_REQUEST["name"].'.jpg';
    if (!file_exists($full_path)) {
        $key = 'not_found';
        $full_path = $default_path.'not_found.jpg';
    }
}


if (!file_exists($full_path)) {
    $full_path = $default_path.$key.'-1.jpg';
    if (!file_exists($full_path)) {
        $full_path = $default_path.trim($key, '_avatar').'.jpg';
        if (!file_exists($full_path)) {
            if ($system->isMockMode()) {
                $log->warning("Can not find the $key photo ... ");
                $full_path = 'assets\\img\\not_found.jpg';
            } else {
                // try to use fallback to get the image
                $full_path = $fallback_path.DIRECTORY_SEPARATOR.$key.'.JPG';
                if (!file_exists($full_path)) {
                    $full_path = $fallback_path.DIRECTORY_SEPARATOR.$key.'.jpg';
                }
                if (file_exists($full_path)) {
                    $log->info("Trying to copy the $full_path to ./$default_path$key.jpg");
                    copy($full_path, $default_path.$key.".jpg");
                    $full_path = $default_path.$key.".jpg";
                } else {
                    $log->warning("Can not find the $key photo ... ");
                    $full_path = 'assets\\img\\not_found.jpg';
                }
            }
        }
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
