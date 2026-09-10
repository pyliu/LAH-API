<?php
// ═══════════════════════════════════════════════════════════════════════════
//  NAS 多媒體影音特區 - 核心設定、路徑解析與公用工具函式 (video_core.php)
//  - 嚴格相容 PHP 5.6 (ASUSTOR AS-202TE ADM 3.5 運行環境)
//  - 100% 離線優先 (Offline-First) 架構
//  - 完美相容 UTF-8 中文檔名與 32-bit NAS 大於 2GB 巨量影片
// ═══════════════════════════════════════════════════════════════════════════

require_once __DIR__ . '/auth.php';
if (!defined('DISABLE_PIN_AUTH')) {
    require_pin_auth();
}

// 設定 UTF-8 環境編碼，避免 locale 為 C 時中文字元處理出錯
@setlocale(LC_ALL, 'zh_TW.UTF-8', 'en_US.UTF-8', 'C.UTF-8', 'UTF-8');
if (function_exists('mb_internal_encoding')) {
    @mb_internal_encoding('UTF-8');
}
if (!@ini_get('date.timezone')) {
    @date_default_timezone_set('Asia/Taipei');
}

// ─── 基礎設定 ─────────────────────────────────────────────────────────────
// 預設影音資料庫路徑清單（支援多目錄聯合掃描與動態瀏覽）
// 優先從 .env 讀取 VIDEO_DIRS 或 VIDEO_DIR 設定（支援逗號 , 分號 ; 直線 | 分隔多個路徑）
$default_video_dirs = array(
    '/share/USB1/@影片',
    '/share/USB1/@保存影片'
);

$env_video_dirs_raw = function_exists('env') ? env('VIDEO_DIRS', env('VIDEO_DIR', '')) : '';
if (!empty($env_video_dirs_raw)) {
    $parts = preg_split('/[,;|\\r\\n]+/', (string)$env_video_dirs_raw);
    $parsed_dirs = array();
    if (is_array($parts)) {
        foreach ($parts as $p) {
            $p = trim($p);
            if ($p !== '') {
                $parsed_dirs[] = $p;
            }
        }
    }
    if (!empty($parsed_dirs)) {
        $default_video_dirs = $parsed_dirs;
    }
}

// 支援目前瀏覽器所有可播放的影片格式 (Video)
$video_extensions = array('mp4', 'webm', 'mkv', 'm4v', 'mov', 'ogv', '3gp', '3gpp', 'ts', 'avi');

// 支援目前瀏覽器所有可播放的音訊格式 (Audio - 音樂/廣播劇/無損)
$audio_extensions = array('mp3', 'flac', 'm4a', 'wav', 'aac', 'ogg', 'oga', 'opus', 'weba');

// 合併所有允許播放的影音副檔名
$allowed_extensions = array_merge($video_extensions, $audio_extensions);

// 支援的外掛字幕副檔名（依瀏覽器支援度排序）
$subtitle_extensions = array('vtt', 'srt', 'ass', 'ssa', 'sub');

// 支援透過 GET 參數自訂路徑測試（支援以逗號 , 分號 ; 或換行分隔多個測試路徑）
if (isset($_GET['dir']) && !empty($_GET['dir'])) {
    $custom_parts = preg_split('/[,;|\\r\\n]+/', (string)$_GET['dir']);
    $parsed_custom_dirs = array();
    if (is_array($custom_parts)) {
        foreach ($custom_parts as $cp) {
            $cp = trim($cp);
            if ($cp !== '') {
                $parsed_custom_dirs[] = $cp;
            }
        }
    }
    $video_dirs = !empty($parsed_custom_dirs) ? $parsed_custom_dirs : array(trim($_GET['dir']));
} else {
    $video_dirs = array();
    foreach ($default_video_dirs as $d) {
        if (@is_dir($d)) {
            $video_dirs[] = $d;
        }
    }
    // 若預設目錄均未掛載，嘗試本機測試 videos 目錄或當前目錄
    if (empty($video_dirs)) {
        if (@is_dir(__DIR__ . '/videos')) {
            $video_dirs[] = __DIR__ . '/videos';
        } elseif (@is_dir(__DIR__)) {
            $video_dirs[] = __DIR__;
        } else {
            // 保留預設目錄以利未掛載時的診斷提示
            $video_dirs = $default_video_dirs;
        }
    }
}
$video_dir = $video_dirs[0]; // 向後相容的主要基準目錄

// ─── 輔助函數 ─────────────────────────────────────────────────────────────

/**
 * 二進位安全之檔名與副檔名解析
 * 解決 PHP pathinfo 在非 UTF-8 locale (如 C/POSIX) 下丟失中文字串的嚴重缺陷
 */
