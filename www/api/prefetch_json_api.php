<?php
require_once(dirname(dirname(__FILE__))."/include/init.php");
require_once(INC_DIR."/System.class.php");
require_once(INC_DIR."/Prefetch.class.php");
require_once(INC_DIR."/RegCaseData.class.php");
require_once(INC_DIR."/SurCaseData.class.php");

$prefetch = new Prefetch();
switch ($_POST["type"]) {
	case "overdue_reg_cases":
		Logger::getInstance()->info("XHR [overdue_reg_cases] 近15天逾期案件查詢請求");
		$rows = $_POST['reload'] === 'true' ? $prefetch->reloadOverdueCaseIn15Days() : $prefetch->getOverdueCaseIn15Days();
		if (empty($rows)) {
			Logger::getInstance()->info("XHR [overdue_reg_cases] 近15天查無逾期資料");
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
			Logger::getInstance()->info("XHR [overdue_reg_cases] 近15天找到".count($items)."件逾期案件");
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
		Logger::getInstance()->info("XHR [almost_overdue_reg_cases] 即將逾期案件查詢請求");
		$rows = $_POST['reload'] === 'true' ? $prefetch->reloadAlmostOverdueCase() : $prefetch->getAlmostOverdueCase();
		if (empty($rows)) {
			Logger::getInstance()->info("XHR [almost_overdue_reg_cases] 近4小時內查無即將逾期資料");
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
			Logger::getInstance()->info("XHR [almost_overdue_reg_cases] 近4小時內找到".count($items)."件即將逾期案件");
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
		Logger::getInstance()->info("XHR [reg_rm30_H_case] 查詢登記公告中案件請求");
		$rows = $_POST['reload'] === 'true' ? $prefetch->reloadRM30HCase() : $prefetch->getRM30HCase();
		if (empty($rows)) {
			Logger::getInstance()->info("XHR [reg_rm30_H_case] 查無資料");
			echoJSONResponse('查無公告中案件');
		} else {
			$total = count($rows);
			Logger::getInstance()->info("XHR [reg_rm30_H_case] 查詢成功($total)");
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
		Logger::getInstance()->info("XHR [reg_cancel_ask_case] 查詢取消請示案件請求");
		$begin = $_POST['begin'];
		$end = $_POST['end'];
		$rows = $_POST['reload'] === 'true' ? $prefetch->reloadAskCase($begin, $end) : $prefetch->getAskCase($begin, $end);
		if (empty($rows)) {
			Logger::getInstance()->info("XHR [reg_cancel_ask_case] 查無曾經取消請示案件資料");
			echoJSONResponse('查無曾經取消請示案件');
		} else {
			$total = count($rows);
			Logger::getInstance()->info("XHR [reg_cancel_ask_case] 查詢成功($total, $begin ~ $end)");
			$baked = array();
			foreach ($rows as $row) {
				$data = new RegCaseData($row);
				$baked[] = $data->getBakedData();
			}
			echoJSONResponse("查詢成功，找到 $total 筆曾經取消請示案件資料。", STATUS_CODE::SUCCESS_WITH_MULTIPLE_RECORDS, array(
				"data_count" => $total,
				"baked" => $baked,
				'cache_remaining_time' => $prefetch->getAskCaseCacheRemainingTime($begin, $end)
			));
		}
		break;
	case "reg_trust_case":
		Logger::getInstance()->info("XHR [reg_trust_case] 查詢取消請示案件請求");
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
			Logger::getInstance()->info("XHR [reg_trust_case] 查無資料");
			echoJSONResponse('查無信託註記資料');
		} else {
			$total = count($rows);
			Logger::getInstance()->info("XHR [reg_trust_case] 查詢成功($total)");
			echoJSONResponse("查詢成功，找到 $total 筆信託註記資料。", STATUS_CODE::SUCCESS_WITH_MULTIPLE_RECORDS, array(
				"data_count" => $total,
				"raw" => $rows,
				'cache_remaining_time' => $cache_remaining
			));
		}
		break;
	case "reg_non_scrivener_case":
		Logger::getInstance()->info("XHR [reg_non_scrivener_case] 查詢非專代案件請求");
		$st = $_POST['start_date'];
		$ed = $_POST['end_date'];
		$rows = $_POST['reload'] === 'true' ? $prefetch->reloadNonScrivenerCase($st, $ed) : $prefetch->getNonScrivenerCase($st, $ed);
		if (empty($rows)) {
			Logger::getInstance()->info("XHR [reg_non_scrivener_case] 查無資料");
			echoJSONResponse('查無非專代案件');
		} else {
			$total = count($rows);
			Logger::getInstance()->info("XHR [reg_non_scrivener_case] 查詢成功($total)");
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
		Logger::getInstance()->info("XHR [reg_non_scrivener_reg_case] 查詢非專代案件請求");
		$st = $_POST['start_date'];
		$ed = $_POST['end_date'];
		$rows = $_POST['reload'] === 'true' ? $prefetch->reloadNonScrivenerRegCase($st, $ed, $_POST['ignore']) : $prefetch->getNonScrivenerRegCase($st, $ed, $_POST['ignore']);
		if (empty($rows)) {
			Logger::getInstance()->info("XHR [reg_non_scrivener_reg_case] 查無資料");
			echoJSONResponse('查無非專代案件');
		} else {
			$total = count($rows);
			$baked = array();
			foreach ($rows as $row) {
				$data = new RegCaseData($row);
				$baked[] = $data->getBakedData();
			}
			Logger::getInstance()->info("XHR [reg_non_scrivener_reg_case] 查詢成功($total)");
			echoJSONResponse("查詢成功，找到 $total 筆非專代案件。", STATUS_CODE::SUCCESS_WITH_MULTIPLE_RECORDS, array(
				"data_count" => $total,
				"baked" => $baked,
				'cache_remaining_time' => $prefetch->getNonScrivenerRegCaseCacheRemainingTime($st, $ed, $_POST['ignore'])
			));
		}
		break;
	case "reg_non_scrivener_sur_case":
		Logger::getInstance()->info("XHR [reg_non_scrivener_sur_case] 查詢非專代測量案件請求");
		$st = $_POST['start_date'];
		$ed = $_POST['end_date'];
		$rows = $_POST['reload'] === 'true' ? $prefetch->reloadNonScrivenerSurCase($st, $ed, $_POST['ignore']) : $prefetch->getNonScrivenerSurCase($st, $ed, $_POST['ignore']);
		if (empty($rows)) {
			Logger::getInstance()->info("XHR [reg_non_scrivener_sur_case] 查無資料");
			echoJSONResponse('查無非專代測量案件');
		} else {
			$total = count($rows);
			$baked = array();
			foreach ($rows as $row) {
				$data = new SurCaseData($row);
				$baked[] = $data->getBakedData();
			}
			Logger::getInstance()->info("XHR [reg_non_scrivener_sur_case] 查詢成功($total)");
			echoJSONResponse("查詢成功，找到 $total 筆非專代測量案件。", STATUS_CODE::SUCCESS_WITH_MULTIPLE_RECORDS, array(
				"data_count" => $total,
				"baked" => $baked,
				'cache_remaining_time' => $prefetch->getNonScrivenerSurCaseCacheRemainingTime($st, $ed, $_POST['ignore'])
			));
		}
		break;
	case "reg_foreigner_case":
		Logger::getInstance()->info("XHR [reg_foreigner_case] 查詢外國人地權案件請求");
		$rows = $_POST['reload'] === 'true' ? $prefetch->reloadForeignerCase($_POST['begin'], $_POST['end']) : $prefetch->getForeignerCase($_POST['begin'], $_POST['end']);
		if (empty($rows)) {
			Logger::getInstance()->info("XHR [reg_foreigner_case] 查無資料");
			echoJSONResponse('查無外國人地權案件');
		} else {
			$total = count($rows);
			$baked = array();
			foreach ($rows as $row) {
				$data = new RegCaseData($row);
				$baked[] = $data->getBakedData();
			}
			Logger::getInstance()->info("XHR [reg_foreigner_case] 查詢成功($total)");
			echoJSONResponse("查詢成功，找到 $total 筆外國人地權案件。", STATUS_CODE::SUCCESS_WITH_MULTIPLE_RECORDS, array(
				"data_count" => $total,
				"baked" => $baked,
				'cache_remaining_time' => $prefetch->getForeignerCaseCacheRemainingTime($_POST['begin'], $_POST['end'])
			));
		}
		break;
	case "trust_query":
		Logger::getInstance()->info("XHR [trust_query] 查詢信託相關資料請求");
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
			Logger::getInstance()->info("XHR [trust_query] 查無${type_msg}資料");
			echoJSONResponse('查無信託相關資料');
		} else {
			$total = count($rows);
			Logger::getInstance()->info("XHR [trust_query] 查詢成功($total)");
			echoJSONResponse("查詢成功，找到 $total 筆${type_msg}資料。", STATUS_CODE::SUCCESS_WITH_MULTIPLE_RECORDS, array(
				"data_count" => $total,
				"raw" => $rows,
				'cache_remaining_time' => $cache_remaining
			));
		}
		break;
	case "375_land_change":
		Logger::getInstance()->info("XHR [375_lang_change] 查詢375租約土標部異動資料請求");
		// 375租約土地標示部異動查詢
		$message = "375租約土地標示部異動查詢";
		$rows = $_POST['reload'] === 'true' ? $prefetch->reloadLand375Change($_POST['start'], $_POST['end']) : $prefetch->getLand375Change($_POST['start'], $_POST['end']);
		$cache_remaining = $prefetch->getLand375ChangeCacheRemainingTime($_POST['start'], $_POST['end']);
		foreach ($rows as $idx => $row) {
			$regdata = new RegCaseData($row);
			$rows[$idx] = $regdata->getBakedData();
		}
		if (empty($rows)) {
			Logger::getInstance()->info("XHR [375_lang_change] 查無 ${message} 資料");
			echoJSONResponse("查無 ${message} 資料");
		} else {
			$total = count($rows);
			Logger::getInstance()->info("XHR [375_lang_change] 查詢成功($total)");
			echoJSONResponse("查詢成功，找到 $total 筆 ${message} 資料。", STATUS_CODE::SUCCESS_WITH_MULTIPLE_RECORDS, array(
				"data_count" => $total,
				"raw" => $rows,
				'cache_remaining_time' => $cache_remaining
			));
		}
		break;
	case "375_owner_change":
		Logger::getInstance()->info("XHR [375_owner_change] 查詢375租約土所部異動資料請求");
		// 375租約土地標示部異動查詢
		$message = "375租約土地所有權部異動查詢";
		$rows = $_POST['reload'] === 'true' ? $prefetch->reloadOwner375Change($_POST['start'], $_POST['end']) : $prefetch->getOwner375Change($_POST['start'], $_POST['end']);
		$cache_remaining = $prefetch->getOwner375ChangeCacheRemainingTime($_POST['start'], $_POST['end']);
		foreach ($rows as $idx => $row) {
			$regdata = new RegCaseData($row);
			$rows[$idx] = $regdata->getBakedData();
		}
		if (empty($rows)) {
			Logger::getInstance()->info("XHR [375_owner_change] 查無 ${message} 資料");
			echoJSONResponse("查無 ${message} 資料");
		} else {
			$total = count($rows);
			Logger::getInstance()->info("XHR [375_owner_change] 查詢成功($total)");
			echoJSONResponse("查詢成功，找到 $total 筆 ${message} 資料。", STATUS_CODE::SUCCESS_WITH_MULTIPLE_RECORDS, array(
				"data_count" => $total,
				"raw" => $rows,
				'cache_remaining_time' => $cache_remaining
			));
		}
		break;
	default:
		Logger::getInstance()->error("不支援的查詢型態【".$_POST["type"]."】");
		echoJSONResponse("不支援的查詢型態【".$_POST["type"]."】", STATUS_CODE::UNSUPPORT_FAIL);
		break;
}
