<?php
require_once __DIR__ . '/FileAPICommand.class.php';
require_once ROOT_DIR . '/include/api/TaxExportBatch.class.php';

final class FileAPITaxExportCommand extends FileAPICommand {
    public static function service(): TaxExportBatch {
        global $client_ip;
        $info = $_SESSION['myinfo'] ?? [];
        $user = $info['id'] ?? '';
        if ($user === '') {
            $users = (new SQLiteUser())->getUserByIP($client_ip);
            $info = $users ? $users[count($users) - 1] : [];
            $user = $info['id'] ?? '';
        }
        if (!is_string($user) || $user === '') {
            throw new InvalidArgumentException('請先登入再產製稅務資料。');
        }
        $system = System::getInstance();
        $authority = $system->getAuthority($client_ip);
        if (empty($authority['isAdmin']) && ($info['unit'] ?? '') !== '資訊課') {
            throw new DomainException('此功能僅限資訊課或管理者使用。');
        }
        $owner = $system->getSiteCode() . ':' . $user;
        $mock = $system->isMockMode();
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
        return new TaxExportBatch(dirname(ROOT_DIR) . '/.tax-export', $owner, $mock);
    }

    public function execute() {
        try {
            $service = self::service();
            $action = $_POST['action'] ?? '';
            foreach (['action', 'batch', 'item', 'begin', 'end'] as $key) {
                if (isset($_POST[$key]) && !is_string($_POST[$key])) {
                    throw new InvalidArgumentException('稅務匯出參數格式錯誤。');
                }
            }
            if ($action === 'start') {
                $data = $service->start($_POST['begin'] ?? '', $_POST['end'] ?? '');
            } elseif ($action === 'advance') {
                set_time_limit(660);
                $data = $service->advance($_POST['batch'] ?? '', $_POST['item'] ?? '', ($_POST['retry'] ?? '') === '1');
            } elseif ($action === 'status') {
                $data = $service->status($_POST['batch'] ?? '');
            } else {
                throw new InvalidArgumentException('不支援的稅務匯出操作。');
            }
            echoJSONResponse('稅務匯出批次已更新。', STATUS_CODE::SUCCESS_NORMAL, ['data' => $data]);
        } catch (DomainException $error) {
            echoJSONResponse($error->getMessage(), STATUS_CODE::FAIL_NO_AUTHORITY);
        } catch (InvalidArgumentException $error) {
            echoJSONResponse($error->getMessage(), STATUS_CODE::DEFAULT_FAIL);
        } catch (Throwable $error) {
            Logger::getInstance()->error('Tax export: ' . $error->getMessage());
            echoJSONResponse('無法處理稅務匯出，請稍後再試。', STATUS_CODE::FAIL_LOAD_ERROR);
        }
    }
}
