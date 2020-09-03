<?php
require_once(dirname(dirname(__FILE__)).DIRECTORY_SEPARATOR."include".DIRECTORY_SEPARATOR."init.php");

$tmp_file = ROOT_DIR.DIRECTORY_SEPARATOR.'exports'.DIRECTORY_SEPARATOR.'tmp.csv';

header("Content-Length: " . filesize($tmp_file));
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename='.($_GET['filename'] ?? 'tmp').'.csv');

readfile($tmp_file);
?>