<?php
require_once(dirname(dirname(__FILE__)).DIRECTORY_SEPARATOR."include".DIRECTORY_SEPARATOR."init.php");
require_once(INC_DIR.DIRECTORY_SEPARATOR."System.class.php");
require_once(INC_DIR.DIRECTORY_SEPARATOR."IPResolver.class.php");
require_once(INC_DIR.DIRECTORY_SEPARATOR."SQLiteUser.class.php");
require_once(INC_DIR.DIRECTORY_SEPARATOR."Ping.class.php");

$system = System::getInstance();
$ipr = new IPResolver();

switch ($_POST["type"]) {
    case "add_user_ip_entry":
        $data = array(
            'ip' => $_POST['ip'],
            'added_type' => $_POST['added_type'] ?? 'DYNAMIC',
            'entry_type' => $_POST['entry_type'] ?? 'USER',
            'entry_desc' => $_POST['entry_desc'] ?? '',
            'entry_id' => $_POST['entry_id'] ?? '',
            'timestamp' => time(),
            'note' => $_POST['note'] ?? ''
        );
        Logger::getInstance()->info('更新使用者回報IP資料 '.$data['ip'].' '.$data['entry_id'].' '.$data['entry_desc']);
        $result = $ipr->addIpEntry($data);

        if ($result && $data['entry_type'] === 'USER' && startsWith($data['entry_id'], $system->getSiteCode())) {
            // also update to user table in dimension.db
            $user = new SQLiteUser();
            if (!$user->autoImportUser($data)) {
                Logger::getInstance()->warning('自動匯入使用者資訊失敗。');
            }
        }

        $message = $result ? '完成 '.$data['ip'].' ('.$data['added_type'].', '.$data['entry_type'].') 更新' : '更新 '.$data['ip'].' 資料失敗';
        Logger::getInstance()->info($message);
		$status_code = $result ? STATUS_CODE::SUCCESS_NORMAL : STATUS_CODE::DEFAULT_FAIL;
        echoJSONResponse($message, $status_code);
        break;
    case "add_static_ip_entry":
        $siteCode = System::getInstance()->getSiteCode();
        $data = array(
            'ip' => $_POST['ip'],
            'added_type' => $_POST['added_type'] ?? 'STATIC',
            'entry_type' => $_POST['entry_type'] ?? 'OTHER_EP',
            'entry_desc' => $_POST['entry_desc'] ?? '',
            'entry_id' => $siteCode.'_'.$_POST['ip'],
            'timestamp' => time(),
            'note' => $_POST['note'] ?? ''
        );
        $result = $ipr->addIpEntry($data);
        $message = $result ? '完成 '.$data['ip'].' ('.$data['added_type'].', '.$data['entry_type'].') 更新' : '更新 '.$data['ip'].' 資料失敗';
        $status_code = $result ? STATUS_CODE::SUCCESS_NORMAL : STATUS_CODE::DEFAULT_FAIL;
        echoJSONResponse($message, $status_code);
        break;
    case "edit_static_ip_entry":
        $siteCode = System::getInstance()->getSiteCode();
        $data = array(
            'ip' => $_POST['ip'],
            'added_type' => $_POST['added_type'] ?? 'STATIC',
            'entry_type' => $_POST['entry_type'] ?? 'OTHER_EP',
            'entry_desc' => $_POST['entry_desc'] ?? '',
            'entry_id' => $siteCode.'_'.$_POST['ip'],
            'timestamp' => time(),
            'note' => $_POST['note'] ?? '',
            'orig_ip' => $_POST['orig_ip'],
            'orig_added_type' => $_POST['orig_added_type'],
            'orig_entry_type' => $_POST['orig_entry_type']
        );
        $result = $ipr->editIpEntry($data);
        $message = $result ? '完成 '.$data['ip'].' ('.$data['added_type'].', '.$data['entry_type'].') 更新' : '更新 '.$data['ip'].' 資料失敗';
        $status_code = $result ? STATUS_CODE::SUCCESS_NORMAL : STATUS_CODE::DEFAULT_FAIL;
        echoJSONResponse($message, $status_code);
        break;
    case "remove_ip_entry":
        $siteCode = System::getInstance()->getSiteCode();
        $data = array(
            'ip' => $_POST['ip'],
            'added_type' => $_POST['added_type'],
            'entry_type' => $_POST['entry_type'],
        );
        $result = $ipr->removeIpEntry($data);
        $message = $result ? '完成 '.$data['ip'].' ('.$data['added_type'].', '.$data['entry_type'].') 資料刪除' : '刪除 '.$data['ip'].' 資料失敗';
        $status_code = $result ? STATUS_CODE::SUCCESS_NORMAL : STATUS_CODE::DEFAULT_FAIL;
        echoJSONResponse($message, $status_code);
        break;
    case "ip_entries":
        // default get active IP entries within a year(unix timestamp is 31556926)
        $interval = intval($_POST['offset'] ?? 31556926);
        $days = ceil(abs($interval / 86400));
        $rows = $ipr->getIPEntries($interval);
        $count = count($rows);
        echoJSONResponse("查詢到 $count 筆資料( ${days} 天內)", STATUS_CODE::SUCCESS_NORMAL, array(
            'raw' => $rows,
            'data_count' => $count
        ));
        break;
    case "dynamic_ip_entries":
        // default get active IP entries within a week(unix timestamp is 604800)
        $interval = intval($_POST['offset'] ?? 604800);
        $days = ceil(abs($interval / 86400));
        $rows = $ipr->getDynamicIPEntries($interval);
        $count = count($rows);
        echoJSONResponse("查詢到 $count 筆動態 IP 資料( ${days} 天內)", STATUS_CODE::SUCCESS_NORMAL, array(
            'raw' => $rows,
            'data_count' => $count
        ));
        break;
    case "static_ip_entries":
        $rows = $ipr->getStaticIPEntries();
        $count = count($rows);
        echoJSONResponse("查詢到 $count 筆靜態 IP 資料", STATUS_CODE::SUCCESS_NORMAL, array(
            'raw' => $rows,
            'data_count' => $count
        ));
        break;
    case "ping":
        Logger::getInstance()->info("XHR [ping] Ping ".$_POST["ip"]." request.");
        $ip = $_POST["ip"];
        $ping = new Ping($ip, 1, 255);	// ip, timeout, ttl
        $latency = 0;
        if ($_POST['port']) {
            $ping->setPort($_POST['port']);
            $latency = $ping->ping('fsockopen');
        } else {
            $latency = $ping->ping();
        }
        $response_code = ($latency > 999 || $latency == '') ? STATUS_CODE::FAIL_TIMEOUT : STATUS_CODE::SUCCESS_NORMAL;
        $message = "$ip 回應時間".(($latency > 999 || $latency == '') ? "逾時" : "為 $latency ms");
        echo json_encode(array(
            "status" => $response_code,
            "ip" => $ip,
            "latency" => empty($latency) ? "0" : $latency,
            "data_count" => "1",
            "message" => $message
        ), 0);
        break;
    case "check_site_http":
        $webAP = $system->getWebAPIp();
        $url = "http://$webAP/Land".strtoupper($_POST['site'])."/";
        $headers = httpHeader($url);
        // if service available, HTTP response code will return 401
        $response401 = trim($headers[0]) === 'HTTP/1.1 401 Unauthorized';
        $response_code = $response401 ? STATUS_CODE::SUCCESS_NORMAL : STATUS_CODE::DEFAULT_FAIL;
        $message = $_POST['site'].($response401 ? '服務正常' : '服務異常');
        echo json_encode(array(
            "status" => $response_code,
            "site" => $_POST['site'],
            "raw" => $headers,
            "message" => $message
        ), 0);
        break;
    default:
        Logger::getInstance()->error("不支援的查詢型態【".$_POST["type"]."】");
        echoJSONResponse("不支援的查詢型態【".$_POST["type"]."】", STATUS_CODE::UNSUPPORT_FAIL);
        break;
}
