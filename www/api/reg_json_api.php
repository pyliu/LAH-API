<?php
require_once(dirname(dirname(__FILE__))."/include/init.php");
require_once(INC_DIR."/RegCaseData.class.php");
require_once(INC_DIR."/Query.class.php");
require_once(INC_DIR."/Cache.class.php");
require_once(INC_DIR."/System.class.php");

$query = new Query();
$cache = Cache::getInstance();
$system = System::getInstance();
$mock = $system->isMockMode();

switch ($_POST["type"]) {
    case "foreigner_pdf_list":
        Logger::getInstance()->info("XHR [foreigner_pdf_list] get pdf list request.");
        $count = 0;
				// $query_result = $mock ? $cache->get('foreigner_pdf_list') : $query->getProblematicCrossCases();
        // $cache->set('foreigner_pdf_list', $query_result);
				// $count = count($query_result);
				// $response_code = STATUS_CODE::SUCCESS_NORMAL;
        $message = "取得 $count 筆外國人PDF資料";
        echoJSONResponse($message, $response_code, array(
            "raw" => $query_result,
            "data_count" => $count
				));
        break;
	default:
		Logger::getInstance()->error("不支援的查詢型態【".$_POST["type"]."】");
		echoJSONResponse("不支援的查詢型態【".$_POST["type"]."】", STATUS_CODE::UNSUPPORT_FAIL);
		break;
}
