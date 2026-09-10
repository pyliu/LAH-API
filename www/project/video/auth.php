<?php
// ═══════════════════════════════════════════════════════════════════════════
//  ASUSTOR NAS 安全認證與 PIN 碼閘門核心 (auth.php)
//  - 100% 離線原生實作，零外部依賴
//  - 無狀態 HMAC-SHA256 簽章憑證 (Stateless Signed HttpOnly Cookie)
//  - 內建防暴力破解頻率限制 (Brute-Force Rate Limiting)
//  - 伺服器端攔截直接輸出深色科技風 PIN Pad 解鎖介面
//  - 嚴格排除 api.php 與 save_chapter.php
// ═══════════════════════════════════════════════════════════════════════════

require_once __DIR__ . '/env.php';

// Cookie 儲存鍵名
define('AUTH_COOKIE_NAME', 'nas_pin_auth');

/**
 * 取得認證簽章專用鹽值
 */
function auth_get_salt() {
    $salt = env('AUTH_SALT', '');
    if (empty($salt)) {
        // 以檔案修改時間與伺服器資訊生成穩定本地鹽值
        $salt = md5(__FILE__ . (isset($_SERVER['SERVER_SIGNATURE']) ? $_SERVER['SERVER_SIGNATURE'] : 'nas_salt_default'));
    }
    return $salt;
}

/**
 * 產生安全簽章 Token
 *
 * @param int $expire_timestamp 過期時間戳
 * @return string Base64 編碼之簽章 Token
 */
function auth_generate_token($expire_timestamp) {
    $pin = env('PIN', env('SECRET_PIN', ''));
    $salt = auth_get_salt();
    $data = $pin . '|' . $expire_timestamp . '|' . $salt;
    $hash = hash_hmac('sha256', $data, $salt);
    return base64_encode($expire_timestamp . '.' . $hash);
}

/**
 * 驗證 Token 是否合法且未過期
 *
 * @param string $token
 * @return bool
 */
function auth_verify_token($token) {
    if (empty($token) || !is_string($token)) {
        return false;
    }

    // 解決 URL Query 或 Cookie 傳遞時 '+' 號被 urldecode 轉為空格的致命認證失真問題
    $token = str_replace(' ', '+', trim($token));

    $decoded = @base64_decode($token, true);
    if ($decoded === false || strpos($decoded, '.') === false) {
        return false;
    }

    list($expire_str, $hash) = explode('.', $decoded, 2);
    $expire = (int)$expire_str;

    // 檢查是否已過期
    if ($expire < time()) {
        return false;
    }

    $pin = env('PIN', env('SECRET_PIN', ''));
    $salt = auth_get_salt();
    $expected_data = $pin . '|' . $expire . '|' . $salt;
    $expected_hash = hash_hmac('sha256', $expected_data, $salt);

    // 常數時間比對防時序攻擊
    if (function_exists('hash_equals')) {
        return hash_equals($expected_hash, $hash);
    }
    return $expected_hash === $hash;
}

/**
 * 檢查當前請求是否已通過 PIN 認證
 *
 * @return bool
 */
function is_authenticated() {
    // 若未設定 PIN 碼，全站直接暢通
    if (!has_pin_protection()) {
        return true;
    }

    // 1. 優先檢查標準 Cookie
    if (isset($_COOKIE[AUTH_COOKIE_NAME]) && auth_verify_token($_COOKIE[AUTH_COOKIE_NAME])) {
        return true;
    }

    // 2. 支援透過 URL 參數傳遞之簽章 Token（專供 Google TV / Chromecast 等不攜帶 Cookie 的裝置串流與外掛字幕）
    if (isset($_GET['auth_token']) && is_string($_GET['auth_token']) && auth_verify_token($_GET['auth_token'])) {
        // 僅允許純媒體串流與字幕相關 API 穿透，避免網頁瀏覽存取繞過 PIN 碼認證閘門
        $action = isset($_GET['action']) ? trim($_GET['action']) : '';
        $allowed_token_actions = array('stream', 'subtitles', 'subtitles_graphic', 'subtitles_vtt', 'poster', 'thumb');
        if (in_array($action, $allowed_token_actions, true)) {
            return true;
        }
    }

    return false;
}

