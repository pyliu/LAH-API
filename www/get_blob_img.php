<?php
require_once("./include/init.php");
require_once("./include/SQLiteImage.class.php");

$sqlite_image = new SQLiteImage();
$blob = $sqlite_image->getImageByPath($_REQUEST['path']);
if ($blob === false) {
    $blob = $sqlite_image->getImageByFilename($_REQUEST['filename']);
}

header('Content-Type: image/jpeg');
header('Content-Length: '.strlen($blob));
ob_clean();
flush();

echo $blob;
