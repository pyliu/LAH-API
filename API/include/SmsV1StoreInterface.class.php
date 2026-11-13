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

    /**
     * 依 TTSearchTimer 邏輯將 SSMSMAIN 待處理紀錄在單一交易內搬移至 SSMSWAIT
     *
     * @param bool $checkRegisterA 是否處理補正通知（sType = 'I'）
     * @param bool $checkRegisterB 是否處理駁回通知（sType = 'D'）
     * @param bool $checkRegisterC 是否處理結案通知（sType = 'F'）
     * @param bool $checkRegisterD 是否處理延期複丈通知（sType = 'C'）
     * @return array{moved_to_wait_count: int, deleted_from_main_count: int}
     */
    public function enqueueQueue(
        bool $checkRegisterA = true,
        bool $checkRegisterB = true,
        bool $checkRegisterC = true,
        bool $checkRegisterD = true
    ): array;

    /**
     * 查詢 SSMSWAIT 待發送任務清單並組裝完整 UTF-8 subject / msg
     *
     * @return array{site_id: string, items: array<int, array<string, mixed>>}
     */
    public function getPendingQueue(): array;
}
