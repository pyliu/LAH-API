<?php
require_once(dirname(dirname(__FILE__)).DIRECTORY_SEPARATOR."include".DIRECTORY_SEPARATOR."init.php");

$code = $_GET["code"];

$exp_folder = ROOT_DIR.DIRECTORY_SEPARATOR.'export'.DIRECTORY_SEPARATOR;

if (isset($_SESSION[$code])) {
    // copy tmp.txt to the target as well
    $target = $exp_folder.$_SESSION[$code];
    header("Content-Length: " . filesize($target));
    // header('Content-Type: text/plain');
    header('Content-Disposition: attachment; filename='.($_SESSION[$code] ?? $code.'.txt'));
    readfile($target);
} else {
    $log->error('$_SESSION variable '.$code.' not set. Can not download the file.');
    die("SESSION變數${code}未設定，無法下載檔案。");
}