/**
 * 取得當前有效之 Token（供前端串流 URL 附加）
 */
function auth_get_current_token() {
    if (isset($_COOKIE[AUTH_COOKIE_NAME]) && auth_verify_token($_COOKIE[AUTH_COOKIE_NAME])) {
        return $_COOKIE[AUTH_COOKIE_NAME];
    }
    if (isset($_GET['auth_token']) && is_string($_GET['auth_token']) && auth_verify_token($_GET['auth_token'])) {
        return $_GET['auth_token'];
    }
    return '';
}

/**
 * 取得客戶端 IP
 */
function auth_get_client_ip() {
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
        return trim($ips[0]);
    }
    return isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '127.0.0.1';
}

/**
 * 取得暫存記錄檔路徑 (防暴力破解)
 */
function auth_get_attempts_file() {
    $dir = __DIR__ . '/cache';
    if (!is_dir($dir)) {
        @mkdir($dir, 0777, true);
    }
    if (is_dir($dir) && is_writable($dir)) {
        return $dir . '/auth_attempts.json';
    }
    return sys_get_temp_dir() . '/nas_auth_attempts.json';
}

/**
 * 檢查目前 IP 是否被防暴力破解機制鎖定
 *
 * @return array array('locked' => bool, 'remain_seconds' => int, 'attempts' => int)
 */
function auth_check_rate_limit() {
    $max_attempts = (int)env('AUTH_MAX_ATTEMPTS', 5);
    if ($max_attempts <= 0) $max_attempts = 5;

    $file = auth_get_attempts_file();
    if (!file_exists($file)) {
        return array('locked' => false, 'remain_seconds' => 0, 'attempts' => 0);
    }

    $raw = @file_get_contents($file);
    $data = $raw ? @json_decode($raw, true) : null;
    if (!is_array($data)) {
        return array('locked' => false, 'remain_seconds' => 0, 'attempts' => 0);
    }

    $ip_key = md5(auth_get_client_ip());
    if (!isset($data[$ip_key])) {
        return array('locked' => false, 'remain_seconds' => 0, 'attempts' => 0);
    }

    $entry = $data[$ip_key];
    $count = isset($entry['count']) ? (int)$entry['count'] : 0;
    $last_time = isset($entry['time']) ? (int)$entry['time'] : 0;
    $lock_duration = 60; // 鎖定冷卻 60 秒

    if ($count >= $max_attempts) {
        $elapsed = time() - $last_time;
        if ($elapsed < $lock_duration) {
            return array(
                'locked' => true,
                'remain_seconds' => $lock_duration - $elapsed,
                'attempts' => $count
            );
        } else {
            // 冷卻時間已過，重設計數
            unset($data[$ip_key]);
            @file_put_contents($file, json_encode($data));
            return array('locked' => false, 'remain_seconds' => 0, 'attempts' => 0);
        }
    }

    return array('locked' => false, 'remain_seconds' => 0, 'attempts' => $count);
}

/**
 * 記錄登入失敗次數
 */
function auth_record_failure() {
    $file = auth_get_attempts_file();
    $data = array();
    if (file_exists($file)) {
        $raw = @file_get_contents($file);
        $data = $raw ? @json_decode($raw, true) : array();
        if (!is_array($data)) $data = array();
    }

    $ip_key = md5(auth_get_client_ip());
    $count = isset($data[$ip_key]['count']) ? (int)$data[$ip_key]['count'] : 0;

    $data[$ip_key] = array(
        'count' => $count + 1,
        'time'  => time(),
        'ip'    => auth_get_client_ip()
    );

    @file_put_contents($file, json_encode($data));
}

/**
 * 重設登入失敗計數
 */
function auth_reset_failure() {
    $file = auth_get_attempts_file();
    if (!file_exists($file)) return;

    $raw = @file_get_contents($file);
    $data = $raw ? @json_decode($raw, true) : array();
    if (!is_array($data)) return;

    $ip_key = md5(auth_get_client_ip());
    if (isset($data[$ip_key])) {
        unset($data[$ip_key]);
        @file_put_contents($file, json_encode($data));
    }
}

