<?php
require_once(dirname(dirname(__FILE__))."/include/init.php");
require_once(ROOT_DIR."/include/Cache.class.php");
require_once(ROOT_DIR."/include/StatsOracle.class.php");
require_once(ROOT_DIR."/include/StatsSQLite3.class.php");

$stats = new StatsOracle();
$stats_sqlite3 = new StatsSQLite3();
$cache = new Cache();

$this_month = (date("Y") - 1911)."".date("m");

$mock = SYSTEM_CONFIG["MOCK_MODE"];
if ($mock) $log->warning("現在處於模擬模式(mock mode)，STATS API僅會回應之前已被快取之最新的資料！");

function queryStats($type, $date, $error_msg) {
    global $stats_sqlite3, $mock, $cache, $stats, $this_month;
    $key = $type.'_'.$date;
    $result = $stats_sqlite3->getStatsRawData($key);
    if ($this_month == $date || empty($result)) {
        $result = $cache->get($type);
        if (!$mock) {
            switch ($type) {
                case "stats_refund":
                    $result = $stats->getRefundCount($date);
                    break;
                case "stats_sur_rain":
                    $result = $stats->getSurRainCount($date);
                    break;
                case "stats_court":
                    $result = $stats->getCourtCaseCount($date);
                    break;
                case "stats_reg_reason":
                    $result = $stats->getRegReasonCount($date);
                    break;
                case "stats_reg_fix":
                    $result = $stats->getRegFixCount($date);
                    break;
                case "stats_reg_reject":
                    $result = $stats->getRegRejectCount($date);
                    break;
            }
        }
        $cache->set($type, $result);
        if ($result === false) {
            echoJSONResponse($error_msg);
            return false;
        } else {
            if ($this_month != $date) {
                $stats_sqlite3->addStatsRawData($key, $result);
            }
        }
    }
    echoJSONResponse("取得 ".count($result)." 筆資料。", STATUS_CODE::SUCCESS_NORMAL, array(
        "data_count" => count($result),
        "raw" => $result,
        "text" => $result[0]['text'],
        "count" => $result[0]['count'] ?? 0
    ));
    return true;
}

switch ($_POST["type"]) {
    case "stats_reg_reject":
        $log->info("XHR [stats_reg_reject] 取得駁回案件數量(".$_POST['date'].")請求。");
        
        $err = "取得駁回案件數量資料失敗。 ".$_POST['date'];
        if (queryStats('stats_reg_reject', $_POST['date'], $err)) {
            $log->info("XHR [stats_reg_reject] 取得駁回案件數量(".$_POST['date'].")成功。");
        } else {
            $log->info("XHR [stats_reg_reject] ${err}。");
        }

        break;
    case "stats_reg_fix":
        $log->info("XHR [stats_reg_fix] 取得補正案件數量(".$_POST['date'].")請求。");
        
        $err = "取得補正案件數量資料失敗。 ".$_POST['date'];
        if (queryStats('stats_reg_fix', $_POST['date'], $err)) {
            $log->info("XHR [stats_reg_fix] 取得補正案件數量(".$_POST['date'].")成功。");
        } else {
            $log->info("XHR [stats_reg_fix] ${err}。");
        }

        break;
    case "stats_refund":
        $log->info("XHR [stats_refund] 取得溢繳規費數量(".$_POST['date'].")請求。");
        
        $err = "取得溢繳規費數量資料失敗。 ".$_POST['date'];
        if (queryStats('stats_refund', $_POST['date'], $err)) {
            $log->info("XHR [stats_refund] 取得溢繳規費數量(".$_POST['date'].")成功。");
        } else {
            $log->info("XHR [stats_refund] ${err}。");
        }

        break;
    case "stats_court":
        $log->info("XHR [stats_court] 取得法院囑託案件【登記原因為查封(33)、塗銷查封(34)】(".$_POST['date'].")請求。");
        
        $err = "取得法院囑託案件【登記原因為查封(33)、塗銷查封(34)】數量資料失敗。 ".$_POST['date'];
        if (queryStats('stats_court', $_POST['date'], $err)) {
            $log->info("XHR [stats_court] 取得法院囑託案件【登記原因為查封(33)、塗銷查封(34)】(".$_POST['date'].")成功。");
        } else {
            $log->info("XHR [stats_court] ${err}。");
        }

        break;
    case "stats_sur_rain":
        $log->info("XHR [stats_sur_rain] 取得因雨延期測量案件數(".$_POST['date'].")請求。");
        
        $err = "取得因雨延期測量案件數資料失敗。 ".$_POST['date'];
        if (queryStats('stats_sur_rain', $_POST['date'], $err)) {
            $log->info("XHR [stats_sur_rain] 取得因雨延期測量案件數量(".$_POST['date'].")成功。");
        } else {
            $log->info("XHR [stats_sur_rain] ${err}。");
        }

        break;
    case "stats_reg_reason":
        $log->info("XHR [stats_reg_reason] 取得登記原因案件數(".$_POST['date'].")請求。");

        $err = "取得登記原因案件數資料失敗。 ".$_POST['date'];
        if (queryStats('stats_reg_reason', $_POST['date'], $err)) {
            $log->info("XHR [stats_reg_reason] 取得登記原因案件數量(".$_POST['date'].")成功。");
        } else {
            $log->info("XHR [stats_reg_reason] ${err}。");
        }

        break;
	default:
		$log->error("不支援的查詢型態【".$_POST["type"]."】");
		echoJSONResponse("不支援的查詢型態【".$_POST["type"]."】", STATUS_CODE::UNSUPPORT_FAIL);
		break;
}
?>
