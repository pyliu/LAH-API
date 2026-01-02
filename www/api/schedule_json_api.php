<?php
/**
 * 系統排程 API 入口
 * * 此檔案負責接收外部 Trigger (如 Linux Crontab 或 Windows Task Scheduler) 的請求，
 * 並呼叫 Scheduler 類別執行對應週期的維護工作。
 */

// =========================================================================
// [Fix] 防止 ECONNRESET: 解除執行時間與記憶體限制
// =========================================================================
// 排程任務通常需要較長時間，避免被 PHP 預設的 30 秒限制殺掉
if (function_exists('set_time_limit')) {
    set_time_limit(0); // 0 代表無限時
}
// 如果排程資料量大，可能需要更多記憶體 (視主機狀況調整，例如 512M 或 -1 無限制)
ini_set('memory_limit', '512M'); 
// 即使 Client 斷線 (例如瀏覽器關閉或 axios timeout)，讓 PHP 繼續在背景跑完重要任務
ignore_user_abort(true);
// =========================================================================

// 1. 載入系統初始化設定 (包含 Autoloader, Global Constants 等)
require_once(dirname(dirname(__FILE__)) . DIRECTORY_SEPARATOR . "include" . DIRECTORY_SEPARATOR . "init.php");

// 2. 獲取請求類型，若未設定則預設為空字串
// PHP 7.0+ 支援 ?? 運算子，PHP 7.3 可用
$type = $_POST["type"] ?? '';

// 3. 記錄 API 請求開始 (包含來源 IP，方便追蹤是誰觸發的)
// 注意: 若前端斷線，這裡仍會記錄到開始
Logger::getInstance()->info("XHR [schedule_api] 接收到排程請求: type={$type} (From: {$client_ip})");

// 4. 初始化排程器
try {
    $scheduler = new Scheduler();
} catch (Throwable $e) { // PHP 7+ 建議使用 Throwable 以捕獲 Fatal Error 和 Exception
    Logger::getInstance()->error("XHR [schedule_api] Scheduler 初始化失敗: " . $e->getMessage());
    echoJSONResponse("Scheduler 初始化失敗", STATUS_CODE::DEFAULT_FAIL);
    exit;
}

// 5. 根據請求類型執行對應排程
switch ($type) {
    // -------------------------------------------------------------------------
    // 常規排程 (通常每 5 分鐘觸發一次，檢查所有週期任務)
    // -------------------------------------------------------------------------
    case "regular":
        Logger::getInstance()->info("XHR [schedule_api] 開始執行常規檢查 (do all jobs)...");
        try {
            // Scheduler->do() 會自動依序檢查所有週期的 Ticket
            // 這邊最容易因為執行太久導致 Timeout
            $scheduler->do();
            
            Logger::getInstance()->info("XHR [schedule_api] 常規檢查執行完成。");
            echoJSONResponse('正常(regular)排程已執行完成。', STATUS_CODE::SUCCESS_NORMAL);
        } catch (Throwable $e) { // 改用 Throwable 捕獲所有層級錯誤
            Logger::getInstance()->error("XHR [schedule_api] 常規檢查執行發生例外: " . $e->getMessage());
            echoJSONResponse("常規檢查執行失敗: " . $e->getMessage(), STATUS_CODE::DEFAULT_FAIL);
        }
        break;

    // -------------------------------------------------------------------------
    // 指定週期強制執行 (手動觸發或特定 Crontab)
    // -------------------------------------------------------------------------
    
    case "15m":
        executeJobAndRespond($scheduler, 'do15minsJobs', '15分鐘排程');
        break;

    case "30m":
        executeJobAndRespond($scheduler, 'do30minsJobs', '30分鐘排程');
        break;

    case "1h":
        executeJobAndRespond($scheduler, 'do1HourJobs', '每小時排程');
        break;

    case "4h":
        executeJobAndRespond($scheduler, 'do4HoursJobs', '每4小時排程');
        break;

    case "8h":
        executeJobAndRespond($scheduler, 'do8HoursJobs', '每8小時排程');
        break;

    case "12h":
        executeJobAndRespond($scheduler, 'doHalfDayJobs', '每12小時排程');
        break;

    case "24h":
        executeJobAndRespond($scheduler, 'doOneDayJobs', '每24小時排程');
        break;

    // -------------------------------------------------------------------------
    // 錯誤處理
    // -------------------------------------------------------------------------
    default:
        $msg = "不支援的查詢型態【{$type}】";
        Logger::getInstance()->warning("XHR [schedule_api] $msg");
        echoJSONResponse($msg, STATUS_CODE::UNSUPPORT_FAIL);
        break;
}

/**
 * 輔助函式：統一執行排程並回傳 JSON 回應
 * * @param Scheduler $scheduler 排程器實例
 * @param string $methodName 要呼叫的方法名稱 (e.g., 'do15minsJobs')
 * @param string $jobName 排程名稱 (用於日誌與回應訊息)
 */
function executeJobAndRespond(Scheduler $scheduler, string $methodName, string $jobName) {
    Logger::getInstance()->info("XHR [schedule_api] 準備執行: {$jobName} ({$methodName})");
    
    try {
        // 動態呼叫方法
        if (!method_exists($scheduler, $methodName)) {
            throw new Exception("方法 {$methodName} 不存在");
        }

        $result = $scheduler->$methodName();
        
        if ($result) {
            $msg = "{$jobName} 已執行完成。";
            Logger::getInstance()->info("XHR [schedule_api] 成功: {$msg}");
            echoJSONResponse($msg, STATUS_CODE::SUCCESS_NORMAL);
        } else {
            // 注意：部分 doXXXJobs 回傳 false 可能代表 Ticket 還沒到期，不一定是錯誤
            // 但如果是手動強制觸發，通常期望它執行。
            $msg = "{$jobName} 執行跳過 (Ticket未到期) 或失敗。";
            Logger::getInstance()->info("XHR [schedule_api] 結束: {$msg}");
            // 這裡視需求決定是否要回傳 FAIL，或視為 SUCCESS 但無動作
            echoJSONResponse($msg, STATUS_CODE::DEFAULT_FAIL);
        }

    } catch (Throwable $e) { // 改用 Throwable
        $errorMsg = "{$jobName} 執行發生例外: " . $e->getMessage();
        Logger::getInstance()->error("XHR [schedule_api] 錯誤: {$errorMsg}");
        echoJSONResponse($errorMsg, STATUS_CODE::DEFAULT_FAIL);
    }
}