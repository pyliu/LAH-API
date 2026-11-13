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
     * 查詢三表佇列或歷史紀錄（SSMSMAIN / SSMSWAIT / SSMSLOG）
     *
     * @param string $table main|wait|log
     * @param int $limit 最大回傳筆數
     * @return array<int, array<string, string>>
     */
    public function getRecords(string $table, int $limit = 200): array;
}
