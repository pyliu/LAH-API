<?php
require_once(dirname(dirname(__FILE__))."/include/init.php");
require_once(INC_DIR.DIRECTORY_SEPARATOR."SQLiteMonitorMail.class.php");
require_once(INC_DIR.DIRECTORY_SEPARATOR."SQLiteConnectivity.class.php");

$sqlite_monitor_mail = new SQLiteMonitorMail();

switch ($_POST["type"]) {
    case "imap_open":
        Logger::getInstance()->info("XHR [check_connectivity] 檢查監控伺服器連線狀態請求");
        $url = "{".$_POST['host'].($_POST['ssl'] === 'true' ? ':993/imap/ssl/novalidate-cert' : ':143/notls')."}INBOX";
        $id = $_POST['account'];
        $pwd = $_POST['password'];
        //Optional parameters
        $options = OP_READONLY;
        $retries = 3;
        $mailbox = imap_open($url, $id, $pwd, $options, $retries);
        if($mailbox) {
            echoJSONResponse('郵件伺服器('.$_POST['host'].')可正常連線', STATUS_CODE::SUCCESS_NORMAL);
        } else {
            echoJSONResponse('無法連線伺服器 - '.$url);
        }
        break;
    case "clean_mail":
        Logger::getInstance()->info("XHR [clean_mail] 清除過期監控郵件請求");
        echoJSONResponse('尚未實作');
        break;
    case "check_mail":
        Logger::getInstance()->info("XHR [check_mail] 檢查最新監控郵件請求");
        $inserted = $sqlite_monitor_mail->fetchFromMailServer();
        $message = "查詢到 $inserted 封郵件";
        Logger::getInstance()->info("XHR [check_mail] $message");
        if (empty($inserted)) {
            echoJSONResponse('沒有新的監控郵件');
        } else {
            echoJSONResponse($message, STATUS_CODE::SUCCESS_NORMAL, array(
				'data_count' => $inserted,
				'raw' => $inserted
			));
        }
        break;
    case "latest":
        $convert = $_POST['convert'] === 'true' ? true : false;
        Logger::getInstance()->info("XHR [latest] 查詢最新監控郵件請求");
        $mail = $sqlite_monitor_mail->getLatestMail($convert);
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
        $convert = $_POST['convert'] === 'true' ? true : false;
        // 搜尋 via subject
        Logger::getInstance()->info("XHR [subject] 查詢監控郵件 BY '%${keyword}%' IN SUBJECT 請求");
        $days_before = $_POST["days"] ?? 1;
        $mails = $sqlite_monitor_mail->getMailsBySubject($_POST["keyword"], $days_before * 24 * 60 * 60, $convert);
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
    case "sender":
        $keyword = $_POST["keyword"];
        $convert = $_POST['convert'] === 'true' ? true : false;
        // 搜尋 via subject
        Logger::getInstance()->info("XHR [sender] 查詢監控郵件 BY sender: ${keyword}  請求");
        $days_before = $_POST["days"] ?? 1;
        $mails = $sqlite_monitor_mail->getMailsBySender($keyword, $days_before * 24 * 60 * 60, $convert);
        if (empty($mails)) {
            echoJSONResponse("sender: ${keyword} 沒有找到郵件");
        } else {
            $count = count($mails);
            echoJSONResponse("查詢到 $count 封郵件", STATUS_CODE::SUCCESS_WITH_MULTIPLE_RECORDS, array(
				'data_count' => $count,
				'raw' => $mails
			));
        }
        break;
    case "monitor_targets":
        $active = $_POST["all"] !== "true";
        Logger::getInstance()->info("XHR [monitor_targets] 查詢監控系統標的請求 (active = $active)");
        $conn = new SQLiteConnectivity();
        if ($arr = $conn->getTargets($active)) {
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
        Logger::getInstance()->info("XHR [replace_connectivity_target] 取代監控系統標的請求");
        $conn = new SQLiteConnectivity();
        if ($arr = $conn->replaceTarget($_POST)) {
            echoJSONResponse("已取代 ".$_POST["name"]." ".$_POST["ip"].":".$_POST["port"]." 監控標的資料。", STATUS_CODE::SUCCESS_NORMAL, array(
                "data_count" => 1,
                "raw" => $arr
            ));
        } else {
            $error = "取代 ".$_POST["ip"]." 監控系統標的失敗。";
            Logger::getInstance()->error("XHR [replace_connectivity_target] ${error}");
            echoJSONResponse($error);
        }
        break;
    case "edit_connectivity_target":
        Logger::getInstance()->info("XHR [edit_connectivity_target] 更新監控系統標的請求");
        $conn = new SQLiteConnectivity();
        if ($arr = $conn->editTarget($_POST, $_POST['editIp'], $_POST['editPort'])) {
            echoJSONResponse("已更新 ".$_POST["name"]." ".$_POST["ip"].":".$_POST["port"]." 監控標的資料。", STATUS_CODE::SUCCESS_NORMAL, array(
                "data_count" => 1,
                "raw" => $arr
            ));
        } else {
            $error = "更新 ".$_POST["ip"]." 監控系統標的失敗。";
            Logger::getInstance()->error("XHR [edit_connectivity_target] ${error}");
            echoJSONResponse($error);
        }
        break;
    case "remove_connectivity_target":
        Logger::getInstance()->info("XHR [remove_connectivity_target] 刪除監控系統標的請求");
        $conn = new SQLiteConnectivity();
        if ($arr = $conn->removeTarget($_POST)) {
            echoJSONResponse("已刪除 ".$_POST["name"]." ".$_POST["ip"].":".$_POST["port"]." 監控標的資料。", STATUS_CODE::SUCCESS_NORMAL, array(
                "data_count" => 1,
                "raw" => $arr
            ));
        } else {
            $error = "刪除 ".$_POST["name"]." 監控系統標的失敗。";
            Logger::getInstance()->error("XHR [remove_connectivity_target] ${error}");
            echoJSONResponse($error);
        }
        break;
    default:
        Logger::getInstance()->error("不支援的查詢型態【".$_POST["type"]."】");
        echoJSONResponse("不支援的查詢型態【".$_POST["type"]."】", STATUS_CODE::UNSUPPORT_FAIL);
        break;
}
