<?php
require_once(dirname(dirname(__FILE__)).DIRECTORY_SEPARATOR."include".DIRECTORY_SEPARATOR."init.php");

$exp_folder = ROOT_DIR.DIRECTORY_SEPARATOR.'export'.DIRECTORY_SEPARATOR;
$tmp_file = $exp_folder.'tmp.txt';

if (isset($_SESSION['export_tmp_txt_filename']) && $_SESSION['export_tmp_txt_filename'] != 'tmp') {
    // copy tmp.txt to the target as well
    $target = $exp_folder.$_SESSION['export_tmp_txt_filename'].'.txt';
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
