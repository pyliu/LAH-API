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
	case "moiadm_publication_history":
		Logger::getInstance()->info("XHR [moiadm_publication_history] get publication_history record request.");
		$date = $_POST['date'] ?? date('Y/m/d');
		$type = $_POST['status'] ?? 'rdy';
		$rows = $mock ? $cache->get('moiadm_publication_history') : $moiadm->getPublicationHistory($date, $status);
		$cache->set('moiadm_publication_history', $rows);
		$message = is_array($rows) ? "目前查到 MOIADM.PUBLICATION_HISTORY 裡有 ".count($rows)." 筆資料" : '查詢 MOIADM.PUBLICATION_HISTORY 失敗';
		$status_code = is_array($rows) ? STATUS_CODE::SUCCESS_NORMAL : STATUS_CODE::FAIL_DB_ERROR;
		Logger::getInstance()->info("XHR [moiadm_publication_history] $message");
		echoJSONResponse($message, $status_code, array(
			"raw" => $rows
		));
		break;
	case "moiadm_smslog":
		Logger::getInstance()->info("XHR [moiadm_smslog] get sms log record request.");
		$keyword = $_POST['keyword'];
		$type = $_POST['searchType'];
		switch ($type) {
			case 'date':
				$rows = $mock ? $cache->get('moiadm_smslog') : $moiadm->getSMSLogRecordsByDate($keyword);
				break;
			case 'cell':
				$rows = $mock ? $cache->get('moiadm_smslog') : $moiadm->getSMSLogRecordsByCell($keyword);
				break;
			case 'note':
				$rows = $mock ? $cache->get('moiadm_smslog') : $moiadm->getSMSLogRecordsByNote($keyword);
				break;
			case 'email':
				$rows = $mock ? $cache->get('moiadm_smslog') : $moiadm->getSMSLogRecordsByEmail($keyword);
				break;
			default:
				$rows = $mock ? $cache->get('moiadm_smslog') : $moiadm->getSMSLogRecords($keyword);
		}
		$cache->set('moiadm_smslog', $rows);
		$message = is_array($rows) ? "目前查到 MOIADM.SMSLog 裡有 ".count($rows)." 筆資料" : '查詢 MOIADM.SMSLog 失敗';
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
