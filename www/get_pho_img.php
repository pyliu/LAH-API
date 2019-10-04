<?php
$full_path = '\\\\220.1.35.24\\Pho\\'.$_REQUEST["name"].'.jpg';
if (!file_exists($full_path)) {
    $full_path = '\\\\220.1.35.24\\Pho\\'.$_REQUEST["name"].'-1.jpg';
    if (!file_exists($full_path)) {
        $full_path = 'assets\\img\\not_found.jpg';
    }
}
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$contentType = finfo_file($finfo, $full_path);
finfo_close($finfo);
header('Content-Type: ' . $contentType);
readfile($full_path);
?>
