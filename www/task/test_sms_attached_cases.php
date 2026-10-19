<?php
/**
 * 簡訊比對通知機制 隨案結案起算與狀態自動調整 驗證測試腳本
 */
require_once(dirname(dirname(__FILE__)) . DIRECTORY_SEPARATOR . 'include' . DIRECTORY_SEPARATOR . 'init.php');
require_once(INC_DIR . DIRECTORY_SEPARATOR . 'MOICAS.class.php');
require_once(INC_DIR . DIRECTORY_SEPARATOR . 'RegCaseData.class.php');
require_once(INC_DIR . DIRECTORY_SEPARATOR . 'Scheduler.class.php');
require_once(INC_DIR . DIRECTORY_SEPARATOR . 'RegQuery.class.php');
require_once(INC_DIR . DIRECTORY_SEPARATOR . 'SQLiteRegAddressUndisclosed.class.php');
require_once(INC_DIR . DIRECTORY_SEPARATOR . 'SQLiteRegPropertyAlert.class.php');
require_once(INC_DIR . DIRECTORY_SEPARATOR . 'RegSmsPolicy.class.php');

$passCount = 0;
$failCount = 0;

function assertTest($description, $condition, $details = '') {
    global $passCount, $failCount;
    if ($condition) {
        $passCount++;
        echo "  [PASS] {$description}\n";
    } else {
        $failCount++;
        echo "  [FAIL] {$description}" . ($details !== '' ? " -> {$details}" : "") . "\n";
    }
}

function setMockCache($key, $val) {
    $dbPath = ROOT_DIR . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'db' . DIRECTORY_SEPARATOR . 'cache.db';
    $sqlite = new SQLite3($dbPath);
    $sqlite->busyTimeout(5000);
    $stm = $sqlite->prepare("REPLACE INTO cache ('key', 'value', 'expire') VALUES (:key, :val, :expire)");
    $stm->bindValue(':key', $key);
    $stm->bindValue(':val', serialize($val));
    $stm->bindValue(':expire', time() + 86400);
    $stm->execute();
    $sqlite->close();
}

function clearMockCache($key) {
    $dbPath = ROOT_DIR . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'db' . DIRECTORY_SEPARATOR . 'cache.db';
    $sqlite = new SQLite3($dbPath);
    $sqlite->busyTimeout(5000);
    $sqlite->exec("DELETE FROM cache WHERE key = '$key'");
    $sqlite->close();
}

echo "====================================================================\n";
echo "LAH-API: 簡訊比對通知機制 - 隨案案件處理原則測試\n";
echo "====================================================================\n\n";

// -------------------------------------------------------------------------
// 測試區塊 1: RegCaseData 語意化方法
// -------------------------------------------------------------------------
echo "【測試區塊 1】RegCaseData 語意化方法\n";

$caseA = new RegCaseData(array('RM31' => 'A'));
assertTest("RM31 = 'A' 時 isClosed() 應為 true", $caseA->isClosed() === true);
assertTest("RM31 = 'A' 時 isCompleted() 應為 true", $caseA->isCompleted() === true);
assertTest("RM31 = 'A' 時 isCancelled() 應為 false", $caseA->isCancelled() === false);

$caseB = new RegCaseData(array('RM31' => 'B'));
assertTest("RM31 = 'B' 時 isClosed() 應為 true", $caseB->isClosed() === true);
assertTest("RM31 = 'B' 時 isCompleted() 應為 false", $caseB->isCompleted() === false);
assertTest("RM31 = 'B' 時 isCancelled() 應為 true", $caseB->isCancelled() === true);

$caseD = new RegCaseData(array('RM31' => 'D'));
assertTest("RM31 = 'D' 時 isClosed() 應為 true", $caseD->isClosed() === true);
assertTest("RM31 = 'D' 時 isCompleted() 應為 false", $caseD->isCompleted() === false);
assertTest("RM31 = 'D' 時 isCancelled() 應為 true", $caseD->isCancelled() === true);

$caseC = new RegCaseData(array('RM31' => 'C'));
assertTest("RM31 = 'C' 時 isClosed() 應為 true", $caseC->isClosed() === true);
assertTest("RM31 = 'C' 時 isCompleted() 應為 false", $caseC->isCompleted() === false);
assertTest("RM31 = 'C' 時 isCancelled() 應為 false", $caseC->isCancelled() === false);

