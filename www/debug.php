<?php
require_once("./include/init.php");

try {
    // 1. 初始化物件
    $prefetch = new Prefetch();
    $rows = $prefetch->getRegFixCase();
    $sqlite_db = new SQLiteRegFixCaseStore();

    // 2. 準備變數
    $total = count($rows);
    $overdueCases = []; // 【修改】用來存放篩選後「已逾期」的案件
    $overdueCount = 0;   // 【修改】計算已逾期案件的數量
    $today = date('Y-m-d');

    // 在迴圈外先取得系統設定，避免重複呼叫
    $siteNumber = System::getInstance()->getSiteNumber();
    $siteAlphabet = System::getInstance()->getSiteAlphabet();
    $endPattern = $siteAlphabet . '1'; // 組合結尾字串

    echo "開始處理 {$total} 筆案件，今天是：{$today}\n";
    echo "----------------------------------------\n";

    // 3. 遍歷所有案件並進行篩選
    foreach ($rows as $row) {
        $data = new RegCaseData($row);
        $this_baked = $data->getBakedData();
        $id = $this_baked['ID'];
        
        // 從 SQLite DB 查詢紀錄
        $result = $sqlite_db->getRegFixCaseRecord($id);
        $record = $result[0] ?? [];
        $this_baked['REG_FIX_CASE_RECORD'] = $record;
        $deadline_date = $record['fix_deadline_date'] ?? null;

        // 【重構】將複雜的判斷式拆分成多個清晰的條件，增加可讀性
        $rm02 = $row['RM02'] ?? ''; // 確保 RM02 存在
        $rm99 = $row['RM99'] ?? ''; // 確保 RM99 存在

        // 條件1: 案件以 "H{字母}" 開頭，且 RM99 為 'N' 或為空
        $match_by_alphabet = (
            strpos($rm02, "H$siteAlphabet") === 0 && 
            ($rm99 === 'N' || empty($rm99))
        );

        // 條件2: 案件以 "H{數字}" 開頭，且 RM99 為 'Y'
        $match_by_number = (
            strpos($rm02, "H$siteNumber") === 0 && 
            $rm99 === 'Y'
        );

        // 條件3: 案件以 "{字母}1" 結尾
        $match_by_ending = (substr($rm02, -strlen($endPattern)) === $endPattern);

        // 只要符合上述任一條件即可
        $isCaseMatched = ($match_by_alphabet || $match_by_number || $match_by_ending);

        // 【修改】除錯輸出，檢查是否「已逾期」
        $isOverdue = ($deadline_date && $deadline_date < $today) ? '是' : '否';
        echo "案件 ID: {$id} | 修正期限: " . ($deadline_date ?? '無') . " | 是否逾期: {$isOverdue} | 符合編號規則: " . ($isCaseMatched ? '是' : '否') . "\n";
        
        // 4. 核心判斷邏輯
        // 條件一: $isCaseMatched 必須為 true
        // 條件二: $deadline_date 必須存在 (不為 null)
        // 條件三: 【修改】修正期限必須早於今天 (已逾期)
        if ($isCaseMatched && $deadline_date && $deadline_date < $today) {
            $overdueCount++;
            $overdueCases[] = $this_baked;
        }
    }

    echo "----------------------------------------\n";
    echo "處理完成，共有 {$overdueCount} 筆符合所有條件的已逾期案件。\n";

    // 5. 輸出最終結果
    echo "已逾期案件的詳細資料：\n";
    var_dump($overdueCases);
} catch (Exception $ex) {
    echo 'Caught exception: ', $e->getMessage(), "\n";
} finally {
    echo "\n\nThis is the finally block.\n\n";
}