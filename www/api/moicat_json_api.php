<?php
require_once(dirname(dirname(__FILE__)).DIRECTORY_SEPARATOR."include".DIRECTORY_SEPARATOR."init.php");
require_once(INC_DIR.DIRECTORY_SEPARATOR."Cache.class.php");
require_once(INC_DIR.DIRECTORY_SEPARATOR."System.class.php");
require_once(INC_DIR.DIRECTORY_SEPARATOR."MOICAT.class.php");

$cache = Cache::getInstance();
$system = System::getInstance();
$moicat = new MOICAT();
$mock = $system->isMockMode();

switch ($_POST["type"]) {
	case "moicat_rxseq":
		Logger::getInstance()->info("XHR [moicat_rxseq] get cert seq record request.");
		$year = $_POST['year'];
		$month = $_POST['month'];
		$day = $_POST['day'];
		$rows = $mock ? $cache->get('moicat_rxseq') : $moicat->getCertSeqRecords($year, $month, $day);
		$cache->set('moicat_rxseq', $rows);
		$message = is_array($rows) ? "目前查到 MOICAT.RXSEQ 裡有 ".count($rows)." 筆資料" : '查詢 MOICAT.RXSEQ 失敗';
		$status_code = is_array($rows) ? STATUS_CODE::SUCCESS_NORMAL : STATUS_CODE::FAIL_DB_ERROR;
		Logger::getInstance()->info("XHR [moicat_rxseq] $message");
		echoJSONResponse($message, $status_code, array(
			"raw" => $rows
		));
		break;
	case "moicat_rxseq_latest":
		Logger::getInstance()->info("XHR [moicat_rxseq_latest] get latest cert seq record request.");
		$record = $mock ? $cache->get('moicat_rxseq_latest') : $moicat->getLatestCertSeqRecord();
		$cache->set('moicat_rxseq_latest', $record);
		$message = is_array($record) ? "查到 MOICAT.RXSEQ 最新一筆資料序號為 ".$record['XS16'] : '查詢 MOICAT.RXSEQ 失敗';
		$status_code = is_array($record) ? STATUS_CODE::SUCCESS_NORMAL : STATUS_CODE::FAIL_DB_ERROR;
		Logger::getInstance()->info("XHR [moicat_rxseq_latest] $message");
		echoJSONResponse($message, $status_code, array(
			"raw" => $record,
			"serial" => is_array($record) ? $record['XS16'] : '',
			"caseno" => is_array($record) ? $record['XS03']."-".$record['XS04_1']."-".$record['XS04_2'] : '',
		));
		break;
	case "moicat_rindx":
		Logger::getInstance()->info("XHR [moicat_rindx] get query temp rindx request.");
		$id = $_POST['year'];
		$code = $_POST['code'];
		$num = $_POST['num'];
		$rows = $mock ? $cache->get('moicat_rindx') : $moicat->getRINDXRecords($year, $code, $num);
		$cache->set('moicat_rindx', $rows);
		$message = is_array($rows) ? "目前查到 MOICAT.RINDX 裡有 ".count($rows)." 筆資料" : '查詢 MOICAT.RXSEQ 失敗';
		$status_code = is_array($rows) ? STATUS_CODE::SUCCESS_NORMAL : STATUS_CODE::FAIL_DB_ERROR;
		Logger::getInstance()->info("XHR [moicat_rindx] $message");
		echoJSONResponse($message, $status_code, array(
			"raw" => $rows
		));
		break;
	case "fix_moicat_rindx":
		Logger::getInstance()->info("XHR [fix_moicat_rindx] fix reg case temp rindx request.");
		$id = $_POST['year'];
		$code = $_POST['code'];
		$num = $_POST['num'];
		$result = $mock ? $cache->get('fix_moicat_rindx') : $moicat->fixRINDXCode($year, $code, $num);
		$cache->set('moicat_rindx', $result);
		$message = '設定 '.$year.'-'.$code.'-'.$num.' MOICAT.RINDX IP_CODE 為 F ';
		$message .= $result ? "成功" : '失敗';
		$status_code = $result ? STATUS_CODE::SUCCESS_NORMAL : STATUS_CODE::DEFAULT_FAIL;
		Logger::getInstance()->info("XHR [fix_moicat_rindx] $message");
		echoJSONResponse($message, $status_code, array(
			"raw" => $result
		));
		break;
	default:
		Logger::getInstance()->error("不支援的查詢型態【".$_POST["type"]."】");
		echoJSONResponse("不支援的查詢型態【".$_POST["type"]."】", STATUS_CODE::UNSUPPORT_FAIL);
		break;
}
