<?php

/**
 * SmsV1CaseSyncHelper - 一般民眾與機關通報案件文案組裝、聯絡方式展開與去重鍵值輔助工具
 */
class SmsV1CaseSyncHelper {
    /**
     * 組裝一般民眾（MS30 = Y / Z）通知文案（保留開頭半形空白）
     *
     * @param string $ms30 'Y' 或 'Z'
     * @param string $rm01 民國年
     * @param string $chtWord 收件字中文（SRKEYN 04）
     * @param string $rm03 案號
     * @param string $chtRm09 登記原因中文（SRKEYN 06）
     * @param string $t07_1 7碼民國日期 YYYMMDD
     * @param string $remark SRKEYN SMS/01 備註
     * @return string
     */
    public static function buildUserNote(
        string $ms30,
        string $rm01,
        string $chtWord,
        string $rm03,
        string $chtRm09,
        string $t07_1,
        string $remark
    ): string {
        $rocY = substr($t07_1, 0, 3);
        $rocM = substr($t07_1, 3, 2);
        $rocD = substr($t07_1, 5);
        $actionText = $ms30 === 'Y' ? '完成收件' : '異動完成';

        return ' 異動即時通-' . $rm01 . '年' . $chtWord . '字' . $rm03 . '號'
            . $chtRm09 . '案已於' . $rocY . '年' . $rocM . '月' . $rocD . '日'
            . $actionText . '，如有疑義請向地政事務所洽詢。' . $remark;
    }

    /**
     * 展開第一組（MOBILE/EMAIL）與第二組（MOBILE2/EMAIL2）聯絡資訊
     *
     * @param array<string, mixed> $row
     * @return array<int, array{mobile: string, email: string}>
     */
    public static function expandUserContactPairs(array $row): array {
        $pairs = array(
            array(
                'mobile' => trim((string)($row['MOBILE'] ?? '')),
                'email' => trim((string)($row['EMAIL'] ?? '')),
            ),
        );

        $mobile2 = trim((string)($row['MOBILE2'] ?? ''));
        $email2 = trim((string)($row['EMAIL2'] ?? ''));
        if ($mobile2 !== '' || $email2 !== '') {
            $pairs[] = array(
                'mobile' => $mobile2,
                'email' => $email2,
            );
        }

        return $pairs;
    }

    /**
     * 組裝機關通報（MS30 = y / z）通知文案
     *
     * @param string $ms30 'y' 或 'z'
     * @param string $county 縣市名稱（SRKEYN 66/01）
     * @param string $chtCc48 段名（SRKEYN 48）
     * @param string $cc00 'C'（土地）或 'F'（建物）
     * @param string $cc49 地號／建號（8碼）
     * @param string $rm01 民國年
     * @param string $chtWord 收件字中文（SRKEYN 04）
     * @param string $rm03 案號
     * @param string $chtRm09 登記原因中文（SRKEYN 06）
     * @param string $t07_1 7碼民國日期 YYYMMDD
     * @param string $maLo 所別全名（SRKEYN 55/01）
     * @return string
     */
    public static function buildGovNote(
        string $ms30,
        string $county,
        string $chtCc48,
        string $cc00,
        string $cc49,
        string $rm01,
        string $chtWord,
        string $rm03,
        string $chtRm09,
        string $t07_1,
        string $maLo
    ): string {
        if ($cc00 === 'C') {
            $numberLabel = substr($cc49, 0, 4) . '-' . substr($cc49, 4) . '地號';
        } else {
            $numberLabel = substr($cc49, 0, 5) . '-' . substr($cc49, 5) . '建號';
        }

        $rocY = substr($t07_1, 0, 3);
        $rocM = substr($t07_1, 3, 2);
        $rocD = substr($t07_1, 5);
        $actionText = $ms30 === 'y' ? '完成收件' : '異動完成';

        return $county . $chtCc48 . $numberLabel
            . '已於' . $rm01 . '年' . $chtWord . '字' . $rm03 . '號'
            . $chtRm09 . '案' . $rocY . '年' . $rocM . '月' . $rocD . '日'
            . $actionText . '，如有任何問題請逕向' . $maLo . '洽詢。';
    }

    /**
     * 依分號（;）拆解機關通報多組 Email，忽略空白片段
     *
     * @param string $emailRaw
     * @return array<int, string>
     */
    public static function splitGovEmails(string $emailRaw): array {
        $parts = explode(';', $emailRaw);
        $result = array();
        foreach ($parts as $part) {
            $trimmed = trim($part);
            if ($trimmed !== '') {
                $result[] = $trimmed;
            }
        }
        return $result;
    }
}
