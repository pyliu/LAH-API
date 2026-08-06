<?php
require_once("./include/init.php");

try {
    // $ad = new AdService();
    // $users = $ad->getMultiDepartmentUsers();
    // echo "AD multi depts users: (".count($users).")\n";
    // foreach ($users as $user) {
    //     echo "- " . print_r($user, true) . "\n\n";
    // }
    // $users = $ad->getLockedUsers();
    // echo "\n\nAD locked users: (".count($users).")\n";
    // foreach ($users as $user) {
    //     echo "- " . print_r($user, true) . "\n\n";
    // }

    // $result = $ad->getUser('HA80013183');
    // echo "HA80013183 查詢結果：\n";
    // echo "- " . print_r($result, true) . "\n\n";

    // $result = $ad->getConfig();
    // echo "AD Config:\n";
    // echo "- " . print_r($result, true) . "\n\n";

    // $suser = new SQLiteUser();
    // $result = $suser->syncUserDynamicIP(86400);
    // echo "Synced user dynamic IP entries:\n";
    // foreach ($result as $row) {
    //     echo "- " . print_r($row, true) . "\n\n";
    // }

    // $moicas = new MOICAS();
    // $rows = $moicas->getMostPopularRM02(System::getInstance()->getSiteCode());
    // echo "- " . print_r($rows, true) . "\n\n";

    // $moisms = new MOISMS();
    // $moisms->resendMOIADMSMSFailureRecordsByDate($tw_date);
    // $rows = $moisms->getMOIADMSMSLOGFailureRecordsByDate('1150331');
    // echo print_r(REG_CODE, true) . "\n\n";
    // $parser = new DGXLandCaseParser();
    // // 測試案例一：混合輸入
    // $testInput1 = "幫我查113年 桃園朴子 第190號，還有 114 HA81 1200 ";
    // $result1 = $parser->parse($testInput1);

    // 測試案例二：多筆純數字繼承
    // $testInput2 = "桃溪 10, 溪桃 10";
    // $result2 = $parser->parse($testInput2);

    // echo json_encode(array(
    //     'case_1_input' => $testInput1,
    //     'case_1_output' => $result1,
    //     'case_2_input' => $testInput2,
    //     'case_2_output' => $result2
    // ), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

    // ── SQLiteRegAddressUndisclosed 測試 ──────────────────────────────
    echo "\n\n=== SQLiteRegAddressUndisclosed 測試 ===\n\n";
    require_once('./include/SQLiteRegAddressUndisclosed.class.php');
    $db = new SQLiteRegAddressUndisclosed();

    // 1. 新增一筆
    $newId = $db->add(array(
        'applicant'        => '測試申請人A',
        'receiving_type'   => 1,
        'receiving_caseno' => '11400001',
        'note'             => '這是測試備註',
    ));
    echo "[add] 新增結果 id = " . var_export($newId, true) . "\n";

    // 2. getOne
    if ($newId) {
        $one = $db->getOne($newId);
        echo "[getOne] id=$newId => " . print_r($one, true) . "\n";
    }

    // 3. exists
    $existId = $db->exists('測試申請人A');
    echo "[exists] 申請人「測試申請人A」=> id = " . var_export($existId, true) . "\n";

    // 4. update
    if ($newId) {
        $updated = $db->update(array(
            'id'               => $newId,
            'applicant'        => '測試申請人A（已更新）',
            'receiving_type'   => 2,
            'receiving_caseno' => '11400002',
            'note'             => '備註已更新',
        ));
        echo "[update] 更新結果 = " . var_export($updated, true) . "\n";
        $after = $db->getOne($newId);
        echo "[getOne after update] => " . print_r($after, true) . "\n";
    }

    // 5. search（搜尋最近 24 小時，有關鍵字）
    $st = time() - 86400;
    $ed = time();
    $rows_all = $db->search($st, $ed);
    echo "[search] 無關鍵字，共 " . count($rows_all) . " 筆\n";
    $rows_kw = $db->search($st, $ed, '更新');
    echo "[search] 關鍵字「更新」，共 " . count($rows_kw) . " 筆\n";

    // 6. delete
    if ($newId) {
        $deleted = $db->delete($newId);
        echo "[delete] id=$newId 刪除結果 = " . var_export($deleted, true) . "\n";
        $after_del = $db->getOne($newId);
        echo "[getOne after delete] => " . var_export($after_del, true) . "\n";
    }

    // 7. getAll + 部分欄位更新測試
    echo "\n--- getAll() 與部分欄位更新測試 ---\n";
    $idA = $db->add(array(
        'applicant'        => '全部測試_甲',
        'receiving_type'   => 0,
        'receiving_caseno' => '11500001',
        'note'             => '甲的備註',
    ));
    $idB = $db->add(array(
        'applicant'        => '全部測試_乙',
        'receiving_type'   => 1,
        'receiving_caseno' => '11500002',
        'note'             => '乙的備註',
    ));
    echo "[add] 甲 id=$idA, 乙 id=$idB\n";

    $all = $db->getAll();
    echo "[getAll] 共 " . count($all) . " 筆：\n";
    foreach ($all as $row) {
        echo "  id={$row['id']} applicant={$row['applicant']} receiving_type={$row['receiving_type']} receiving_caseno={$row['receiving_caseno']}\n";
    }

    // 部分欄位更新：只改 note，其他欄位應保留原值
    $db->update(array(
        'id'   => $idA,
        'note' => '甲的備註（只改了note）',
    ));
    $partialUpdated = $db->getOne($idA);
    echo "[partial update] id=$idA 只改 note => applicant={$partialUpdated['applicant']}, receiving_type={$partialUpdated['receiving_type']}, note={$partialUpdated['note']}\n";

    // 清理測試資料
    $db->delete($idA);
    $db->delete($idB);
    echo "[cleanup] 甲乙測試資料已刪除\n";

    echo "\n=== 測試結束 ===\n";
    // ─────────────────────────────────────────────────────────────────

    

} catch (Exception $ex) {
    echo 'Caught exception: ', $ex->getMessage(), "\n";
} finally {
    echo "\n\nThis is the finally block.\n\n";
}