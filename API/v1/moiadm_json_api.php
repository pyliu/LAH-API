<?php
require_once(dirname(dirname(__FILE__)).DIRECTORY_SEPARATOR."include".DIRECTORY_SEPARATOR."init.php");
require_once(INC_DIR.DIRECTORY_SEPARATOR."Cache.class.php");
require_once(INC_DIR.DIRECTORY_SEPARATOR."System.class.php");
require_once(INC_DIR.DIRECTORY_SEPARATOR."MOIADM.class.php");
require_once(INC_DIR.DIRECTORY_SEPARATOR."SQLiteRKEYNALL.class.php");

$cache = Cache::getInstance();
$system = System::getInstance();
$moiadm = new MOIADM();
$mock = $system->isMockMode();

switch ($_POST["type"]) {
	case "moiadm_publication_history":
		Logger::getInstance()->info("XHR [moiadm_publication_history] get publication_history record request.");
		$date = $_POST['date'] ?? date('Y/m/d');
		$status = $_POST['status'] ?? 'rdy';
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
	case "host_sections":
		Logger::getInstance()->info("XHR [host_sections] get sections data request.");
		$sqlite = new SQLiteRKEYNALL();
		$rows = $mock ? $cache->get('host_sections') : $sqlite->getSectionsByCounty('H');
		$cache->set('host_sections', $rows);
		$result = [];
		$site = System::getInstance()->getSiteCode();
		foreach ($rows as $row) {
			// filter by site code
			if ($row['KRMK'] === $site) {
				$result[] = array(
					'code' => $row['KCDE_4'],
					'name' => $row['KNAME']
				);
			}
		}
		$message = $rows !== false ? "目前查到快取的 RKEYN_ALL 裡有 ".count($result)." 筆本所管轄地段資料" : '查詢快取的 RKEYN_ALL 失敗';
		$status_code = is_array($rows) ? STATUS_CODE::SUCCESS_NORMAL : STATUS_CODE::FAIL_DB_ERROR;
		Logger::getInstance()->info("XHR [host_sections] $message");
		echoJSONResponse($message, $status_code, array(
			"raw" => $result
		));
		break;
	default:
		Logger::getInstance()->error("不支援的查詢型態【".$_POST["type"]."】");
		echoJSONResponse("不支援的查詢型態【".$_POST["type"]."】", STATUS_CODE::UNSUPPORT_FAIL);
		break;
}
