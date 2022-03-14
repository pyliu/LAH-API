<?php
require_once(dirname(dirname(__FILE__)).DIRECTORY_SEPARATOR."include".DIRECTORY_SEPARATOR."init.php");
require_once(INC_DIR.DIRECTORY_SEPARATOR."System.class.php");
require_once(INC_DIR.DIRECTORY_SEPARATOR."Scheduler.class.php");

$scheduler = new Scheduler();

switch ($_POST["type"]) {
    case "reqular":
        $scheduler->do();
        echoJSONResponse('已呼叫 Scheduler 執行完成。', STATUS_CODE::SUCCESS_NORMAL);
        break;
    default:
        Logger::getInstance()->error("不支援的查詢型態【".$_POST["type"]."】");
        echoJSONResponse("不支援的查詢型態【".$_POST["type"]."】", STATUS_CODE::UNSUPPORT_FAIL);
        break;
}

