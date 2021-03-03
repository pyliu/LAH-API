<?php
require_once(dirname(dirname(__FILE__))."/include/init.php");
require_once(ROOT_DIR."/include/Cache.class.php");
require_once(ROOT_DIR."/include/System.class.php");
require_once(ROOT_DIR."/include/StatsOracle.class.php");
require_once(ROOT_DIR."/include/StatsSQLite3.class.php");

$stats = new StatsOracle();
$stats_sqlite3 = new StatsSQLite3();
$cache = Cache::getInstance();
$system = System::getInstance();

$this_month = (date("Y") - 1911)."".date("m");

$mock = $system->isMockMode();

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
    case "stats_refresh_month":
        $result = $stats_sqlite3->removeAllStatsRawData($_POST['date']);
        if ($result === false) {
            echoJSONResponse("刪除 ".$_POST['date']." 快取資料失敗");
        } else {
            echoJSONResponse("成功刪除 ".$_POST['date']." 快取資料。", STATUS_CODE::SUCCESS_NORMAL, array(
                "data_count" => 1,
                "raw" => $result
            ));
        }
        break;
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
    case "stats_set_conn_count":
        if ($system->isKeyValid($_POST['api_key'])) {
            // combine&clean data ... 
            $processed = array();
            foreach ($_POST['records'] as $record) {
                // record string is like 2,192.168.88.40
                $pair = explode(',',  $record);
                $count = $pair[0];
                $est_ip = $pair[1];
                if (empty($est_ip)) {
                    $log->warning("IP為空值，將略過此筆紀錄。($est_ip, $count)");
                    continue;
                }
                if (array_key_exists($est_ip, $processed)) {
                    $processed[$est_ip] += $count;
                } else {
                    $processed[$est_ip] = $count;
                }
            }
            $clean_count = count($processed);
            $success = $stats_sqlite3->addAPConnHistory($_POST['log_time'], $_POST['ap_ip'], $processed);
            if ($success != $clean_count) {
                $log->error("XHR [stats_set_conn_count] 設定AP歷史連線資料失敗。[成功：${success}，全部：${clean_count}]");
            }
        } else {
            $log->error("XHR [stats_set_conn_count] Wrong API key to set AP connections. [expect: ".$system->get('API_KEY')." get ".$_POST["api_key"]."]");
        }
        break;
    case "stats_latest_ap_conn":
        if (!empty($_POST["ap_ip"]) && $arr = $stats_sqlite3->getLatestAPConnHistory($_POST["ap_ip"], $_POST["all"])) {
            $count = count($arr);
            echoJSONResponse("取得 $count 筆資料。", STATUS_CODE::SUCCESS_NORMAL, array(
                "data_count" => $count,
                "raw" => $arr
            ));
        } else {
            $error = "取得最新AP [".$_POST["ap_ip"]."] 連線數紀錄失敗。";
            $log->error("XHR [stats_latest_ap_conn] ${error}");
            echoJSONResponse($error);
        }
        break;
    case "stats_ap_conn_history":
        if ($arr = $stats_sqlite3->getAPConnHistory($_POST["ap_ip"], $_POST["count"])) {
            $count = count($arr);
            echoJSONResponse("取得 $count 筆資料。", STATUS_CODE::SUCCESS_NORMAL, array(
                "data_count" => $count,
                "raw" => $arr
            ));
        } else {
            $error = "取得跨所AP ".$_POST["ap_ip"]." 連線歷史紀錄失敗。";
            $log->error("XHR [stats_ap_conn_history] ${error}");
            echoJSONResponse($error);
        }
        break;
    case "stats_connectivity_target":
        if ($arr = $stats_sqlite3->getCheckingTargets()) {
            $count = count($arr);
            echoJSONResponse("取得 $count 筆資料。", STATUS_CODE::SUCCESS_NORMAL, array(
                "data_count" => $count,
                "raw" => $arr
            ));
        } else {
            $error = "取得連結狀態目標列表失敗。";
            $log->error("XHR [stats_connectivity_target] ${error}");
            echoJSONResponse($error);
        }
        break;
    case "stats_connectivity_history":
        if ($arr = $stats_sqlite3->getConnectivityStatus($_POST["force"])) {
            $count = count($arr);
            echoJSONResponse("取得 $count 筆資料。", STATUS_CODE::SUCCESS_NORMAL, array(
                "data_count" => $count,
                "raw" => $arr
            ));
        } else {
            $error = "取得連結狀態紀錄失敗。";
            $log->error("XHR [stats_connectivity_history] ${error}");
            echoJSONResponse($error);
        }
        break;
    case "stats_ip_connectivity_history":
        if ($arr = $stats_sqlite3->getIPConnectivityStatus($_POST["ip"], $_POST["force"], $_POST['port'])) {
            echoJSONResponse("取得 1 筆資料。", STATUS_CODE::SUCCESS_NORMAL, array(
                "data_count" => 1,
                "raw" => $arr
            ));
        } else {
            $error = "取得 ".$_POST["ip"]." 連結狀態紀錄失敗。";
            $log->error("XHR [stats_connectivity_history] ${error}");
            echoJSONResponse($error);
        }
        break;
	default:
		$log->error("不支援的查詢型態【".$_POST["type"]."】");
		echoJSONResponse("不支援的查詢型態【".$_POST["type"]."】", STATUS_CODE::UNSUPPORT_FAIL);
		break;
}
