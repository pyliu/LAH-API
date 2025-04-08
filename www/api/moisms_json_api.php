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
	case "moisms_log_query_by_date":
		Logger::getInstance()->info("XHR [moisms_log_query_by_date] get sms log stats request.");

		$st = $_POST['st'];
		$ed = $_POST['ed'];
		
		$moiadm_rows = $mock ? $cache->get('moisms_log_query_by_date_moiadm') : $moisms->getMOIADMSMSLogRecordsByDate($st, $ed);
		$cache->set('moisms_log_query_by_date_moiadm', $moiadm_rows);
		Logger::getInstance()->info("XHR [moisms_log_query_by_date] MOIADM query has ".count($moiadm_rows)." records");

		$sms98_rows = $mock ? $cache->get('moisms_log_query_by_date_sms98') : $moisms->getSMS98LOG_SMSRecordsByDate($st, $ed);
		$cache->set('moisms_log_query_by_date_sms98', $sms98_rows);
		Logger::getInstance()->info("XHR [moisms_log_query_by_date] SMS98 query has ".count($sms98_rows)." records");

		$ma04_rows = $mock ? $cache->get('moisms_log_query_by_date_ma04') : $moisms->getMOICASSMS_MA04RecordsByDate($st, $ed);
		$cache->set('moisms_log_query_by_date_ma04', $ma04_rows);
		Logger::getInstance()->info("XHR [moisms_log_query_by_date] MOICAS MA04 query has ".count($ma04_rows)." records");

		$ma05_rows = $mock ? $cache->get('moisms_log_query_by_date_ma05') : $moisms->getMOICASSMS_MA05RecordsByDate($st, $ed);
		$cache->set('moisms_log_query_by_date_ma05', $ma05_rows);
		Logger::getInstance()->info("XHR [moisms_log_query_by_date] MOICAS MA05 query has ".count($ma05_rows)." records");
		
		$rows = array_merge($moiadm_rows, $sms98_rows, $ma04_rows, $ma05_rows);
		// sort by datetime desc
		function DATETIME_CMP($a, $b)
		{
				return strcmp($b['SMS_DATE'].$b['SMS_TIME'], $a['SMS_DATE'].$a['SMS_TIME']);
		}
		usort($rows, "DATETIME_CMP");

		$message = is_array($rows) ? "目前查到 ".count($rows)." 筆資料" : '查詢SMS Log失敗';
		$status_code = is_array($rows) ? STATUS_CODE::SUCCESS_NORMAL : STATUS_CODE::FAIL_DB_ERROR;
		Logger::getInstance()->info("XHR [moisms_log_query_by_date] $message");
		echoJSONResponse($message, $status_code, array(
			"raw" => $rows
		));
		break;
	case "moiadm_smslog_query_by_date":
		// 地籍異動即時通LOG紀錄
		Logger::getInstance()->info("XHR [moiadm_smslog_query_by_date] get MOIADM.SMSLOG query request.");

		$st = $_POST['st'];
		$ed = $_POST['ed'];
		
		$rows = $mock ? $cache->get('moiadm_smslog_query_by_date') : $moisms->getMOIADMSMSLogRecordsByDate($st, $ed);
		$cache->set('moiadm_smslog_query_by_date', $rows);
		Logger::getInstance()->info("XHR [moiadm_smslog_query_by_date] MOIADM query has ".count($rows)." records");

		// sort by datetime desc
		function DATETIME_CMP($a, $b)
		{
				return strcmp($b['SMS_DATE'].$b['SMS_TIME'], $a['SMS_DATE'].$a['SMS_TIME']);
		}
		usort($rows, "DATETIME_CMP");

		$message = is_array($rows) ? "目前查到 ".count($rows)." 筆資料" : '查詢MOIADM.SMSLOG失敗';
		$status_code = is_array($rows) ? STATUS_CODE::SUCCESS_NORMAL : STATUS_CODE::FAIL_DB_ERROR;
		Logger::getInstance()->info("XHR [moiadm_smslog_query_by_date] $message");
		echoJSONResponse($message, $status_code, array(
			"raw" => $rows
		));
		break;
	case "moiadm_failure_sms_query":
		// 取得失敗的地籍異動即時通紀錄
		Logger::getInstance()->info("XHR [moiadm_failure_sms_query] get MOIADM failure SMS request.");

		$tw_date = $_POST['tw_date'];

		$valid = isValidTaiwanDate($tw_date);
		$result = false;
		if ($valid) {
			$rows = $mock ? $cache->get('moiadm_failure_sms_query') : $moisms->getMOIADMSMSLOGFailureRecordsByDate($tw_date);
			$cache->set('moiadm_failure_sms_query', $rows);
			Logger::getInstance()->info("XHR [moiadm_failure_sms_query] operation has been done.");
		} else {
			Logger::getInstance()->info("XHR [moiadm_failure_sms_query] tw_date 參數格式錯誤. ($tw_date)");
		}
		$status_code = $result ? STATUS_CODE::SUCCESS_NORMAL : STATUS_CODE::DEFAULT_FAIL;
		echoJSONResponse("取得失敗的地籍異動即時通紀錄完成", $status_code, array(
			"raw" => $rows
		));
		break;
	case "moiadm_failure_sms_resend":
		// 重送失敗的地籍異動即時通紀錄
		Logger::getInstance()->info("XHR [moiadm_failure_sms_resend] resending MOIADM failure SMS request.");

		$tw_date = $_POST['tw_date'];

		$valid = isValidTaiwanDate($tw_date);
		$result = false;
		if ($valid) {
			$result = $mock ? $cache->get('moiadm_failure_sms_resend') : $moisms->resendMOIADMSMSFailureRecordsByDate($tw_date);
			$cache->set('moiadm_failure_sms_resend', $result);
			Logger::getInstance()->info("XHR [moiadm_failure_sms_resend] operation has been done.");
		} else {
			Logger::getInstance()->info("XHR [moiadm_failure_sms_resend] tw_date 參數格式錯誤. ($tw_date)");
		}
		$status_code = $result ? STATUS_CODE::SUCCESS_NORMAL : STATUS_CODE::DEFAULT_FAIL;
		echoJSONResponse("重送失敗的地籍異動即時通紀錄完成", $status_code, array(
			"raw" => $result
		));
		break;
	case "moicas_ma05_sms":
		// 利用 MOICAS.MA05 表格手動發送簡訊
		Logger::getInstance()->info("XHR [moicas_ma05_sms] send MOICAS MA05 SMS request.");

		$cell = $_POST['cell'];
		$message = $_POST['message'];
		// 預約日期、時間(可為空值 👉 即時發送出去)
		$rdate = $_POST['rdate'];
		$rtime = $_POST['rtime'];
		
		$result = $mock ? $cache->get('moicas_ma05_sms') : $moisms->manualSendBookingSMS($cell, $message, $rdate, $rtime);
		$cache->set('moicas_ma05_sms', $result);
		Logger::getInstance()->info("XHR [moicas_ma05_sms] operation has been done.");
		$status_code = $result ? STATUS_CODE::SUCCESS_NORMAL : STATUS_CODE::DEFAULT_FAIL;
		echoJSONResponse("傳送手動簡訊進入待傳送佇列完成", $status_code, array(
			"raw" => $result
		));
		break;
	case "moicas_ma05_resend_sms":
		// 利用 MOICAS.MA05 表格手動發送簡訊
		Logger::getInstance()->info("XHR [moicas_ma05_resend_sms] re-send MOICAS MA05 SMS request.");

		$ma5_no = $_POST['ma5_no'];
		$cell = $_POST['cell'];
		$message = $_POST['message'];
		
		$result = $mock ? $cache->get('moicas_ma05_resend_sms') : (empty($ma5_no) ? $moisms->setTodayMA05SMSToResend($cell, $message) : $moisms->setMA05SMSToResendByMA5NO($ma5_no));
		$cache->set('moicas_ma05_resend_sms', $result);
		Logger::getInstance()->info("XHR [moicas_ma05_resend_sms] operation has been done.");
		$status_code = $result ? STATUS_CODE::SUCCESS_NORMAL : STATUS_CODE::DEFAULT_FAIL;
		echoJSONResponse("重送簡訊設定完成", $status_code, array(
			"raw" => $result
		));
		break;
	default:
		Logger::getInstance()->error("不支援的查詢型態【".$_POST["type"]."】");
		echoJSONResponse("不支援的查詢型態【".$_POST["type"]."】", STATUS_CODE::UNSUPPORT_FAIL);
		break;
}
