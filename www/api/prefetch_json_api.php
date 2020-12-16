<?php
require_once(dirname(dirname(__FILE__))."/include/init.php");
require_once(INC_DIR."/Prefetch.class.php");
require_once(INC_DIR."/RegCaseData.class.php");

$prefetch = new Prefetch();

switch ($_POST["type"]) {
	case "overdue_reg_cases":
		$log->info("XHR [overdue_reg_cases] 近15天逾期案件查詢請求");
		$rows = $prefetch->getOverdueCasesIn15Days();
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
		$rows = $prefetch->getAlmostOverdueCases();
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
		$log->info("XHR [reg_rm30_H_case] 查詢登記公告中案件請求 (".str_replace("\n", ' ', print_r($_POST, true)).")");
		$rows = $_POST['reload'] === 'false' ? $prefetch->getRM30HCase() : $prefetch->reloadRM30HCase();
		$remaining = $prefetch->getRM30HCaseCacheRemainingTime();
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
				'cache_remaining_time' => $remaining
			));
		}
		break;
	default:
		$log->error("不支援的查詢型態【".$_POST["type"]."】");
		echoJSONResponse("不支援的查詢型態【".$_POST["type"]."】", STATUS_CODE::UNSUPPORT_FAIL);
		break;
}
