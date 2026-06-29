<?php
/**
 * DGX 地政案件編號解析 API (PHP 7.3 相容)
 * * 接收使用者輸入字串，透過 DGXLandCaseParser 解析為標準化的案件陣列。
 * * 支援多種 type 分流處理，目前實作：case_ids
 * * @author Senior PHP Developer
 * @since 2017/2026
 */

declare(strict_types=1);

// ── 速率限制設定 ──────────────────────────────────────────────
// 每個來源 IP，在 RATE_LIMIT_WINDOW_SEC 秒內最多允許 N 次請求
// 觸發後回傳 HTTP 429，避免 LLM 推理資源被意外耗盡
define('RATE_LIMIT_MAX_REQUESTS', 10);
define('RATE_LIMIT_WINDOW_SEC',   60);

/**
 * 簡易 IP 速率限制 (File-based, PHP 7.3 相容)
 *
 * 以系統臨時目錄記錄各 IP 的請求時間戳。
 * 採排他鎖 (LOCK_EX) 確保高併發下計數正確。
 * Fail-open：若檔案系統操作失敗，一律放行，不阻斷正常業務。
 *
 * @param  string $ip        請求端 IP
 * @param  int    $maxReqs   時間窗口內最大請求數
 * @param  int    $windowSec 時間窗口（秒）
 * @return bool   true = 允許通過，false = 超出限制
 */
function checkRateLimit(string $ip, int $maxReqs, int $windowSec): bool
{
    $dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'dgx_rl';

    // 併發安全建目錄：先 mkdir 再 is_dir，避免 race condition
    if (!is_dir($dir) && !@mkdir($dir, 0700, true) && !is_dir($dir)) {
        return true; // fail-open
    }

    // 用 IP 的 MD5 做檔名，迴避 IPv6 冒號等特殊字元
    $file = $dir . DIRECTORY_SEPARATOR . md5($ip) . '.json';
    $fp   = @fopen($file, 'c+'); // 'c+' = 不存在則建立，不截斷，可讀寫
    if ($fp === false) {
        return true; // fail-open
    }

    flock($fp, LOCK_EX); // 取得排他鎖，阻塞直到獲得

    $raw        = stream_get_contents($fp);
    $timestamps = (array) (json_decode($raw ?: '[]', true) ?: []);
    $now        = time();

    // 移除窗口期外的過期時間戳（加型別保護，防止檔案被污染）
    $timestamps = array_values(array_filter($timestamps, function ($t) use ($now, $windowSec) {
        return is_int($t) && ($now - $t) < $windowSec;
    }));

    $allowed = count($timestamps) < $maxReqs;
    if ($allowed) {
        $timestamps[] = $now;
    }

    // 回寫更新後的時間戳陣列
    ftruncate($fp, 0);
    rewind($fp);
    fwrite($fp, json_encode($timestamps));

    flock($fp, LOCK_UN);
    fclose($fp);

    return $allowed;
}

// 引入既有系統底層與自動加載 (依循原系統架構)
require_once dirname(__DIR__) . '/include/init.php';
require_once INC_DIR . '/System.class.php';
require_once INC_DIR . '/DGXLandCaseParser.class.php';

// 設定標頭為 JSON
header('Content-Type: application/json; charset=utf-8');

