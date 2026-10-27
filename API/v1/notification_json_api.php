<?php
require_once(dirname(dirname(__FILE__))."/include/init.php");
require_once(INC_DIR.DIRECTORY_SEPARATOR."System.class.php");
require_once(INC_DIR.DIRECTORY_SEPARATOR."Notification.class.php");

$reqType = $_POST["type"] ?? "";
switch ($reqType) {
    case "add_notification":
        $channels = $_POST['channels'] ?? [];
        if (!is_array($channels)) {
            $channels = !empty($channels) ? [$channels] : [];
        }
        // send to sender if no channel found
        if (empty($channels)) {
            $sender = $_POST['sender'] ?? 'UNKNOWN';
            $channels = array($sender);
        }
        $title = trim($_POST['title'] ?? '');
        $notify = new Notification();
        $success = 0;
        $fail = 0;
        $successfulAdded = array();
        foreach ($channels as $channel) {
            if ($channel === 'myself') {
                $siteCode = System::getInstance()->getSiteCode();
                $sender = $_POST['sender'] ?? '';
                if (startsWith($sender, $siteCode)) {
                    $channel = $sender;
                } else {
                    Logger::getInstance()->warning('因 $_POST["sender"] 欄位 '.$sender.' 開頭並非 '.$siteCode.' 故略過處理送給自己之公告。');
                    continue;
                }
            }
            Logger::getInstance()->info('新增訊息至 '.$channel.' 頻道。');
            $lastId = $notify->addMessage($channel, array(
                'title' => $title,
                'content' => trim($_POST['content'] ?? ''),
                'priority' => intval($_POST['priority'] ?? 0),
                'expire_datetime' => $_POST['expire_datetime'] ?? '',
                'sender' => $_POST['sender'] ?? 'UNKNOWN',
                'from_ip' => $_POST['from_ip'] ?? ($client_ip ?? '')
            ));
            Logger::getInstance()->info('新增訊息「'.$title.'」至 '.$channel.' 頻道。 ('.($lastId === false ? '失敗' : '成功').')');
            if ($lastId === false || empty($lastId)) {
                $fail++;
            } else {
                $success++;
                $successfulAdded[] = array(
                    "channel" => $channel,
                    "addedId" => $lastId
                );
            }
        }
        $message = "新增訊息成功 $success 筆，失敗 $fail 筆。";
        $status_code = $fail === 0 ? STATUS_CODE::SUCCESS_NORMAL : STATUS_CODE::DEFAULT_FAIL;
        echoJSONResponse($message, $status_code, array(
            "added" => $successfulAdded,
            "data_count" => $success,
            "title" => $title
        ));
        break;
    case "upd_notification":
        Logger::getInstance()->info(print_r($_POST, true));
        echoJSONResponse('NOT IMPLEMENTED');
        break;
    case "remove_notification":
        $type = $_POST['message_type'] ?? '';
        // e.g. ['inf', '3']
        $channels = $_POST['channels'] ?? [];
        if (!is_array($channels)) {
            $channels = [];
        }
        $notify = new Notification();
        $success = 0;
        $fail = 0;
        foreach ($channels as $data) {
            $channel = $data['channel'] ?? '';
            $id = $data['id'] ?? '';
            $res = $notify->removeMessage($channel, array(
                'id' => $id,
                'type' => $type
            ));
            Logger::getInstance()->info('從 '.$channel.' 頻道移除公告訊息。 ('.($res === false ? '失敗' : '成功').')');
            if ($res === false) {
                $fail++;
            } else {
                $success++;
            }
        }
        $message = "移除訊息成功 $success 筆，失敗 $fail 筆。";
        $status_code = $fail === 0 ? STATUS_CODE::SUCCESS_NORMAL : STATUS_CODE::DEFAULT_FAIL;
        echoJSONResponse($message, $status_code);
        break;
    case "remove_notification_by_title":
        $channels = $_POST['channels'] ?? [];
        if (!is_array($channels)) {
            $channels = !empty($channels) ? [$channels] : [];
        }
        $title = $_POST['title'] ?? '';
        $notify = new Notification();
        $success = 0;
        $fail = 0;
        foreach ($channels as $channel) {
            $result = $notify->removeOutdatedMessageByTitle($channel, $title);
            Logger::getInstance()->info('從 '.$channel.' 頻道移除「'.$title.'」訊息'.($result ? '成功' : '失敗').'。');
            if ($result === false) {
                $fail++;
            } else {
                $success++;
            }
        }
        $message = "根據標題移除訊息成功 $success 筆，失敗 $fail 筆。";
        $status_code = $fail === 0 ? STATUS_CODE::SUCCESS_NORMAL : STATUS_CODE::DEFAULT_FAIL;
        echoJSONResponse($message, $status_code);
        break;
    case "get_notification":
        $channel = $_POST['channel'] ?? '';
        $limit = isset($_POST['limit']) && is_numeric($_POST['limit']) ? (int)$_POST['limit'] : 10;
        $notify = new Notification();
        $raw = $notify->getMessages($channel, $limit);
        $count = is_countable($raw) ? count($raw) : 0;
        $message = "取得 $count 筆訊息成功。";
        if (is_array($raw)) {
            $status = $count > 0 ? STATUS_CODE::SUCCESS_WITH_MULTIPLE_RECORDS : STATUS_CODE::SUCCESS_NORMAL;
            echoJSONResponse($message, $status, array(
                "data_count" => $count,
                "raw" => $raw
            ));
        } else {
            echoJSONResponse($message, STATUS_CODE::DEFAULT_FAIL);
        }
        break;
    case "get_notification_before":
        $channel = $_POST['channel'] ?? '';
        $before = $_POST['before'] ?? '';
        $limit = isset($_POST['limit']) && is_numeric($_POST['limit']) ? (int)$_POST['limit'] : 10;
        $notify = new Notification();
        $raw = $notify->getMessagesBefore($channel, $before, $limit);
        $count = is_countable($raw) ? count($raw) : 0;
        $message = "取得 $count 筆訊息成功。";
        if (is_array($raw)) {
            $status = $count > 0 ? STATUS_CODE::SUCCESS_WITH_MULTIPLE_RECORDS : STATUS_CODE::SUCCESS_NORMAL;
            echoJSONResponse($message, $status, array(
                "data_count" => $count,
                "raw" => $raw
            ));
        } else {
            echoJSONResponse($message, STATUS_CODE::DEFAULT_FAIL);
        }
        break;
    case "get_log":
        $st = $_POST['st'] ?? '';
        $ed = $_POST['ed'] ?? '';

        Logger::getInstance()->info(__FILE__.": [get_log] 接收到 get_log 請求 $st ~ $ed");

        try {
            if (strlen($st) < 7 || strlen($ed) < 7) {
                throw new Exception("時間格式不正確");
            }
            $yearSt = intval(substr($st, 0, 3)) + 1911;
            $monthSt = substr($st, 3, 2);
            $daySt = substr($st, 5, 2);
            $dateTimeSt = new DateTime("$yearSt-$monthSt-$daySt");
            $formattedSt = $dateTimeSt->format("Y-m-d H:i:s");

            $yearEd = intval(substr($ed, 0, 3)) + 1911;
            $monthEd = substr($ed, 3, 2);
            $dayEd = substr($ed, 5, 2);
            $dateTimeEd = new DateTime("$yearEd-$monthEd-$dayEd 23:59:59");
            $formattedEd = $dateTimeEd->format("Y-m-d H:i:s");

            $notify = new Notification();
            $logs = $notify->getLogs($formattedSt, $formattedEd);
            $count = is_countable($logs) ? count($logs) : 0;
            if (is_array($logs)) {
                $status = $count > 0 ? STATUS_CODE::SUCCESS_WITH_MULTIPLE_RECORDS : STATUS_CODE::SUCCESS_NORMAL;
                echoJSONResponse("取得 $count 筆紀錄成功。", $status, array(
                    "raw" => $logs
                ));
            } else {
                echoJSONResponse('讀取 notification_log 失敗', STATUS_CODE::DEFAULT_FAIL);
            }
        } catch (Exception $e) {
            Logger::getInstance()->error(__FILE__.": [get_log] 時間格式錯誤: ".$e->getMessage());
            echoJSONResponse('時間格式錯誤【'.$st.' ~ '.$ed.'】', STATUS_CODE::DEFAULT_FAIL);
        }
        break;
    case "user_message":
        $param = $_POST["id"] ?? $_POST["channel"] ?? $_POST["name"] ?? $_POST["ip"] ?? '';
        $param = empty($param) ? ($client_ip ?? '') : $param;
        $count = isset($_POST["count"]) && is_numeric($_POST["count"]) ? (int)$_POST["count"] : 10;
        Logger::getInstance()->info("XHR [user_message] 查詢使用者即時通私訊【".$param.", count: ".$count."】請求");
        $notify = new Notification();
        $results = $notify->getUserMessages($param, $count);
        if ($results === false || empty($results)) {
            Logger::getInstance()->info("XHR [user_message] 查無 {$param} 即時通私訊。");
            echoJSONResponse("查無 {$param} 即時通私訊。", STATUS_CODE::SUCCESS_WITH_NO_RECORD, array(
                "data_count" => 0,
                "raw" => array(),
                "query_string" => "id=".($_POST["id"] ?? '')."&name=".($_POST["name"] ?? '')."&ip=".($_POST["ip"] ?? '')."&count=".$count
            ));
        } else {
            $countResults = is_countable($results) ? count($results) : 0;
            $msg = "XHR [user_message] 查詢 {$param} 即時通私訊成功。(".$countResults.")";
            Logger::getInstance()->info($msg);
            $status = $countResults > 1 ? STATUS_CODE::SUCCESS_WITH_MULTIPLE_RECORDS : STATUS_CODE::SUCCESS_NORMAL;
            echoJSONResponse($msg, $status, array(
                "data_count" => $countResults,
                "raw" => $results,
                "query_string" => "id=".($_POST["id"] ?? '')."&name=".($_POST["name"] ?? '')."&ip=".($_POST["ip"] ?? '')."&count=".$count
            ));
        }
        break;
    case "user_unread_message":
        $param = $_POST["id"] ?? $_POST["channel"] ?? $_POST["name"] ?? $_POST["ip"] ?? '';
        $param = empty($param) ? ($client_ip ?? '') : $param;
        Logger::getInstance()->info("XHR [user_unread_message] 查詢使用者未讀即時通私訊【".$param."】請求");
        $notify = new Notification();
        $results = $notify->getUserUnreadMessages($param);
        if (empty($results)) {
            Logger::getInstance()->info("XHR [user_unread_message] 查無 {$param} 未讀即時通私訊。");
            echoJSONResponse("查無 {$param} 未讀即時通私訊。", STATUS_CODE::SUCCESS_WITH_NO_RECORD, array(
                "data_count" => 0,
                "raw" => array()
            ));
        } else {
            $countResults = is_countable($results) ? count($results) : 0;
            $msg = "XHR [user_unread_message] 查詢 {$param} 未讀即時通私訊成功。(".$countResults.")";
            Logger::getInstance()->info($msg);
            $status = $countResults > 1 ? STATUS_CODE::SUCCESS_WITH_MULTIPLE_RECORDS : STATUS_CODE::SUCCESS_NORMAL;
            echoJSONResponse($msg, $status, array(
                "data_count" => $countResults,
                "raw" => $results
            ));
        }
        break;
    case "set_read_user_message":
        $param = $_POST["id"] ?? $_POST["channel"] ?? $_POST["name"] ?? $_POST["ip"] ?? '';
        $param = empty($param) ? ($client_ip ?? '') : $param;
        $sn = $_POST["sn"] ?? $_POST["id"] ?? 0;
        Logger::getInstance()->info("XHR [set_read_user_message] 設定已讀使用者即時通私訊【".$param.", sn: ".$sn."】請求");
        $notify = new Notification();
        $result = $notify->setUserMessageRead($param, $sn);
        if ($result) {
            $msg = "設定 ".$sn." 已讀成功。";
            Logger::getInstance()->info("XHR [set_read_user_message] ".$msg);
            echoJSONResponse($msg, STATUS_CODE::SUCCESS_NORMAL, array(
                "data_count" => 1,
                "raw" => $result,
                "query_string" => "sn=".$sn
            ));
        } else {
            Logger::getInstance()->error("XHR [set_read_user_message] 設定 ".$sn." 已讀即時通私訊失敗。");
            echoJSONResponse("設定已讀失敗", STATUS_CODE::DEFAULT_FAIL);
        }
        break;
    case "set_unread_user_message":
        $param = $_POST["id"] ?? $_POST["channel"] ?? $_POST["name"] ?? $_POST["ip"] ?? '';
        $param = empty($param) ? ($client_ip ?? '') : $param;
        $sn = $_POST["sn"] ?? $_POST["id"] ?? 0;
        Logger::getInstance()->info("XHR [set_unread_user_message] 設定未讀使用者即時通私訊【".$param.", sn: ".$sn."】請求");
        $notify = new Notification();
        $result = $notify->setUserMessageUnread($param, $sn);
        if ($result) {
            $msg = "設定 ".$sn." 未讀成功。";
            Logger::getInstance()->info("XHR [set_unread_user_message] ".$msg);
            echoJSONResponse($msg, STATUS_CODE::SUCCESS_NORMAL, array(
                "data_count" => 1,
                "raw" => $result,
                "query_string" => "sn=".$sn
            ));
        } else {
            Logger::getInstance()->error("XHR [set_unread_user_message] 設定 ".$sn." 未讀即時通私訊失敗。");
            echoJSONResponse("設定未讀失敗", STATUS_CODE::DEFAULT_FAIL);
        }
        break;
    case "del_user_message":
        $param = $_POST["id"] ?? $_POST["channel"] ?? $_POST["name"] ?? $_POST["ip"] ?? '';
        $param = empty($param) ? ($client_ip ?? '') : $param;
        $sn = $_POST["sn"] ?? $_POST["id"] ?? 0;
        Logger::getInstance()->info("XHR [del_user_message] 刪除即時通私訊【".$param.", sn: ".$sn."】請求");
        $notify = new Notification();
        $result = $notify->deleteUserMessage($param, $sn);
        if ($result) {
            $msg = "刪除「".$sn."」訊息成功";
            Logger::getInstance()->info("XHR [del_user_message] ".$msg);
            echoJSONResponse($msg, STATUS_CODE::SUCCESS_NORMAL, array(
                "data_count" => 1,
                "sn" => $sn,
                "query_string" => "sn=".$sn
            ));
        } else {
            Logger::getInstance()->error("XHR [del_user_message] 刪除「".$sn."」訊息失敗。");
            echoJSONResponse("刪除「".$sn."」訊息失敗", STATUS_CODE::DEFAULT_FAIL);
        }
        break;
    default:
        Logger::getInstance()->error("不支援的查詢型態【".$reqType."】");
        echoJSONResponse("不支援的查詢型態【".$reqType."】", STATUS_CODE::UNSUPPORT_FAIL);
        break;
}
