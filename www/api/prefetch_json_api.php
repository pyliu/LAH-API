<?php
require_once(dirname(dirname(__FILE__))."/include/init.php");
require_once(INC_DIR."/System.class.php");
require_once(INC_DIR."/Prefetch.class.php");
require_once(INC_DIR."/RegCaseData.class.php");
require_once(INC_DIR."/SurCaseData.class.php");

$db_ip = System::getInstance()->getOraTargetDBIP();
$db_port = System::getInstance()->getOraTargetDBPort();
$latency = System::getInstance()->ping($db_ip, $db_port);
if ($latency > 999 || $latency == '') {
	$db_target = System::getInstance()->getOraConnectTarget();
	$msg = "API伺服器無法連線至DB，請確認網路可連線至 $db_target 資料庫. ($db_ip:$db_port)";
	$log->error($msg);
	echoJSONResponse($msg, STATUS_CODE::FAIL_REMOTE_UNREACHABLE);
} else {
	$prefetch = new Prefetch();
	switch ($_POST["type"]) {
		case "overdue_reg_cases":
			$log->info("XHR [overdue_reg_cases] 近15天逾期案件查詢請求");
			$rows = $_POST['reload'] === 'true' ? $prefetch->reloadOverdueCaseIn15Days() : $prefetch->getOverdueCaseIn15Days();
			if (empty($rows)) {
				$log->info("XHR [overdue_reg_cases] 近15天查無逾期資料");
				echoJSONResponse("15天內查無逾期資料", STATUS_CODE::SUCCESS_WITH_NO_RECORD, array(
					"items" => array(),
					"items_by_id" => array(),
					"data_count" => 0,
					"raw" => $rows,
					'cache_remaining_time' => $prefetch->getOverdueCaseCacheRemainingTime()
				));
			} else {
				$items = [];
				$items_by_id = [];
				foreach ($rows as $row) {
					$regdata = new RegCaseData($row);
					$this_item = array(
						"收件字號" => $regdata->getReceiveSerial(),
						"登記原因" => $regdata->getCaseReason(),
						"辦理情形" => $regdata->getStatus(),
						"收件時間" => $regdata->getReceiveDate()." ".$regdata->getReceiveTime(),
						"限辦期限" => $regdata->getDueDate(),
						"初審人員" => $regdata->getFirstReviewer() . " " . $regdata->getFirstReviewerID(),
						"作業人員" => $regdata->getCurrentOperator()
					);
					$items[] = $this_item;
					$items_by_id[$regdata->getFirstReviewerID()][] = $this_item;
				}
				$log->info("XHR [overdue_reg_cases] 近15天找到".count($items)."件逾期案件");
				echoJSONResponse("近15天找到".count($items)."件逾期案件", STATUS_CODE::SUCCESS_NORMAL, array(
					"items" => $items,
					"items_by_id" => $items_by_id,
					"data_count" => count($items),
					"raw" => $rows,
					'cache_remaining_time' => $prefetch->getOverdueCaseCacheRemainingTime()
				));
			}
			break;
		case "almost_overdue_reg_cases":
			$log->info("XHR [almost_overdue_reg_cases] 即將逾期案件查詢請求");
			$rows = $_POST['reload'] === 'true' ? $prefetch->reloadAlmostOverdueCase() : $prefetch->getAlmostOverdueCase();
			if (empty($rows)) {
				$log->info("XHR [almost_overdue_reg_cases] 近4小時內查無即將逾期資料");
				echoJSONResponse("近4小時內查無即將逾期資料", STATUS_CODE::SUCCESS_WITH_NO_RECORD, array(
					"items" => array(),
					"items_by_id" => array(),
					"data_count" => 0,
					"raw" => $rows,
					'cache_remaining_time' => $prefetch->getAlmostOverdueCaseCacheRemainingTime()
				));
			} else {
				$items = [];
				$items_by_id = [];
				foreach ($rows as $row) {
					$regdata = new RegCaseData($row);
					$this_item = array(
						"收件字號" => $regdata->getReceiveSerial(),
						"登記原因" => $regdata->getCaseReason(),
						"辦理情形" => $regdata->getStatus(),
						"收件時間" => $regdata->getReceiveDate()." ".$regdata->getReceiveTime(),
						"限辦期限" => $regdata->getDueDate(),
						"初審人員" => $regdata->getFirstReviewer() . " " . $regdata->getFirstReviewerID(),
						"作業人員" => $regdata->getCurrentOperator()
					);
					$items[] = $this_item;
					$items_by_id[$regdata->getFirstReviewerID()][] = $this_item;
				}
				$log->info("XHR [almost_overdue_reg_cases] 近4小時內找到".count($items)."件即將逾期案件");
				echoJSONResponse("近4小時內找到".count($items)."件即將逾期案件", STATUS_CODE::SUCCESS_NORMAL, array(
					"items" => $items,
					"items_by_id" => $items_by_id,
					"data_count" => count($items),
					"raw" => $rows,
					'cache_remaining_time' => $prefetch->getAlmostOverdueCaseCacheRemainingTime()
				));
			}
			break;
		case "reg_rm30_H_case":
			$log->info("XHR [reg_rm30_H_case] 查詢登記公告中案件請求");
			$rows = $_POST['reload'] === 'true' ? $prefetch->reloadRM30HCase() : $prefetch->getRM30HCase();
			if (empty($rows)) {
				$log->info("XHR [reg_rm30_H_case] 查無資料");
				echoJSONResponse('查無公告中案件');
			} else {
				$total = count($rows);
				$log->info("XHR [reg_rm30_H_case] 查詢成功($total)");
				$baked = array();
				foreach ($rows as $row) {
					$data = new RegCaseData($row);
					$baked[] = $data->getBakedData();
				}
				echoJSONResponse("查詢成功，找到 $total 筆公告中資料。", STATUS_CODE::SUCCESS_WITH_MULTIPLE_RECORDS, array(
					'data_count' => $total,
					'baked' => $baked,
					'cache_remaining_time' => $prefetch->getRM30HCaseCacheRemainingTime()
				));
			}
			break;
		case "reg_cancel_ask_case":
			$log->info("XHR [reg_cancel_ask_case] 查詢取消請示案件請求");
			$days = $_POST['days'] ?? 92;	// default is 3 months
			$rows = $_POST['reload'] === 'true' ? $prefetch->reloadAskCase($days) : $prefetch->getAskCase($days);
			if (empty($rows)) {
				$log->info("XHR [reg_cancel_ask_case] 查無資料");
				echoJSONResponse('查無取消請示案件');
			} else {
				$total = count($rows);
				$log->info("XHR [reg_cancel_ask_case] 查詢成功($total)");
				$baked = array();
				foreach ($rows as $row) {
					$data = new RegCaseData($row);
					$baked[] = $data->getBakedData();
				}
				echoJSONResponse("查詢成功，找到 $total 筆請示中資料。", STATUS_CODE::SUCCESS_WITH_MULTIPLE_RECORDS, array(
					"data_count" => $total,
					"baked" => $baked,
					'cache_remaining_time' => $prefetch->getAskCaseCacheRemainingTime()
				));
			}
			break;
		case "reg_trust_case":
			$log->info("XHR [reg_trust_case] 查詢取消請示案件請求");
			if ($_POST['query'] === 'E') {
				// 建物所有權部資料
				$rows = $_POST['reload'] === 'true' ? $prefetch->reloadTrustRebow($_POST['year']) : $prefetch->getTrustRebow($_POST['year']);
				$cache_remaining = $prefetch->getTrustRebowCacheRemainingTime($_POST['year']);
			} else if ($_POST['query'] === 'B') {
				// 土地所有權部資料
				$rows = $_POST['reload'] === 'true' ? $prefetch->reloadTrustRblow($_POST['year']) : $prefetch->getTrustRblow($_POST['year']);
				$cache_remaining = $prefetch->getTrustRblowCacheRemainingTime($_POST['year']);
			} else if ($_POST['query'] === 'TE') {
				// 建物所有權部例外資料
				$rows = $_POST['reload'] === 'true' ? $prefetch->reloadTrustRebowException($_POST['year']) : $prefetch->getTrustRebowException($_POST['year']);
				$cache_remaining = $prefetch->getTrustRebowExceptionCacheRemainingTime($_POST['year']);
			} else if ($_POST['query'] === 'TB') {
				// 土地所有權部例外資料
				$rows = $_POST['reload'] === 'true' ? $prefetch->reloadTrustRblowException($_POST['year']) : $prefetch->getTrustRblowException($_POST['year']);
				$cache_remaining = $prefetch->getTrustRblowExceptionCacheRemainingTime($_POST['year']);
			}
			if (empty($rows)) {
				$log->info("XHR [reg_trust_case] 查無資料");
				echoJSONResponse('查無信託註記資料');
			} else {
				$total = count($rows);
				$log->info("XHR [reg_trust_case] 查詢成功($total)");
				echoJSONResponse("查詢成功，找到 $total 筆信託註記資料。", STATUS_CODE::SUCCESS_WITH_MULTIPLE_RECORDS, array(
					"data_count" => $total,
					"raw" => $rows,
					'cache_remaining_time' => $cache_remaining
				));
			}
			break;
		case "reg_non_scrivener_case":
			$log->info("XHR [reg_non_scrivener_case] 查詢非專代案件請求");
			$st = $_POST['start_date'];
			$ed = $_POST['end_date'];
			$rows = $_POST['reload'] === 'true' ? $prefetch->reloadNonScrivenerCase($st, $ed) : $prefetch->getNonScrivenerCase($st, $ed);
			if (empty($rows)) {
				$log->info("XHR [reg_non_scrivener_case] 查無資料");
				echoJSONResponse('查無非專代案件');
			} else {
				$total = count($rows);
				$log->info("XHR [reg_non_scrivener_case] 查詢成功($total)");
				$baked = array();
				foreach ($rows as $row) {
					$data = new RegCaseData($row);
					$baked[] = $data->getBakedData();
				}
				echoJSONResponse("查詢成功，找到 $total 筆非專代案件。", STATUS_CODE::SUCCESS_WITH_MULTIPLE_RECORDS, array(
					"data_count" => $total,
					"baked" => $baked,
					'cache_remaining_time' => $prefetch->getNonScrivenerCaseCacheRemainingTime($st, $ed)
				));
			}
			break;
		case "reg_non_scrivener_reg_case":
			$log->info("XHR [reg_non_scrivener_reg_case] 查詢非專代案件請求");
			$st = $_POST['start_date'];
			$ed = $_POST['end_date'];
			$rows = $_POST['reload'] === 'true' ? $prefetch->reloadNonScrivenerRegCase($st, $ed, $_POST['ignore']) : $prefetch->getNonScrivenerRegCase($st, $ed, $_POST['ignore']);
			if (empty($rows)) {
				$log->info("XHR [reg_non_scrivener_reg_case] 查無資料");
				echoJSONResponse('查無非專代案件');
			} else {
				$total = count($rows);
				$baked = array();
				foreach ($rows as $row) {
					$data = new RegCaseData($row);
					$baked[] = $data->getBakedData();
				}
				$log->info("XHR [reg_non_scrivener_reg_case] 查詢成功($total)");
				echoJSONResponse("查詢成功，找到 $total 筆非專代案件。", STATUS_CODE::SUCCESS_WITH_MULTIPLE_RECORDS, array(
					"data_count" => $total,
					"baked" => $baked,
					'cache_remaining_time' => $prefetch->getNonScrivenerRegCaseCacheRemainingTime($st, $ed)
				));
			}
			break;
		case "reg_non_scrivener_sur_case":
			$log->info("XHR [reg_non_scrivener_sur_case] 查詢非專代測量案件請求");
			$st = $_POST['start_date'];
			$ed = $_POST['end_date'];
			$rows = $_POST['reload'] === 'true' ? $prefetch->reloadNonScrivenerSurCase($st, $ed, $_POST['ignore']) : $prefetch->getNonScrivenerSurCase($st, $ed, $_POST['ignore']);
			if (empty($rows)) {
				$log->info("XHR [reg_non_scrivener_sur_case] 查無資料");
				echoJSONResponse('查無非專代測量案件');
			} else {
				$total = count($rows);
				$baked = array();
				foreach ($rows as $row) {
					$data = new SurCaseData($row);
					$baked[] = $data->getBakedData();
				}
				$log->info("XHR [reg_non_scrivener_sur_case] 查詢成功($total)");
				echoJSONResponse("查詢成功，找到 $total 筆非專代測量案件。", STATUS_CODE::SUCCESS_WITH_MULTIPLE_RECORDS, array(
					"data_count" => $total,
					"baked" => $baked,
					'cache_remaining_time' => $prefetch->getNonScrivenerSurCaseCacheRemainingTime($st, $ed)
				));
			}
			break;
		case "reg_foreigner_case":
			$log->info("XHR [reg_foreigner_case] 查詢外國人地權案件請求");
			$st = $_POST['year_month'];
			$rows = $_POST['reload'] === 'true' ? $prefetch->reloadForeignerCase($_POST['year_month']) : $prefetch->getForeignerCase($_POST['year_month']);
			if (empty($rows)) {
				$log->info("XHR [reg_foreigner_case] 查無資料");
				echoJSONResponse('查無外國人地權案件');
			} else {
				$total = count($rows);
				$baked = array();
				foreach ($rows as $row) {
					$data = new RegCaseData($row);
					$baked[] = $data->getBakedData();
				}
				$log->info("XHR [reg_foreigner_case] 查詢成功($total)");
				echoJSONResponse("查詢成功，找到 $total 筆外國人地權案件。", STATUS_CODE::SUCCESS_WITH_MULTIPLE_RECORDS, array(
					"data_count" => $total,
					"baked" => $baked,
					'cache_remaining_time' => $prefetch->getForeignerCaseCacheRemainingTime($_POST['year_month'])
				));
			}
			break;
		case "trust_query":
			$log->info("XHR [trust_query] 查詢信託相關資料請求");
			$type_msg = "信託相關資料";
			if ($_POST['query'] === 'land') {
				// 土地註記塗銷
				$type_msg = "土地註記塗銷查詢";
				$rows = $_POST['reload'] === 'true' ? $prefetch->reloadTrustObliterateLand($_POST['start'], $_POST['end']) : $prefetch->getTrustObliterateLand($_POST['start'], $_POST['end']);
				$cache_remaining = $prefetch->getTrustObliterateLandCacheRemainingTime($_POST['start'], $_POST['end']);
			} else if ($_POST['query'] === 'building') {
				// 建物註記塗銷
				$type_msg = "建物註記塗銷查詢";
				$rows = $_POST['reload'] === 'true' ? $prefetch->reloadTrustObliterateBuilding($_POST['start'], $_POST['end']) : $prefetch->getTrustObliterateBuilding($_POST['start'], $_POST['end']);
				$cache_remaining = $prefetch->getTrustObliterateBuildingCacheRemainingTime($_POST['start'], $_POST['end']);
			} else if ($_POST['query'] === 'reg_reason') {
				// 信託案件資料查詢
				$type_msg = "信託案件資料查詢";
				$rows = $_POST['reload'] === 'true' ? $prefetch->reloadTrustQuery($_POST['start'], $_POST['end']) : $prefetch->getTrustRegQuery($_POST['start'], $_POST['end']);
				$cache_remaining = $prefetch->getTrustRegQueryCacheRemainingTime($_POST['start'], $_POST['end']);
				foreach ($rows as $idx => $row) {
					$regdata = new RegCaseData($row);
					$rows[$idx] = $regdata->getBakedData();
				}
			}
			if (empty($rows)) {
				$log->info("XHR [trust_query] 查無${type_msg}資料");
				echoJSONResponse('查無信託相關資料');
			} else {
				$total = count($rows);
				$log->info("XHR [trust_query] 查詢成功($total)");
				echoJSONResponse("查詢成功，找到 $total 筆${type_msg}資料。", STATUS_CODE::SUCCESS_WITH_MULTIPLE_RECORDS, array(
					"data_count" => $total,
					"raw" => $rows,
					'cache_remaining_time' => $cache_remaining
				));
			}
			break;
		default:
			$log->error("不支援的查詢型態【".$_POST["type"]."】");
			echoJSONResponse("不支援的查詢型態【".$_POST["type"]."】", STATUS_CODE::UNSUPPORT_FAIL);
			break;
	}
}
