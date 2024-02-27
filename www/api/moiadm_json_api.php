<?php
require_once(dirname(dirname(__FILE__)).DIRECTORY_SEPARATOR."include".DIRECTORY_SEPARATOR."init.php");
require_once(INC_DIR.DIRECTORY_SEPARATOR."Cache.class.php");
require_once(INC_DIR.DIRECTORY_SEPARATOR."System.class.php");
require_once(INC_DIR.DIRECTORY_SEPARATOR."MOIADM.class.php");

$cache = Cache::getInstance();
$system = System::getInstance();
$moiadm = new MOIADM();
$mock = $system->isMockMode();

switch ($_POST["type"]) {
	case "moiadm_smslog":
		Logger::getInstance()->info("XHR [moiadm_smslog] get sms log record request.");
		$keyword = $_POST['keyword'];
		$rows = $mock ? $cache->get('moiadm_smslog') : $moiadm->getSMSLogRecords($keyword);
		$cache->set('moiadm_smslog', $rows);
		$message = is_array($rows) ? "目前查到 MOIADM.SMSLog 裡有 ".count($rows)." 筆資料" : '查詢 MOICAT.RXSEQ 失敗';
		$status_code = is_array($rows) ? STATUS_CODE::SUCCESS_NORMAL : STATUS_CODE::FAIL_DB_ERROR;
		Logger::getInstance()->info("XHR [moiadm_smslog] $message");
		echoJSONResponse($message, $status_code, array(
			"raw" => $rows
		));
		break;
	default:
		Logger::getInstance()->error("不支援的查詢型態【".$_POST["type"]."】");
		echoJSONResponse("不支援的查詢型態【".$_POST["type"]."】", STATUS_CODE::UNSUPPORT_FAIL);
		break;
}
