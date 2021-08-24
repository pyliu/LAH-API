<?php
require_once(dirname(dirname(__FILE__))."/include/init.php");
require_once(INC_DIR.DIRECTORY_SEPARATOR."System.class.php");
require_once(INC_DIR.DIRECTORY_SEPARATOR."Notification.class.php");

$system = System::getInstance();

switch ($_POST["type"]) {
    case "add_notification":
        Logger::getInstance()->info(print_r($_POST, true));
        $channel = $_POST['channel'] ?? $_POST['sender'];
        $title = trim($_POST['title']);
        $notify = new Notification();
        $result = $notify->addMessage($channel, array(
            'title' => $title,
            'content' => trim($_POST['content']),
            'priority' => intval($_POST['priority']),
            'expire_datetime' => $_POST['expire_datetime'],
            'sender' => $_POST['sender'],
            'from_ip' => $_POST['from_ip']
        ));
        $message = $result ? "新增 $channel 頻道訊息成功 ($title)" : "新增 $channel 頻道訊息失敗 ($title)";
        $status_code = $result ? STATUS_CODE::SUCCESS_NORMAL : STATUS_CODE::DEFAULT_FAIL;
        echoJSONResponse($message, $status_code);
        break;
    case "upd_notificatione":
        Logger::getInstance()->info(print_r($_POST, true));
        echoJSONResponse('NOT IMPLEMENTED');
        break;
    case "remove_notification":
        Logger::getInstance()->info(print_r($_POST, true));
        echoJSONResponse('NOT IMPLEMENTED');
        break;
    default:
        Logger::getInstance()->error("不支援的查詢型態【".$_POST["type"]."】");
        echoJSONResponse("不支援的查詢型態【".$_POST["type"]."】", STATUS_CODE::UNSUPPORT_FAIL);
        break;
}
