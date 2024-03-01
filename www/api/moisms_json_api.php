<?php
require_once(dirname(dirname(__FILE__)).DIRECTORY_SEPARATOR."include".DIRECTORY_SEPARATOR."init.php");
require_once(INC_DIR.DIRECTORY_SEPARATOR."Cache.class.php");
require_once(INC_DIR.DIRECTORY_SEPARATOR."System.class.php");
require_once(INC_DIR.DIRECTORY_SEPARATOR."MOISMS.class.php");

$cache = Cache::getInstance();
$system = System::getInstance();
$moisms = new MOISMS();
$mock = $system->isMockMode();

switch ($_POST["type"]) {
	case "moisms_log_query":
		Logger::getInstance()->info("XHR [moisms_log_query] get sms log record request.");
		$keyword = $_POST['keyword'];
		$type = $_POST['searchType'];
		switch ($type) {
			case 'date':
				$rows = $mock ? $cache->get('moisms_log_query') : $moisms->getMOIADMSMSLogRecordsByDate($keyword);
				break;
			case 'cell':
				$rows = $mock ? $cache->get('moisms_log_query') : $moisms->getMOIADMSMSLogRecordsByCell($keyword);
				break;
			case 'note':
				$rows = $mock ? $cache->get('moisms_log_query') : $moisms->getMOIADMSMSLogRecordsByNote($keyword);
				break;
			case 'email':
				$rows = $mock ? $cache->get('moisms_log_query') : $moisms->getMOIADMSMSLogRecordsByEmail($keyword);
				break;
			default:
				$rows = $mock ? $cache->get('moisms_log_query') : $moisms->getMOIADMSMSLogRecords($keyword);
		}
		$cache->set('moisms_log_query', $rows);
		$message = is_array($rows) ? "目前查到 MOIADM.SMSLog 裡有 ".count($rows)." 筆資料" : '查詢 MOIADM.SMSLog 失敗';
		$status_code = is_array($rows) ? STATUS_CODE::SUCCESS_NORMAL : STATUS_CODE::FAIL_DB_ERROR;
		Logger::getInstance()->info("XHR [moisms_log_query] $message");
		echoJSONResponse($message, $status_code, array(
			"raw" => $rows
		));
		break;
	default:
		Logger::getInstance()->error("不支援的查詢型態【".$_POST["type"]."】");
		echoJSONResponse("不支援的查詢型態【".$_POST["type"]."】", STATUS_CODE::UNSUPPORT_FAIL);
		break;
}
