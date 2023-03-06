<?php
require_once(dirname(dirname(__FILE__)).DIRECTORY_SEPARATOR."include".DIRECTORY_SEPARATOR."init.php");
require_once(INC_DIR.DIRECTORY_SEPARATOR."Cache.class.php");
require_once(INC_DIR.DIRECTORY_SEPARATOR."System.class.php");
require_once(INC_DIR.DIRECTORY_SEPARATOR."MOICAS.class.php");

$cache = Cache::getInstance();
$system = System::getInstance();
$moicas = new MOICAS();
$mock = $system->isMockMode();

switch ($_POST["type"]) {
	case "cmcrd_tmp_check":
		Logger::getInstance()->info("XHR [cmcrd_tmp_check] check CMCRD empty temp record request.");
		$rows = $mock ? $cache->get('cmcrd_tmp_check') : $moicas->getCMCRDTempRecords($_POST['year']);
		$cache->set('cmcrd_tmp_check', $rows);
		$message = is_array($rows) ? "目前查到CMCRD裡有 ".count($rows)." 筆暫存('Y%')資料" : '查詢CMCRD失敗';
		$status_code = is_array($rows) ? STATUS_CODE::SUCCESS_NORMAL : STATUS_CODE::FAIL_DB_ERROR;
		Logger::getInstance()->info("XHR [cmcrd_tmp_check] $message");
		echoJSONResponse($message, $status_code, array(
			"raw" => $rows
		));
		break;
	case "remove_cmcrd_tmp_record":
		Logger::getInstance()->info("XHR [remove_cmcrd_tmp_record] remove CMCRD temp record request.");
		$result = $mock ? $cache->get('remove_cmcrd_tmp_record') : $moicas->removeCMCRDRecords($_POST['mc01'], $_POST['mc02']);
		$cache->set('remove_cmcrd_tmp_record', $result);
		$message = $result !== false ? '刪除 '.$_POST['MC01'].'-'.$_POST['MC02'].' CMCRD 暫存檔成功' : '刪除 CMCRD 暫存檔失敗【'.$_POST['MC01'].', '.$_POST['MC02'].'】';
		$status_code = is_array($rows) ? STATUS_CODE::SUCCESS_NORMAL : STATUS_CODE::DEFAULT_FAIL;
		Logger::getInstance()->info("XHR [remove_cmcrd_tmp_record] $message");
		echoJSONResponse($message, $status_code, array(
			"raw" => $result
		));
		break;
	default:
		Logger::getInstance()->error("不支援的查詢型態【".$_POST["type"]."】");
		echoJSONResponse("不支援的查詢型態【".$_POST["type"]."】", STATUS_CODE::UNSUPPORT_FAIL);
		break;
}
