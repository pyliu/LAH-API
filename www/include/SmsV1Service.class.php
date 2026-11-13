<?php
require_once(__DIR__ . DIRECTORY_SEPARATOR . 'SmsV1Encoding.class.php');
require_once(__DIR__ . DIRECTORY_SEPARATOR . 'SmsV1QueueHelper.class.php');
require_once(__DIR__ . DIRECTORY_SEPARATOR . 'SmsV1StoreInterface.class.php');
require_once(__DIR__ . DIRECTORY_SEPARATOR . 'SmsV1MockStore.class.php');

/**
 * SmsV1Service - SMS v1 REST API 業務邏輯服務層
 */
class SmsV1Service {
    /** @var SmsV1StoreInterface */
    private $store;

    public function __construct(?SmsV1StoreInterface $store = null) {
        if ($store !== null) {
            $this->store = $store;
        } else {
            if (!class_exists('System', false)) {
                require_once(__DIR__ . DIRECTORY_SEPARATOR . 'System.class.php');
            }
            $isMock = System::getInstance()->isMockMode();
            if ($isMock) {
                $this->store = new SmsV1MockStore();
            } else {
                if (!class_exists('SmsV1OracleStore', false)) {
                    require_once(__DIR__ . DIRECTORY_SEPARATOR . 'SmsV1OracleStore.class.php');
                }
                $this->store = new SmsV1OracleStore();
            }
        }
    }

    public function getStore(): SmsV1StoreInterface {
        return $this->store;
    }

    /**
     * 取得站台與縣市基本資訊
     *
     * @return array{site_id: string, office_name: string, county_name: string}
     */
    public function getMetadata(): array {
        $meta = $this->store->getMetadata();
        return array(
            'site_id' => (string)($meta['site_id'] ?? ''),
            'office_name' => (string)($meta['office_name'] ?? ''),
            'county_name' => (string)($meta['county_name'] ?? ''),
        );
    }

    /**
     * 觸發案件掃描與三表去重入列（POST /cases/sync）
     *
     * @param string|null $targetDate 選填 7 碼民國日期 YYYMMDD
     * @return array{target_date: string, user_cases_scanned: int, user_notifications_inserted: int, gov_cases_scanned: int, gov_notifications_inserted: int}
     */
    public function syncCases(?string $targetDate = null): array {
        $resolvedDate = $targetDate === null ? SmsV1Encoding::getRocDate() : trim($targetDate);
        if (!SmsV1Encoding::isValidRocDate($resolvedDate)) {
            throw new \InvalidArgumentException('Invalid target_date_yyy_mm_dd: expected 7-digit ROC date YYYMMDD.');
        }

        $result = $this->store->syncCases($resolvedDate);
        return array(
            'target_date' => (string)($result['target_date'] ?? $resolvedDate),
            'user_cases_scanned' => (int)($result['user_cases_scanned'] ?? 0),
            'user_notifications_inserted' => (int)($result['user_notifications_inserted'] ?? 0),
            'gov_cases_scanned' => (int)($result['gov_cases_scanned'] ?? 0),
            'gov_notifications_inserted' => (int)($result['gov_notifications_inserted'] ?? 0),
        );
    }

    /**
     * 將 SSMSMAIN 待處理紀錄搬移至 SSMSWAIT（POST /queue/enqueue）
     *
     * @param bool $checkRegisterA
     * @param bool $checkRegisterB
     * @param bool $checkRegisterC
     * @param bool $checkRegisterD
     * @return array{moved_to_wait_count: int, deleted_from_main_count: int}
     */
    public function enqueueQueue(
        bool $checkRegisterA = true,
        bool $checkRegisterB = true,
        bool $checkRegisterC = true,
        bool $checkRegisterD = true
    ): array {
        $result = $this->store->enqueueQueue(
            $checkRegisterA,
            $checkRegisterB,
            $checkRegisterC,
            $checkRegisterD
        );

        return array(
            'moved_to_wait_count' => (int)($result['moved_to_wait_count'] ?? 0),
            'deleted_from_main_count' => (int)($result['deleted_from_main_count'] ?? 0),
        );
    }

