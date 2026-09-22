<?php
require_once(dirname(dirname(__FILE__)).DIRECTORY_SEPARATOR.'init.php');
require_once(INC_DIR.DIRECTORY_SEPARATOR."api".DIRECTORY_SEPARATOR."FileAPICommand.class.php");

class FileAPIDataCleanCommand extends FileAPICommand {
    private static $export_codes = array(
        'AI00301',
        'AI00401',
        'AI00601_B',
        'AI00601_E',
        'AI00701',
        'AI00801',
        'AI00901',
        'AI01001',
        'AI01101',
        'AI02901_B',
        'AI02901_E'
    );

    public function __construct() {}

    public function __destruct() {}

    public function execute() {
        $exp_folder = EXPORT_DIR.DIRECTORY_SEPARATOR;
        $deleted_files = array();
        $fail_files = array();

        $raw_filenames = $_POST['filenames'] ?? array();
        if (is_string($raw_filenames)) {
            $raw_filenames = explode(',', $raw_filenames);
        }

        $clean_all = !empty($_POST['all']) && ($_POST['all'] === true || $_POST['all'] === 'true' || $_POST['all'] === '1');
        $sections = $_POST['sections'] ?? array();
        if (is_string($sections)) {
            $sections = explode(',', $sections);
        }

        $target_files = array();

        // 1. 若指定特定檔案名稱
        if (is_array($raw_filenames) && !empty($raw_filenames)) {
            foreach ($raw_filenames as $fname) {
                $clean = basename(trim($fname));
                if (!empty($clean)) {
                    $ext = strtolower(pathinfo($clean, PATHINFO_EXTENSION));
                    // 僅允許清理 txt 與 zip 檔案，避免誤刪其他資源
                    if (in_array($ext, array('txt', 'zip'), true)) {
                        $fpath = $exp_folder . $clean;
                        if (is_file($fpath) && file_exists($fpath)) {
                            $target_files[$clean] = $fpath;
                        }
                    }
                }
            }
        }

        // 2. 若為全部清理或未指定特定檔案
        if ($clean_all || empty($target_files)) {
            $all_txt = glob($exp_folder . "*.txt");
            if (!empty($all_txt)) {
                foreach ($all_txt as $fpath) {
                    $bname = basename($fpath);
                    // 匹配地籍匯出檔案格式 (包含 AI 代碼)
                    $is_export_data = false;
                    foreach (self::$export_codes as $code) {
                        if (strpos($bname, $code) !== false) {
                            $is_export_data = true;
                            break;
                        }
                    }
                    if ($is_export_data) {
                        $target_files[$bname] = $fpath;
                    }
                }
            }

            // 匹配地籍匯出 ZIP 檔
            $all_zip = glob($exp_folder . "*地籍資料*.zip");
            if (!empty($all_zip)) {
                foreach ($all_zip as $fpath) {
                    $bname = basename($fpath);
                    $target_files[$bname] = $fpath;
                }
            }

            // 匹配預設地籍匯出 ZIP 檔名
            $def_zip = glob($exp_folder . "*_地籍資料匯出.zip");
            if (!empty($def_zip)) {
                foreach ($def_zip as $fpath) {
                    $bname = basename($fpath);
                    $target_files[$bname] = $fpath;
                }
            }
        }

        // 3. 判斷是否僅查詢清單 (不執行刪除)
        $is_list_only = (isset($_POST['action']) && $_POST['action'] === 'list') || (isset($_POST['type']) && $_POST['type'] === 'file_data_list');
        if ($is_list_only) {
            $items = array();
            foreach ($target_files as $name => $path) {
                if (file_exists($path)) {
                    $fsize = filesize($path);
                    $fmtime = filemtime($path);
                    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
                    $code = '';
                    foreach (self::$export_codes as $c) {
                        if (strpos($name, $c) !== false) {
                            $code = $c;
                            break;
                        }
                    }
                    $items[] = array(
                        'filename' => $name,
                        'size' => $fsize,
                        'size_formatted' => $this->formatBytes($fsize),
                        'mtime' => date('Y-m-d H:i:s', $fmtime),
                        'type' => $ext,
                        'code' => $code
                    );
                }
            }
            // 依檔案修改時間降冪排列
            usort($items, function($a, $b) {
                return strcmp($b['mtime'], $a['mtime']);
            });

            echoJSONResponse("取得後端產出檔案清單成功", STATUS_CODE::SUCCESS_NORMAL, array(
                'data' => $items,
                'data_count' => count($items)
            ));
            return true;
        }

        // 4. 執行刪除
        foreach ($target_files as $name => $path) {
            if (@unlink($path)) {
                $deleted_files[] = $name;
            } else {
                $fail_files[] = $name;
            }
        }

        // 4. 清除相關 Session
        foreach (self::$export_codes as $code) {
            if (isset($_SESSION[$code])) {
                unset($_SESSION[$code]);
            }
        }

        $deleted_count = count($deleted_files);
        if ($deleted_count > 0) {
            Logger::getInstance()->info("已清理 {$deleted_count} 個匯出檔案: " . implode(', ', $deleted_files));
            echoJSONResponse("成功清理 {$deleted_count} 個後端產出檔案。", STATUS_CODE::SUCCESS_NORMAL, array(
                'deleted_count' => $deleted_count,
                'deleted_files' => $deleted_files,
                'failed_files' => $fail_files
            ));
        } else {
            echoJSONResponse("後端無符合條件之產出檔案可供清理。", STATUS_CODE::SUCCESS_WITH_NO_RECORD, array(
                'deleted_count' => 0,
                'deleted_files' => array(),
                'failed_files' => $fail_files
            ));
        }
        return true;
    }

    private function formatBytes($bytes, $precision = 1) {
        $units = array('B', 'KB', 'MB', 'GB');
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);
        return round($bytes, $precision) . ' ' . $units[$pow];
    }
}
