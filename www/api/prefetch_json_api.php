<?php
require_once(dirname(dirname(__FILE__))."/include/init.php");

function getExpireCaseData (&$regdata) {
	return array(
		"收件字號" => $regdata->getReceiveSerial(),
		"登記原因" => $regdata->getCaseReason(),
		"辦理情形" => $regdata->getStatus(),
		"收件時間" => $regdata->getReceiveDate()." ".$regdata->getReceiveTime(),
		"限辦期限" => $regdata->getDueDate(),
		"初審人員" => $regdata->getFirstReviewer() . " " . $regdata->getFirstReviewerID(),
		"作業人員" => $regdata->getCurrentOperator()
	);
}

function findMaxScheduledCloseDatetime(&$rows, $st_index) {
	$row = $rows[$st_index];
	$max = $row['RM29_1'].$row['RM29_2'];
	$rm32 = intval($row['RM32']);
	for ($i = 1; $i < $rm32; $i++) {
		// init val
		$st_rm01 = $row['RM01'];
		$st_rm02 = $row['RM02'];
		$st_rm03 = $row['RM03'];
		$st_rm03_val = intval($row['RM03']);
		// move to next row
		$row = $rows[$st_index + $i];

		$now_rm03_val = intval($row['RM03']);
		$offset = $now_rm03_val - $st_rm03_val;
		if ($st_rm01 !== $row['RM01'] || $st_rm02 !== $row['RM02'] || $offset > 10 || $offset < 0) {
			Logger::getInstance()->warning("目前：$st_rm01-$st_rm02-$st_rm03 <==> 下一筆：".$row['RM01']."-".$row['RM02']."-".$row['RM03']);
			return false;
		}

		$current = $row['RM29_1'].$row['RM29_2'];
		if ($current > $max) {
			$max = $current;
			Logger::getInstance()->info("最大預計結案日期時間設定為 ".$row['RM01']."-".$row['RM02']."-".$row['RM03']." 案件資料 👉 $max");
		}
	}
	return $max;
}

