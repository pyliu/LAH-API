<?php
require_once(dirname(dirname(__FILE__))."/include/init.php");
// require_once(INC_DIR."/RegCaseData.class.php");
require_once(INC_DIR."/RegQuery.class.php");
require_once(INC_DIR."/Cache.class.php");
require_once(INC_DIR."/System.class.php");

$query = new RegQuery();
$cache = Cache::getInstance();
$system = System::getInstance();
$mock = $system->isMockMode();

switch ($_POST["type"]) {
    case "foreigner_pdf_list":
        Logger::getInstance()->info("XHR [foreigner_pdf_list] get pdf list request.");
        $count = 0;
        $result = $query->getRegForeignerPDF($_POST['start_ts'], $_POST['end_ts'], $_POST['keyword']);
				// $result = $mock ? $cache->get('foreigner_pdf_list') : $query->getRegForeignerPDF($_POST['start_ts'], $_POST['end_ts'], $_POST['keyword']);
        // $cache->set('foreigner_pdf_list', $result);
				$count = count($result);
				$response_code = $result === false ? STATUS_CODE::DEFAULT_FAIL : STATUS_CODE::SUCCESS_NORMAL;
        $message = $response_code === STATUS_CODE::SUCCESS_NORMAL ? "取得 $count 筆外國人PDF資料" : "無法取得外國人PDF資料";
        Logger::getInstance()->info("XHR [foreigner_pdf_list] $message");
        echoJSONResponse($message, $response_code, array( "raw" => $result ));
        break;
    case "remove_foreigner_pdf":
        Logger::getInstance()->info("XHR [remove_foreigner_pdf] remove foreigner pdf request.");
        $id = $_POST['id'];
        $result = $query->removeRegForeignerPDF($id);
        $response_code = $result === false ? STATUS_CODE::DEFAULT_FAIL : STATUS_CODE::SUCCESS_NORMAL;
        $message = $response_code === STATUS_CODE::SUCCESS_NORMAL ? "已刪除外國人PDF資料 ($id)" : "無法刪除外國人PDF資料 ($id)";
        Logger::getInstance()->info("XHR [remove_foreigner_pdf] $message");
        echoJSONResponse($message, $response_code);
        break;
    default:
        Logger::getInstance()->error("不支援的查詢型態【".$_POST["type"]."】");
        echoJSONResponse("不支援的查詢型態【".$_POST["type"]."】", STATUS_CODE::UNSUPPORT_FAIL);
        break;
}
