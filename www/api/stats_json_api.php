<?php
require_once(dirname(dirname(__FILE__))."/include/init.php");
require_once(ROOT_DIR."/include/Cache.class.php");
require_once(ROOT_DIR."/include/System.class.php");
require_once(ROOT_DIR."/include/StatsOracle.class.php");
require_once(ROOT_DIR."/include/StatsSQLite.class.php");
require_once(ROOT_DIR."/include/SQLiteConnectivity.class.php");
require_once(ROOT_DIR."/include/RegCaseData.class.php");
require_once(ROOT_DIR."/include/SQLiteOFFICESSTATS.class.php");
require_once(ROOT_DIR."/include/Scheduler.class.php");

$stats = new StatsOracle();
$stats_sqlite3 = new StatsSQLite();
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
    Logger::getInstance()->info(__METHOD__.": ($type, $date) 取得 ".count($result)." 筆資料。");
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
        Logger::getInstance()->info("XHR [stats_reg_reject] 取得駁回案件數量(".$_POST['date'].")請求。");
        
        $err = "取得駁回案件數量資料失敗。 ".$_POST['date'];
        if (queryStats('stats_reg_reject', $_POST['date'], $err)) {
            Logger::getInstance()->info("XHR [stats_reg_reject] 取得駁回案件數量(".$_POST['date'].")成功。");
        } else {
            Logger::getInstance()->info("XHR [stats_reg_reject] ${err}。");
        }

        break;
    case "stats_reg_fix":
        Logger::getInstance()->info("XHR [stats_reg_fix] 取得補正案件數量(".$_POST['date'].")請求。");
        
        $err = "取得補正案件數量資料失敗。 ".$_POST['date'];
        if (queryStats('stats_reg_fix', $_POST['date'], $err)) {
            Logger::getInstance()->info("XHR [stats_reg_fix] 取得補正案件數量(".$_POST['date'].")成功。");
        } else {
            Logger::getInstance()->info("XHR [stats_reg_fix] ${err}。");
        }

        break;
    case "stats_refund":
        Logger::getInstance()->info("XHR [stats_refund] 取得溢繳規費數量(".$_POST['date'].")請求。");
        
        $err = "取得溢繳規費數量資料失敗。 ".$_POST['date'];
        if (queryStats('stats_refund', $_POST['date'], $err)) {
            Logger::getInstance()->info("XHR [stats_refund] 取得溢繳規費數量(".$_POST['date'].")成功。");
        } else {
            Logger::getInstance()->info("XHR [stats_refund] ${err}。");
        }

        break;
    case "stats_court":
        Logger::getInstance()->info("XHR [stats_court] 取得法院囑託案件【登記原因為查封(33)、塗銷查封(34)】(".$_POST['date'].")請求。");
        
        $err = "取得法院囑託案件【登記原因為查封(33)、塗銷查封(34)】數量資料失敗。 ".$_POST['date'];
        if (queryStats('stats_court', $_POST['date'], $err)) {
            Logger::getInstance()->info("XHR [stats_court] 取得法院囑託案件【登記原因為查封(33)、塗銷查封(34)】(".$_POST['date'].")成功。");
        } else {
            Logger::getInstance()->info("XHR [stats_court] ${err}。");
        }

        break;
    case "stats_sur_rain":
        Logger::getInstance()->info("XHR [stats_sur_rain] 取得因雨延期測量案件數(".$_POST['date'].")請求。");
        
        $err = "取得因雨延期測量案件數資料失敗。 ".$_POST['date'];
        if (queryStats('stats_sur_rain', $_POST['date'], $err)) {
            Logger::getInstance()->info("XHR [stats_sur_rain] 取得因雨延期測量案件數量(".$_POST['date'].")成功。");
        } else {
            Logger::getInstance()->info("XHR [stats_sur_rain] ${err}。");
        }

        break;
    case "stats_reg_reason":
        Logger::getInstance()->info("XHR [stats_reg_reason] 取得登記原因案件數(".$_POST['date'].")請求。");

        $err = "取得登記原因案件數資料失敗。 ".$_POST['date'];
        if (queryStats('stats_reg_reason', $_POST['date'], $err)) {
            Logger::getInstance()->info("XHR [stats_reg_reason] 取得登記原因案件數量(".$_POST['date'].")成功。");
        } else {
            Logger::getInstance()->info("XHR [stats_reg_reason] ${err}。");
        }

        break;
    case "stats_reg_all":
        Logger::getInstance()->info("XHR [stats_reg_all] 取得全部登記案件數(".$_POST['date'].")請求。");

        $err = "取得全部登記案件數資料失敗。 ".$_POST['date'];
        if (queryStats('stats_reg_all', $_POST['date'], $err)) {
            Logger::getInstance()->info("XHR [stats_reg_all] 取得全部登記案件數量(".$_POST['date'].")成功。");
        } else {
            Logger::getInstance()->info("XHR [stats_reg_all] ${err}。");
        }

        break;
    case "stats_reg_remote":
        Logger::getInstance()->info("XHR [stats_reg_remote] 取得遠途先審案件數(".$_POST['date'].")請求。");

        $err = "取得遠途先審案件數資料失敗。 ".$_POST['date'];
        if (queryStats('stats_reg_remote', $_POST['date'], $err)) {
            Logger::getInstance()->info("XHR [stats_reg_remote] 取得遠途先審案件數量(".$_POST['date'].")成功。");
        } else {
            Logger::getInstance()->info("XHR [stats_reg_remote] ${err}。");
        }

        break;
    case "stats_reg_subcase":
        Logger::getInstance()->info("XHR [stats_reg_subcase] 取得本所處理跨所子號案件數(".$_POST['date'].")請求。");

        $err = "取得本所處理跨所子號案件數資料失敗。 ".$_POST['date'];
        if (queryStats('stats_reg_subcase', $_POST['date'], $err)) {
            Logger::getInstance()->info("XHR [stats_reg_subcase] 取得本所處理跨所子號案件數量(".$_POST['date'].")成功。");
        } else {
            Logger::getInstance()->info("XHR [stats_reg_subcase] ${err}。");
        }

        break;
    case "stats_regf":
        Logger::getInstance()->info("XHR [stats_regf] 取得外國人地權登記統計檔(".$_POST['date'].")請求。");

        $err = "取得外國人地權登記統計檔資料失敗。 ".$_POST['date'];
        if (queryStats('stats_regf', $_POST['date'], $err)) {
            Logger::getInstance()->info("XHR [stats_regf] 取得外國人地權登記統計檔(".$_POST['date'].")成功。");
        } else {
            Logger::getInstance()->info("XHR [stats_regf] ${err}。");
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
                    Logger::getInstance()->warning("IP為空值，將略過此筆紀錄。($est_ip, $count)");
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
                Logger::getInstance()->error("XHR [stats_set_conn_count] 設定AP歷史連線資料失敗。[成功：${success}，全部：${clean_count}]");
            }
        } else {
            Logger::getInstance()->error("XHR [stats_set_conn_count] Wrong API key to set AP connections. [expect: ".$system->get('API_KEY')." get ".$_POST["api_key"]."]");
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
            Logger::getInstance()->error("XHR [stats_latest_ap_conn] ${error}");
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
            Logger::getInstance()->error("XHR [stats_ap_conn_history] ${error}");
            echoJSONResponse($error);
        }
        break;
    case "stats_reg_period_count":
        $arr = $mock ? $cache->get('stats_reg_period_count') : $stats->getRegPeriodCount($_POST["st"], $_POST["ed"]);
		$cache->set('stats_reg_period_count', $arr);
        if ($arr) {
            $count = count($arr);
            echoJSONResponse("取得 $count 筆資料。", STATUS_CODE::SUCCESS_NORMAL, array(
                "raw" => $arr
            ));
        } else {
            $error = "取得登記案件區間統計數 ".$_POST["st"]." ~ ".$_POST["ed"]." 失敗。";
            Logger::getInstance()->error("XHR [stats_reg_period_count] $error");
            echoJSONResponse($error);
        }
        break;
    case "stats_sur_period_count":
        $arr = $mock ? $cache->get('stats_sur_period_count') : $stats->getSurPeriodCount($_POST["st"], $_POST["ed"]);
		$cache->set('stats_sur_period_count', $arr);
        if ($arr) {
            $count = count($arr);
            echoJSONResponse("取得 $count 筆資料。", STATUS_CODE::SUCCESS_NORMAL, array(
                "raw" => $arr
            ));
        } else {
            $error = "取得測量案件區間統計數 ".$_POST["st"]." ~ ".$_POST["ed"]." 失敗。";
            Logger::getInstance()->error("XHR [stats_sur_period_count] $error");
            echoJSONResponse($error);
        }
        break;
    case "stats_reg_first_count":
        $arr = $mock ? $cache->get('stats_reg_first_count') : $stats->getRegFirstCount($_POST["st"], $_POST["ed"]);
		$cache->set('stats_reg_first_count', $arr);
        if (is_array($arr)) {
            $count = count($arr);
            $baked = array();
            foreach ($arr as $row) {
                $data = new RegCaseData($row);
                $baked[] = $data->getBakedData();
            }
            echoJSONResponse("取得 $count 筆資料。", STATUS_CODE::SUCCESS_NORMAL, array(
                "raw" => $baked
            ));
        } else {
            $error = "取得第一次登記案件 ".$_POST["st"]." ~ ".$_POST["ed"]." 失敗。";
            Logger::getInstance()->error("XHR [stats_reg_first_count] $error");
            echoJSONResponse($error);
        }
        break;
    case "stats_reg_first_sub_count":
        $arr = $mock ? $cache->get('stats_reg_first_sub_count') : $stats->getRegFirstSubCount($_POST["st"], $_POST["ed"]);
		$cache->set('stats_reg_first_sub_count', $arr);
        if (is_array($arr)) {
            $count = count($arr);
            $baked = array();
            foreach ($arr as $row) {
                $data = new RegCaseData($row);
                $baked[] = $data->getBakedData();
            }
            echoJSONResponse("取得 $count 筆資料。", STATUS_CODE::SUCCESS_NORMAL, array(
                "raw" => $baked
            ));
        } else {
            $error = "取得第一次登記案件 ".$_POST["st"]." ~ ".$_POST["ed"]." 失敗。";
            Logger::getInstance()->error("XHR [stats_reg_first_sub_count] $error");
            echoJSONResponse($error);
        }
        break;
    case "stats_reg_rm02_count":
        $arr = $mock ? $cache->get('stats_reg_rm02_count') : $stats->getRegRM02Count($_POST["rm02"], $_POST["st"], $_POST["ed"]);
		$cache->set('stats_reg_rm02_count', $arr);
        if (is_array($arr)) {
            $count = count($arr);
            $baked = array();
            foreach ($arr as $row) {
                $data = new RegCaseData($row);
                $baked[] = $data->getBakedData();
            }
            echoJSONResponse("取得 $count 筆資料。", STATUS_CODE::SUCCESS_NORMAL, array(
                "raw" => $baked
            ));
        } else {
            $error = "取得".$_POST["rm02"]."案件 ".$_POST["st"]." ~ ".$_POST["ed"]." 失敗。";
            Logger::getInstance()->error("XHR [stats_reg_rm02_count] $error");
            echoJSONResponse($error);
        }
        break;
    case "stats_reg_rm02_sub_count":
        $arr = $mock ? $cache->get('stats_reg_rm02_sub_count') : $stats->getRegRM02SubCount($_POST["rm02"], $_POST["st"], $_POST["ed"]);
		$cache->set('stats_reg_rm02_sub_count', $arr);
        if (is_array($arr)) {
            $count = count($arr);
            $baked = array();
            foreach ($arr as $row) {
                $data = new RegCaseData($row);
                $baked[] = $data->getBakedData();
            }
            echoJSONResponse("取得 $count 筆資料。", STATUS_CODE::SUCCESS_NORMAL, array(
                "raw" => $baked
            ));
        } else {
            $error = "取得".$_POST["rm02"]."案件 ".$_POST["st"]." ~ ".$_POST["ed"]." 失敗。";
            Logger::getInstance()->error("XHR [stats_reg_rm02_sub_count] $error");
            echoJSONResponse($error);
        }
        break;
    case "stats_xap_stats":
        $arr = [];
        if ($mock) {
            $arr = $cache->get('stats_xap_stats');
        } else {
            // Logger::getInstance()->error("XHR [stats_xap_stats] force: ".$_POST['force']);
            if ($_POST['force'] === 'true') {
                $scheduler = new Scheduler();
                $scheduler->addOfficeCheckStatus();
            }
            $sqlite = new SQLiteOFFICESSTATS();
            $arr = $sqlite->getLatestBatch();
        }
        $cache->set('stats_xap_stats', $arr);
        if (is_array($arr)) {
            $count = count($arr);
            echoJSONResponse("取得 ".count($arr)." 筆資料。", STATUS_CODE::SUCCESS_NORMAL, array(
                "raw" => $arr
            ));
        } else {
            $error = "取得最近各地所連線狀態失敗。";
            Logger::getInstance()->error("XHR [stats_xap_stats] $error");
            echoJSONResponse($error);
        }
        break;
    case "stats_xap_stats_cached":
        $arr = [];
        if ($mock) {
            $arr = $cache->get('stats_xap_stats_cached');
        } else {
            $sqlite = new SQLiteOFFICESSTATS();
            $arr = $sqlite->getLatestBatch();
        }
        $cache->set('stats_xap_stats_cached', $arr);
        if (is_array($arr)) {
            $count = count($arr);
            echoJSONResponse("取得 ".count($arr)." 筆資料。", STATUS_CODE::SUCCESS_NORMAL, array(
                "raw" => $arr
            ));
        } else {
            $error = "取得已快取的最新各地所連線狀態失敗。";
            Logger::getInstance()->error("XHR [stats_xap_stats_cached] $error");
            echoJSONResponse($error);
        }
        break;
    case "stats_xap_stats_down":
        $arr = [];
        if ($mock) {
            $arr = $cache->get('stats_xap_stats_down');
        } else {
            $sqlite = new SQLiteOFFICESSTATS();
            $current_datetime = new DateTime();
            $day_seconds =  24 * 60 * 60;
            if ($_POST['opt'] == 'day') {
                $one_day_ago_datetime = new DateTime("-1 days");
                $offset = $current_datetime->diff($one_day_ago_datetime)->days * $day_seconds;
                $arr = $sqlite->getRecentDownRecordsByTimestamp($offset);
            } else if ($_POST['opt'] == 'week') {
                $one_week_ago_datetime = new DateTime("-1 weeks");
                $offset = $current_datetime->diff($one_week_ago_datetime)->days * $day_seconds;
                $arr = $sqlite->getRecentDownRecordsByTimestamp($offset);
            } else if ($_POST['opt'] == 'month') {
                $one_month_ago_datetime = new DateTime("-1 months");
                $offset = $current_datetime->diff($one_month_ago_datetime)->days * $day_seconds;
                $arr = $sqlite->getRecentDownRecordsByTimestamp($offset);
            } else if ($_POST['opt'] == 'year') {
                $one_years_ago_datetime = new DateTime("-1 years");
                $offset = $current_datetime->diff($one_years_ago_datetime)->days * $day_seconds;
                $arr = $sqlite->getRecentDownRecordsByTimestamp($offset);
            } else {
                $arr = $sqlite->getRecentDownRecords($_POST['count'] ?? 10);
            }
        }
        $cache->set('stats_xap_stats_down', $arr);
        if (is_array($arr)) {
            $count = count($arr);
            echoJSONResponse("取得 ".count($arr)." 筆資料。", STATUS_CODE::SUCCESS_NORMAL, array(
                "raw" => $arr
            ));
        } else {
            $error = "取得各地所失效紀錄失敗。";
            Logger::getInstance()->error("XHR [stats_xap_stats_down] $error");
            echoJSONResponse($error);
        }
        break;
	default:
		Logger::getInstance()->error("不支援的查詢型態【".$_POST["type"]."】");
		echoJSONResponse("不支援的查詢型態【".$_POST["type"]."】", STATUS_CODE::UNSUPPORT_FAIL);
		break;
}
