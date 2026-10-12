<?php
/**
 * 一次性遷移腳本：非本所隨案案件（有手機）之簡訊狀態 未發送(0)/失敗(2) → 忽略(3)
 *
 * 規則（同 RegSmsPolicy）：
 *   - 隨案 (receiving_type = 1)、有手機、收件字（案號中間 4 碼）完整
 *   - 收件字不是「本所所別代號開頭」或「對應跨縣市代號結尾」
 *   - 已成功(1)、已忽略(3) 者不動；收件字無法判定者不動
 *
 * 用法（預設 dry-run，只列出不寫入）：
 *   php migrate_non_local_sms_ignore.php            # 預覽
 *   php migrate_non_local_sms_ignore.php --apply    # 實際寫入
 *   php migrate_non_local_sms_ignore.php --site=HB  # 指定所別（預設取系統設定 SITE）
 *
 * 注意：寫入會同時更新該筆 modifytime。執行前請先備份 db 目錄下
 *       reg_address_undisclosed.db 與 reg_property_alert.db。
 */
require_once(dirname(dirname(__FILE__)).DIRECTORY_SEPARATOR."include".DIRECTORY_SEPARATOR."init.php");
require_once(INC_DIR.DIRECTORY_SEPARATOR."System.class.php");
require_once(INC_DIR.DIRECTORY_SEPARATOR."SQLiteRegAddressUndisclosed.class.php");
require_once(INC_DIR.DIRECTORY_SEPARATOR."SQLiteRegPropertyAlert.class.php");
require_once(INC_DIR.DIRECTORY_SEPARATOR."RegSmsPolicy.class.php");

$apply = in_array('--apply', $argv ?? array());
$site = System::getInstance()->getSiteCode();
foreach (($argv ?? array()) as $arg) {
    if (strpos($arg, '--site=') === 0) {
        $site = strtoupper(trim(substr($arg, 7)));
    }
}
$cross = RegSmsPolicy::getCrossCode($site);
if (empty($cross)) {
    echo "無法判定所別 ($site)，請以 --site=HA 指定 (HA~HH)\n";
    exit(1);
}
echo "所別: $site / 跨縣市代號: $cross / 模式: ".($apply ? "APPLY(寫入)" : "DRY-RUN(僅預覽)")."\n";

$targets = array(
    '住址隱匿' => new SQLiteRegAddressUndisclosed(),
    '地籍異動即時通' => new SQLiteRegPropertyAlert()
);

$grand = 0;
foreach ($targets as $title => $db) {
    $count = 0;
    foreach ($db->getAll() as $row) {
        $status = (int)($row['sms_status'] ?? 0);
        if ($status === 1 || $status === RegSmsPolicy::SMS_IGNORED) {
            continue;
        }
        if (!RegSmsPolicy::shouldIgnoreSms($row['receiving_type'] ?? 0, $row['receiving_caseno'] ?? '', $row['cellphone'] ?? '', $site)) {
            continue;
        }
        $count++;
        echo "  [$title] id={$row['id']} serial={$row['serial_no']} caseno={$row['receiving_caseno']} sms_status:$status → 3\n";
        if ($apply) {
            $db->updateSmsStatus($row['id'], RegSmsPolicy::SMS_IGNORED);
        }
    }
    echo "[$title] ".($apply ? "已更新" : "待更新")." $count 筆\n";
    $grand += $count;
}
echo "合計 $grand 筆\n";
