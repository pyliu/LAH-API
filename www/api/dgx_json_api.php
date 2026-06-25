<?php
/**
 * DGX 地政案件編號解析 API (PHP 7.3 相容)
 * * 接收使用者輸入字串，透過 DGXLandCaseParser 解析為標準化的案件陣列。
 * * 支援多種 type 分流處理，目前實作：case_ids
 * * @author Senior PHP Developer
 * @since 2017/2026
 */

declare(strict_types=1);

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
    $type = $_POST['type'] ?? '';

    // 2. 針對 POST 的 type 參數進行分流
    switch ($type) {
        case 'case_ids':
            // 3. 取得並驗證輸入參數
            $inputString = trim($_POST['input_string'] ?? '');
            
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
            
            // 呼叫解析器
            $parsedResult = $parser->parse($inputString);

            // 5. 處理業務邏輯錯誤
            if (isset($parsedResult['success']) && $parsedResult['success'] === false) {
                $errorMessage = $parsedResult['error'] ?? '模型解析失敗';
                $logger->warning(sprintf("XHR [dgx_json_api] 解析失敗: %s", $errorMessage));
                
                http_response_code(422); // 422 Unprocessable Entity
                echo json_encode([
                    'status' => 'error',
                    'message' => $errorMessage,
                    'raw_output' => $parsedResult['raw_output'] ?? null
                ], JSON_THROW_ON_ERROR);
                exit;
            }

            // 6. 成功回傳
            http_response_code(200);
            echo json_encode([
                'status' => 'success',
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