/**
 * 發行登入憑證 Cookie
 */
function auth_set_cookie($remember = true) {
    $days = (int)env('AUTH_EXPIRES_DAYS', 7);
    if ($days <= 0) $days = 7;

    $expire = $remember ? (time() + ($days * 86400)) : (time() + 86400);
    $token = auth_generate_token($expire);

    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    // PHP 5.6+ 相容 setcookie 語法
    @setcookie(
        AUTH_COOKIE_NAME,
        $token,
        $expire,
        '/',
        '',
        $secure,
        true // HttpOnly
    );
}

/**
 * 清除登入憑證 Cookie
 */
function auth_clear_cookie() {
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    @setcookie(
        AUTH_COOKIE_NAME,
        '',
        time() - 3600,
        '/',
        '',
        $secure,
        true
    );
    unset($_COOKIE[AUTH_COOKIE_NAME]);
}

/**
 * 伺服器端攔截閘門函式
 * 供 index.php 與 video.php 於最頂部引入調用
 *
 * @param string|null $custom_redirect 自訂驗證成功後跳轉之網址
 */
function require_pin_auth($custom_redirect = null) {
    if (php_sapi_name() === 'cli') return;
    // 0. 若為 CORS OPTIONS 預檢請求，立即釋出 CORS 標頭並返回 204，絕不攔截
    if (isset($_SERVER['REQUEST_METHOD']) && strtoupper($_SERVER['REQUEST_METHOD']) === 'OPTIONS') {
        if (!headers_sent()) {
            header('Access-Control-Allow-Origin: *');
            header('Access-Control-Allow-Methods: GET, HEAD, POST, OPTIONS, PUT, DELETE');
            header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, Range, Accept-Encoding, If-Range, Cache-Control');
            header('Access-Control-Max-Age: 86400');
            header('HTTP/1.1 204 No Content');
        }
        exit;
    }

    if (is_authenticated()) {
        return;
    }

    // 若為 Ajax / JSON / API 請求，直接回應 401 JSON
    $is_ajax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') ||
               (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false) ||
               (isset($_SERVER['CONTENT_TYPE']) && strpos($_SERVER['CONTENT_TYPE'], 'application/json') !== false) ||
               isset($_GET['action']) || isset($_POST['action']);

    if ($is_ajax) {
        if (!headers_sent()) {
            header('HTTP/1.1 401 Unauthorized');
            header('Content-Type: application/json; charset=utf-8');
        }
        echo json_encode(array('success' => false, 'error' => 'Authentication required', 'auth_required' => true));
        exit;
    }

    // 瀏覽器存取：直接於伺服器端渲染全螢幕深色科技 PIN 解鎖介面並中斷執行
    $target_url = $custom_redirect ? $custom_redirect : $_SERVER['REQUEST_URI'];
    render_pin_pad_page($target_url);
    exit;
}

