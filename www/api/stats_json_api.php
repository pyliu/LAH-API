<?php
require_once(dirname(dirname(__FILE__))."/include/init.php");
require_once(ROOT_DIR."/include/Cache.class.php");
require_once(ROOT_DIR."/include/StatsOracle.class.php");

$stats = new StatsOracle();
$cache = new Cache();

$mock = SYSTEM_CONFIG["MOCK_MODE"];
if ($mock) $log->warning("現在處於模擬模式(mock mode)，STATS API僅會回應之前已被快取之最新的資料！");

switch ($_POST["type"]) {
    case "stats_refund":
		$log->info("XHR [stats_refund] 取得溢繳規費數量(".$_POST['date'].")請求。");
		$result = $stats->getRefundCount($_POST['date']);
		$result = $mock ? $cache->get('stats_refund') : $stats->getRefundCount($_POST['date']);
        $cache->set('stats_refund', $result);
        echoJSONResponse("取得 ".count($result)." 筆資料。", STATUS_CODE::SUCCESS_NORMAL, array(
			"data_count" => count($result),
            "raw" => $result,
            "text" => "溢繳規費數量",
            "count" => $result[0]['count']
		));
		break;
	default:
		$log->error("不支援的查詢型態【".$_POST["type"]."】");
		echoJSONResponse("不支援的查詢型態【".$_POST["type"]."】", STATUS_CODE::UNSUPPORT_FAIL);
		break;
}
?>