    /**
     * 查詢待發送任務清單（GET /queue/pending）
     *
     * @return array{site_id: string, items: array<int, array<string, mixed>>}
     */
    public function getPendingQueue(): array {
        $result = $this->store->getPendingQueue();
        return array(
            'site_id' => (string)($result['site_id'] ?? ''),
            'items' => is_array($result['items'] ?? null) ? $result['items'] : array(),
        );
    }

    /**
     * 回報單筆派送結果（POST /queue/report）
     *
     * @param array<string, mixed> $identity
     * @param bool $success
     * @param string $msg
     * @param int $maxRetries
     * @param string|null $errorDesc
     * @return array<string, mixed>
     */
    public function reportQueueItem(
        array $identity,
        bool $success,
        string $msg = '',
        int $maxRetries = 0,
        ?string $errorDesc = null
    ): array {
        if ($maxRetries < 0) {
            throw new \InvalidArgumentException('Invalid max_retries: expected non-negative integer.');
        }
        $normalizedIdentity = SmsV1QueueHelper::normalizeReportIdentity($identity);
        $result = $this->store->reportQueueItem(
            $normalizedIdentity,
            $success,
            $msg,
            $maxRetries,
            $errorDesc
        );
        if ($success === false && $errorDesc !== null && $errorDesc !== '') {
            if (class_exists('Logger', false)) {
                try {
                    $caseRef = $normalizedIdentity['ms03'] . '-' . $normalizedIdentity['ms04_1'] . '-' . $normalizedIdentity['ms04_2'];
                    Logger::getInstance()->warning(
                        __METHOD__ . " [{$caseRef}] dispatch failed (action=" . ($result['action'] ?? '') . "): {$errorDesc}"
                    );
                } catch (\Throwable $ignored) {
                }
            }
        }
        return $result;
    }

    /**
     * 查詢三表紀錄並附加 MS_TYPE / MS30 / MS31 中文狀態對照
     *
     * @param string $table main|wait|log
     * @param int $limit
     * @return array{table: string, limit: int, count: int, records: array<int, array<string, string>>}
     */
    public function getRecords(string $table = 'main', int $limit = 200): array {
        $normalizedTable = strtolower(trim($table));
        if (!in_array($normalizedTable, array('main', 'wait', 'log'), true)) {
            throw new \InvalidArgumentException('Invalid table parameter. Allowed values: main, wait, log.');
        }
        if ($limit < 1 || $limit > 5000) {
            throw new \InvalidArgumentException('Limit must be between 1 and 5000.');
        }

        $rows = $this->store->getRecords($normalizedTable, $limit);
        $records = array();
        foreach ($rows as $row) {
            $msType = trim((string)($row['ms_type'] ?? ''));
            $ms30 = trim((string)($row['ms30'] ?? ''));
            $ms31 = trim((string)($row['ms31'] ?? ''));

            $row['ms_type_label'] = self::mapMsTypeLabel($msType);
            $row['ms30_label'] = self::mapMs30Label($ms30);
            $row['ms31_label'] = $normalizedTable === 'log' ? self::mapMs31Label($ms31) : '';
            $records[] = $row;
        }

        return array(
            'table' => $normalizedTable,
            'limit' => $limit,
            'count' => count($records),
            'records' => $records,
        );
    }

    public static function mapMsTypeLabel(string $code): string {
        static $map = array(
            'M' => '產權通知',
            'O' => '其他',
            'R' => '登記',
            'S' => '複丈',
            'W' => '指定送達處所',
        );
        $trimmed = trim($code);
        return $map[$trimmed] ?? '';
    }

    public static function mapMs30Label(string $code): string {
        static $map = array(
            '0' => '待傳送',
            'I' => '補正',
            'D' => '駁回',
            'F' => '結案',
            'C' => '延期複丈',
            '2' => '未辦繼承通知',
            'X' => '產權審核結果通知',
            'Y' => '產權收件通知',
            'Z' => '產權異動完成通知',
            'y' => '產權收件通知(工輔法)',
            'z' => '產權異動完成通知(工輔法)',
            '7' => '調案',
            '8' => '異議連繫',
            '9' => '跨所代收',
        );
        $trimmed = trim($code);
        return $map[$trimmed] ?? '';
    }

    public static function mapMs31Label(string $code): string {
        static $map = array(
            'S' => '成功',
            'F' => '失敗',
        );
        $trimmed = trim($code);
        return $map[$trimmed] ?? '';
    }
}
