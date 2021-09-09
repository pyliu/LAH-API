<?php
require_once(dirname(dirname(__FILE__))."/include/init.php");
require_once(INC_DIR.DIRECTORY_SEPARATOR."System.class.php");
require_once(INC_DIR.DIRECTORY_SEPARATOR."IPResolver.class.php");
require_once(INC_DIR.DIRECTORY_SEPARATOR."SQLiteUser.class.php");

$system = System::getInstance();
$ipr = new IPResolver();

switch ($_POST["type"]) {
    case "add_user_ip_entry":
        $data = array(
            'ip' => $_POST['ip'],
            'added_type' => $_POST['added_type'] ?? 'DYNAMIC',
            'entry_type' => $_POST['entry_type'] ?? 'USER',
            'entry_desc' => $_POST['entry_desc'] ?? '',
            'entry_id' => $_POST['entry_id'] ?? '',
            'timestamp' => time(),
            'note' => $_POST['note'] ?? ''
        );
        $result = $ipr->addIpEntry($data);

        if ($result && $data['entry_type'] === 'USER' && startsWith($data['entry_id'], $system->getSiteCode())) {
            // also update to user table in dimension.db
            $user = new SQLiteUser();
            if (!$user->autoImportUser($data)) {
                Logger::getInstance()->warning('自動匯入使用者資訊失敗。');
            }
        }

        $message = $result ? '完成 '.$data['ip'].' ('.$data['added_type'].', '.$data['entry_type'].') 更新' : '更新 '.$data['ip'].' 資料失敗';
		$status_code = $result ? STATUS_CODE::SUCCESS_NORMAL : STATUS_CODE::DEFAULT_FAIL;
        echoJSONResponse($message, $status_code);
        break;
    case "add_static_ip_entry":
        $siteCode = System::getInstance()->getSiteCode();
        $data = array(
            'ip' => $_POST['ip'],
            'added_type' => $_POST['added_type'] ?? 'STATIC',
            'entry_type' => $_POST['entry_type'] ?? 'OTHER_EP',
            'entry_desc' => $_POST['entry_desc'] ?? '',
            'entry_id' => $siteCode.'_'.$_POST['ip'],
            'timestamp' => time(),
            'note' => $_POST['note'] ?? ''
        );
        $result = $ipr->addIpEntry($data);
        $message = $result ? '完成 '.$data['ip'].' ('.$data['added_type'].', '.$data['entry_type'].') 更新' : '更新 '.$data['ip'].' 資料失敗';
        $status_code = $result ? STATUS_CODE::SUCCESS_NORMAL : STATUS_CODE::DEFAULT_FAIL;
        echoJSONResponse($message, $status_code);
        break;
    
    case "remove_ip_entry":
        $siteCode = System::getInstance()->getSiteCode();
        $data = array(
            'ip' => $_POST['ip'],
            'added_type' => $_POST['added_type'],
            'entry_type' => $_POST['entry_type'],
        );
        $result = $ipr->removeIpEntry($data);
        $message = $result ? '完成 '.$data['ip'].' ('.$data['added_type'].', '.$data['entry_type'].') 資料刪除' : '刪除 '.$data['ip'].' 資料失敗';
        $status_code = $result ? STATUS_CODE::SUCCESS_NORMAL : STATUS_CODE::DEFAULT_FAIL;
        echoJSONResponse($message, $status_code);
        break;
    case "ip_entries":
        // default get active IP entries within a year(unix timestamp is 31556926)
        $interval = intval($_POST['offset'] ?? 31556926);
        $days = ceil(abs($interval / 86400));
        $rows = $ipr->getIPEntries($interval);
        $count = count($rows);
        echoJSONResponse("查詢到 $count 筆資料( ${days} 天內)", STATUS_CODE::SUCCESS_NORMAL, array(
            'raw' => $rows,
            'data_count' => $count
        ));
        break;
    case "dynamic_ip_entries":
        // default get active IP entries within a month(unix timestamp is 2629743)
        $interval = intval($_POST['offset'] ?? 2629743);
        $days = ceil(abs($interval / 86400));
        $rows = $ipr->getDynamicIPEntries();
        $count = count($rows);
        echoJSONResponse("查詢到 $count 筆動態 IP 資料( ${days} 天內)", STATUS_CODE::SUCCESS_NORMAL, array(
            'raw' => $rows,
            'data_count' => $count
        ));
        break;
    case "static_ip_entries":
        $rows = $ipr->getStaticIPEntries();
        $count = count($rows);
        echoJSONResponse("查詢到 $count 筆靜態 IP 資料", STATUS_CODE::SUCCESS_NORMAL, array(
            'raw' => $rows,
            'data_count' => $count
        ));
        break;
    default:
        Logger::getInstance()->error("不支援的查詢型態【".$_POST["type"]."】");
        echoJSONResponse("不支援的查詢型態【".$_POST["type"]."】", STATUS_CODE::UNSUPPORT_FAIL);
        break;
}
