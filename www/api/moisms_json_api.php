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
		// possible to search by content, so needs to do BIG5 convertion here
		$keyword = mb_convert_encoding($_POST['keyword'], 'BIG5', 'UTF-8');
		
		$moiadm_rows = $mock ? $cache->get('moisms_log_query_moiadm') : $moisms->getMOIADMSMSLogRecords($keyword);
		$cache->set('moisms_log_query_moiadm', $moiadm_rows);
		Logger::getInstance()->info("XHR [moisms_log_query] MOIADM query has ".count($moiadm_rows)." records");

		$sms98_rows = $mock ? $cache->get('moisms_log_query_sms98') : $moisms->getSMS98LOG_SMSRecords($keyword);
		$cache->set('moisms_log_query_sms98', $sms98_rows);
		Logger::getInstance()->info("XHR [moisms_log_query] SMS98 query has ".count($sms98_rows)." records");

		$ma04_rows = $mock ? $cache->get('moisms_log_query_ma04') : $moisms->getMOICASSMS_MA04Records($keyword);
		$cache->set('moisms_log_query_ma04', $ma04_rows);
		Logger::getInstance()->info("XHR [moisms_log_query] MOICAS MA04 query has ".count($ma04_rows)." records");

		$ma05_rows = $mock ? $cache->get('moisms_log_query_ma05') : $moisms->getMOICASSMS_MA05Records($keyword);
		$cache->set('moisms_log_query_ma05', $ma05_rows);
		Logger::getInstance()->info("XHR [moisms_log_query] MOICAS MA05 query has ".count($ma05_rows)." records");
		
		$rows = array_merge($moiadm_rows, $sms98_rows, $ma04_rows, $ma05_rows);
		// sort by datetime desc
		function DATETIME_CMP($a, $b)
		{
				return strcmp($b['SMS_DATE'].$b['SMS_TIME'], $a['SMS_DATE'].$a['SMS_TIME']);
		}
		usort($rows, "DATETIME_CMP");

		$message = is_array($rows) ? "目前查到 ".count($rows)." 筆資料" : '查詢SMS Log失敗';
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
