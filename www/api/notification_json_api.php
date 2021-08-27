<?php
require_once(dirname(dirname(__FILE__))."/include/init.php");
require_once(INC_DIR.DIRECTORY_SEPARATOR."System.class.php");
require_once(INC_DIR.DIRECTORY_SEPARATOR."Notification.class.php");

switch ($_POST["type"]) {
    case "add_notification":
        // Logger::getInstance()->info(print_r($_POST, true));
        $channels = $_POST['channels'];
        // send to sender if no channel found
        if (empty($channels)) {
            $channels = array($_POST['sender']);
        }
        $title = trim($_POST['title']);
        $notify = new Notification();
        $success = 0;
        $fail = 0;
        foreach ($channels as $channel) {
            if ($channel === 'myself') {
                $siteCode = System::getInstance()->getSiteCode();
                if (startsWith($_POST['sender'], $siteCode)) {
                    $channel = $_POST['sender'];
                } else {
                    Logger::getInstance()->warning('因 $_POST["sender"] 欄位 '.$_POST['sender'].' 開頭並非 '.$siteCode.' 故略過處理送給自己之公告。');
                    continue;
                }
            }
            Logger::getInstance()->info('新增公告訊息至 '.$channel.' 頻道。');
            $result = $notify->addMessage($channel, array(
                'title' => $title,
                'content' => trim($_POST['content']),
                'priority' => intval($_POST['priority']),
                'expire_datetime' => $_POST['expire_datetime'] ?? '',
                'sender' => $_POST['sender'] ?? 'UNKNOWN',
                'from_ip' => $_POST['from_ip']
            ));
            Logger::getInstance()->info('新增公告訊息「'.$title.'」至 '.$channel.' 頻道。 ('.($result ? '成功' : '失敗').')');
            if ($result) {
                $success++;
            } else {
                $fail++;
            }
        }
        $message = "新增訊息成功 $success 筆，失敗 $fail 筆。($title)";
        $status_code = $fail === 0 ? STATUS_CODE::SUCCESS_NORMAL : STATUS_CODE::DEFAULT_FAIL;
        echoJSONResponse($message, $status_code);
        break;
    case "upd_notification":
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
