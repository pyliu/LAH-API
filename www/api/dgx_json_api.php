<?php
/**
 * DGX 地政案件編號解析 API (PHP 7.3 相容)
 * * 接收使用者輸入字串，透過 DGXLandCaseParser 解析為標準化的案件陣列。
 * * 支援多種 type 分流處理，目前實作：case_ids
 * * 整合 IP 速率限制與本地調用次數計數器 (持久化至 assets 目錄)
 * * 回傳狀態碼整合 STATUS_CODE 常數定義
 * * @author Senior PHP Developer
 * @since 2017/2026
 */

declare(strict_types=1);

// 引入既有系統底層與自動加載 (依賴 init.php 的 autoload 機制與 GlobalConstants)
require_once dirname(__DIR__) . '/include/init.php';

// ── 速率限制設定 ──────────────────────────────────────────────
// 每個來源 IP，在 RATE_LIMIT_WINDOW_SEC 秒內最多允許 N 次請求
// 觸發後回傳 HTTP 429，避免 LLM 推理資源被意外耗盡
define('RATE_LIMIT_MAX_REQUESTS', 10);
define('RATE_LIMIT_WINDOW_SEC',   60);

/**
 * 簡易 IP 速率限制 (File-based, PHP 7.3 相容)
 *
 * 以系統臨時目錄記錄各 IP 的請求時間戳 (暫時性資料適合放 tmp)。
 * 採排他鎖 (LOCK_EX) 確保高併發下計數正確。
 * Fail-open：若檔案系統操作失敗，一律放行，不阻斷正常業務。
 *
 * @param  string $ip        請求端 IP
 * @param  int    $maxReqs   時間窗口內最大請求數
 * @param  int    $windowSec 時間窗口（秒）
 * @return bool   true = 允許通過，false = 超出限制
 */
function checkRateLimit(string $ip, int $maxReqs = RATE_LIMIT_MAX_REQUESTS, int $windowSec = RATE_LIMIT_WINDOW_SEC): bool {
    $filePath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'dgx_api_rate_' . md5($ip) . '.txt';
    $fp = @fopen($filePath, 'c+');
    
    if (!$fp) {
        return true; // Fail-open
    }

    if (@flock($fp, LOCK_EX)) {
        $content = stream_get_contents($fp);
        $timestamps = $content ? explode(',', trim($content)) : [];
        $now = time();

        // 過濾掉超過時間窗口的舊紀錄
        $validTimestamps = array_filter($timestamps, function($ts) use ($now, $windowSec) {
            return ($now - (int)$ts) <= $windowSec;
        });

        if (count($validTimestamps) >= $maxReqs) {
            @flock($fp, LOCK_UN);
            @fclose($fp);
            return false; // 超出限制
        }

        // 加入本次請求時間
        $validTimestamps[] = $now;
        
        rewind($fp);
        ftruncate($fp, 0);
        fwrite($fp, implode(',', $validTimestamps));
        
        @flock($fp, LOCK_UN);
    }
    @fclose($fp);
    
    return true;
}

/**
 * 簡易檔案型計數器 (持久化保存版)
 * 利用 flock 確保高併發請求下的計數安全性，並儲存於上一層的 assets 目錄
 *
 * @param string $type 查詢的分類名稱
 * @return int 目前的總調用次數
 */
function incrementQueryCount(string $type): int {
    // 檔名過濾避免 Path Traversal
    $safeType = preg_replace('/[^a-zA-Z0-9_]/', '', $type);
    
    // 設定儲存路徑為: 上一層目錄/assets/
    $targetDir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'assets';
    
    // 確保 assets 目錄存在
    if (!is_dir($targetDir)) {
        @mkdir($targetDir, 0755, true);
    }
    
    // 產出如: ai_case_ids_count.txt 的檔名
    $filePath = $targetDir . DIRECTORY_SEPARATOR . 'ai_' . $safeType . '_count.txt';
    $count = 0;
    
    $fp = @fopen($filePath, 'c+');
    if ($fp) {
        if (@flock($fp, LOCK_EX)) {
            $content = stream_get_contents($fp);
            $count = is_numeric($content) ? (int)$content : 0;
            $count++; // 計數加一
            
            rewind($fp);
            ftruncate($fp, 0);
            fwrite($fp, (string)$count);
            
            @flock($fp, LOCK_UN);
        }
        @fclose($fp);
    }
    
    return $count;
}

