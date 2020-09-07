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
    global $stats_sqlite3, $mock, $cache, $stats, $this_month, $log;
    $key = $type.'_'.$date;
    
    // remove old record first for rest operation
    if (array_key_exists('reload', $_POST) && $_POST['reload'] == 'true') {
        $stats_sqlite3->removeStatsRawData($key);
    }
    
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
                    //$result = $stats->getRegCaseCount($date);
                    break;
                case "stats_reg_fix":
                    $result = $stats->getRegFixCount($date);
                    break;
                case "stats_reg_reject":
                    $result = $stats->getRegRejectCount($date);
                    break;
                case "stats_reg_all":
                    $result = $stats->getRegCaseCount($date);
                    break;
                case "stats_reg_remote":
                    $result = $stats->getRegRemoteCount($date);
                    break;
                case "stats_reg_subcase":
                    $result = $stats->getRegSubCaseCount($date);
                    break;
                case "stats_regf":
                    $result = $stats->getRegfCount($date);
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
    $log->info(__METHOD__.": ($type, $date) 取得 ".count($result)." 筆資料。");
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
    case "stats_reg_all":
        $log->info("XHR [stats_reg_all] 取得全部登記案件數(".$_POST['date'].")請求。");

        $err = "取得全部登記案件數資料失敗。 ".$_POST['date'];
        if (queryStats('stats_reg_all', $_POST['date'], $err)) {
            $log->info("XHR [stats_reg_all] 取得全部登記案件數量(".$_POST['date'].")成功。");
        } else {
            $log->info("XHR [stats_reg_all] ${err}。");
        }

        break;
    case "stats_reg_remote":
        $log->info("XHR [stats_reg_remote] 取得遠途先審案件數(".$_POST['date'].")請求。");

        $err = "取得遠途先審案件數資料失敗。 ".$_POST['date'];
        if (queryStats('stats_reg_remote', $_POST['date'], $err)) {
            $log->info("XHR [stats_reg_remote] 取得遠途先審案件數量(".$_POST['date'].")成功。");
        } else {
            $log->info("XHR [stats_reg_remote] ${err}。");
        }

        break;
    case "stats_reg_subcase":
        $log->info("XHR [stats_reg_subcase] 取得本所處理跨所子號案件數(".$_POST['date'].")請求。");

        $err = "取得本所處理跨所子號案件數資料失敗。 ".$_POST['date'];
        if (queryStats('stats_reg_subcase', $_POST['date'], $err)) {
            $log->info("XHR [stats_reg_subcase] 取得本所處理跨所子號案件數量(".$_POST['date'].")成功。");
        } else {
            $log->info("XHR [stats_reg_subcase] ${err}。");
        }

        break;
    case "stats_regf":
        $log->info("XHR [stats_regf] 取得外國人地權登記統計檔(".$_POST['date'].")請求。");

        $err = "取得外國人地權登記統計檔資料失敗。 ".$_POST['date'];
        if (queryStats('stats_regf', $_POST['date'], $err)) {
            $log->info("XHR [stats_regf] 取得外國人地權登記統計檔(".$_POST['date'].")成功。");
        } else {
            $log->info("XHR [stats_regf] ${err}。");
        }
        break;
    case "stats_set_ap_conn":
        //$log->info("XHR [stats_set_ap_conn] 設定AP連線數統計(".$_POST['log_time'].", ".$_POST['ip'].", records: ".count($_POST['sites']).")請求。");
        for ($i = 0; $i < count($_POST['sites']); $i++) {
            if ($stats_sqlite3->addAPConnection($_POST['log_time'], $_POST['ip'], $_POST['sites'][$i], $_POST['counts'][$i])) {
                //$log->info("XHR [stats_set_ap_conn] 設定 [".$_POST['sites'][$i].",".$_POST['counts'][$i]."] 統計完成。");
            } else {
                $log->error("XHR [stats_set_ap_conn] 設定 [".$_POST['sites'][$i].",".$_POST['counts'][$i]."] 統計失敗。");
            }
        }
        $stats_sqlite3->wipeAPConnection();
        break;
    case "stats_xap_conn_latest":
        $count = $_POST['count'] ?? 11;
        $log->info("XHR [stats_xap_conn_latest] 取得最新AP連線紀錄(".$count.")請求。");
        if ($arr = $stats_sqlite3->getLastestAPConnection($count)) {
            echoJSONResponse("取得 ".count($arr)." 筆資料。", STATUS_CODE::SUCCESS_NORMAL, array(
                "data_count" => count($arr),
                "raw" => $arr
            ));
        } else {
            $log->error("XHR [stats_xap_conn_latest] 取得最新AP連線紀錄失敗。");
        }
        break;
    case "stats_ap_conn_HX_history":
        $log->info("XHR [stats_ap_conn_HX_history] 取得跨所AP ".$_POST["site"]." 連線歷史紀錄請求。(筆數".$_POST["count"].")");
        if ($arr = $stats_sqlite3->getAPConnectionHXHistory($_POST["site"], $_POST["count"])) {
            $count = count($arr);
            $log->info("XHR [stats_ap_conn_HX_history] 取得 $count 筆資料。");
            echoJSONResponse("取得 $count 筆資料。", STATUS_CODE::SUCCESS_NORMAL, array(
                "data_count" => $count,
                "raw" => $arr
            ));
        } else {
            $log->error("XHR [stats_ap_conn_HX_history] 取得跨所AP ".$_POST["site"]." 連線歷史紀錄失敗。");
        }
        break;
	default:
		$log->error("不支援的查詢型態【".$_POST["type"]."】");
		echoJSONResponse("不支援的查詢型態【".$_POST["type"]."】", STATUS_CODE::UNSUPPORT_FAIL);
		break;
}
?>
