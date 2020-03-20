<?php
require_once("./include/init.php");
$full_path = SYSTEM_CONFIG["USER_PHOTO_FOLDER"].$_REQUEST["name"].'.jpg';
if (!file_exists($full_path)) {
    $full_path = SYSTEM_CONFIG["USER_PHOTO_FOLDER"].$_REQUEST["name"].'-1.jpg';
    if (!file_exists($full_path)) {
        $full_path = SYSTEM_CONFIG["USER_PHOTO_FOLDER"].$_REQUEST["id"].'.jpg';
        if (!file_exists($full_path)) {
            $full_path = 'assets\\img\\not_found.jpg';
        }
    }
}
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$contentType = finfo_file($finfo, $full_path);
finfo_close($finfo);
header('Content-Type: ' . $contentType);
readfile($full_path);
?>
