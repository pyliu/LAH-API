<?php
require_once(dirname(dirname(__FILE__))."/include/init.php");
require_once(INC_DIR.DIRECTORY_SEPARATOR."SQLiteMonitorMail.class.php");

$sqlite_monitor_mail = new SQLiteMonitorMail();

switch ($_POST["type"]) {
    case "latest":
        Logger::getInstance()->info("XHR [latest] 查詢最新監控郵件請求");
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
    case "subject":
        $keyword = $_POST["keyword"];
        // 搜尋 via subject
        Logger::getInstance()->info("XHR [subject] 查詢監控郵件 BY '%${keyword}%' IN SUBJECT 請求");
        $days_before = $_POST["days"] ?? 1;
        $mails = $sqlite_monitor_mail->getMailsBySubject($_POST["keyword"], $days_before * 24 * 60 * 60);
        if (empty($mails)) {
            echoJSONResponse("'%${keyword}%' IN SUBJECT 沒有找到郵件");
        } else {
            $count = count($mails);
            echoJSONResponse("查詢到 $count 封郵件", STATUS_CODE::SUCCESS_WITH_MULTIPLE_RECORDS, array(
				'data_count' => $count,
				'raw' => $mails
			));
        }
        break;
    default:
        Logger::getInstance()->error("不支援的查詢型態【".$_POST["type"]."】");
        echoJSONResponse("不支援的查詢型態【".$_POST["type"]."】", STATUS_CODE::UNSUPPORT_FAIL);
        break;
}