function get_filename_parts($filename) {
    $last_dot = strrpos($filename, '.');
    if ($last_dot !== false) {
        return array(
            'title' => substr($filename, 0, $last_dot),
            'ext'   => strtolower(substr($filename, $last_dot + 1))
        );
    }
    return array(
        'title' => $filename,
        'ext'   => ''
    );
}

/**
 * 檔案系統編碼相容性轉換 (Windows ANSI / CP950 -> UTF-8)
 * 保證在 Windows 開發環境與 NAS Linux (UTF-8) 均能輸出合法 UTF-8 字串供 JSON 序列化
 */
function fs_to_utf8($str) {
    if (DIRECTORY_SEPARATOR === '\\' && is_string($str) && $str !== '') {
        if (@preg_match('//u', $str) !== 1) {
            $u = @iconv('CP950', 'UTF-8//IGNORE', $str);
            if ($u !== false && $u !== '') {
                return $u;
            }
        }
    }
    return $str;
}

/**
 * UTF-8 字串轉為本機檔案系統路徑 (相容 Windows ANSI / CP950 / BIG5 / GBK)
 * 解決 Windows CRT 檔案函數 (file_exists, fopen, realpath) 無法直接讀取 UTF-8 繁中路徑之限制
 */
function safe_fs_path($path) {
    if (DIRECTORY_SEPARATOR === '\\' && is_string($path) && $path !== '') {
        if (@file_exists($path)) {
            return $path;
        }
        $cp = @iconv('UTF-8', 'CP950//IGNORE', $path);
        if ($cp !== false && @file_exists($cp)) {
            return $cp;
        }
        $big5 = @iconv('UTF-8', 'BIG5//IGNORE', $path);
        if ($big5 !== false && @file_exists($big5)) {
            return $big5;
        }
        $gbk = @iconv('UTF-8', 'GBK//IGNORE', $path);
        if ($gbk !== false && @file_exists($gbk)) {
            return $gbk;
        }
    }
    return $path;
}

/**
 * 跨平台安全 Shell 參數過濾（相容 UTF-8 中文檔名，防止 PHP 原生 escapeshellarg 在 C locale 吞噬多位元組字元）
 */
function safe_escapeshellarg($arg) {
    if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
        return '"' . str_replace(array('"', '%'), array('""', '%%'), $arg) . '"';
    }
    return "'" . str_replace("'", "'\\''", $arg) . "'";
}

/**
 * 跨平台精確 64-bit 檔案大小獲取函式
 * 在 32 位元 PHP (x86 Atom) 中，filesize() 超過 2GB 會整數溢位回傳 false 或負值
 */
function get_real_filesize($filepath) {
    $filepath = safe_fs_path($filepath);
    if (!@file_exists($filepath)) return 0.0;

    // 1. Linux/NAS 環境下透過 stat 核心指令獲取 64-bit 精確 byte 數
    if (strtoupper(substr(PHP_OS, 0, 3)) !== 'WIN') {
        $cmd = 'stat -c %s ' . safe_escapeshellarg($filepath) . ' 2>/dev/null';
        $out = trim(@shell_exec($cmd));
        if ($out !== '' && ctype_digit($out) && (float)$out > 0) {
            return (float)$out;
        }
    } else {
        // Windows 環境透過 cmd for %~zI 獲取 64-bit 精確檔案大小
        $cmd = 'cmd.exe /c "for %I in (' . safe_escapeshellarg($filepath) . ') do @echo %~zI" 2>nul';
        $out = trim(@shell_exec($cmd));
        if ($out !== '' && ctype_digit($out) && (float)$out > 0) {
            return (float)$out;
        }
    }

    // 2. 原生 filesize 配合 %u 格式化
    $size = @filesize($filepath);
    if ($size !== false && $size > 0) {
        return (float)sprintf("%u", $size);
    }

    // 3. 嘗試以串流指標 SEEK_END 取得大小
    $fp = @fopen($filepath, 'rb');
    if ($fp) {
        @fseek($fp, 0, SEEK_END);
        $pos = @ftell($fp);
        @fclose($fp);
        if ($pos !== false && $pos > 0) {
            return (float)sprintf("%u", $pos);
        }
    }

    return 0.0;
}

/**
 * 格式化檔案大小 (支援浮點數大檔運算)
 */
