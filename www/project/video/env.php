<?php
// ═══════════════════════════════════════════════════════════════════════════
//  ASUSTOR NAS 環境變數安全讀取與認證核心 (env.php)
//  - 嚴格禁止外部直接 Web 訪問 (Direct Access Denied)
//  - 直接讀取與解析 .env 鍵值設定檔
//  - 提供常數時間時序安全比對 (Timing-Safe PIN Verify)
//  - 100% 離線原生實作，零外部依賴
// ═══════════════════════════════════════════════════════════════════════════

// 1. 防護機制：禁止外界透過瀏覽器直接執行此核心腳本
if (basename(__FILE__) === basename(isset($_SERVER['SCRIPT_FILENAME']) ? $_SERVER['SCRIPT_FILENAME'] : '')) {
    if (!headers_sent()) {
        header('HTTP/1.1 403 Forbidden');
        header('Content-Type: text/plain; charset=utf-8');
    }
    exit('403 Forbidden: Direct script access is denied.');
}

if (!defined('IN_ENV_APP')) {
    define('IN_ENV_APP', true);
}

/**
 * 載入並解析 .env 設定檔內容
 *
 * @param string|null $custom_path 自訂設定檔絕對路徑
 * @return array 解析完成的鍵值對陣列
 */
function load_env($custom_path = null) {
    static $env_cache = null;

    if ($env_cache !== null) {
        return $env_cache;
    }

    $env_cache = array();

    // 讀取標準 .env 檔案
    $env_path = $custom_path ? $custom_path : (__DIR__ . '/.env');

    if (@file_exists($env_path) && @is_readable($env_path)) {
        $lines = @file($env_path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines !== false) {
            foreach ($lines as $line) {
                $line = trim($line);

                // 略過以 # 或 ; 開頭的註解行
                if ($line === '' || substr($line, 0, 1) === '#' || substr($line, 0, 1) === ';') {
                    continue;
                }

                // 尋找第一個等號
                $eq_pos = strpos($line, '=');
                if ($eq_pos === false) {
                    continue;
                }

                $key = trim(substr($line, 0, $eq_pos));
                $val = trim(substr($line, $eq_pos + 1));

                // 去除前後包裹的雙引號或單引號
                $len = strlen($val);
                if ($len >= 2) {
                    $first = substr($val, 0, 1);
                    $last = substr($val, -1);
                    if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
                        $val = substr($val, 1, -1);
                    }
                }

                if ($key !== '') {
                    $env_cache[$key] = $val;
                    if (!isset($_SERVER[$key])) $_SERVER[$key] = $val;
                    if (!isset($_ENV[$key]))    $_ENV[$key] = $val;
                }
            }
        }
    }

    return $env_cache;
}

/**
 * 讀取指定的環境變數
 *
 * @param string $key 變數鍵名
 * @param mixed $default 若不存在時的預設值
 * @return mixed
 */
function env($key, $default = null) {
    $data = load_env();
    if (array_key_exists($key, $data)) {
        return $data[$key];
    }
    $env_val = getenv($key);
    if ($env_val !== false) {
        return $env_val;
    }
    return $default;
}

/**
 * 檢查是否已配置 PIN 碼
 *
 * @return bool
 */
function has_pin_protection() {
    $pin = env('PIN', env('SECRET_PIN', ''));
    return trim($pin) !== '';
}

/**
 * 驗證使用者輸入的 PIN 碼（具備常數時間時序攻擊防護）
 *
 * @param string $input_pin 使用者輸入的 PIN 碼
 * @return bool 驗證是否成功
 */
function verify_pin($input_pin) {
    $correct_pin = (string)env('PIN', env('SECRET_PIN', ''));
    $correct_pin = trim($correct_pin);

    // 若未設定 PIN，視為不需驗證通過
    if ($correct_pin === '') {
        return true;
    }

    $input_pin = trim((string)$input_pin);

    // 優先使用 PHP 5.6+ 內建的 hash_equals 進行常數時間比對（避免時序攻擊推算字元）
    if (function_exists('hash_equals')) {
        return hash_equals($correct_pin, $input_pin);
    }

    // 相容性常數時間比對回退實作
    if (strlen($correct_pin) !== strlen($input_pin)) {
        return false;
    }
    $res = 0;
    for ($i = 0; $i < strlen($correct_pin); $i++) {
        $res |= ord($correct_pin[$i]) ^ ord($input_pin[$i]);
    }
    return $res === 0;
}
