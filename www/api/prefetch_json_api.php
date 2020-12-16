<?php
require_once(dirname(dirname(__FILE__))."/include/init.php");
require_once(INC_DIR."/Prefetch.class.php");
require_once(INC_DIR."/RegCaseData.class.php");
require_once(INC_DIR."/System.class.php");

$system = new System();
$mock = $system->isMockMode();

$prefetch = new Prefetch();

switch ($_POST["type"]) {
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
