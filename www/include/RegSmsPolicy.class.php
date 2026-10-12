<?php
require_once('init.php');
require_once('System.class.php');

/**
 * 住址隱匿 / 地籍異動即時通 簡訊狀態政策
 *
 * 隨案案件若收件字不屬於本所（所別代號開頭 或 跨縣市代號結尾），本所無從得知簡訊是否發送，
 * 故簡訊狀態一律設為「忽略」(3)。
 *
 * 所別代號 HA~HH 對應跨縣市代號 H1~H8，例如：
 *   桃園所 HA / H1，中壢所 HB / H2 ... 龜山所 HH / H8
 */
class RegSmsPolicy {
    const SMS_IGNORED = 3;

    /**
     * 由所別代號 (如 HA) 取得跨縣市代號 (如 H1)
     */
    public static function getCrossCode($site_code) {
        $site_code = strtoupper(trim((string)$site_code));
        if (!preg_match('/^H([A-H])$/', $site_code, $m)) {
            return '';
        }
        return 'H'.(ord($m[1]) - ord('A') + 1);
    }

    /**
     * 自案號 (YYY-XXXX-NNNNNN 或 13 碼) 取出 4 碼收件字，格式不完整回傳空字串
     */
    public static function extractCaseWord($caseno) {
        $raw = strtoupper(str_replace('-', '', trim((string)$caseno)));
        if (strlen($raw) !== 13) {
            return '';
        }
        $word = substr($raw, 3, 4);
        return preg_match('/^[0-9A-Z]{4}$/', $word) ? $word : '';
    }

    /**
     * 判斷收件字是否為本所案件
     * @return bool|null true: 本所, false: 非本所, null: 無法判定
     */
    public static function isLocalCaseWord($word, $site_code = null) {
        $word = strtoupper(trim((string)$word));
        if (strlen($word) !== 4) {
            return null;
        }
        if ($site_code === null) {
            $site_code = System::getInstance()->getSiteCode();
        }
        $site_code = strtoupper(trim((string)$site_code));
        $cross = self::getCrossCode($site_code);
        if (empty($cross)) {
            return null;
        }
        return substr($word, 0, 2) === $site_code || substr($word, -2) === $cross;
    }

    /**
     * 是否應強制將簡訊狀態設為忽略
     * 條件：隨案(receiving_type = 1)、有手機號碼、收件字完整且非本所
     */
    public static function shouldIgnoreSms($receiving_type, $receiving_caseno, $cellphone, $site_code = null) {
        if ((int)$receiving_type !== 1) {
            return false;
        }
        if (trim((string)$cellphone) === '') {
            return false;
        }
        $word = self::extractCaseWord($receiving_caseno);
        return self::isLocalCaseWord($word, $site_code) === false;
    }
}
