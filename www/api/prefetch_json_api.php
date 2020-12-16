<?php
require_once(dirname(dirname(__FILE__))."/include/init.php");
require_once(INC_DIR."/Prefetch.class.php");
require_once(INC_DIR."/System.class.php");

$system = new System();
$mock = $system->isMockMode();

switch ($_POST["type"]) {
	case "reg_rm30_H_case":
		$log->info("XHR [reg_rm30_H_case] 查詢登記公告中案件請求");
		$rows = $mock ? $cache->get('reg_rm30_H_case') : $query->getRM30HCase();
		$cache->set('reg_rm30_H_case', $rows);
		if (empty($rows)) {
			$log->info("XHR [reg_rm30_H_case] 查無資料");
			echoErrorJSONString();
		} else {
			$total = count($rows);
			$log->info("XHR [reg_rm30_H_case] 查詢成功($total)");
			$baked = array();
			foreach ($rows as $row) {
				$data = new RegCaseData($row);
				$baked[] = $data->getBakedData();
			}
			$result = array(
				"status" => STATUS_CODE::SUCCESS_WITH_MULTIPLE_RECORDS,
				"message" => "查詢成功，找到 $total 筆公告中資料。",
				"data_count" => $total,
				"baked" => $baked
			);
			echo json_encode($result, 0);
		}
		break;
	default:
		$log->error("不支援的查詢型態【".$_POST["type"]."】");
		echoErrorJSONString("不支援的查詢型態【".$_POST["type"]."】", STATUS_CODE::UNSUPPORT_FAIL);
		break;
}
