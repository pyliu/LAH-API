<?php
require_once(dirname(dirname(__FILE__)).DIRECTORY_SEPARATOR."include".DIRECTORY_SEPARATOR."init.php");
require_once(INC_DIR.DIRECTORY_SEPARATOR."Cache.class.php");
require_once(INC_DIR.DIRECTORY_SEPARATOR."System.class.php");
require_once(INC_DIR.DIRECTORY_SEPARATOR."MOICAD.class.php");

$cache = Cache::getInstance();
$system = System::getInstance();
$moicad = new MOICAD();
$mock = $system->isMockMode();

switch ($_POST["type"]) {
	case "rcoda":
		Logger::getInstance()->info("XHR [rcoda] get RCODA record request.");
		$rows = $mock ? $cache->get('moicad_rcoda') : $moicad->getRCODA();
		$cache->set('moicad_rcoda', $rows);
		$message = is_array($rows) ? "目前查到 MOICAD.RCODA 裡有 ".count($rows)." 筆資料" : '查詢 MOICAD.RCODA 失敗';
		$status_code = is_array($rows) ? STATUS_CODE::SUCCESS_NORMAL : STATUS_CODE::FAIL_DB_ERROR;
		Logger::getInstance()->info("XHR [rcoda] $message");
		echoJSONResponse($message, $status_code, array(
			"raw" => $rows
		));
		break;
	default:
		Logger::getInstance()->error("不支援的查詢型態【".$_POST["type"]."】");
		echoJSONResponse("不支援的查詢型態【".$_POST["type"]."】", STATUS_CODE::UNSUPPORT_FAIL);
		break;
}
