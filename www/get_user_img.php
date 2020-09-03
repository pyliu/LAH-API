<?php
require_once("./include/init.php");
$default_path = ROOT_DIR.DIRECTORY_SEPARATOR."assets".DIRECTORY_SEPARATOR."img".DIRECTORY_SEPARATOR."users".DIRECTORY_SEPARATOR;
$fallback_path = SYSTEM_CONFIG["USER_PHOTO_FOLDER"];
$key = $_REQUEST["name"] ?? $_REQUEST["id"] ?? "not_found";
$full_path = $default_path.$key.'.jpg';
if (!file_exists($full_path)) {
    $full_path = $default_path.$key.'-1.jpg';
    if (!file_exists($full_path)) {
        $full_path = $default_path.trim($_REQUEST["name"], '_avatar').'.jpg';
        if (!file_exists($full_path)) {
            // try to use fallback to get the image
            $full_path = $fallback_path.$key.'.JPG';
            if (!file_exists($full_path)) {
                $full_path = $fallback_path.$key.'.jpg';
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


$finfo = finfo_open(FILEINFO_MIME_TYPE);
$contentType = finfo_file($finfo, $full_path);
finfo_close($finfo);
header('Content-Type: ' . $contentType);
ob_clean();
flush();
readfile($full_path);
?>
