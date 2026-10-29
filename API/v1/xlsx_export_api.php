<?php
require_once(dirname(dirname(__FILE__)).DIRECTORY_SEPARATOR."include".DIRECTORY_SEPARATOR."init.php");
require_once(INC_DIR.DIRECTORY_SEPARATOR."System.class.php");

if (!headers_sent()) {
    header('Content-Type: application/json; charset=utf-8');
}

$type = $_POST["type"] ?? '';

switch ($type) {
    case "xlsx_export_overdue":
        $message = "IMPLing";
        echoJSONResponse($message);
        break;
    default:
        $type_str = $_POST["type"] ?? '';
        Logger::getInstance()->error("不支援的查詢型態【".$type_str."】");
        echoJSONResponse("不支援的查詢型態【".$type_str."】", STATUS_CODE::UNSUPPORT_FAIL);
        break;
}