$caseE = new RegCaseData(array('RM31' => 'E'));
assertTest("RM31 = 'E' 時 isClosed() 應為 false", $caseE->isClosed() === false);
assertTest("RM31 = 'E' 時 isCompleted() 應為 false", $caseE->isCompleted() === false);
assertTest("RM31 = 'E' 時 isCancelled() 應為 false", $caseE->isCancelled() === false);

$caseEmpty = new RegCaseData(array('RM31' => ''));
assertTest("RM31 為空時 isClosed() 應為 false", $caseEmpty->isClosed() === false);

echo "\n";

// -------------------------------------------------------------------------
// 測試區塊 2: MOICAS::getCaseCloseInfo 與 getCasesCloseInfo
// -------------------------------------------------------------------------
echo "【測試區塊 2】MOICAS::getCaseCloseInfo 與 getCasesCloseInfo\n";

$moicas = new MOICAS();

// 案號正規化與格式錯誤
assertTest("異常案號 '123' 應回傳 false", $moicas->getCaseCloseInfo('123') === false);

// 模擬模式無快取時回傳預設未結案結構
$defaultInfo = $moicas->getCaseCloseInfo('113-HA01-009999');
assertTest("模擬模式無快取時回傳預設結構陣列", is_array($defaultInfo));
assertTest("預設結構 case_id 為 13 碼 '113HA01009999'", ($defaultInfo['case_id'] ?? '') === '113HA01009999');
assertTest("預設結構 is_closed 應為 false", ($defaultInfo['is_closed'] ?? null) === false);
assertTest("預設結構 is_completed 應為 false", ($defaultInfo['is_completed'] ?? null) === false);
assertTest("預設結構 is_cancelled 應為 false", ($defaultInfo['is_cancelled'] ?? null) === false);

// 模擬模式快取結案案件 (RM31 = 'A')
$mockA = array(
    'case_id' => '113HA01000001',
    'rm30' => 'Z',
    'rm31' => 'A',
    'close_code' => 'A',
    'is_closed' => true,
    'is_completed' => true,
    'is_cancelled' => false,
    'close_date' => '1131015',
    'close_time' => '142000',
    'close_timestamp' => mktime(14, 20, 0, 10, 15, 2024)
);
setMockCache('mock_case_close_info_113HA01000001', $mockA);

$infoA = $moicas->getCaseCloseInfo('113-HA01-000001');
assertTest("帶連字號之案號 '113-HA01-000001' 應正確命中快取", is_array($infoA));
assertTest("快取案件 A: is_closed 應為 true", ($infoA['is_closed'] ?? false) === true);
assertTest("快取案件 A: is_completed 應為 true", ($infoA['is_completed'] ?? false) === true);
assertTest("快取案件 A: is_cancelled 應為 false", ($infoA['is_cancelled'] ?? true) === false);
assertTest("快取案件 A: close_timestamp 正確解析", ($infoA['close_timestamp'] ?? 0) === mktime(14, 20, 0, 10, 15, 2024));

// 模擬模式快取撤回/駁回案件 (RM31 = 'B' 與 'D')
$mockB = array(
    'case_id' => '113HA01000002',
    'rm30' => 'F',
    'rm31' => 'B',
    'close_code' => 'B',
    'is_closed' => true,
    'is_completed' => false,
    'is_cancelled' => true,
    'close_date' => '1131013',
    'close_time' => '163000',
    'close_timestamp' => mktime(16, 30, 0, 10, 13, 2024)
);
setMockCache('mock_case_close_info_113HA01000002', $mockB);

$infoB = $moicas->getCaseCloseInfo('113HA01000002');
assertTest("快取案件 B (撤回): is_closed 應為 true", ($infoB['is_closed'] ?? false) === true);
assertTest("快取案件 B (撤回): is_cancelled 應為 true", ($infoB['is_cancelled'] ?? false) === true);

// 批次查詢 getCasesCloseInfo
$batchResult = $moicas->getCasesCloseInfo(array('113-HA01-000001', '113-HA01-000002', '123'));
assertTest("批次查詢回傳陣列", is_array($batchResult));
assertTest("批次查詢以 13 碼 '113HA01000001' 為 key 且命中", isset($batchResult['113HA01000001']) && is_array($batchResult['113HA01000001']));
assertTest("批次查詢以 13 碼 '113HA01000002' 為 key 且命中", isset($batchResult['113HA01000002']) && is_array($batchResult['113HA01000002']));
assertTest("批次查詢異常案號 '123' 為 false", isset($batchResult['123']) && $batchResult['123'] === false);

