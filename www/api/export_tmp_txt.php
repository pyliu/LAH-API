<?php
require_once(dirname(dirname(__FILE__)).DIRECTORY_SEPARATOR."include".DIRECTORY_SEPARATOR."init.php");

$tmp_file = ROOT_DIR.DIRECTORY_SEPARATOR.'exports'.DIRECTORY_SEPARATOR.'tmp.txt';

if (isset($_SESSION['export_tmp_txt_filename'])) {
    // copy tmp.txt to the target as well
    $target = ROOT_DIR.DIRECTORY_SEPARATOR.'exports'.DIRECTORY_SEPARATOR.$_SESSION['export_tmp_txt_filename'].'.txt';
    $res = @copy($tmp_file, $target);
    if ($res) {
        $log->info('Copied tmp.txt to '.$target);
    } else {
        $log->error('Cannot copy tmp.txt to '.$target);
    }
}

header("Content-Length: " . filesize($tmp_file));
// header('Content-Type: text/plain');
header('Content-Disposition: attachment; filename='.($_GET['filename'] ?? 'tmp').'.txt');

readfile($tmp_file);
?>