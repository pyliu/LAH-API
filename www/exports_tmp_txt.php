<?php
require_once("include/init.php");

$tmp_file = ROOT_DIR.DIRECTORY_SEPARATOR.'exports'.DIRECTORY_SEPARATOR.'tmp.txt';

header("Content-Length: " . filesize($tmp_file));
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename='.($_GET['filename'] ?? 'tmp').'.txt');

readfile($tmp_file);
?>