// ── 主程式邏輯 ──────────────────────────────────────────────

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
    
    // 2. 執行速率限制 (Rate Limiting) 檢查
    if (!checkRateLimit($reqIp)) {
        $logger->warning(sprintf("XHR [dgx_json_api] IP: %s 觸發速率限制 (HTTP 429)", $reqIp));
        throw new RuntimeException('請求過於頻繁，請稍後再試', 429);
    }

    $type = $_POST['type'] ?? '';

    // 3. 針對 POST 的 type 參數進行分流
    switch ($type) {
        case 'case_ids':
            // 接收參數為 text
            $inputString = trim($_POST['text'] ?? '');
            
            if ($inputString === '') {
                throw new InvalidArgumentException('輸入字串不能為空', 400);
            }

            // 防範超長字串導致的資源消耗 (限制 1000 字元)
            if (mb_strlen($inputString, 'UTF-8') > 1000) {
                throw new InvalidArgumentException('輸入字串長度超過限制', 400);
            }

            $logger->info(sprintf("XHR [dgx_json_api] 收到來自 IP: %s 的 case_ids 解析請求", $reqIp));

            // 4. 實例化解析器並執行
            $parser = new DGXLandCaseParser();
            $parsedResult = $parser->parse($inputString);

            // 5. 處理業務邏輯錯誤
            if (isset($parsedResult['success']) && $parsedResult['success'] === false) {
                $errorMessage = $parsedResult['error'] ?? '模型解析失敗';
                $logger->warning(sprintf("XHR [dgx_json_api] 解析失敗: %s", $errorMessage));
                
                http_response_code(422); // 422 Unprocessable Entity
                echo json_encode([
                    'status' => STATUS_CODE::DEFAULT_FAIL,
                    'message' => $errorMessage,
                    'raw_output' => $parsedResult['raw_output'] ?? null
                ], JSON_THROW_ON_ERROR);
                exit;
            }

            // 6. 紀錄查詢次數並寫入 Log
            $queryCount = incrementQueryCount($type);
            $logger->info(sprintf("XHR [dgx_json_api] 解析成功，[%s] 累積查詢次數更新為: %d", $type, $queryCount));

            // 7. 成功回傳
            http_response_code(200);
            echo json_encode([
                'status' => STATUS_CODE::SUCCESS_NORMAL,
                'data' => $parsedResult['results'] ?? [],
                'query_count' => $queryCount
            ], JSON_THROW_ON_ERROR);
            break;

        default:
            // 拋出 OutOfBoundsException 以便在 Catch 中特別區分「不支援的類型」
            throw new OutOfBoundsException('未指定或不支援的操作類型 (type)', 400);
    }

} catch (OutOfBoundsException $e) {
    // 處理不支援的操作類型 (對應 UNSUPPORT_FAIL)
    if (isset($logger)) {
        $logger->warning("XHR [dgx_json_api] 參數錯誤: " . $e->getMessage());
    }
    http_response_code($e->getCode() ?: 400);
    echo json_encode(['status' => STATUS_CODE::UNSUPPORT_FAIL, 'message' => $e->getMessage()]);

} catch (InvalidArgumentException $e) {
    // 處理客戶端輸入資料錯誤
    if (isset($logger)) {
        $logger->warning("XHR [dgx_json_api] 參數錯誤: " . $e->getMessage());
    }
    http_response_code($e->getCode() ?: 400);
    echo json_encode(['status' => STATUS_CODE::DEFAULT_FAIL, 'message' => $e->getMessage()]);

} catch (RuntimeException $e) {
    // 處理狀態與執行環境錯誤 (如 429 Rate Limit 或 405 Method Not Allowed)
    if (isset($logger)) {
        $level = $e->getCode() < 500 ? 'warning' : 'error';
        $logger->$level("XHR [dgx_json_api] 執行狀態異常: " . $e->getMessage());
    }
    http_response_code($e->getCode() ?: 500);
    echo json_encode(['status' => STATUS_CODE::DEFAULT_FAIL, 'message' => $e->getMessage()]);

} catch (JsonException $e) {
    // PHP 7.3: 處理 JSON 編碼/解碼例外
    if (isset($logger)) {
        $logger->error("XHR [dgx_json_api] JSON 處理失敗: " . $e->getMessage());
    }
    http_response_code(500);
    echo json_encode(['status' => STATUS_CODE::FAIL_JSON_ENCODE, 'message' => '伺服器資料格式化失敗']);

} catch (Throwable $e) {
    // 捕捉所有未預期錯誤 (PHP 7 的 Throwable 包含 Error 與 Exception)
    if (isset($logger)) {
        $logger->error("XHR [dgx_json_api] 系統發生未預期錯誤: " . $e->getMessage());
    }
    http_response_code(500);
    echo json_encode(['status' => STATUS_CODE::DEFAULT_FAIL, 'message' => '伺服器內部錯誤，請聯繫管理員']);
}