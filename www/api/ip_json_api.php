<?php
require_once(dirname(dirname(__FILE__))."/include/init.php");
require_once(INC_DIR.DIRECTORY_SEPARATOR."System.class.php");
require_once(INC_DIR.DIRECTORY_SEPARATOR."IPResolver.class.php");

$system = System::getInstance();
$ipr = new IPResolver();

switch ($_POST["type"]) {
    case "add_ip_entry":
        // Logger::getInstance()->info(print_r($_POST, true));
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
        $message = $result ? '完成 '.$data['ip'].' ('.$data['added_type'].', '.$data['entry_type'].') 更新' : '更新 '.$data['ip'].' 資料失敗';
		$status_code = $result ? STATUS_CODE::SUCCESS_NORMAL : STATUS_CODE::DEFAULT_FAIL;
        echoJSONResponse($message, $status_code);
        break;
    case "ip_entries":
        $rows = $ipr->getIPEntries();
        $count = count($rows);
        echoJSONResponse("查詢到 $count 筆資料", STATUS_CODE::SUCCESS_NORMAL, array(
            'raw' => $rows,
            'data_count' => $count
        ));
        break;
    case "dynamic_ip_entries":
        break;
    case "static_ip_entries":
        break;
    default:
        Logger::getInstance()->error("不支援的查詢型態【".$_POST["type"]."】");
        echoJSONResponse("不支援的查詢型態【".$_POST["type"]."】", STATUS_CODE::UNSUPPORT_FAIL);
        break;
}