$prefetch = new Prefetch();
switch ($_POST["type"]) {
	case "overdue_reg_cases_filtered":
		Logger::getInstance()->info("XHR [overdue_reg_cases_filtered] 逾期案件查詢請求");
		$rows = $_POST['reload'] === 'true' ? $prefetch->reloadOverdueCaseIn15Days() : $prefetch->getOverdueCaseIn15Days();
		if (empty($rows)) {
			Logger::getInstance()->info("XHR [overdue_reg_cases_filtered] 查無逾期資料");
			echoJSONResponse("查無逾期資料", STATUS_CODE::SUCCESS_WITH_NO_RECORD, array(
				"items" => array(),
				"items_by_id" => array(),
				"data_count" => 0,
				"raw" => $rows,
				'cache_remaining_time' => $prefetch->getOverdueCaseCacheRemainingTime()
			));
		} else {
			$items = [];
			$items_by_id = [];

			$tw_date = new Datetime("now");
			$tw_date->modify("-1911 year");
			$now = ltrim($tw_date->format("YmdHis"), "0");	// ex: 1120725084812

			$date_15days_before = new Datetime("now");
			$date_15days_before->modify("-1911 year");
			$date_15days_before->modify("-15 days");
			$start = ltrim($date_15days_before->format("YmdHis"), "0");	// ex: 1120710084812
			
			$count = count($rows);
			for ($i = 0; $i < $count; $i++) {
				$row = $rows[$i];
				$scheduled_close_datetime = $row['RM29_1'].$row['RM29_2'];
				$case_count = intval($row['RM32']);
				if ($case_count < 2) {
					if ($now >= $scheduled_close_datetime) {
						$regdata = new RegCaseData($row);
						$this_item = getExpireCaseData($regdata);
						$items[] = $this_item;
						$items_by_id[$regdata->getFirstReviewerID()][] = $this_item;
					}
				} else {
					// find max scheduled close datetime
					$max_scheduled_close_datetime = findMaxScheduledCloseDatetime($rows, $i);
					if ($max_scheduled_close_datetime === false) {
						// the series cases not valid ... skip this case
						Logger::getInstance()->warning($row['RM01']."-".$row['RM02']."-".$row['RM03']." 連件資料判斷有誤，當作單獨案件處理。");
						if ($now >= $scheduled_close_datetime) {
							$regdata = new RegCaseData($row);
							$this_item = getExpireCaseData($regdata);
							$items[] = $this_item;
							$items_by_id[$regdata->getFirstReviewerID()][] = $this_item;
						}
						continue;
					}
					// all or nothing
					if ($now >= $max_scheduled_close_datetime) {
						for ($y = 0; $y < $case_count; $y++) {
							$tmp = $rows[$i + $y];
							$regdata = new RegCaseData($tmp);
							$this_item = getExpireCaseData($regdata);
							$items[] = $this_item;
							$items_by_id[$regdata->getFirstReviewerID()][] = $this_item;
						}
					}
					// skip series cases to next start pivot
					$i += ($case_count - 1);
				}
			}

			Logger::getInstance()->info("XHR [overdue_reg_cases_filtered] 找到".count($items)."件逾期案件");
			echoJSONResponse("找到".count($items)."件逾期案件", STATUS_CODE::SUCCESS_NORMAL, array(
				"items" => $items,
				"items_by_id" => $items_by_id,
				"data_count" => count($items),
				"raw" => $rows,
				'cache_remaining_time' => $prefetch->getNotCloseCaseCacheRemainingTime()
			));
		}
		break;
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
				$this_item = getExpireCaseData($regdata);
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
				$this_item = getExpireCaseData($regdata);
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
		Logger::getInstance()->info("XHR [reg_cancel_ask_case] 查詢請示(取消)案件請求");
		$begin = $_POST['begin'];
		$end = $_POST['end'];
		$rows = $_POST['reload'] === 'true' ? $prefetch->reloadAskCase($begin, $end) : $prefetch->getAskCase($begin, $end);
		if (empty($rows)) {
			Logger::getInstance()->info("XHR [reg_cancel_ask_case] 查無曾經請示(取消)案件資料");
			echoJSONResponse('查無曾經請示(取消)案件');
		} else {
			$total = count($rows);
			Logger::getInstance()->info("XHR [reg_cancel_ask_case] 查詢成功($total, $begin ~ $end)");
			$baked = array();
			foreach ($rows as $row) {
				$data = new RegCaseData($row);
				$baked[] = $data->getBakedData();
			}
			echoJSONResponse("查詢成功，找到 $total 筆曾經請示(取消)案件資料。", STATUS_CODE::SUCCESS_WITH_MULTIPLE_RECORDS, array(
				"data_count" => $total,
				"baked" => $baked,
				'cache_remaining_time' => $prefetch->getAskCaseCacheRemainingTime($begin, $end)
			));
		}
		break;
	case "reg_trust_case":
		Logger::getInstance()->info("XHR [reg_trust_case] 查詢請示(取消)案件請求");
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
	case "not_done_change":
		Logger::getInstance()->info("XHR [not_done_change] 查詢未辦標的註記異動資料請求");
		$message = "未辦標的註記異動查詢";
		$rows = $_POST['reload'] === 'true' ? $prefetch->reloadNotDoneChange($_POST['start'], $_POST['end']) : $prefetch->getNotDoneChange($_POST['start'], $_POST['end']);
		$cache_remaining = $prefetch->getNotDoneChangeCacheRemainingTime($_POST['start'], $_POST['end']);
		if (empty($rows)) {
			Logger::getInstance()->info("XHR [not_done_change] 查無 ${message} 資料");
			echoJSONResponse("查無 ${message} 資料");
		} else {
			$total = count($rows);
			Logger::getInstance()->info("XHR [not_done_change] 查詢成功($total)");
			echoJSONResponse("查詢成功，找到 $total 筆 ${message} 資料。", STATUS_CODE::SUCCESS_WITH_MULTIPLE_RECORDS, array(
				"data_count" => $total,
				"raw" => $rows,
				'cache_remaining_time' => $cache_remaining
			));
		}
		break;
	case "land_ref_change":
		Logger::getInstance()->info("XHR [land_ref_change] 查詢土地參考資訊異動資料請求");
		$message = "土地參考資訊異動查詢";
		$rows = $_POST['reload'] === 'true' ? $prefetch->reloadLandRefChange($_POST['start'], $_POST['end']) : $prefetch->getLandRefChange($_POST['start'], $_POST['end']);
		$cache_remaining = $prefetch->getLandRefChangeCacheRemainingTime($_POST['start'], $_POST['end']);
		if (empty($rows)) {
			Logger::getInstance()->info("XHR [land_ref_change] 查無 ${message} 資料");
			echoJSONResponse("查無 ${message} 資料");
		} else {
			$total = count($rows);
			Logger::getInstance()->info("XHR [land_ref_change] 查詢成功($total)");
			echoJSONResponse("查詢成功，找到 $total 筆 ${message} 資料。", STATUS_CODE::SUCCESS_WITH_MULTIPLE_RECORDS, array(
				"data_count" => $total,
				"raw" => $rows,
				'cache_remaining_time' => $cache_remaining
			));
		}
		break;
	case "reg_fix_case":
		Logger::getInstance()->info("XHR [reg_fix_case] 查詢補正案件查詢請求");
		$message = "補正案件查詢";
		$rows = $_POST['reload'] === 'true' ? $prefetch->reloadRegFixCase() : $prefetch->getRegFixCase();
		$cache_remaining = $prefetch->getRegFixCaseCacheRemainingTime();
		if (empty($rows)) {
			Logger::getInstance()->info("XHR [reg_fix_case] 查無 ${message} 資料");
			echoJSONResponse("查無 ${message} 資料");
		} else {
			require_once(INC_DIR.DIRECTORY_SEPARATOR.'SQLiteRegFixCaseStore.class.php');
			$sqlite_db = new SQLiteRegFixCaseStore();

			$total = count($rows);
			$baked = array();
			foreach ($rows as $row) {
				$data = new RegCaseData($row);
				$this_baked = $data->getBakedData();

				
				$id = $this_baked['ID'];
				// this query goes to SQLite DB
				$result = $sqlite_db->getRegFixCaseRecord($id);
				$this_baked['REG_FIX_CASE_RECORD'] = $result[0] ?? [];

				$baked[] = $this_baked;
			}
			Logger::getInstance()->info("XHR [reg_fix_case] 查詢成功($total)");
			echoJSONResponse("查詢成功，找到 $total 筆 ${message} 資料。", STATUS_CODE::SUCCESS_WITH_MULTIPLE_RECORDS, array(
				"data_count" => $total,
				"raw" => $baked,
				'cache_remaining_time' => $cache_remaining
			));
		}
		break;
	case "reg_not_done_case":
		Logger::getInstance()->info("XHR [reg_not_done_case] 查詢未結案登記案件請求");
		$message = "未結案登記案件查詢";
		$rows = $_POST['reload'] === 'true' ? $prefetch->reloadRegNotDoneCase() : $prefetch->getRegNotDoneCase();
		$cache_remaining = $prefetch->getRegNotDoneCaseCacheRemainingTime();
		
		if (empty($rows)) {
			Logger::getInstance()->info("XHR [reg_not_done_case] 查無 ${message} 資料");
			echoJSONResponse("查無 ${message} 資料");
		} else {
			// also get authority from sqlite db
			require_once(INC_DIR.DIRECTORY_SEPARATOR.'SQLiteRegAuthChecksStore.class.php');
			$sqlite_db = new SQLiteRegAuthChecksStore();

			$total = count($rows);
			$baked = array();
			foreach ($rows as $row) {
				$data = new RegCaseData($row);
				$this_baked = $data->getBakedData();

				$id = $this_baked['ID'];
				// this query goes to SQLite DB, return array of result
				$result = $sqlite_db->getRegAuthChecksRecord($id);
				$this_baked['CASE_NOTIFY_RAW'] = $result;
				if (is_array($result) && count($result) === 1) {
					$auth = $result[0];
					$this_baked['CASE_NOTIFY_AUTHORITY'] = $auth['authority'];
					$this_baked['CASE_NOTIFY_NOTE'] = $auth['note'];
				} else{
					// default is 0 that means the case don't need to notify applicant
					$this_baked['CASE_NOTIFY_AUTHORITY'] = 0;
					$this_baked['CASE_NOTIFY_NOTE'] = '';
				}

				$baked[] = $this_baked;
			}
			Logger::getInstance()->info("XHR [reg_not_done_case] 查詢成功($total)");
			echoJSONResponse("查詢成功，找到 $total 筆 ${message} 資料。", STATUS_CODE::SUCCESS_WITH_MULTIPLE_RECORDS, array(
				"data_count" => $total,
				"raw" => $baked,
				'cache_remaining_time' => $cache_remaining
			));
		}

		break;
	case "reg_untaken_case":
		Logger::getInstance()->info("XHR [reg_untaken_case] 查詢結案未歸檔登記案件請求");
		$message = "結案未歸檔登記案件查詢";
		$st = $_POST['start'];
		$ed = $_POST['end'];
		$rows = $_POST['reload'] === 'true' ? $prefetch->reloadRegUntakenCase($st, $ed) : $prefetch->getRegUntakenCase($st, $ed);
		$cache_remaining = $prefetch->getRegUntakenCaseCacheRemainingTime($st, $ed);
		
		if (empty($rows)) {
			Logger::getInstance()->info("XHR [reg_untaken_case] 查無 ${message} 資料");
			echoJSONResponse("查無 ${message} 資料($st ~ $ed)");
		} else {
			// also get authority from sqlite db
			require_once(INC_DIR.DIRECTORY_SEPARATOR.'SQLiteRegUntakenStore.class.php');
			$sqlite_db = new SQLiteRegUntakenStore();

			$total = count($rows);
			$baked = array();
			foreach ($rows as $row) {
				$data = new RegCaseData($row);
				$this_baked = $data->getBakedData();

				$id = $this_baked['ID'];
				// this query goes to SQLite DB, return array of result
				$result = $sqlite_db->getRegUntakenRecord($id);
				$record = $result[0] ?? [];
				$this_baked['UNTAKEN_TAKEN_DATE'] = $record['taken_date'] ?? '';
				$this_baked['UNTAKEN_TAKEN_STATUS'] = $record['taken_status'] ?? '';
				$this_baked['UNTAKEN_LENT_DATE'] = $record['lent_date'] ?? '';
				$this_baked['UNTAKEN_RETURN_DATE'] = $record['return_date'] ?? '';
				$this_baked['UNTAKEN_BORROWER'] = $record['borrower'] ?? '';
				$this_baked['UNTAKEN_NOTE'] = $record['note'] ?? '';

				$baked[] = $this_baked;
			}
			Logger::getInstance()->info("XHR [reg_untaken_case] 查詢成功($total, $st ~ $ed)");
			echoJSONResponse("查詢成功，找到 $total 筆 ${message} 資料。($st ~ $ed)", STATUS_CODE::SUCCESS_WITH_MULTIPLE_RECORDS, array(
				"data_count" => $total,
				"raw" => $baked,
				'cache_remaining_time' => $cache_remaining
			));
		}

		break;
	case "sur_overdue_case":
		Logger::getInstance()->info("XHR [sur_overdue_case] 查詢測量逾期案件請求");
		$message = "逾期測量案件";
		$rows = $_POST['reload'] === 'true' ? $prefetch->reloadSurOverdueCase() : $prefetch->getSurOverdueCase();
		$cache_remaining = $prefetch->getSurOverdueCaseCacheRemainingTime();
		if (empty($rows)) {
			Logger::getInstance()->info("XHR [sur_overdue_case] 查無 ${message} 資料");
			echoJSONResponse("查無 ${message} 資料");
		} else {
			$total = count($rows);
			Logger::getInstance()->info("XHR [sur_overdue_case] 查詢成功($total)");
			echoJSONResponse("查詢成功，找到 $total 筆 ${message} 資料。", STATUS_CODE::SUCCESS_WITH_MULTIPLE_RECORDS, array(
				"data_count" => $total,
				"raw" => $rows,
				'cache_remaining_time' => $cache_remaining
			));
		}

		break;
	case "sur_not_close_case":
		Logger::getInstance()->info("XHR [sur_not_close_case] 查詢未結案測量案件請求");
		$message = "未結案測量案件";
		$rows = $_POST['reload'] === 'true' ? $prefetch->reloadSurNotCloseCase() : $prefetch->getSurNotCloseCase();
		$cache_remaining = $prefetch->getSurNotCloseCaseCacheRemainingTime();
		if (empty($rows)) {
			Logger::getInstance()->info("XHR [sur_not_close_case] 查無 ${message} 資料");
			echoJSONResponse("查無 ${message} 資料");
		} else {
			$total = count($rows);
			Logger::getInstance()->info("XHR [sur_not_close_case] 查詢成功($total)");
			echoJSONResponse("查詢成功，找到 $total 筆 ${message} 資料。", STATUS_CODE::SUCCESS_WITH_MULTIPLE_RECORDS, array(
				"data_count" => $total,
				"raw" => $rows,
				'cache_remaining_time' => $cache_remaining
			));
		}

		break;
	case "sur_near_case":
		Logger::getInstance()->info("XHR [sur_near_case] 查詢即將到期測量案件請求");
		$message = "即將到期測量案件";
		$rows = $_POST['reload'] === 'true' ? $prefetch->reloadSurNearCase() : $prefetch->getSurNearCase();
		$cache_remaining = $prefetch->getSurNearCaseCacheRemainingTime();
		if (empty($rows)) {
			Logger::getInstance()->info("XHR [sur_near_case] 查無 ${message} 資料");
			echoJSONResponse("查無 ${message} 資料");
		} else {
			$total = count($rows);
			Logger::getInstance()->info("XHR [sur_near_case] 查詢成功($total)");
			echoJSONResponse("查詢成功，找到 $total 筆 ${message} 資料。", STATUS_CODE::SUCCESS_WITH_MULTIPLE_RECORDS, array(
				"data_count" => $total,
				"raw" => $rows,
				'cache_remaining_time' => $cache_remaining
			));
		}

		break;
	case "val_realprice_map":
		Logger::getInstance()->info("XHR [val_realprice_map] 查詢實價登錄申報對應請求");
		$message = "實價登錄申報案件";
		$st = $_POST["start_date"];
		$ed = $_POST["end_date"];
		$rows = $_POST['reload'] === 'true' ? $prefetch->reloadValRealPriceMap($st, $ed) : $prefetch->getValRealPriceMap($st, $ed);
		$cache_remaining = $prefetch->getValRealPriceMapCacheRemainingTime($st, $ed);
		if (empty($rows)) {
			Logger::getInstance()->info("XHR [val_realprice_map] 查無 ${message} 資料");
			echoJSONResponse("查無 ${message} 資料");
		} else {
			$total = count($rows);
			$baked = array();

			require_once(INC_DIR.DIRECTORY_SEPARATOR.'SQLiteValRealpriceMemoStore.class.php');
			$sqlite_db = new SQLiteValRealpriceMemoStore();

			foreach ($rows as $row) {
				$data = new RegCaseData($row);
				$this_baked = $data->getBakedData();

				$caseNo = $this_baked['P1MP_CASENO'] ?? $this_baked['ID'];
				if (!empty($caseNo)) {
					// this query goes to SQLite DB, return array of result
					$result = $sqlite_db->getValRealpriceMemoRecord($caseNo);
					// fallback to use reg case id as key to find memo data
					if (empty($result)) {
						$result = $sqlite_db->getValRealpriceMemoRecord($this_baked['ID']);
					}
					$this_baked['P1MP_DECLARE_RAW'] = $result;
					if (is_array($result) && count($result) === 1) {
						$memo = $result[0];
						$this_baked['P1MP_DECLARE_DATE'] = $memo['declare_date'];
						$this_baked['P1MP_DECLARE_NOTE'] = $memo['declare_note'];
					} else{
						// default is 1 that means the case needs to notify applicant
						$this_baked['P1MP_DECLARE_DATE'] = '';
						$this_baked['P1MP_DECLARE_NOTE'] = '';
					}
				}
				$baked[] = $this_baked;
			}
			Logger::getInstance()->info("XHR [val_realprice_map] 查詢成功($total)");
			echoJSONResponse("查詢成功，找到 $total 筆 ${message} 資料。", STATUS_CODE::SUCCESS_WITH_MULTIPLE_RECORDS, array(
				// "data_count" => $total,
				// "raw" => $rows,
				// 'cache_remaining_time' => $cache_remaining,
				"data_count" => $total,
				"baked" => $baked,
				'cache_remaining_time' => $cache_remaining
			));
		}
		break;
	case "reg_inheritance_restriction":
		Logger::getInstance()->info("XHR [reg_inheritance_restriction] 查詢外人管制清冊資料請求");
		$message = "外人管制清冊";
		$rows = $_POST['reload'] === 'true' ? $prefetch->reloadRegInheritanceRestriction() : $prefetch->getRegInheritanceRestriction();
		$cache_remaining = $prefetch->getRegInheritanceRestrictionCacheRemainingTime();
		if (empty($rows)) {
			Logger::getInstance()->info("XHR [reg_inheritance_restriction] 查無 $message 資料");
			echoJSONResponse("查無 $message 資料");
		} else {
			$total = count($rows);
			$srfr = new SQLiteRegForeignerRestriction();
			$baked = array();
			// to check if PDF exists
			$srfp = new SQLiteRegForeignerPDF();
			foreach ($rows as $row) {
				$data = new RegCaseData($row);
				$this_baked = $data->getBakedData();
				// use pkey(地段+地號+統編) to read restriction data
				$pkey = $row['BA48'].$row['BA49'].$row['BB09'].$row['BB07'];
				$this_baked['RESTRICTION_DATA'] = $srfr->getOne($pkey);
				$this_baked['hasPDF'] = $srfp->exists($row['BB03'], $row['BB04_2'], $row['BB09']) > 0;
				$baked[] = $this_baked;
			}
			Logger::getInstance()->info("XHR [reg_inheritance_restriction] 查詢成功($total)");
			echoJSONResponse("查詢成功，找到 $total 筆 $message 資料。", STATUS_CODE::SUCCESS_WITH_MULTIPLE_RECORDS, array(
				// "data_count" => $total,
				// "raw" => $rows,
				// 'cache_remaining_time' => $cache_remaining,
				"data_count" => $total,
				"baked" => $baked,
				'cache_remaining_time' => $cache_remaining
			));
		}
		break;
	default:
		Logger::getInstance()->error("不支援的查詢型態【".$_POST["type"]."】");
		echoJSONResponse("不支援的查詢型態【".$_POST["type"]."】", STATUS_CODE::UNSUPPORT_FAIL);
		break;
}
