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
		$rows = $mock ? $cache->get('cmcrd_tmp_check') : $moicas->getCMCRDEmptyMC03Records($_POST['year']);
		$cache->set('cmcrd_tmp_check', $rows);
		$message = is_array($rows) ? "目前查到CMCRD裡有 ".count($rows)." 筆暫存('Y%')資料" : '查詢CMCRD失敗';
		$status_code = is_array($rows) ? STATUS_CODE::SUCCESS_NORMAL : STATUS_CODE::FAIL_DB_ERROR;
		echoJSONResponse($message, $status_code, array(
			"raw" => $rows
		));
		break;
	default:
		Logger::getInstance()->error("不支援的查詢型態【".$_POST["type"]."】");
		echoJSONResponse("不支援的查詢型態【".$_POST["type"]."】", STATUS_CODE::UNSUPPORT_FAIL);
		break;
}