// ═══════════════════════════════════════════════════════════════════════════
//  API 路由處理 (處理 action=check, login, logout)
// ═══════════════════════════════════════════════════════════════════════════
if (isset($_GET['action'])) {
    $action = trim($_GET['action']);

    // 1. 檢查認證狀態 API
    if ($action === 'check') {
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store, no-cache, must-revalidate');
        echo json_encode(array(
            'has_pin'       => has_pin_protection(),
            'authenticated' => is_authenticated()
        ));
        exit;
    }

    // 2. 提交 PIN 登入 API
    if ($action === 'login') {
        header('Content-Type: application/json; charset=utf-8');

        // 檢查頻率限制
        $rate = auth_check_rate_limit();
        if ($rate['locked']) {
            http_response_code(429);
            echo json_encode(array(
                'success' => false,
                'error'   => '嘗試次數過多，為維護安全已暫時鎖定，請於 ' . $rate['remain_seconds'] . ' 秒後再試。',
                'locked'  => true
            ));
            exit;
        }

        $input_pin = '';
        $remember = true;

        // 讀取 POST 資料 (支援 JSON 或 Form-urlencoded)
        $raw_input = file_get_contents('php://input');
        $json_data = @json_decode($raw_input, true);
        if (is_array($json_data) && isset($json_data['pin'])) {
            $input_pin = (string)$json_data['pin'];
            if (isset($json_data['remember'])) {
                $remember = (bool)$json_data['remember'];
            }
        } elseif (isset($_POST['pin'])) {
            $input_pin = (string)$_POST['pin'];
            if (isset($_POST['remember'])) {
                $remember = ($_POST['remember'] === '1' || $_POST['remember'] === 'true' || $_POST['remember'] === 'on');
            }
        }

        if (verify_pin($input_pin)) {
            auth_reset_failure();
            auth_set_cookie($remember);

            $redirect = isset($_REQUEST['redirect']) && !empty($_REQUEST['redirect']) ? $_REQUEST['redirect'] : 'index.php';
            // 清理 HTML 實體編碼干擾（如 &amp; 轉為 &）
            $redirect = str_replace('&amp;', '&', $redirect);
            // 剔除 URL 中殘留之 auth_token 參數，保持跳轉網址乾淨
            $redirect = preg_replace('/([?&])auth_token=[^&]*(&|$)/', '$1', $redirect);
            $redirect = rtrim($redirect, '?&');
            if (empty($redirect)) {
                $redirect = 'index.php';
            }

            // 安全防護：避免外部惡意 Open Redirect
            if (preg_match('#^https?://#i', $redirect)) {
                $parsed = parse_url($redirect);
                $host = isset($parsed['host']) ? $parsed['host'] : '';
                $current_host = isset($_SERVER['HTTP_HOST']) ? explode(':', $_SERVER['HTTP_HOST'])[0] : '';
                if ($host !== $current_host && !empty($host)) {
                    $redirect = 'index.php';
                }
            }

            echo json_encode(array(
                'success'  => true,
                'redirect' => $redirect
            ));
            exit;
        } else {
            auth_record_failure();
            $max_attempts = (int)env('AUTH_MAX_ATTEMPTS', 5);
            $new_rate = auth_check_rate_limit();
            $remains = max(0, $max_attempts - $new_rate['attempts']);

            http_response_code(403);
            echo json_encode(array(
                'success' => false,
                'error'   => 'PIN 碼輸入錯誤，請重新輸入。' . ($remains > 0 ? " (剩餘 {$remains} 次嘗試機會)" : " (已達上限，系統將暫時鎖定)"),
                'locked'  => $new_rate['locked']
            ));
            exit;
        }
    }

    // 3. 登出 API
    if ($action === 'logout') {
        auth_clear_cookie();
        $redirect = isset($_GET['redirect']) && !empty($_GET['redirect']) ? $_GET['redirect'] : 'index.php';
        if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(array('success' => true, 'redirect' => $redirect));
            exit;
        }
        header('Location: ' . $redirect);
        exit;
    }
}

// 獨立造訪 auth.php 時，若未帶 action，直接顯示解鎖頁面
if (basename(__FILE__) === basename(isset($_SERVER['SCRIPT_FILENAME']) ? $_SERVER['SCRIPT_FILENAME'] : '')) {
    $redirect = isset($_GET['redirect']) && !empty($_GET['redirect']) ? $_GET['redirect'] : 'index.php';
    if (is_authenticated()) {
        header('Location: ' . $redirect);
        exit;
    }
    render_pin_pad_page($redirect);
    exit;
}

/**
 * 輸出工業極簡深色科技風格 PIN Pad 解鎖頁面 (100% 離線原生、零外部依賴)
 */