function format_bytes($bytes, $precision = 1) {
    $bytes = (float)$bytes;
    if ($bytes <= 0) return '0 B';
    $units = array('B', 'KB', 'MB', 'GB', 'TB');
    $pow = floor(log($bytes, 1024));
    $pow = max(0, min((int)$pow, count($units) - 1));
    $bytes /= pow(1024, $pow);
    return round($bytes, $precision) . ' ' . $units[$pow];
}

/**
 * 單一目錄安全路徑解析
 */
function safe_path_resolve_single($base_dir, $sub_path) {
    $clean = str_replace(array('../', '..\\', "\0"), '', $sub_path);
    $clean = trim($clean, '/\\');
    $target = $base_dir . ($clean !== '' ? '/' . $clean : '');

    // 優先使用 realpath
    $real_target = @realpath($target);
    $real_base = @realpath($base_dir);
    if ($real_target && $real_base && strpos($real_target, $real_base) === 0) {
        return $real_target;
    }

    // 若因 UTF-8 字元使 realpath 失敗，進行規範化字串前綴校驗
    if (@file_exists($target)) {
        $norm_target = str_replace('\\', '/', $target);
        $norm_base = str_replace('\\', '/', $base_dir);
        if (strpos($norm_target . '/', rtrim($norm_base, '/') . '/') === 0) {
            return $target;
        }
    }

    // Windows 平台編碼容錯 (UTF-8 轉 ANSI / CP950 / BIG5)
    if (DIRECTORY_SEPARATOR === '\\') {
        $target_fs = safe_fs_path($target);
        if ($target_fs !== $target && @file_exists($target_fs)) {
            $real_target = @realpath($target_fs);
            $real_base = @realpath($base_dir);
            if ($real_target && $real_base && strpos($real_target, $real_base) === 0) {
                return $target_fs;
            }
            $norm_target = str_replace('\\', '/', $target_fs);
            $norm_base = str_replace('\\', '/', $base_dir);
            if (strpos($norm_target . '/', rtrim($norm_base, '/') . '/') === 0) {
                return $target_fs;
            }
        }
    }

    return false;
}

/**
 * 跨目錄多媒體安全路徑解析（防止跨目錄穿越，並相容多資料庫聯合前綴）
 */
function safe_path_resolve($base_dir, $sub_path) {
    global $video_dirs;
    $dirs = (isset($video_dirs) && is_array($video_dirs) && !empty($video_dirs)) ? $video_dirs : array($base_dir);

    $clean = str_replace(array('../', '..\\', "\0"), '', $sub_path);
    $clean = trim($clean, '/\\');

    // 1. 若路徑開頭帶有特定庫名稱（例如 "@影片/動畫/xxx" 或 "@保存影片/xxx"）
    foreach ($dirs as $b) {
        $b_name = basename($b);
        if ($clean === $b_name) {
            $r = @realpath($b);
            return $r ? $r : $b;
        }
        $prefix = $b_name . '/';
        if (substr($clean, 0, strlen($prefix)) === $prefix) {
            $inner = substr($clean, strlen($prefix));
            $res = safe_path_resolve_single($b, $inner);
            if ($res) return $res;
        }
    }

    // 2. 優先在傳入的特定 base_dir 解析
    $res = safe_path_resolve_single($base_dir, $clean);
    if ($res && @file_exists($res)) return $res;

    // 3. 搜尋其他目錄中是否存在
    foreach ($dirs as $b) {
        if ($b === $base_dir) continue;
        $res2 = safe_path_resolve_single($b, $clean);
        if ($res2 && @file_exists($res2)) return $res2;
    }

    // 4. 回退至預設解析
    return safe_path_resolve_single($base_dir, $clean);
}

/**
 * 32-bit PHP 安全 fseek (支援 > 2GB offset 定位)
 */
function safe_fseek($fp, $offset) {
    rewind($fp);
    $chunk_limit = 2147483647; // 2GB 邊界
    while ($offset > $chunk_limit) {
        if (fseek($fp, $chunk_limit, SEEK_CUR) !== 0) {
            return -1;
        }
        $offset -= $chunk_limit;
    }
    return fseek($fp, (int)$offset, SEEK_CUR);
}

/**
 * 格式化相對時間 (例如: 10 分鐘前, 2 天前)
 */
function get_time_ago($time) {
    if (!$time) return '-';
    $diff = time() - $time;
    if ($diff < 60) return '剛剛';
    if ($diff < 3600) return floor($diff / 60) . ' 分鐘前';
    if ($diff < 86400) return floor($diff / 3600) . ' 小時前';
    if ($diff < 86400 * 7) return floor($diff / 86400) . ' 天前';
    if ($diff < 86400 * 30) return floor($diff / (86400 * 7)) . ' 週前';
    return date('Y-m-d', $time);
}
