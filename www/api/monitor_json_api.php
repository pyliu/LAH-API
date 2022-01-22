<?php
require_once(dirname(dirname(__FILE__))."/include/init.php");
require_once(INC_DIR.DIRECTORY_SEPARATOR."SQLiteMonitorMail.class.php");
require_once(INC_DIR.DIRECTORY_SEPARATOR."SQLiteConnectivity.class.php");

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
    case "monitor_targets":
        Logger::getInstance()->info("XHR [monitor_targets] 查詢監控系統標的請求");
        $conn = new SQLiteConnectivity();
        if ($arr = $conn->getTargets($_POST["all"] !== "true")) {
            $count = count($arr);
            echoJSONResponse("取得 $count 筆資料。", STATUS_CODE::SUCCESS_NORMAL, array(
                "data_count" => $count,
                "raw" => $arr
            ));
        } else {
            $error = "取得監控系統標的失敗。";
            Logger::getInstance()->error("XHR [monitor_targets] ${error}");
            echoJSONResponse($error);
        }
        break;
    case "monitor_targets_history":
        Logger::getInstance()->info("XHR [monitor_targets_history] 查詢全監控系統標的歷史資料請求");
        $conn = new SQLiteConnectivity();
        if ($arr = $conn->getStatus($_POST["force"])) {
            $count = count($arr);
            echoJSONResponse("取得 $count 筆資料。", STATUS_CODE::SUCCESS_NORMAL, array(
                "data_count" => $count,
                "raw" => $arr
            ));
        } else {
            $error = "取得全監控系統標的歷史資料失敗。";
            Logger::getInstance()->error("XHR [monitor_targets_history] ${error}");
            echoJSONResponse($error);
        }
        break;
    case "monitor_target_history":
        Logger::getInstance()->info("XHR [monitor_target_history] 查詢監控系統標的歷史資料請求");
        $conn = new SQLiteConnectivity();
        if ($arr = $conn->getIPStatus($_POST["ip"], $_POST["force"], $_POST['port'])) {
            echoJSONResponse("取得 1 筆資料。", STATUS_CODE::SUCCESS_NORMAL, array(
                "data_count" => 1,
                "raw" => $arr
            ));
        } else {
            $error = "取得 ".$_POST["ip"]." 監控系統標的歷史資料失敗。";
            Logger::getInstance()->error("XHR [monitor_target_history] ${error}");
            echoJSONResponse($error);
        }
        break;
    case "replace_connectivity_target":
        Logger::getInstance()->info("XHR [replace_connectivity_target] 更新監控系統標的請求");
        $conn = new SQLiteConnectivity();
        if ($arr = $conn->replaceTarget($_POST)) {
            echoJSONResponse("已更新 ".$_POST["name"]." ".$_POST["ip"].":".$_POST["port"]." 監控標的資料。", STATUS_CODE::SUCCESS_NORMAL, array(
                "data_count" => 1,
                "raw" => $arr
            ));
        } else {
            $error = "更新 ".$_POST["ip"]." 監控系統標的失敗。";
            Logger::getInstance()->error("XHR [replace_connectivity_target] ${error}");
            echoJSONResponse($error);
        }
        break;
    default:
        Logger::getInstance()->error("不支援的查詢型態【".$_POST["type"]."】");
        echoJSONResponse("不支援的查詢型態【".$_POST["type"]."】", STATUS_CODE::UNSUPPORT_FAIL);
        break;
}
