<?php
require_once(dirname(dirname(__FILE__))."/include/init.php");
require_once(INC_DIR.DIRECTORY_SEPARATOR."SQLiteMonitorMail.class.php");

switch ($_POST["type"]) {
    case "latest":
        Logger::getInstance()->info("XHR [latest] 查詢最新監控郵件請求");
        $sqlite_monitor_mail = new SQLiteMonitorMail();
        $mail = $sqlite_monitor_mail->getLatestMail();
        if (empty($mail)) {
            echoJSONResponse('沒有找到郵件');
        } else {
            echoJSONResponse('查詢到1封郵件', STATUS_CODE::SUCCESS_NORMAL, array(
				'data_count' => 1,
				'raw' => $mail
			));
        }
        break;
    default:
        Logger::getInstance()->error("不支援的查詢型態【".$_POST["type"]."】");
        echoJSONResponse("不支援的查詢型態【".$_POST["type"]."】", STATUS_CODE::UNSUPPORT_FAIL);
        break;
}
