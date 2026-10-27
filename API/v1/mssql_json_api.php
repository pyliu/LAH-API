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
$reqType = $_POST["type"] ?? "";

if (!$mssql_on) {
	if (in_array($reqType, array("user_message", "user_unread_message", "set_read_user_message", "set_unread_user_message", "del_user_message"))) {
		require_once(INC_DIR."/Notification.class.php");
		$notify = new Notification();
		switch ($reqType) {
			case "user_message":
				$param = $_POST["id"] ?? $_POST["name"] ?? $_POST["ip"] ?? '';
				$param = empty($param) ? ($client_ip ?? '') : $param;
				$count = isset($_POST["count"]) && is_numeric($_POST["count"]) ? (int)$_POST["count"] : 5;
				$results = $notify->getUserMessages($param, $count);
				if (empty($results)) {
					Logger::getInstance()->info("XHR [user_message] (即時通) 查無 {$param} 訊息。");
					echoJSONResponse("查無 {$param} 訊息。");
				} else {
					$c = is_countable($results) ? count($results) : 0;
					$msg = "XHR [user_message] (即時通) 查詢 {$param} 訊息成功。({$c})";
					Logger::getInstance()->info($msg);
					echoJSONResponse($msg, STATUS_CODE::SUCCESS_NORMAL, array(
						"data_count" => $c,
						"raw" => $results,
						"query_string" => "id=".($_POST["id"] ?? '')."&name=".($_POST["name"] ?? '')."&ip=".($_POST["ip"] ?? '')."&count=".$count
					));
				}
				exit;
			case "user_unread_message":
				$param = $_POST["id"] ?? $_POST["name"] ?? $_POST["ip"] ?? '';
				$param = empty($param) ? ($client_ip ?? '') : $param;
				$results = $notify->getUserUnreadMessages($param);
				if (empty($results)) {
					Logger::getInstance()->info("XHR [user_unread_message] (即時通) 查無 {$param} 未讀訊息。");
					echoJSONResponse("查無 {$param} 未讀訊息。");
				} else {
					$c = is_countable($results) ? count($results) : 0;
					$msg = "XHR [user_unread_message] (即時通) 查詢 {$param} 未讀訊息成功。({$c})";
					Logger::getInstance()->info($msg);
					echoJSONResponse($msg, STATUS_CODE::SUCCESS_NORMAL, array(
						"data_count" => $c,
						"raw" => $results
					));
				}
				exit;
			case "set_read_user_message":
				$param = $_POST["id"] ?? $_POST["name"] ?? $_POST["ip"] ?? '';
				$param = empty($param) ? ($client_ip ?? '') : $param;
				$sn = $_POST["sn"] ?? 0;
				$result = $notify->setUserMessageRead($param, $sn);
				if ($result) {
					$msg = "設定 ".$sn." 已讀成功。";
					echoJSONResponse($msg, STATUS_CODE::SUCCESS_NORMAL, array(
						"data_count" => 1,
						"raw" => $result,
						"query_string" => "sn=".$sn
					));
				} else {
					echoJSONResponse("設定已讀即時通私訊失敗。");
				}
				exit;
			case "set_unread_user_message":
				$param = $_POST["id"] ?? $_POST["name"] ?? $_POST["ip"] ?? '';
				$param = empty($param) ? ($client_ip ?? '') : $param;
				$sn = $_POST["sn"] ?? 0;
				$result = $notify->setUserMessageUnread($param, $sn);
				if ($result) {
					$msg = "設定 ".$sn." 未讀成功。";
					echoJSONResponse($msg, STATUS_CODE::SUCCESS_NORMAL, array(
						"data_count" => 1,
						"raw" => $result,
						"query_string" => "sn=".$sn
					));
				} else {
					echoJSONResponse("設定未讀即時通私訊失敗。");
				}
				exit;
			case "del_user_message":
				$param = $_POST["id"] ?? $_POST["name"] ?? $_POST["ip"] ?? '';
				$param = empty($param) ? ($client_ip ?? '') : $param;
				$sn = $_POST["sn"] ?? 0;
				$result = $notify->deleteUserMessage($param, $sn);
				if ($result) {
					$msg = "刪除「".$sn."」訊息成功";
					echoJSONResponse($msg, STATUS_CODE::SUCCESS_NORMAL, array(
						"data_count" => 1,
						"sn" => $sn
					));
				} else {
					echoJSONResponse("刪除「".$sn."」訊息失敗");
				}
				exit;
		}
	}
	Logger::getInstance()->warning("XHR [".$reqType."] MSSQL查詢模式已停用。");
	echoJSONResponse("MSSQL查詢模式已停用");
}

