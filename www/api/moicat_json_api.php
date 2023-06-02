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
	default:
		Logger::getInstance()->error("不支援的查詢型態【".$_POST["type"]."】");
		echoJSONResponse("不支援的查詢型態【".$_POST["type"]."】", STATUS_CODE::UNSUPPORT_FAIL);
		break;
}
