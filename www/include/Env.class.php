<?php
/**
 * Env - 輕量化環境變數讀取與解析核心
 * 支援讀取 ROOT_DIR/.env (即 www/.env) 鍵值設定檔，零外部套件依賴。
 */

class Env {
    /** @var array|null 快取的環境變數清單 */
    private static $env_cache = null;

    /**
     * 載入並解析 .env 設定檔
     *
     * @param string|null $custom_path 自訂路徑，預設為 ROOT_DIR/.env
     * @return array 解析後的鍵值陣列
     */
    public static function load($custom_path = null) {
        if (self::$env_cache !== null && $custom_path === null) {
            return self::$env_cache;
        }

        $cache = array();
        $env_path = $custom_path ?: (defined('ROOT_DIR') ? ROOT_DIR . DIRECTORY_SEPARATOR . '.env' : dirname(__DIR__) . DIRECTORY_SEPARATOR . '.env');

        if (@file_exists($env_path) && @is_readable($env_path)) {
            $lines = @file($env_path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            if ($lines !== false) {
                foreach ($lines as $line) {
                    $line = trim($line);

                    // 略過空行與註解（# 或 ; 開頭）
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

                    // 去除前後引號（單引號或雙引號）
                    $len = strlen($val);
                    if ($len >= 2) {
                        $first = substr($val, 0, 1);
                        $last = substr($val, -1);
                        if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
                            $val = substr($val, 1, -1);
                        }
                    }

                    if ($key !== '') {
                        $cache[$key] = $val;
                        if (!isset($_SERVER[$key])) $_SERVER[$key] = $val;
                        if (!isset($_ENV[$key]))    $_ENV[$key] = $val;
                    }
                }
            }
        }

        if ($custom_path === null) {
            self::$env_cache = $cache;
        }

        return $cache;
    }

    /**
     * 取得指定環境變數值
     *
     * @param string $key 變數名稱
     * @param mixed $default 預設值
     * @return mixed
     */
    public static function get($key, $default = null) {
        if (self::$env_cache === null) {
            self::load();
        }

        if (array_key_exists($key, self::$env_cache)) {
            return self::$env_cache[$key];
        }

        $env_val = getenv($key);
        if ($env_val !== false) {
            return $env_val;
        }

        return $default;
    }

    /**
     * 檢查指定環境變數是否存在
     *
     * @param string $key 變數名稱
     * @return bool
     */
    public static function has($key) {
        if (self::$env_cache === null) {
            self::load();
        }
        return array_key_exists($key, self::$env_cache) || getenv($key) !== false;
    }

    /**
     * 重設快取（供測試或動態重載使用）
     */
    public static function reset() {
        self::$env_cache = null;
    }
}

if (!function_exists('env')) {
    /**
     * 全域輔助函式 env()
     *
     * @param string $key 變數名稱
     * @param mixed $default 預設值
     * @return mixed
     */
    function env($key, $default = null) {
        return Env::get($key, $default);
    }
}