switch ($reqType) {
	case "user_message":
		$id = $_POST["id"] ?? '';
		$name = $_POST["name"] ?? '';
		$ip = $_POST["ip"] ?? '';
		$count = $_POST["count"] ?? 5;
		Logger::getInstance()->info("XHR [user_message] 查詢使用者信差訊息【".$id.", ".$name.", ".$ip.", ".$count."】請求");
		$param = $_POST["id"] ?? $_POST["name"] ?? $_POST["ip"] ?? '';
		$param = empty($param) ? ($client_ip ?? '') : $param;
		$message = new Message();
		$results = $mock ? $cache->get('user_message') : $message->getMessageByUser($param, $count);
		if (!$mock) $cache->set('user_message', $results);
		if (empty($results)) {
			Logger::getInstance()->info("XHR [user_message] 查無 {$param} 信差訊息。");
			echoJSONResponse("查無 {$param} 信差訊息。");
		} else {
			$c = is_countable($results) ? count($results) : 0;
			$msg = "XHR [user_message] 查詢 {$param} 信差訊息成功。(".$c.")";
			Logger::getInstance()->info($msg);
			echoJSONResponse($msg, STATUS_CODE::SUCCESS_NORMAL, array(
				"data_count" => $c,
				"raw" => $results,
				"query_string" => "id=".$id."&name=".$name."&ip=".$ip."&count=".$count,
			));
		}
		break;
	case "user_unread_message":
		$param = $_POST["id"] ?? $_POST["name"] ?? $_POST["ip"] ?? '';
		Logger::getInstance()->info("XHR [user_unread_message] 查詢使用者未讀信差訊息【".$param."】請求");
		$param = empty($param) ? ($client_ip ?? '') : $param;
		$message = new Message();
		$results = $mock ? $cache->get('user_unread_message') : $message->getUnreadMessageByUser($param);
		if (!$mock) $cache->set('user_unread_message', $results);
		if (empty($results)) {
			Logger::getInstance()->info("XHR [user_unread_message] 查無 {$param} 未讀信差訊息。");
			echoJSONResponse("查無 {$param} 未讀信差訊息。");
		} else {
			$c = is_countable($results) ? count($results) : 0;
			$msg = "XHR [user_unread_message] 查詢 {$param} 未讀信差訊息成功。(".$c.")";
			Logger::getInstance()->info($msg);
			echoJSONResponse($msg, STATUS_CODE::SUCCESS_NORMAL, array(
				"data_count" => $c,
				"raw" => $results
			));
		}
		break;
	case "set_read_user_message":
		$sn = $_POST["sn"] ?? '';
		Logger::getInstance()->info("XHR [set_read_user_message] 設定已讀使用者信差訊息【".$sn."】請求");
		$message = new Message();
		$result = $mock ? $cache->get('set_read_user_message') : $message->setRead($sn);
		if (!$mock) $cache->set('set_read_user_message', $result);
		if ($result) {
			$msg = "設定 ".$sn." 已讀成功。";
			Logger::getInstance()->info("XHR [set_read_user_message] ".$msg);
			echoJSONResponse($msg, STATUS_CODE::SUCCESS_NORMAL, array(
				"data_count" => 1,
				"raw" => $result,
				"query_string" => "sn=".$sn
			));
		} else {
			Logger::getInstance()->error("XHR [set_read_user_message] 設定 ".$sn." 已讀信差訊息失敗。");
			$err = $message->lastError();
			echoJSONResponse(!empty($err) ? print_r($err, true) : "設定 ".$sn." 已讀信差訊息失敗。");
		}
		break;
	case "set_unread_user_message":
		$sn = $_POST["sn"] ?? '';
		Logger::getInstance()->info("XHR [set_unread_user_message] 設定未讀使用者信差訊息【".$sn."】請求");
		$message = new Message();
		$result = $mock ? $cache->get('set_unread_user_message') : $message->setUnread($sn);
		if (!$mock) $cache->set('set_unread_user_message', $result);
		if ($result) {
			$msg = "設定 ".$sn." 未讀成功。";
			Logger::getInstance()->info("XHR [set_unread_user_message] ".$msg);
			echoJSONResponse($msg, STATUS_CODE::SUCCESS_NORMAL, array(
				"data_count" => 1,
				"raw" => $result,
				"query_string" => "sn=".$sn
			));
		} else {
			Logger::getInstance()->error("XHR [set_unread_user_message] 設定 ".$sn." 未讀信差訊息失敗。");
			$err = $message->lastError();
			echoJSONResponse(!empty($err) ? print_r($err, true) : "設定 ".$sn." 未讀信差訊息失敗。");
		}
		break;
	case "del_user_message":
		$sn = $_POST["sn"] ?? '';
		Logger::getInstance()->info("XHR [del_user_message] 刪除訊息【".$sn."】請求");
		$msg = new Message();
		$ret = $mock ? $cache->get('del_user_message') : $msg->delete($sn);
		if (!$mock) $cache->set('del_user_message', $ret);
		if ($ret) {
			$msg = "刪除「".$sn."」訊息成功";
			Logger::getInstance()->info("XHR [del_user_message] {$msg}。");
			echoJSONResponse($msg, STATUS_CODE::SUCCESS_NORMAL, array(
				"data_count" => 1,
				"sn" => $sn,
				"query_string" => "sn=".$sn
			));
		} else {
			Logger::getInstance()->error("XHR [del_user_message] 刪除「".$sn."」訊息失敗。");
			echoJSONResponse("刪除「".$sn."」訊息失敗");
		}
		break;
	case "send_message":
		$title = $_POST["title"] ?? '';
		$content = $_POST["content"] ?? '';
		$who = $_POST["who"] ?? '';
		$send_time = $_POST["send_time"] ?? '';
		$end_time = $_POST["end_time"] ?? '';
		Logger::getInstance()->info("XHR [send_message] 送出訊息【".$title.", ".$content.", ".$who.", ".$send_time.", ".$end_time."】請求");
		$msg = new Message();
		$id = $mock ? $cache->get('send_message') : $msg->sendByInterval($title, $content, $who, date('Y-m-d ').$send_time, date('Y-m-d ').$end_time);	// send message by send_time and drop it by end_time
		if (!$mock) $cache->set('send_message', $id);
		if ($id > 0) {
			Logger::getInstance()->info("XHR [send_message] 給「".$who."」訊息「".$title."」已寫入內網資料庫【sn: $id 】");
			echoJSONResponse("給「".$who."」訊息傳送成功 (sn: $id)", STATUS_CODE::SUCCESS_NORMAL, array(
				"data_count" => 1,
				"sn" => $id,
				"query_string" => "title=".$title."&content=".$content."&who=".$who
			));
		} else if ($id == -1) {
			$msg = "現職人員找不到 ".$who." 故無法傳送訊息。";
			Logger::getInstance()->warning("XHR [send_message] {$msg}");
			echoJSONResponse($msg);
		} else if ($id == -2 || $id == -3) {
			$msg = "時間區間有問題，故無法傳送訊息。【".$send_time.", ".$end_time."】";
			Logger::getInstance()->warning("XHR [send_message] {$msg}");
			echoJSONResponse($msg);
		} else if ($id == -4) {
			$msg = "忽略時間已超過現在時間，故無需傳送訊息。【end: ".$end_time.", now: ".date('H:i:s')."】";
			Logger::getInstance()->warning("XHR [send_message] {$msg}");
			echoJSONResponse($msg);
		} else {
			Logger::getInstance()->error("XHR [send_message] 新增「".$title."」訊息失敗【{$id}】。");
			echoJSONResponse("新增 ".$title." 訊息失敗【{$id}】。");
		}
		break;
	case "upd_ext_doc":
		$id = $_POST["id"] ?? '';
		$ext = $_POST["ext"] ?? '';
		Logger::getInstance()->info("XHR [upd_ext_doc] 更新舊資料庫分機號碼【".$id.", ".$ext."】請求");
		$doc = new DocUserInfo();
		$result = $mock ? $cache->get('upd_ext_doc') : $doc->updateExt($id, $ext);
		if (!$mock) $cache->set('upd_ext_doc', $result);
		if ($result) {
			$msg = "更新舊資料庫分機號碼 ".$id.", ".$ext." 成功。";
			Logger::getInstance()->info("XHR [upd_ext_doc] ".$msg);
			echoJSONResponse($msg, STATUS_CODE::SUCCESS_NORMAL);
		} else {
			Logger::getInstance()->info("XHR [upd_ext_doc] 更新舊資料庫分機號碼 ".$id.", ".$ext." 失敗。");
			echoJSONResponse("更新舊資料庫分機號碼 ".$id.", ".$ext." 失敗。");
		}
		break;
	case "upd_ip_tdoc":
		$id = $_POST['id'] ?? '';
		$ip = $_POST['ip'] ?? '';
		Logger::getInstance()->info("XHR [upd_ip_tdoc] 設定 ".$id." 為 ".$ip."請求");
		$mssql_on = $system->isMSSQLEnable();
		if ($mssql_on) {
			$tdoc = new TdocUserInfo();
			$result = $mock ? $cache->get('upd_ip_tdoc') : $tdoc->updateIp($id, $ip);
			if (!$mock) $cache->set('upd_ip_tdoc', $result);
			$msg = "更新知識網使用者 ".$id." IP  => ".$ip." 已送出";
			Logger::getInstance()->info("XHR [upd_ip_tdoc] ".$msg);
			echoJSONResponse($msg, STATUS_CODE::SUCCESS_NORMAL);
		} else {
			Logger::getInstance()->info("XHR [upd_ip_tdoc] MSSQL連線未啟用。");
			echoJSONResponse("MSSQL連線未啟用。");
		}
		break;
	default:
		Logger::getInstance()->error("不支援的查詢型態【".$reqType."】");
		echoJSONResponse("不支援的查詢型態【".$reqType."】", STATUS_CODE::UNSUPPORT_FAIL);
		break;
}
