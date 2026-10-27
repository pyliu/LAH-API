<?php
require_once(dirname(dirname(__FILE__)).DIRECTORY_SEPARATOR."include".DIRECTORY_SEPARATOR."init.php");
require_once(INC_DIR.DIRECTORY_SEPARATOR."System.class.php");

$reqType = $_POST["type"] ?? "";
switch ($reqType) {
    case "???":
        $message = "IMPLing";
        echoJSONResponse($message);
        break;
    default:
        Logger::getInstance()->error("不支援的查詢型態【".$reqType."】");
        echoJSONResponse("不支援的查詢型態【".$reqType."】", STATUS_CODE::UNSUPPORT_FAIL);
        break;
}