try {
    $system = System::getInstance();
    $logger = Logger::getInstance();

    // 1. 驗證請求方法
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new RuntimeException('不支援的請求方法', 405);
    }

    $reqIp = $_POST['req_ip'] ?? $_SERVER['REMOTE_ADDR'] ?? 'unknown';

    // 2. 速率限制：防止 LLM 推理資源被頻繁請求耗盡
    if (!checkRateLimit($reqIp, RATE_LIMIT_MAX_REQUESTS, RATE_LIMIT_WINDOW_SEC)) {
        $logger->warning(sprintf(
            "XHR [dgx_json_api] 速率限制觸發，IP: %s（%d 次/%d 秒）",
            $reqIp, RATE_LIMIT_MAX_REQUESTS, RATE_LIMIT_WINDOW_SEC
        ));
        http_response_code(429); // Too Many Requests
        echo json_encode([
            'status'  => 'error',
            'message' => sprintf('請求過於頻繁，每 %d 秒最多 %d 次，請稍後再試。', RATE_LIMIT_WINDOW_SEC, RATE_LIMIT_MAX_REQUESTS)
        ]);
        exit;
    }

    // 3. 針對 POST 的 type 參數進行分流
    $type = $_POST['type'] ?? '';

    switch ($type) {
        case 'case_ids':
            // 4. 取得並驗證輸入參數
            $inputString = trim($_POST['text'] ?? '');
            
            if ($inputString === '') {
                throw new InvalidArgumentException('輸入字串不能為空', 400);
            }

            // 防範超長字串導致的資源消耗 (限制 1000 字元)
            if (mb_strlen($inputString, 'UTF-8') > 1000) {
                throw new InvalidArgumentException('輸入字串長度超過限制', 400);
            }

            $logger->info(sprintf("XHR [dgx_json_api] 收到來自 IP: %s 的 case_ids 解析請求", $reqIp));

            // 5. 實例化解析器並執行
            $parser = new DGXLandCaseParser();
            
            // 呼叫解析器
            $parsedResult = $parser->parse($inputString);

            // 6. 處理業務邏輯錯誤
            if (isset($parsedResult['success']) && $parsedResult['success'] === false) {
                $errorMessage = $parsedResult['error'] ?? '模型解析失敗';
                $logger->warning(sprintf("XHR [dgx_json_api] 解析失敗: %s", $errorMessage));
                
                http_response_code(422); // 422 Unprocessable Entity
                echo json_encode([
                    'status' => STATUS_CODE::DEFAULT_FAIL,
                    'message' => $errorMessage,
                    'raw' => $parsedResult['raw_output'] ?? null
                ], JSON_THROW_ON_ERROR);
                exit;
            }

            // 7. 成功回傳
            http_response_code(200);
            echo json_encode([
                'status' => STATUS_CODE::SUCCESS_NORMAL,
                'raw' => $parsedResult['results'] ?? []
            ], JSON_THROW_ON_ERROR); // PHP 7.3 特性: 發生編碼錯誤時拋出 JsonException
            break;

        // 若未來有其他 type 需求，可在此處擴充
        // case 'other_type':
        //     break;

        default:
            throw new InvalidArgumentException('未指定或不支援的操作類型 (type)', 400);
    }

} catch (InvalidArgumentException $e) {
    // 處理客戶端輸入錯誤
    if (isset($logger)) {
        $logger->warning("XHR [dgx_json_api] 參數錯誤: " . $e->getMessage());
    }
    http_response_code($e->getCode() ?: 400);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);

} catch (RuntimeException $e) {
    // 處理狀態與執行環境錯誤
    if (isset($logger)) {
        $logger->error("XHR [dgx_json_api] 執行時錯誤: " . $e->getMessage());
    }
    http_response_code($e->getCode() ?: 500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);

} catch (JsonException $e) {
    // PHP 7.3: 處理 JSON 編碼/解碼例外
    if (isset($logger)) {
        $logger->error("XHR [dgx_json_api] JSON 處理失敗: " . $e->getMessage());
    }
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => '伺服器資料格式化失敗']);

} catch (Throwable $e) {
    // 捕捉所有未預期錯誤 (PHP 7 的 Throwable 包含 Error 與 Exception)
    if (isset($logger)) {
        $logger->error("XHR [dgx_json_api] 系統發生未預期錯誤: " . $e->getMessage());
    }
    http_response_code(500);
    // 基於安全考量，對外不拋出真實的 Call Stack
    echo json_encode(['status' => 'error', 'message' => '伺服器內部錯誤，請聯繫管理員']);
}