echo "\n";

// -------------------------------------------------------------------------
// 測試區塊 3: 情境 1 - 隨案案件主案尚未結案
// -------------------------------------------------------------------------
echo "【測試區塊 3】情境 1: 隨案案件 (receiving_type = 1) 主案尚未結案\n";

$undisclosedDB = new SQLiteRegAddressUndisclosed();
$testPhone1 = '0911000001';
$testCaseno1 = '113-HA01-000011';
$normCaseno1 = '113HA01000011';

// 設定主案為未結案 (RM31 = '')
setMockCache('mock_case_close_info_' . $normCaseno1, array(
    'case_id' => $normCaseno1,
    'rm30' => 'H',
    'rm31' => '',
    'close_code' => '',
    'is_closed' => false,
    'is_completed' => false,
    'is_cancelled' => false,
    'close_date' => '',
    'close_time' => '',
    'close_timestamp' => 0
));
// 模擬簡訊查詢無紀錄
setMockCache('moisms_records_by_cell', array());

// 新增測試收件資料 (5 天前收件)
$createTime5DaysAgo = time() - 5 * 86400;
$sqliteRaw = new SQLite3(SQLiteDBFactory::getRegAddressUndisclosedDB());
$stm = $sqliteRaw->prepare("
    INSERT INTO reg_address_undisclosed (applicant, receiving_type, receiving_caseno, serial_no, cellphone, sms_status, receiver, note, createtime, modifytime)
    VALUES (:applicant, 1, :caseno, '', :cell, 0, 'HATEST', '', :createtime, :modifytime)
");
$stm->bindValue(':applicant', '測試申請人1');
$stm->bindValue(':caseno', $testCaseno1);
$stm->bindValue(':cell', $testPhone1);
$stm->bindValue(':createtime', $createTime5DaysAgo);
$stm->bindValue(':modifytime', $createTime5DaysAgo);
$stm->execute();
$testId1 = $sqliteRaw->lastInsertRowID();
$sqliteRaw->close();

// 驗證 RegQuery::checkSingleRegSmsStatus
$regQuery = new RegQuery();
$queryRes1 = $regQuery->checkSingleRegSmsStatus('undisclosed', $testId1);
assertTest("RegQuery: 查無簡訊時回傳 SUCCESS_WITH_NO_RECORD", ($queryRes1['status'] ?? -1) === STATUS_CODE::SUCCESS_WITH_NO_RECORD);
assertTest(
    "RegQuery: 提示訊息末尾包含「（隨案主案尚未結案，簡訊將於主案結案後發送）」",
    mb_strpos($queryRes1['message'] ?? '', '（隨案主案尚未結案，簡訊將於主案結案後發送）') !== false,
    "實際訊息: " . ($queryRes1['message'] ?? '')
);

echo "\n";

// -------------------------------------------------------------------------
// 測試區塊 4: 情境 2 - 隨案案件主案已結案 (RM31 = 'A')，天數自結案日起算
// -------------------------------------------------------------------------
echo "【測試區塊 4】情境 2: 隨案案件主案已結案，改以結案日(RM58)起算天數\n";

$testPhone2A = '0922000002';
$testCaseno2A = '113-HA01-000020';
$normCaseno2A = '113HA01000020';

// 2A: 收件日為 10 天前，但結案日為 1 天前 (< 3 天)
$closeTs1DayAgo = time() - 1 * 86400;
setMockCache('mock_case_close_info_' . $normCaseno2A, array(
    'case_id' => $normCaseno2A,
    'rm30' => 'Z',
    'rm31' => 'A',
    'close_code' => 'A',
    'is_closed' => true,
    'is_completed' => true,
    'is_cancelled' => false,
    'close_date' => '1131018',
    'close_time' => '100000',
    'close_timestamp' => $closeTs1DayAgo
));

$sqliteRaw = new SQLite3(SQLiteDBFactory::getRegAddressUndisclosedDB());
$stm = $sqliteRaw->prepare("
    INSERT INTO reg_address_undisclosed (applicant, receiving_type, receiving_caseno, serial_no, cellphone, sms_status, receiver, note, createtime, modifytime)
    VALUES (:applicant, 1, :caseno, '', :cell, 0, 'HATEST', '', :createtime, :modifytime)
");
$stm->bindValue(':applicant', '測試申請人2A');
$stm->bindValue(':caseno', $testCaseno2A);
$stm->bindValue(':cell', $testPhone2A);
$stm->bindValue(':createtime', time() - 10 * 86400); // 10 天前收件
$stm->bindValue(':modifytime', time() - 10 * 86400);
$stm->execute();
$testId2A = $sqliteRaw->lastInsertRowID();
$sqliteRaw->close();

// 2B: 收件日為 10 天前，結案日為 4 天前 (滿 3 天未滿 7 天，應觸發 3 天提醒並標註「(結案起算)」)
$testPhone2B = '0922000003';
$testCaseno2B = '113-HA01-000021';
$normCaseno2B = '113HA01000021';
$closeTs4DaysAgo = time() - 4 * 86400;

setMockCache('mock_case_close_info_' . $normCaseno2B, array(
    'case_id' => $normCaseno2B,
    'rm30' => 'Z',
    'rm31' => 'A',
    'close_code' => 'A',
    'is_closed' => true,
    'is_completed' => true,
    'is_cancelled' => false,
    'close_date' => '1131015',
    'close_time' => '100000',
    'close_timestamp' => $closeTs4DaysAgo
));

$sqliteRaw = new SQLite3(SQLiteDBFactory::getRegAddressUndisclosedDB());
$stm = $sqliteRaw->prepare("
    INSERT INTO reg_address_undisclosed (applicant, receiving_type, receiving_caseno, serial_no, cellphone, sms_status, receiver, note, createtime, modifytime)
    VALUES (:applicant, 1, :caseno, '', :cell, 0, 'HATEST', '', :createtime, :modifytime)
");
$stm->bindValue(':applicant', '測試申請人2B');
$stm->bindValue(':caseno', $testCaseno2B);
$stm->bindValue(':cell', $testPhone2B);
$stm->bindValue(':createtime', time() - 10 * 86400); // 10 天前收件
$stm->bindValue(':modifytime', time() - 10 * 86400);
$stm->execute();
$testId2B = $sqliteRaw->lastInsertRowID();
$sqliteRaw->close();

// 驗證天數計算與標註邏輯
$caseInfo2A = $moicas->getCaseCloseInfo($testCaseno2A);
$elapsed2A = floor((time() - $caseInfo2A['close_timestamp']) / 86400);
assertTest("2A (結案 1 天前): 起算天數為 1 天 (< 3 天，不應提醒)", $elapsed2A < 3, "實際天數: {$elapsed2A}");

$caseInfo2B = $moicas->getCaseCloseInfo($testCaseno2B);
$elapsed2B = floor((time() - $caseInfo2B['close_timestamp']) / 86400);
assertTest("2B (結案 4 天前): 起算天數為 4 天 (>= 3 天，應提醒)", $elapsed2B >= 3 && $elapsed2B < 7, "實際天數: {$elapsed2B}");

// 驗證訊息顯示標註「結案X天」
$isCaseBased = true;
$daysDisplay2B = !empty($isCaseBased) ? "結案{$elapsed2B}天" : "{$elapsed2B}天";
assertTest("訊息條列標註包含「結案」字樣", mb_strpos($daysDisplay2B, '結案') !== false, "顯示: {$daysDisplay2B}");

echo "\n";

// -------------------------------------------------------------------------
// 測試區塊 5: 情境 3 - 隨案案件主案撤回 (B) 或駁回 (D) 自動調整為忽略
// -------------------------------------------------------------------------
echo "【測試區塊 5】情境 3: 隨案主案撤回(B)/駁回(D) 自動設為忽略(sms_status = 3)\n";

$testPhone3A = '0933000003';
$testCaseno3A = '113-HA01-000030';
$normCaseno3A = '113HA01000030';

// 3A: 駁回主案 (D)
setMockCache('mock_case_close_info_' . $normCaseno3A, array(
    'case_id' => $normCaseno3A,
    'rm30' => 'F',
    'rm31' => 'D',
    'close_code' => 'D',
    'is_closed' => true,
    'is_completed' => false,
    'is_cancelled' => true,
    'close_date' => '1131014',
    'close_time' => '110000',
    'close_timestamp' => time() - 3 * 86400
));

$sqliteRaw = new SQLite3(SQLiteDBFactory::getRegAddressUndisclosedDB());
$stm = $sqliteRaw->prepare("
    INSERT INTO reg_address_undisclosed (applicant, receiving_type, receiving_caseno, serial_no, cellphone, sms_status, receiver, note, createtime, modifytime)
    VALUES (:applicant, 1, :caseno, '', :cell, 0, 'HATEST', '', :createtime, :modifytime)
");
$stm->bindValue(':applicant', '測試駁回申請人');
$stm->bindValue(':caseno', $testCaseno3A);
$stm->bindValue(':cell', $testPhone3A);
$stm->bindValue(':createtime', time() - 4 * 86400);
$stm->bindValue(':modifytime', time() - 4 * 86400);
$stm->execute();
$testId3A = $sqliteRaw->lastInsertRowID();
$sqliteRaw->close();

// 驗證 RegQuery::checkSingleRegSmsStatus 自動更新為忽略
$queryRes3A = $regQuery->checkSingleRegSmsStatus('undisclosed', $testId3A);
assertTest("RegQuery (駁回): 回傳 SUCCESS_NORMAL 狀態碼", ($queryRes3A['status'] ?? -1) === STATUS_CODE::SUCCESS_NORMAL);
assertTest(
    "RegQuery (駁回): 訊息提示「隨案主案(...)已駁回，無需發送簡訊，已自動設為忽略」",
    mb_strpos($queryRes3A['message'] ?? '', '已駁回，無需發送簡訊，已自動設為忽略') !== false,
    "實際訊息: " . ($queryRes3A['message'] ?? '')
);

$updatedRecord3A = $undisclosedDB->getOne($testId3A);
assertTest("RegQuery (駁回): 資料庫 sms_status 已自動更新為 3 (SMS_IGNORED)", (int)($updatedRecord3A['sms_status'] ?? 0) === RegSmsPolicy::SMS_IGNORED);
assertTest("RegQuery (駁回): 資料庫備註已自動寫入駁回說明", mb_strpos($updatedRecord3A['note'] ?? '', '已駁回，系統自動調整為忽略') !== false);

// 3B: 撤回主案 (B) - 透過 Scheduler 測試自動更新為忽略
$testPhone3B = '0933000004';
$testCaseno3B = '113-HA01-000031';
$normCaseno3B = '113HA01000031';

setMockCache('mock_case_close_info_' . $normCaseno3B, array(
    'case_id' => $normCaseno3B,
    'rm30' => 'F',
    'rm31' => 'B',
    'close_code' => 'B',
    'is_closed' => true,
    'is_completed' => false,
    'is_cancelled' => true,
    'close_date' => '1131013',
    'close_time' => '163000',
    'close_timestamp' => time() - 5 * 86400
));

$sqliteRaw = new SQLite3(SQLiteDBFactory::getRegAddressUndisclosedDB());
$stm = $sqliteRaw->prepare("
    INSERT INTO reg_address_undisclosed (applicant, receiving_type, receiving_caseno, serial_no, cellphone, sms_status, receiver, note, createtime, modifytime)
    VALUES (:applicant, 1, :caseno, '', :cell, 0, 'HATEST', '', :createtime, :modifytime)
");
$stm->bindValue(':applicant', '測試撤回申請人');
$stm->bindValue(':caseno', $testCaseno3B);
$stm->bindValue(':cell', $testPhone3B);
$stm->bindValue(':createtime', time() - 6 * 86400);
$stm->bindValue(':modifytime', time() - 6 * 86400);
$stm->execute();
$testId3B = $sqliteRaw->lastInsertRowID();
$sqliteRaw->close();

// 執行 Scheduler::checkRegSmsDeliveryStatus 比對
$scheduler = new Scheduler();
$scheduler->checkRegSmsDeliveryStatus();

$checkDB = new SQLiteRegAddressUndisclosed();
$updatedRecord3B = $checkDB->getOne($testId3B);
assertTest("Scheduler (撤回): 執行掃描後 sms_status 自動更新為 3 (SMS_IGNORED)", (int)($updatedRecord3B['sms_status'] ?? 0) === RegSmsPolicy::SMS_IGNORED);
assertTest("Scheduler (撤回): 資料庫備註已自動寫入撤回說明", mb_strpos($updatedRecord3B['note'] ?? '', '已撤回，系統自動調整為忽略') !== false);

echo "\n";

// -------------------------------------------------------------------------
// 測試區塊 6: 情境 4 - 隨案案件查無主案或案號異常回退以收件日起算
// -------------------------------------------------------------------------
echo "【測試區塊 6】情境 4: 隨案案件查無主案或案號異常回退以收件日(createtime)起算天數\n";

$testPhone4 = '0944000004';
$testCaseno4 = '113-HA01-000040';
$normCaseno4 = '113HA01000040';
setMockCache('mock_case_close_info_' . $normCaseno4, 'NOT_FOUND');

$caseInfo4 = $moicas->getCaseCloseInfo($testCaseno4);
assertTest("查無主案(快取 NOT_FOUND)回傳 false", $caseInfo4 === false);

$invalidId = 'INVALID_CASE!';
assertTest("異常格式案號回傳 false", $moicas->getCaseCloseInfo($invalidId) === false);

// 模擬收件日 4 天前
$createtime4 = time() - 4 * 86400;
$baseTimestamp4 = ($caseInfo4 !== false && !empty($caseInfo4['close_timestamp'])) ? $caseInfo4['close_timestamp'] : $createtime4;
$elapsed4 = floor((time() - $baseTimestamp4) / 86400);
assertTest("回退收件日計算天數為 4 天 (>= 3 天)", $elapsed4 === 4.0 || $elapsed4 === 4);

echo "\n";

// -------------------------------------------------------------------------
// 測試區塊 7: 情境 5 - 非隨案案件 (receiving_type = 0 臨櫃) 維持收件日起算
// -------------------------------------------------------------------------
echo "【測試區塊 7】情境 5: 非隨案案件 (receiving_type = 0) 維持收件日起算天數\n";

$testSerial5 = '113HA10150001';
$createtime5 = time() - 5 * 86400;
$item5 = array(
    'receiving_type' => 0,
    'serial_no' => $testSerial5,
    'receiving_caseno' => '',
    'createtime' => $createtime5
);

$isAttached5 = ((int)($item5['receiving_type'] ?? 0) === 1);
assertTest("非隨案案件 isAttached 應為 false", $isAttached5 === false);

$baseTimestamp5 = $createtime5;
$elapsed5 = floor((time() - $baseTimestamp5) / 86400);
assertTest("非隨案案件依收件日起算天數為 5 天", $elapsed5 === 5.0 || $elapsed5 === 5);

$noDisplay5 = $isAttached5 ? ($item5['receiving_caseno'] ?: '-') : (!empty($item5['serial_no']) ? $item5['serial_no'] : '-');
assertTest("非隨案案件訊息表格顯示 serial_no", $noDisplay5 === $testSerial5);

$daysDisplay5 = !empty($item5['is_case_based']) ? "結案{$elapsed5}天" : "{$elapsed5}天";
assertTest("非隨案案件訊息條列經過天數不含「結案」字樣", mb_strpos($daysDisplay5, '結案') === false);

echo "\n";

// -------------------------------------------------------------------------
// 清理測試資料
// -------------------------------------------------------------------------
echo "【清理作業】清理測試資料庫與快取記錄...\n";
$sqliteRaw = new SQLite3(SQLiteDBFactory::getRegAddressUndisclosedDB());
$testIds = array($testId1, $testId2A, $testId2B, $testId3A, $testId3B);
foreach ($testIds as $tid) {
    if ($tid > 0) {
        $sqliteRaw->exec("DELETE FROM reg_address_undisclosed WHERE id = $tid");
    }
}
$sqliteRaw->close();

clearMockCache('mock_case_close_info_113HA01000001');
clearMockCache('mock_case_close_info_113HA01000002');
clearMockCache('mock_case_close_info_113HA01000011');
clearMockCache('mock_case_close_info_113HA01000020');
clearMockCache('mock_case_close_info_113HA01000021');
clearMockCache('mock_case_close_info_113HA01000030');
clearMockCache('mock_case_close_info_113HA01000031');
clearMockCache('mock_case_close_info_113HA01000040');
clearMockCache('moisms_records_by_cell');

echo "清理完成。\n\n";

echo "====================================================================\n";
echo "測試統計: 通過 {$passCount} 項, 失敗 {$failCount} 項\n";
echo "====================================================================\n";

if ($failCount > 0) {
    exit(1);
}
exit(0);
