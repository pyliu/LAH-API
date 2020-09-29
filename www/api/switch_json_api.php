<?php
require_once(dirname(dirname(__FILE__))."/include/init.php");

if ($client_ip != '127.0.0.1') {
    $log->error("$client_ip 不是 127.0.0.1 無法存取此API(".__FILE__.")");
    echoJSONResponse("非本機 loopback IP 無法執行命令。", STATUS_CODE::DEFAULT_FAIL);
    exit;
}

require_once(ROOT_DIR."/include/System.class.php");
$system = new System();

switch ($_POST["type"]) {
    case "switch_enable_mock":
        $result = $system->enableMockMode();
        $msg = $result ? '啟用MOCK模式成功' : '啟用MOCK模式失敗';
		$log->info("XHR [switch_enable_mock] $msg");
		echoJSONResponse($msg, $result ? STATUS_CODE::SUCCESS_NORMAL : STATUS_CODE::DEFAULT_FAIL);
        break;
    case "switch_disable_mock":
        $result = $system->disableMockMode();
        $msg = $result ? '停用MOCK模式成功' : '停用MOCK模式失敗';
		$log->info("XHR [switch_disable_mock] $msg");
		echoJSONResponse($msg, $result ? STATUS_CODE::SUCCESS_NORMAL : STATUS_CODE::DEFAULT_FAIL);
        break;
    case "switch_mock_flag":
        $result = $system->isMockMode();
        $msg = $result ? '目前是MOCK模式' : '目前不是MOCK模式';
		$log->info("XHR [switch_mock_flag] $msg");
		echoJSONResponse($msg, STATUS_CODE::SUCCESS_NORMAL, array('mock_flag' => $result));
        break;
	default:
		$log->error("不支援的查詢型態【".$_POST["type"]."】");
		echoJSONResponse("不支援的查詢型態【".$_POST["type"]."】", STATUS_CODE::UNSUPPORT_FAIL);
		break;
}
?>
