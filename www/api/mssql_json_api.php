<?php
require_once(dirname(dirname(__FILE__))."/include/init.php");
require_once(INC_DIR."/DocUserInfo.class.php");
require_once(INC_DIR."/TdocUserInfo.class.php");
require_once(INC_DIR."/Message.class.php");
require_once(INC_DIR."/Cache.class.php");
require_once(INC_DIR."/System.class.php");

$cache = Cache::getInstance();
$system = System::getInstance();

$mock = $system->isMockMode();
$mssql_on = $system->isMSSQLEnable();
if (!$mssql_on) {
	$log->warning("XHR [".$_POST["type"]."] MSSQL查詢模式已停用。");
	echoJSONResponse("MSSQL查詢模式已停用");
}

switch ($_POST["type"]) {
	case "user_message":
		$log->info("XHR [user_message] 查詢使用者信差訊息【".$_POST["id"].", ".$_POST["name"].", ".$_POST["ip"].", ".$_POST["count"]."】請求");
		$param = $_POST["id"] ?? $_POST["name"] ?? $_POST["ip"];
		$param = empty($param) ? $client_ip : $param;
		$message = new Message();
		$results = $mock ? $cache->get('user_message') : $message->getMessageByUser($param, $_POST["count"] ?? 5);
		if (!$mock) $cache->set('user_message', $results);
		if (empty($results)) {
			$log->info("XHR [user_message] 查無 ${param} 信差訊息。");
			echoJSONResponse("查無 ${param} 信差訊息。");
		} else {
            $msg = "XHR [user_message] 查詢 ${param} 信差訊息成功。(".count($results).")";
			$log->info($msg);
            echoJSONResponse($msg, STATUS_CODE::SUCCESS_NORMAL, array(
				"data_count" => count($results),
				"raw" => $results,
				"query_string" => "id=".$_POST["id"]."&name=".$_POST["name"]."&ip=".$_POST["ip"]."&count=".$_POST["count"],
			));
		}
		break;
	case "user_unread_message":
		$param = $_POST["id"] ?? $_POST["name"] ?? $_POST["ip"];
		$log->info("XHR [user_unread_message] 查詢使用者未讀信差訊息【".$param."】請求");
		$param = empty($param) ? $client_ip : $param;
		$message = new Message();
		$results = $mock ? $cache->get('user_unread_message') : $message->getUnreadMessageByUser($param);
		if (!$mock) $cache->set('user_unread_message', $results);
		if (empty($results)) {
			$log->info("XHR [user_unread_message] 查無 ${param} 未讀信差訊息。");
			echoJSONResponse("查無 ${param} 未讀信差訊息。");
		} else {
            $msg = "XHR [user_unread_message] 查詢 ${param} 未讀信差訊息成功。(".count($results).")";
			$log->info($msg);
			echoJSONResponse($msg, STATUS_CODE::SUCCESS_NORMAL, array(
				"data_count" => count($results),
				"raw" => $results
			));
		}
		break;
	case "set_read_user_message":
		$log->info("XHR [set_read_user_message] 設定已讀使用者信差訊息【".$_POST["sn"]."】請求");
		$message = new Message();
		$result = $mock ? $cache->get('set_read_user_message') : $message->setRead($_POST["sn"]);
		if (!$mock) $cache->set('set_read_user_message', $result);
		if ($result) {
            $msg = "設定 ".$_POST['sn']." 已讀成功。";
            $log->info("XHR [set_read_user_message] ".$msg);
            echoJSONResponse($msg, STATUS_CODE::SUCCESS_NORMAL, array(
				"data_count" => 1,
				"raw" => $result,
				"query_string" => "sn=".$_POST["sn"]
			));
		} else {
			$log->error("XHR [set_read_user_message] "."設定 ".$_POST['sn']." 已讀信差訊息失敗。");
			echoJSONResponse(print_r($message->lastError(), true));
		}
		break;
	case "set_unread_user_message":
		$log->info("XHR [set_unread_user_message] 設定未讀使用者信差訊息【".$_POST["sn"]."】請求");
		$message = new Message();
		$result = $mock ? $cache->get('set_unread_user_message') : $message->setUnread($_POST["sn"]);
		if (!$mock) $cache->set('set_unread_user_message', $result);
		if ($result) {
            $msg = "設定 ".$_POST['sn']." 未讀成功。";
            $log->info("XHR [set_unread_user_message] ".$msg);
            echoJSONResponse($msg, STATUS_CODE::SUCCESS_NORMAL, array(
				"data_count" => 1,
				"raw" => $result,
				"query_string" => "sn=".$_POST["sn"]
            ));
		} else {
			$log->error("XHR [set_unread_user_message] "."設定 ".$_POST['sn']." 未讀信差訊息失敗。");
			echoJSONResponse(print_r($message->lastError(), true));
		}
		break;
	case "del_user_message":
		$log->info("XHR [del_user_message] 刪除訊息【".$_POST["sn"]."】請求");
		$msg = new Message();
		$ret = $mock ? $cache->get('del_user_message') : $msg->delete($_POST["sn"]);
		if (!$mock) $cache->set('del_user_message', $ret);
		if ($ret) {
            $msg = "刪除「".$_POST["sn"]."」訊息成功";
            $log->info("XHR [del_user_message] ${msg}。");
            echoJSONResponse($msg, STATUS_CODE::SUCCESS_NORMAL, array(
				"data_count" => 1,
				"sn" => $_POST["sn"],
                "query_string" => "sn=".$_POST["sn"]
            ));
		} else {
			$log->error("XHR [del_user_message] 刪除「".$_POST["sn"]."」訊息失敗。");
			echoJSONResponse("刪除「".$_POST["sn"]."」訊息失敗");
		}
		break;
	case "send_message":
		$log->info("XHR [send_message] 送出訊息【".$_POST["title"].", ".$_POST["content"].", ".$_POST["who"].", ".$_POST["send_time"].", ".$_POST["end_time"]."】請求");
		$msg = new Message();
		$id = $mock ? $cache->get('send_message') : $msg->sendByInterval($_POST["title"], $_POST["content"], $_POST["who"], date('Y-m-d ').$_POST["send_time"], date('Y-m-d ').$_POST["end_time"]);	// send message by send_time and drop it by end_time
		if (!$mock) $cache->set('send_message', $id);
		if ($id > 0) {
			$log->info("XHR [send_message] 給「".$_POST["who"]."」訊息「".$_POST["title"]."」已寫入內網資料庫【sn: $id 】");
            echoJSONResponse("給「".$_POST["who"]."」訊息傳送成功 (sn: $id)", STATUS_CODE::SUCCESS_NORMAL, array(
				"data_count" => 1,
				"sn" => $id,
                "query_string" => "title=".$_POST["title"]."&content=".$_POST["content"]."&who=".$_POST["who"]
            ));
		} else if ($id == -1) {
			$msg = "現職人員找不到 ".$_POST["who"]." 故無法傳送訊息。";
			$log->warning("XHR [send_message] ${msg}");
			echoJSONResponse($msg);
		} else if ($id == -2 || $id == -3) {
			$msg = "時間區間有問題，故無法傳送訊息。【".$_POST["send_time"].", ".$_POST["end_time"]."】";
			$log->warning("XHR [send_message] ${msg}");
			echoJSONResponse($msg);
		} else if ($id == -4) {
			$msg = "忽略時間已超過現在時間，故無需傳送訊息。【end: ".$_POST["end_time"].", now: ".date('H:i:s')."】";
			$log->warning("XHR [send_message] ${msg}");
			echoJSONResponse($msg);
		} else {
			$log->error("XHR [send_message] 新增「".$_POST["title"]."」訊息失敗【${id}】。");
			echoJSONResponse("新增 ".$_POST["title"]." 訊息失敗【${id}】。");
		}
		break;
    case "upd_ext_doc":
        $log->info("XHR [upd_ext_doc] 更新舊資料庫分機號碼【".$_POST["id"].", ".$_POST["ext"]."】請求");
        // connect MSSQL doc DB to update legacy data
        $doc = new DocUserInfo();
        $result = $mock ? $cache->get('upd_ext_doc') : $doc->updateExt($_POST["id"], $_POST["ext"]);
        if (!$mock) $cache->set('upd_ext_doc', $result);
        if ($result) {
            $msg = "更新舊資料庫分機號碼 ".$_POST["id"].", ".$_POST["ext"]." 成功。";
            $log->info("XHR [upd_ext_doc] ".$msg);
            echoJSONResponse($msg, STATUS_CODE::SUCCESS_NORMAL);
        } else {
            $log->info("XHR [upd_ext_doc] 更新舊資料庫分機號碼 ".$_POST["id"].", ".$_POST["ext"]." 失敗。");
            echoJSONResponse("更新舊資料庫分機號碼 ".$_POST["id"].", ".$_POST["ext"]." 失敗。");
        }
        break;
	case "upd_ip_tdoc":
		$log->info("XHR [upd_ip_tdoc] 設定 ".$_POST['id']." 為 ".$_POST['ip']."請求");
		$mssql_on = $system->isMSSQLEnable();
		if ($mssql_on) {
			// connect MSSQL tdoc DB AP_USER to update legacy data (AP_PCIP)
			$tdoc = new TdocUserInfo();
			$result = $mock ? $cache->get('upd_ip_tdoc') : $tdoc->updateIp($_POST["id"], $_POST["ip"]);
			if (!$mock) $cache->set('upd_ip_tdoc', $result);
			// if ($result) {
				$msg = "更新知識網使用者 ".$_POST["id"]." IP  => ".$_POST["ip"]." 已送出";
				$log->info("XHR [upd_ip_tdoc] ".$msg);
				echoJSONResponse($msg, STATUS_CODE::SUCCESS_NORMAL);
			// } else {
			// 	$msg = "更新知識網IP ".$_POST["id"]." => ".$_POST["ip"]." 失敗。".print_r($tdoc->getLastError(), true);
			// 	$log->info("XHR [upd_ip_tdoc] $msg");
			// 	echoJSONResponse($msg);
			// }
		} else {
			$log->info("XHR [upd_ip_tdoc] MSSQL連線未啟用。");
			echoJSONResponse("MSSQL連線未啟用。");
		}
		break;
    default:
        $log->error("不支援的查詢型態【".$_POST["type"]."】");
        echoErrorJSONString("不支援的查詢型態【".$_POST["type"]."】", STATUS_CODE::UNSUPPORT_FAIL);
        break;
}
