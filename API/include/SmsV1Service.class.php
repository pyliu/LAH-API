<?php
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
}
