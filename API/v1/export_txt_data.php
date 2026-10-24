<?php
require_once(dirname(dirname(__FILE__)).DIRECTORY_SEPARATOR."include".DIRECTORY_SEPARATOR."init.php");

$code = isset($_GET["code"]) ? trim($_GET["code"]) : '';
$filename = isset($_GET["filename"]) ? basename(trim($_GET["filename"])) : '';
$type = isset($_REQUEST["type"]) ? trim($_REQUEST["type"]) : '';
$is_zip = ($type === 'zip') || isset($_REQUEST["zip"]);
$is_clean = ($type === 'clean') || ($type === 'file_data_clean') || isset($_REQUEST["clean"]);

$exp_folder = EXPORT_DIR.DIRECTORY_SEPARATOR;

// 0-1. 支援清理已匯出資料檔案
if ($is_clean) {
    require_once(INC_DIR.DIRECTORY_SEPARATOR."api".DIRECTORY_SEPARATOR."FileAPIDataCleanCommand.class.php");
    $clean_cmd = new FileAPIDataCleanCommand();
    $clean_cmd->execute();
    exit;
}

// 0-2. 支援多檔案打包 ZIP 格式即時下載
if ($is_zip) {
    $raw_filenames = $_REQUEST['filenames'] ?? array();
    if (is_string($raw_filenames)) {
        $raw_filenames = explode(',', $raw_filenames);
    }

    $zip_files = array();
    if (is_array($raw_filenames)) {
        foreach ($raw_filenames as $fname) {
            $clean = basename(trim($fname));
            $fpath = $exp_folder . $clean;
            if (!empty($clean) && is_file($fpath) && file_exists($fpath)) {
                $zip_files[$clean] = $fpath;
            }
        }
    }

    // 若未指定 filenames 但有 section，則自目錄匹配
    if (empty($zip_files)) {
        $section = isset($_REQUEST['section']) ? trim($_REQUEST['section']) : '';
        if (!empty($section)) {
            $matched = glob($exp_folder . "*_{$section}_*.txt");
            if (!empty($matched)) {
                foreach ($matched as $fpath) {
                    $zip_files[basename($fpath)] = $fpath;
                }
            }
        }
    }

    if (!empty($zip_files)) {
        global $today;
        $req_zip_name = isset($_REQUEST['zip_filename']) ? basename(trim($_REQUEST['zip_filename'])) : '';
        if (empty($req_zip_name)) {
            $req_zip_name = ($today ?? date('Ymd')) . '_地籍資料匯出.zip';
        }
        if (pathinfo($req_zip_name, PATHINFO_EXTENSION) !== 'zip') {
            $req_zip_name .= '.zip';
        }

        $zip_path = $exp_folder . $req_zip_name;
        $zip = new ZipArchive();
        if ($zip->open($zip_path, ZipArchive::CREATE | ZipArchive::OVERWRITE) === TRUE) {
            foreach ($zip_files as $entry_name => $file_path) {
                $zip->addFile($file_path, $entry_name);
            }
            $zip->close();
        }

        if (file_exists($zip_path) && is_file($zip_path)) {
            $filesize = filesize($zip_path);
            $encoded_filename = rawurlencode($req_zip_name);
            $fallback_filename = 'export_data.zip';

            if (ob_get_level()) {
                ob_end_clean();
            }

            header('Content-Description: File Transfer');
            header('Content-Type: application/zip');
            header("Content-Disposition: attachment; filename=\"{$fallback_filename}\"; filename*=UTF-8''{$encoded_filename}");
            header('Content-Length: ' . $filesize);
            header('Expires: 0');
            header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
            header('Pragma: public');

            readfile($zip_path);
            exit;
        }
    }

    Logger::getInstance()->error("Export ZIP file creation failed or no files found.");
    header("HTTP/1.1 404 Not Found");
    die("找不到可供打包的檔案。");
}

// 1. 若未帶 filename，嘗試自 session 取得
if (empty($filename) && !empty($code) && isset($_SESSION[$code])) {
    $filename = basename($_SESSION[$code]);
}

// 2. 若仍無 filename 但有 code，嘗試在 export 目錄尋找最新產製的對應代碼檔案
if (empty($filename) && !empty($code)) {
    $matched = glob($exp_folder . "*_{$code}_*.txt");
    if (!empty($matched)) {
        usort($matched, function($a, $b) {
            return filemtime($b) - filemtime($a);
        });
        $filename = basename($matched[0]);
    }
}

$target = !empty($filename) ? $exp_folder . $filename : '';

if (!empty($target) && file_exists($target) && is_file($target)) {
    $filesize = filesize($target);
    $encoded_filename = rawurlencode($filename);
    $fallback_filename = !empty($code) ? "{$code}.txt" : "export.txt";

    if (ob_get_level()) {
        ob_end_clean();
    }

    header('Content-Description: File Transfer');
    header('Content-Type: application/octet-stream');
    header("Content-Disposition: attachment; filename=\"{$fallback_filename}\"; filename*=UTF-8''{$encoded_filename}");
    header('Content-Length: ' . $filesize);
    header('Expires: 0');
    header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
    header('Pragma: public');

    readfile($target);
    exit;
} else {
    Logger::getInstance()->error("Export file not found: {$filename} (code: {$code})");
    header("HTTP/1.1 404 Not Found");
    die("找不到可供下載的檔案 (檔案名稱: {$filename}，代碼: {$code})。");
}