function render_pin_pad_page($redirect_url) {
    if (!headers_sent()) {
        header('Content-Type: text/html; charset=utf-8');
        header('Cache-Control: no-store, no-cache, must-revalidate');
    }
    $escaped_redirect = htmlspecialchars($redirect_url, ENT_QUOTES, 'UTF-8');
    $expire_days = (int)env('AUTH_EXPIRES_DAYS', 7);
    if ($expire_days <= 0) $expire_days = 7;
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
  <title>ASUSTOR NAS - 安全驗證</title>
  <link rel="icon" href="favicon.ico" type="image/x-icon">
  <style>
    /* ═══════════════════════════════════════════════════════════════════
       工業極簡深色監控風格 (Dark Terminal Security Pad)
       100% 離線原生實作，零外部字型或 CDN 依賴
    ═══════════════════════════════════════════════════════════════════ */
    :root {
      --bg: #0a0c0f;
      --bg2: #111318;
      --bg3: #181b22;
      --border: #252932;
      --border2: #2e3340;
      --cyan: #00d4aa;
      --cyan-glow: rgba(0, 212, 170, 0.16);
      --red: #ef4444;
      --red-glow: rgba(239, 68, 68, 0.2);
      --text: #e2e8f0;
      --text2: #8892a4;
      --text3: #4b5563;
      --mono: 'JetBrains Mono', 'Consolas', 'Courier New', monospace;
      --sans: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Noto Sans TC", "Microsoft JhengHei", sans-serif;
    }

    * { box-sizing: border-box; margin: 0; padding: 0; user-select: none; }

    body {
      background-color: var(--bg);
      background-image: 
        radial-gradient(ellipse 60% 50% at 50% 0%, rgba(0, 212, 170, 0.08), transparent 70%),
        radial-gradient(circle at 100% 100%, rgba(59, 130, 246, 0.04), transparent 50%);
      color: var(--text);
      font-family: var(--sans);
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 20px;
    }

    .auth-card {
      width: 100%;
      max-width: 380px;
      background: var(--bg2);
      border: 1px solid var(--border);
      border-radius: 16px;
      padding: 32px 28px;
      box-shadow: 0 16px 40px rgba(0, 0, 0, 0.6), 0 0 0 1px rgba(255, 255, 255, 0.03);
      position: relative;
      overflow: hidden;
      transition: transform 0.2s ease, border-color 0.2s ease;
    }

    .auth-card::before {
      content: '';
      position: absolute;
      top: 0; left: 0; right: 0; height: 3px;
      background: linear-gradient(90deg, #00d4aa, #3b82f6);
    }

    .auth-header {
      text-align: center;
      margin-bottom: 24px;
    }

    .auth-badge {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      padding: 4px 12px;
      border-radius: 999px;
      background: rgba(0, 212, 170, 0.08);
      border: 1px solid rgba(0, 212, 170, 0.2);
      color: var(--cyan);
      font-size: 11px;
      font-weight: 600;
      letter-spacing: 0.05em;
      text-transform: uppercase;
      margin-bottom: 12px;
      font-family: var(--mono);
    }

    .auth-title {
      font-size: 20px;
      font-weight: 700;
      letter-spacing: 0.02em;
      color: var(--text);
      margin-bottom: 6px;
    }

    .auth-subtitle {
      font-size: 13px;
      color: var(--text2);
      line-height: 1.4;
    }

    /* PIN 輸入與指示點 */
    .pin-display-container {
      margin-bottom: 20px;
    }

    .pin-dots {
      display: flex;
      justify-content: center;
      gap: 12px;
      margin-bottom: 12px;
      height: 24px;
      align-items: center;
    }

    .pin-dot {
      width: 14px;
      height: 14px;
      border-radius: 50%;
      border: 2px solid var(--border2);
      background: transparent;
      transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
    }

    .pin-dot.filled {
      background: var(--cyan);
      border-color: var(--cyan);
      box-shadow: 0 0 10px var(--cyan-glow);
      transform: scale(1.1);
    }

    .pin-hidden-input {
      position: absolute;
      opacity: 0;
      pointer-events: none;
      width: 1px; height: 1px;
    }

    /* 錯誤警示條 */
    .auth-alert {
      display: none;
      background: rgba(239, 68, 68, 0.12);
      border: 1px solid rgba(239, 68, 68, 0.3);
      color: #fca5a5;
      font-size: 12.5px;
      border-radius: 8px;
      padding: 10px 14px;
      margin-bottom: 18px;
      text-align: center;
      line-height: 1.4;
      animation: alertSlide 0.25s ease;
    }

    .auth-alert.visible {
      display: block;
    }

    @keyframes alertSlide {
      from { opacity: 0; transform: translateY(-4px); }
      to { opacity: 1; transform: translateY(0); }
    }

    /* 震動回饋動畫 */
    @keyframes shake {
      0%, 100% { transform: translateX(0); }
      20%, 60% { transform: translateX(-8px); }
      40%, 80% { transform: translateX(8px); }
    }

    .shake {
      animation: shake 0.4s cubic-bezier(0.36, 0.07, 0.19, 0.97) both;
      border-color: var(--red) !important;
    }

    /* 虛擬九宮格鍵盤 */
    .keypad {
      display: grid;
      grid-template-columns: repeat(3, 1fr);
      gap: 12px;
      margin-bottom: 20px;
    }

    .key-btn {
      background: var(--bg3);
      border: 1px solid var(--border);
      color: var(--text);
      border-radius: 12px;
      height: 52px;
      font-size: 20px;
      font-weight: 600;
      font-family: var(--mono);
      display: flex;
      align-items: center;
      justify-content: center;
      cursor: pointer;
      transition: all 0.12s ease;
      outline: none;
      -webkit-tap-highlight-color: transparent;
    }

    .key-btn:hover {
      background: var(--border2);
      border-color: var(--cyan);
      color: #fff;
    }

    .key-btn:active {
      transform: scale(0.93);
      background: var(--cyan-glow);
    }

    .key-btn.action {
      font-size: 13px;
      font-weight: 500;
      color: var(--text2);
      font-family: var(--sans);
    }

    .key-btn.submit {
      background: rgba(0, 212, 170, 0.12);
      border-color: rgba(0, 212, 170, 0.4);
      color: var(--cyan);
    }

    .key-btn.submit:hover {
      background: var(--cyan);
      color: #0a0c0f;
    }

    /* 記住我選項 */
    .remember-container {
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 8px;
      font-size: 12.5px;
      color: var(--text2);
      margin-bottom: 8px;
      cursor: pointer;
    }

    .remember-container input[type="checkbox"] {
      accent-color: var(--cyan);
      cursor: pointer;
      width: 14px;
      height: 14px;
    }

    /* 底部提示 */
    .auth-footer {
      text-align: center;
      font-size: 11px;
      color: var(--text3);
      margin-top: 14px;
      font-family: var(--mono);
    }

    .lock-icon {
      font-size: 26px;
      margin-bottom: 6px;
      display: inline-block;
    }
  </style>
</head>
<body>

  <div class="auth-card" id="authCard">
    <div class="auth-header">
      <div class="lock-icon">🔒</div>
      <div class="auth-badge">Security Access · 隨機鍵盤</div>
      <h1 class="auth-title">ASUSTOR NAS 驗證</h1>
      <p class="auth-subtitle">請輸入系統 PIN 碼以解鎖存取權限</p>
    </div>

    <!-- 警示訊息 -->
    <div class="auth-alert" id="authAlert"></div>

    <!-- PIN 碼圓點顯示 -->
    <div class="pin-display-container">
      <div class="pin-dots" id="pinDots">
        <div class="pin-dot"></div>
        <div class="pin-dot"></div>
        <div class="pin-dot"></div>
        <div class="pin-dot"></div>
        <div class="pin-dot"></div>
        <div class="pin-dot"></div>
      </div>
      <!-- 隱藏原生輸入框，方便實體鍵盤自然輸入 -->
      <input type="password" id="pinHiddenInput" class="pin-hidden-input" inputmode="numeric" autocomplete="one-time-code" autofocus>
    </div>

    <!-- 虛擬九宮格按鍵 (0-9 隨機安全排列) -->
    <?php
      $digits = range(0, 9);
      shuffle($digits);
    ?>
    <div class="keypad" id="keypad">
      <button type="button" class="key-btn num-key" onclick="handlePinKey('<?php echo $digits[0]; ?>')"><?php echo $digits[0]; ?></button>
      <button type="button" class="key-btn num-key" onclick="handlePinKey('<?php echo $digits[1]; ?>')"><?php echo $digits[1]; ?></button>
      <button type="button" class="key-btn num-key" onclick="handlePinKey('<?php echo $digits[2]; ?>')"><?php echo $digits[2]; ?></button>
      <button type="button" class="key-btn num-key" onclick="handlePinKey('<?php echo $digits[3]; ?>')"><?php echo $digits[3]; ?></button>
      <button type="button" class="key-btn num-key" onclick="handlePinKey('<?php echo $digits[4]; ?>')"><?php echo $digits[4]; ?></button>
      <button type="button" class="key-btn num-key" onclick="handlePinKey('<?php echo $digits[5]; ?>')"><?php echo $digits[5]; ?></button>
      <button type="button" class="key-btn num-key" onclick="handlePinKey('<?php echo $digits[6]; ?>')"><?php echo $digits[6]; ?></button>
      <button type="button" class="key-btn num-key" onclick="handlePinKey('<?php echo $digits[7]; ?>')"><?php echo $digits[7]; ?></button>
      <button type="button" class="key-btn num-key" onclick="handlePinKey('<?php echo $digits[8]; ?>')"><?php echo $digits[8]; ?></button>
      <button type="button" class="key-btn action" onclick="handlePinClear()" title="清除">清除</button>
      <button type="button" class="key-btn num-key" onclick="handlePinKey('<?php echo $digits[9]; ?>')"><?php echo $digits[9]; ?></button>
      <button type="button" class="key-btn action" onclick="handlePinBackspace()" title="倒退刪除">⌫</button>
    </div>

    <!-- 記住此裝置 -->
    <label class="remember-container">
      <input type="checkbox" id="rememberMe" checked>
      <span>記住此裝置 (<?php echo $expire_days; ?> 天內免再輸入)</span>
    </label>

    <div class="auth-footer">
      <div>AS-202TE ENCRYPTED GATEWAY</div>
    </div>
  </div>

  <script>
    var currentPin = '';
    var maxPinLength = 12; // 支援最長 12 碼 PIN
    var redirectUrl = <?php echo json_encode($redirect_url); ?>;
    var isSubmitting = false;

    var dotsContainer = document.getElementById('pinDots');
    var alertBox = document.getElementById('authAlert');
    var authCard = document.getElementById('authCard');
    var hiddenInput = document.getElementById('pinHiddenInput');
    var rememberMe = document.getElementById('rememberMe');

    // 點擊任意處聚焦隱藏輸入框，支援實體鍵盤直接鍵入
    document.addEventListener('click', function (e) {
      if (!e.target.closest('.key-btn')) {
        hiddenInput.focus();
      }
    });

    // 實體鍵盤輸入監聽
    hiddenInput.addEventListener('input', function (e) {
      var val = hiddenInput.value.replace(/\D/g, '');
      currentPin = val.slice(0, maxPinLength);
      updateDots();
      if (currentPin.length >= 4 && currentPin.length === val.length && e.inputType !== 'deleteContentBackward') {
        // 若使用者按 Enter 或滿碼時可輔助處理
      }
    });

    document.addEventListener('keydown', function (e) {
      if (e.key >= '0' && e.key <= '9') {
        handlePinKey(e.key);
      } else if (e.key === 'Backspace') {
        handlePinBackspace();
      } else if (e.key === 'Escape' || e.key === 'Delete') {
        handlePinClear();
      } else if (e.key === 'Enter') {
        submitPin();
      }
    });

    function handlePinKey(digit) {
      if (isSubmitting || currentPin.length >= maxPinLength) return;
      currentPin += digit;
      updateDots();
      hiddenInput.value = currentPin;

      // 快速自動驗證：當輸入達常見 PIN 長度 (例如 6 碼) 時，延遲 180ms 自動提交
      if (currentPin.length === 6) {
        setTimeout(function() {
          if (currentPin.length === 6) submitPin();
        }, 180);
      }
    }

    function handlePinBackspace() {
      if (isSubmitting || currentPin.length === 0) return;
      currentPin = currentPin.slice(0, -1);
      updateDots();
      hiddenInput.value = currentPin;
    }

    function handlePinClear() {
      if (isSubmitting) return;
      currentPin = '';
      updateDots();
      hiddenInput.value = '';
      hideAlert();
    }

    // 隨機重排 0-9 數字鍵盤 (採用 Fisher-Yates 洗牌與 CSPRNG 加密級隨機數)
    function shuffleKeypad() {
      var digits = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];
      for (var i = digits.length - 1; i > 0; i--) {
        var randomVal;
        if (window.crypto && window.crypto.getRandomValues) {
          var arr = new Uint32Array(1);
          window.crypto.getRandomValues(arr);
          randomVal = arr[0] / (0xffffffff + 1);
        } else {
          randomVal = Math.random();
        }
        var j = Math.floor(randomVal * (i + 1));
        var temp = digits[i];
        digits[i] = digits[j];
        digits[j] = temp;
      }
      var numKeys = document.querySelectorAll('.keypad .num-key');
      for (var k = 0; k < numKeys.length && k < digits.length; k++) {
        var d = digits[k];
        numKeys[k].textContent = d;
        numKeys[k].setAttribute('data-digit', d);
        numKeys[k].setAttribute('onclick', "handlePinKey('" + d + "')");
      }
    }

    function updateDots() {
      var dots = dotsContainer.querySelectorAll('.pin-dot');
      var len = currentPin.length;
      // 動態擴增圓點若 PIN 超過 6 碼
      if (len > dots.length && len <= maxPinLength) {
        var diff = len - dots.length;
        for (var i = 0; i < diff; i++) {
          var newDot = document.createElement('div');
          newDot.className = 'pin-dot';
          dotsContainer.appendChild(newDot);
        }
        dots = dotsContainer.querySelectorAll('.pin-dot');
      }

      for (var i = 0; i < dots.length; i++) {
        if (i < len) {
          dots[i].classList.add('filled');
        } else {
          dots[i].classList.remove('filled');
        }
      }
    }

    function showAlert(msg) {
      alertBox.textContent = msg;
      alertBox.classList.add('visible');
    }

    function hideAlert() {
      alertBox.classList.remove('visible');
    }

    function triggerShake() {
      authCard.classList.remove('shake');
      void authCard.offsetWidth; // 強制重繪 (reflow)
      authCard.classList.add('shake');
      setTimeout(function() {
        authCard.classList.remove('shake');
      }, 500);
    }

    function submitPin() {
      if (isSubmitting) return;
      if (!currentPin || currentPin.length < 4) {
        showAlert('請輸入至少 4 位數 PIN 碼');
        triggerShake();
        return;
      }

      isSubmitting = true;
      hideAlert();

      var payload = {
        pin: currentPin,
        remember: rememberMe.checked
      };

      // 原生 Fetch 發送驗證請求
      fetch('auth.php?action=login&redirect=' + encodeURIComponent(redirectUrl), {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify(payload)
      })
      .then(function (res) {
        return res.json().then(function (data) {
          return { status: res.status, data: data };
        });
      })
      .then(function (result) {
        isSubmitting = false;
        if (result.data && result.data.success) {
          // 驗證成功，圓點呈現綠色並平滑跳轉
          dotsContainer.querySelectorAll('.pin-dot').forEach(function(d) {
            d.style.borderColor = '#10b981';
            d.style.background = '#10b981';
          });
          var target = result.data.redirect || redirectUrl || 'index.php';
          // 保留 hash 錨點
          if (window.location.hash && target.indexOf('#') === -1) {
            target += window.location.hash;
          }
          window.location.replace(target);
        } else {
          var errMsg = (result.data && result.data.error) ? result.data.error : 'PIN 碼錯誤，請重新輸入';
          showAlert(errMsg);
          triggerShake();
          handlePinClear();
          shuffleKeypad(); // 驗證失敗後重新打亂鍵盤排序，防止偷窺與痕跡推測
        }
      })
      .catch(function (err) {
        isSubmitting = false;
        showAlert('連線逾時或伺服器回應異常，請稍後再試。');
        triggerShake();
        shuffleKeypad();
      });
    }
  </script>
</body>
</html>
<?php
}
