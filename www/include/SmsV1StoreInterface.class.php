<?php

/**
 * SmsV1StoreInterface - SMS v1 資料存取層契約（Mock SQLite 與 Oracle 9i 共用）
 */
interface SmsV1StoreInterface {
    /**
     * 取得站台與縣市基本資訊（SRKEYN 55/01、66/01）
     *
     * @return array{site_id: string, office_name: string, county_name: string}
     */
    public function getMetadata(): array;

    /**
     * 掃描指定民國日期案件並寫入 SSMSMAIN（含三表去重與交易保護）
     *
     * @param string $targetDate 7 碼民國日期 YYYMMDD
     * @return array{target_date: string, user_cases_scanned: int, user_notifications_inserted: int, gov_cases_scanned: int, gov_notifications_inserted: int}
     */
    public function syncCases(string $targetDate): array;

    /**
     * 查詢三表佇列或歷史紀錄（SSMSMAIN / SSMSWAIT / SSMSLOG）
     *
     * @param string $table main|wait|log
     * @param int $limit 最大回傳筆數
     * @return array<int, array<string, string>>
     */
    public function getRecords(string $table, int $limit = 200): array;
}
