<?php
require_once("./include/init.php");
require_once("./include/RegCaseData.class.php");
require_once("./include/SurCaseData.class.php");
require_once("./include/PrcAllCasesData.class.php");
require_once("./include/Query.class.php");
require_once("./include/Message.class.php");
require_once("./include/WatchDog.class.php");
require_once("./include/JSONAPICommandFactory.class.php");
require_once("./include/UserInfo.class.php");

function echoErrorJSONString($msg = "", $status = STATUS_CODE::DEFAULT_FAIL) {
	echo json_encode(array(
		"status" => $status,
		"data_count" => "0",
		"message" => empty($msg) ? "查無資料" : $msg
	), 0);
}

$query = new Query();

switch ($_POST["type"]) {
	case "overdue_reg_cases":
		$log->info("XHR [overdue_reg_cases] 近15天逾期案件查詢請求");
		$rows = $query->queryOverdueCasesIn15Days($_POST["first_reviewer"]);
		if (empty($rows)) {
			$log->info("XHR [overdue_reg_cases] 近15天查無逾期資料");
			echoErrorJSONString("15天內查無逾期資料");
		} else {
			$items = [];
			foreach ($rows as $row) {
				$regdata = new RegCaseData($row);
				$items[] = array(
					"收件字號" => $regdata->getReceiveSerial(),
					"登記原因" => $regdata->getCaseReason(),
					"辦理情形" => $regdata->getStatus(),
					"收件時間" => $regdata->getReceiveDate()." ".$regdata->getReceiveTime(),
					"限辦期限" => $regdata->getDueDate(),
					"初審人員" => $regdata->getFirstReviewer(),
					"作業人員" => $regdata->getCurrentOperator()
				);
			}
			$result = array(
				"status" => STATUS_CODE::SUCCESS_NORMAL,
				"items" => $items,
				"data_count" => count($items),
				"raw" => $rows
			);
			$log->info("XHR [overdue_reg_cases] 近15天找到".count($items)."件逾期案件");
			echo json_encode($result, 0);
		}
		break;
	case "watchdog":
		$log->info("XHR [watchdog] 監控請求");
		// use http://localhost the client will be "::1"
		// if you want to enable the watchdog, open above link with chrome on server. (do not close it)
		if ($client_ip == "::1") {
			$watchdog = new WatchDog();
			$done = $watchdog->do();
			if ($done) {
				$log->info("XHR [watchdog] 檢查完成");
				echo json_encode(array(
					"status" => STATUS_CODE::SUCCESS_NORMAL,
					"data_count" => 0,
					"raw" => $done
				), 0);
			} else {
				$log->warning("XHR [watchdog] 檢查完成，但回傳值有問題【${done}】");
				echoErrorJSONString("XHR [watchdog] 檢查完成，但回傳值有問題【${done}】");
			}
		} else {
			$log->info("XHR [watchdog] 跳過執行，因為IP不為「::1」");
			echoErrorJSONString("XHR [watchdog] 跳過執行，因為IP不為「::1」", STATUS_CODE::FAIL_NOT_VALID_SERVER);
		}
		break;
	case "xcase-check":
		$log->info("XHR [xcase-check] 查詢跨所註記遺失請求");
		$query_result = $query->getProblematicCrossCases();
		if (empty($query_result)) {
			$log->info("XHR [xcase-check] 查無資料");
			echoErrorJSONString();
		} else {
			$rows = $query_result;
			$case_ids = [];
			foreach ($rows as $row) {
				$case_ids[] = $row['RM01'].'-'.$row['RM02'].'-'.$row['RM03'];
			}
			$result = array(
				"status" => STATUS_CODE::SUCCESS_NORMAL,
				"case_ids" => $case_ids,
				"data_count" => count($rows),
				"raw" => $rows
			);
			
			// Send Message to Admins
			$msg = new Message();
			$content = "系統目前找到下列跨所註記遺失案件:\r\n\r\n".implode("\r\n", $case_ids)."\r\n\r\n請前往 http://".$_SERVER["SERVER_ADDR"]."/watch_dog.php 修正。";
			foreach (SYSTEM_CONFIG['ADM_IPS'] as $adm_ip) {
				if ($adm_ip == '::1') {
					continue;
				}
				$sn = $msg->send('跨所案件註記遺失通知', $content, $adm_ip, "+9 minute");
				$log->info("訊息已送出(${sn})給 ${adm_ip}");
			}

			$log->info("XHR [xcase-check] 找到".count($rows)."件案件遺失註記");
			echo json_encode($result, 0);
		}
		break;
	case "fix_xcase":
		$log->info("XHR [fix_xcase] 修正跨所註記遺失【".$_POST["id"]."】請求");
		$result_flag = $query->fixProblematicCrossCases($_POST["id"]);
		if ($result_flag) {
			$result = array(
				"status" => STATUS_CODE::SUCCESS_NORMAL,
				"data_count" => "0",
				"raw" => $result_flag
			);
			echo json_encode($result, 0);
		} else {
			$log->error("XHR [fix_xcase] 更新失敗【".$_POST["id"]."】");
			echoErrorJSONString("更新失敗【".$_POST["id"]."】");
		}
		break;
	case "max":
		$log->info("XHR [max] 查詢案件最大號【".$_POST["year"].", ".$_POST["code"]."】請求");
		$year = $_POST["year"];
		$code = $_POST["code"];
		$max_num = $query->getMaxNumByYearWord($year, $code);
		$log->info("XHR [max] 查詢成功【查詢 ${year}-${code} 回傳值為 ${max_num}");
		echo json_encode(array(
			"status" => STATUS_CODE::SUCCESS_NORMAL,
			"message" => "查詢 ${year}-${code} 回傳值為 ${max_num}",
			"max" => $max_num
		), 0);
		break;
	case "ralid":
		$log->info("XHR [ralid] 查詢土地標示部資料【".$_POST["text"]."】請求");
		$query_result = $query->getSectionRALIDCount($_POST["text"]);
		if (empty($query_result)) {
			$log->info("XHR [ralid] 查無資料");
			echoErrorJSONString();
		} else {
			$result = array(
				"status" => STATUS_CODE::SUCCESS_NORMAL,
                "data_count" => count($query_result),
                "query_string" => $_POST["text"],
				"raw" => $query_result
			);
			$log->info("XHR [ralid] 查到 ".$result["data_count"]." 筆資料");
			echo json_encode($result, 0);
		}
		break;
	case "crsms":
		$log->info("XHR [crsms] 查詢登記案件資料【".$_POST["id"]."】請求");
		$query_result = $query->getCRSMSCasesByID($_POST["id"]);
		if (empty($query_result)) {
			$log->info("XHR [crsms] 查無資料");
			echoErrorJSONString();
		} else {
			$result = array(
				"status" => STATUS_CODE::SUCCESS_NORMAL,
				"data_count" => count($query_result),
				"query_string" => $_POST["id"],
				"raw" => $query_result
			);
			$log->info("XHR [crsms] 查詢成功");
			echo json_encode($result, 0);
		}
		break;
	case "cmsms":
		$log->info("XHR [cmsms] 查詢測量案件資料【".$_POST["id"]."】請求");
		$query_result = $query->getCMSMSCasesByID($_POST["id"]);
		if (empty($query_result)) {
			$log->info("XHR [cmsms] 查無資料");
			echoErrorJSONString();
		} else {
			$result = array(
				"status" => STATUS_CODE::SUCCESS_NORMAL,
				"data_count" => count($query_result),
				"query_string" => $_POST["id"],
				"raw" => $query_result
			);
			$log->info("XHR [cmsms] 查詢成功");
			echo json_encode($result, 0);
		}
		break;
	case "easycard":
		$log->info("XHR [easycard] 查詢悠遊卡資料【".$_POST["qday"]."】請求");
		$query_result = $query->getEasycardPayment($_POST["qday"]);
		if (empty($query_result)) {
			$msg = $_POST["qday"] ."查無悠遊卡交易異常資料！";
			if (empty($_POST["qday"])) {
				$msg = "一周內查無悠遊卡交易異常資料！【大於等於".$week_ago."】";
			}
			$log->info("XHR [easycard] $msg");
			echoErrorJSONString($msg);
		} else {
			$result = array(
				"status" => STATUS_CODE::SUCCESS_NORMAL,
				"data_count" => count($query_result),
				"query_string" => $_POST["qday"],
				"raw" => $query_result
			);
			$log->info("XHR [easycard] 找到 ".count($query_result)." 筆資料");
			echo json_encode($result, 0);
		}
		break;
	case "fix_easycard":
		$log->info("XHR [fix_easycard] 修正悠遊卡交易【".$_POST["qday"].", ".$_POST["pc_num"]."】請求");
		$result_flag = $query->fixEasycardPayment($_POST["qday"], $_POST["pc_num"]);
		if ($result_flag) {
			$result = array(
				"status" => STATUS_CODE::SUCCESS_NORMAL,
				"data_count" => "0",
				"raw" => $result_flag
			);
			$log->info("XHR [fix_easycard] 更新成功");
			echo json_encode($result, 0);
		} else {
			$log->error("XHR [fix_easycard] 更新失敗【".$_POST["qday"].", ".$_POST["pc_num"]."】");
			echoErrorJSONString("更新失敗【".$_POST["qday"].", ".$_POST["pc_num"]."】");
		}
		break;
	case "reg_case":
		if (empty($_POST["id"])) {
			$log->error("XHR [reg_case] 查詢ID為空值");
			echoErrorJSONString();
			break;
		}
		$log->info("XHR [reg_case] 查詢登記案件【".$_POST["id"]."】請求");
		$row = $query->getRegCaseDetail($_POST["id"]);
		if (empty($row)) {
			$log->info("XHR [reg_case] 查無資料");
			echoErrorJSONString();
		} else {
			$data = new RegCaseData($row);
			$log->info("XHR [reg_case] 查詢成功");
			echo $data->getJsonHtmlData();
		}
		break;
	case "reg_stats":
		if (empty($_POST["year_month"])) {
			$log->error("XHR [reg_stats] 查詢年月為空值");
			echoErrorJSONString();
			break;
		}
		$log->info("XHR [reg_stats] 查詢登記案件統計【".$_POST["year_month"]."】請求");
		$rows = $query->getRegCaseStatsMonthly($_POST["year_month"]);
		if (empty($rows)) {
			$log->info("XHR [reg_stats] 查無資料");
			echoErrorJSONString();
		} else {
			$log->info("XHR [reg_stats] 查詢成功");
			$result = array(
				"status" => STATUS_CODE::SUCCESS_NORMAL,
				"data_count" => count($rows),
				"raw" => $rows	// each record key is reason, count
			);
			echo json_encode($result, 0);
		}
		break;
	case "sur_case":
		if (empty($_POST["id"])) {
			$log->error("XHR [sur_case] 查詢ID為空值");
			echoErrorJSONString();
			break;
		}
		$log->info("XHR [sur_case] 查詢測量案件【".$_POST["id"]."】請求");
		$row = $query->getSurCaseDetail($_POST["id"]);
		if (empty($row)) {
			$log->info("XHR [sur_case] 查無資料");
			echoErrorJSONString();
		} else {
			$data = new SurCaseData($row);
			$log->info("XHR [sur_case] 查詢成功");
			echo $data->getJsonHtmlData();
		}
		break;
	case "fix_sur_delay_case":
		if (empty($_POST["id"])) {
			$log->error("XHR [fix_sur_delay_case] 查詢ID為空值");
			echoErrorJSONString();
			break;
		}
		$log->info("XHR [fix_sur_delay_case] 修正測量延期案件【".$_POST["id"].", ".$_POST["UPD_MM22"].", ".$_POST["CLR_DELAY"]."】請求");
		$result_flag = $query->fixSurDelayCase($_POST["id"], $_POST["UPD_MM22"], $_POST["CLR_DELAY"]);
		if ($result_flag) {
			$result = array(
				"status" => STATUS_CODE::SUCCESS_NORMAL,
				"data_count" => "0",
				"raw" => $result_flag
			);
			$log->info("XHR [fix_sur_delay_case] 更新成功");
			echo json_encode($result, 0);
		} else {
			$log->info("XHR [fix_sur_delay_case] 更新失敗【".$_POST["id"]."】");
			echoErrorJSONString("更新失敗【".$_POST["id"]."】");
		}
		break;
	case "prc_case":
		$log->info("XHR [prc_case] 查詢地價案件【".$_POST["id"]."】請求");
		$rows = $query->getPrcCaseAll($_POST["id"]);
		if (empty($rows)) {
			$log->info("XHR [prc_case] 查無資料");
			echoErrorJSONString();
		} else {
			$data = new PrcAllCasesData($rows);
			$log->info("XHR [prc_case] 查詢成功");
			echo json_encode(array(
				"status" => STATUS_CODE::SUCCESS_NORMAL,
				"data_count" => count($rows),
				"html" => $data->getTableHtml()
			), 0);
		}
		break;
	case "expac":
		$log->info("XHR [expac] 查詢規費收費項目【".$_POST["year"].", ".$_POST["num"]."】請求");
		// make total number length is 7
		$rows = $query->getExpacItems($_POST["year"], str_pad($_POST["num"], 7, '0', STR_PAD_LEFT));
		if (empty($rows)) {
			$log->info("XHR [expac] 查無資料");
			echoErrorJSONString();
		} else {
			$result = array(
				"status" => STATUS_CODE::SUCCESS_NORMAL,
				"data_count" => count($rows),
				"query_string" => "year=".$_POST["year"]."&num=".$_POST["num"],
				"raw" => $rows
			);
			$log->info("XHR [expac] 查詢成功");
			echo json_encode($result, 0);
		}
		break;
	case "mod_expac":
		$log->info("XHR [mod_expac] 修正規費項目【".$_POST["year"].", ".$_POST["num"].", ".$_POST["code"].", ".$_POST["amount"]."】請求");
		// make total number length is 7
		$result_flag = $query->modifyExpacItem($_POST["year"], str_pad($_POST["num"], 7, '0', STR_PAD_LEFT), $_POST["code"], $_POST["amount"]);
		if ($result_flag) {
			$result = array(
				"status" => STATUS_CODE::SUCCESS_NORMAL,
				"data_count" => "0",
				"raw" => $result_flag
			);
			$log->info("XHR [mod_expac] 更新成功");
			echo json_encode($result, 0);
		} else {
			$log->error("XHR [mod_expac] 更新失敗【".$_POST["year"].", ".$_POST["num"].", ".$_POST["code"].", ".$_POST["amount"]."】");
			echoErrorJSONString("更新失敗【".$_POST["year"].", ".$_POST["num"].", ".$_POST["code"].", ".$_POST["amount"]."】");
		}
		break;
	case "expaa":
		$log->info("XHR [expaa] 查詢規費資料【".$_POST["qday"].", ".$_POST["num"]."】請求");
		// make total number length is 7
		$rows = $query->getExpaaData($_POST["qday"], empty($_POST["num"]) ? "" : str_pad($_POST["num"], 7, '0', STR_PAD_LEFT));
		if (empty($rows)) {
			$log->info("XHR [expaa] 查無資料。【".$_POST["qday"].", ".$_POST["num"]."】");
			echoErrorJSONString("查無資料。【".$_POST["qday"].", ".$_POST["num"]."】");
		} else if (count($rows) == 1 && $_POST["list_mode"] == "false") {
			$mapping = array();
			// AA39 is 承辦人員, AA89 is 修改人員代碼
			$users = GetDBUserMapping();
			foreach ($rows[0] as $key => $value) {
				if (is_null($value)) {
					continue;
				}
				$col_mapping = include("./include/Config.ColsNameMapping.EXPAA.php");
				if (empty($col_mapping[$key])) {
					$mapping[$key] = $value;
				} else {
					$mapping[$col_mapping[$key]] = ($key == "AA39" || $key == "AA89") ? $users[$value]."【${value}】" : $value;
				}
			}
			$result = array(
				"status" => STATUS_CODE::SUCCESS_NORMAL,
				"data_count" => count($rows),
				"query_string" => "qday=".$_POST["qday"]."&num=".$_POST["num"],
				"raw" => $mapping
			);
			$log->info("XHR [expaa] 查詢 ".$_POST["qday"]." 電腦給號 ".$_POST["num"]." 成功");
			echo json_encode($result, 0);
		} else {
			$result = array(
				"status" => STATUS_CODE::SUCCESS_WITH_MULTIPLE_RECORDS,
				"data_count" => count($rows),
				"message" => "於 ".$_POST["qday"]." 找到 ".count($rows)." 筆資料",
				"query_string" => "qday=".$_POST["qday"],
				"raw" => $rows
			);
			$log->info("XHR [expaa] 於 ".$_POST["qday"]." 找到 ".count($rows)." 筆資料");
			echo json_encode($result, 0);
		}
		break;
	case "expaa_AA09_update":
	case "expaa_AA100_update":
		$column = $_POST["type"] == "expaa_AA09_update" ? "AA09" : "AA100";
		$log->info("XHR [expaa_AA09_update/expaa_AA100_update] 修正規費資料【$column".", ".$_POST["date"].", ".$_POST["number"].", ".$_POST["update_value"]."】請求");
		$result_flag = $query->updateExpaaData($column, $_POST["date"], str_pad($_POST["number"], 7, '0', STR_PAD_LEFT), $_POST["update_value"]);
		if ($result_flag) {
			$result = array(
				"status" => STATUS_CODE::SUCCESS_NORMAL,
				"data_count" => "0",
				"raw" => $result_flag,
				"message" => "更新 ${column} 成功"
			);
			$log->info("XHR [expaa_AA09_update/expaa_AA100_update] 更新 ${column} 成功");
			echo json_encode($result, 0);
		} else {
			$log->error("XHR [expaa_AA09_update/expaa_AA100_update] 更新規費欄位失敗【".$_POST["date"].", ".$_POST["number"].", ".$column.", ".$_POST["update_value"]."】");
			echoErrorJSONString("更新規費欄位失敗【".$_POST["date"].", ".$_POST["number"].", ".$column.", ".$_POST["update_value"]."】");
		}
		break;
	case "get_dummy_ob_fees":
		$log->info("XHR [get_dummy_ob_fees] 查詢作廢規費假資料 請求");
		$rows = $query->getDummyObFees();
		$len = count($rows);
		if ($len > 0) {
			$result = array(
				"status" => STATUS_CODE::SUCCESS_NORMAL,
				"data_count" => $len,
				"raw" => $rows,
				"message" => "更新 ${column} 成功"
			);
			$log->info("XHR [get_dummy_ob_fees] 取得 ${len} 件假資料");
			echo json_encode($result, 0);
		} else {
			$log->error("XHR [get_dummy_ob_fees] 本年度(${this_year})查無作廢規費假資料");
			echoErrorJSONString("本年度(${this_year})查無作廢規費假資料");
		}
		break;
	case "add_dummy_ob_fees":
		$log->info("XHR [add_dummy_ob_fees] 新增作廢規費假資料 請求");
		$result_flag = $query->addDummyObFees($_POST["today"], $_POST["pc_number"], $_POST["operator"], $_POST["fee_number"], $_POST["reason"]);
		if ($result_flag) {
			$result = array(
				"status" => STATUS_CODE::SUCCESS_NORMAL,
				"data_count" => "0",
				"pc_number" => $_POST["pc_number"],
				"raw" => $result_flag,
				"message" => "新增假資料成功【".$_POST["today"].", ".$_POST["pc_number"]."】"
			);
			$log->info("XHR [add_dummy_ob_fees] 新增假資料成功");
			echo json_encode($result, 0);
		} else {
			$log->error("XHR [add_dummy_ob_fees] 新增假資料失敗【".$_POST["today"].", ".$_POST["pc_number"].", ".$_POST["operator"].", ".$_POST["fee_number"].", ".$_POST["reason"]."】");
			echoErrorJSONString("新增假資料失敗【".$_POST["today"].", ".$_POST["pc_number"].", ".$_POST["operator"].", ".$_POST["fee_number"].", ".$_POST["reason"]."】");
		}
		break;
	case "diff_xcase":
		$log->info("XHR [diff_xcase] 查詢同步案件資料【".$_POST["id"]."】請求");
		$rows = $query->getXCaseDiff($_POST["id"]);
		if ($rows === -1) {
			$log->warning("XHR [diff_xcase] 參數格式錯誤【".$_POST["id"]."】");
			echoErrorJSONString("參數格式錯誤【".$_POST["id"]."】");
		} else if ($rows === -2) {
			$log->warning("XHR [diff_xcase] 遠端查無資料【".$_POST["id"]."】");
			echoErrorJSONString("遠端查無資料【".$_POST["id"]."】", STATUS_CODE::FAIL_WITH_REMOTE_NO_RECORD);
		} else if ($rows === -3) {
			$log->warning("XHR [diff_xcase] 本地查無資料【".$_POST["id"]."】");
			echoErrorJSONString("本地查無資料【".$_POST["id"]."】", STATUS_CODE::FAIL_WITH_LOCAL_NO_RECORD);
		} else if (is_array($rows) && empty($rows)) {
			$log->info("XHR [diff_xcase] 遠端資料與本所一致【".$_POST["id"]."】");
			echoErrorJSONString("遠端資料與本所一致【".$_POST["id"]."】");
		} else {
			$result = array(
				"status" => STATUS_CODE::SUCCESS_NORMAL,
				"data_count" => count($rows),
				"query_string" => "id=".$_POST["id"],
				"raw" => $rows
			);
			$log->info("XHR [diff_xcase] 比對成功");
			echo json_encode($result, 0);
		}
		break;
	case "inst_xcase":
		$log->info("XHR [inst_xcase] 插入遠端案件【".$_POST["id"]."】請求");
		$result_flag = $query->instXCase($_POST["id"]);
		if ($result_flag === true) {
			$result = array(
				"status" => STATUS_CODE::SUCCESS_NORMAL,
				"data_count" => "0",
				"raw" => $result_flag
			);
			$log->info("XHR [inst_xcase] 新增成功");
			echo json_encode($result, 0);
		} else if ($result_flag === -1) {
			$log->error("XHR [inst_xcase] 傳入ID錯誤，新增失敗【".$_POST["id"]."】");
			echoErrorJSONString("傳入ID錯誤，新增失敗【".$_POST["id"]."】");
		} else if ($result_flag === -2) {
			$log->error("XHR [inst_xcase] 遠端無案件資料，新增失敗【".$_POST["id"]."】");
			echoErrorJSONString("遠端無案件資料，新增失敗【".$_POST["id"]."】");
		} else {
			$log->error("XHR [inst_xcase] 新增失敗【".$_POST["id"]."】");
			echoErrorJSONString("新增失敗【".$_POST["id"]."】");
		}
		break;
	case "sync_xcase":
		$log->info("XHR [sync_xcase] 同步遠端案件【".$_POST["id"]."】請求");
		$result_flag = $query->syncXCase($_POST["id"]);
		if ($result_flag) {
			$result = array(
				"status" => STATUS_CODE::SUCCESS_NORMAL,
				"data_count" => "0",
				"raw" => $result_flag
			);
			$log->info("XHR [sync_xcase] 同步成功【".$_POST["id"]."】");
			echo json_encode($result, 0);
		} else {
			$log->error("XHR [sync_xcase] 同步失敗【".$_POST["id"]."】");
			echoErrorJSONString("同步失敗【".$_POST["id"]."】");
		}
		break;
	case "sync_xcase_column":
	$log->info("XHR [sync_xcase_column] 同步遠端案件之特定欄位【".$_POST["id"].", ".$_POST["column"]."】請求");
		$result_flag = $query->syncXCaseColumn($_POST["id"], $_POST["column"]);
		if ($result_flag) {
			$result = array(
				"status" => STATUS_CODE::SUCCESS_NORMAL,
				"data_count" => "0",
				"raw" => $result_flag
			);
			$log->info("XHR [sync_xcase_column] 同步成功【".$_POST["id"].", ".$_POST["column"]."】");
			echo json_encode($result, 0);
		} else {
			$log->error("XHR [sync_xcase_column] 同步失敗【".$_POST["id"].", ".$_POST["column"]."】");
			echoErrorJSONString("同步失敗【".$_POST["id"].", ".$_POST["column"]."】");
		}
		break;
	case "announcement_data":
		$log->info("XHR [announcement_data] 查詢公告資料請求");
		$rows = $query->getAnnouncementData();
		$result = array(
			"status" => STATUS_CODE::SUCCESS_NORMAL,
			"data_count" => count($rows),
			"raw" => $rows
		);
		$log->info("XHR [announcement_data] 取得公告資料成功");
		echo json_encode($result, 0);
		break;
	case "update_announcement_data":
		$log->info("XHR [update_announcement_data] 更新公告資料【".$_POST["code"].",".$_POST["day"].",".$_POST["flag"]."】請求");
		$result_flag = $query->updateAnnouncementData($_POST["code"], $_POST["day"], $_POST["flag"]);
		if ($result_flag) {
			$result = array(
				"status" => STATUS_CODE::SUCCESS_NORMAL,
				"data_count" => "0",
				"raw" => $result_flag
			);
			$log->info("XHR [update_announcement_data] 更新公告期限成功");
			echo json_encode($result, 0);
		} else {
			$log->error("XHR [update_announcement_data] 更新公告期限失敗【".$_POST["code"].", ".$_POST["day"].", ".$_POST["flag"]."】");
			echoErrorJSONString("更新公告期限失敗【".$_POST["code"].", ".$_POST["day"].", ".$_POST["flag"]."】");
		}
		break;
	case "clear_announcement_flag":
		$log->info("XHR [clear_announcement_flag] 清除先行准登旗標請求");
		$result_flag = $query->clearAnnouncementFlag();
		if ($result_flag) {
			$result = array(
				"status" => STATUS_CODE::SUCCESS_NORMAL,
				"data_count" => "0",
				"raw" => $result_flag
			);
			$log->info("XHR [clear_announcement_flag] 清除成功");
			echo json_encode($result, 0);
		} else {
			$log->error("XHR [clear_announcement_flag] 清除先行准登失敗");
			echoErrorJSONString("清除先行准登失敗");
		}
		break;
	case "query_temp_data":
		$log->info("XHR [query_temp_data] 查詢暫存資料【".$_POST["year"].", ".$_POST["code"].", ".$_POST["number"]."】請求");
		$rows = $query->getCaseTemp($_POST["year"], $_POST["code"], str_pad($_POST["number"], 6, '0', STR_PAD_LEFT));
		if (!empty($rows)) {
			$result = array(
				"status" => STATUS_CODE::SUCCESS_NORMAL,
				"data_count" => count($rows),	// how many tables that have temp data for this case
				"raw" => $rows
			);
			$log->info("XHR [query_temp_data] 查詢成功");
			echo json_encode($result, 0);
		} else {
			$log->info("XHR [query_temp_data] ".$_POST["year"]."-".$_POST["code"]."-".$_POST["number"]." 查無暫存資料");
			echoErrorJSONString("本案件查無暫存資料。");
		}
		break;
	case "clear_temp_data":
		$log->info("XHR [clear_temp_data] 清除暫存【".$_POST["year"].", ".$_POST["code"].", ".$_POST["number"].", ".$_POST["table"]."】請求");
		$result_flag = $query->clearCaseTemp($_POST["year"], $_POST["code"], str_pad($_POST["number"], 6, '0', STR_PAD_LEFT), $_POST["table"]);
		if ($result_flag) {
			$result = array(
				"status" => STATUS_CODE::SUCCESS_NORMAL,
				"data_count" => "0",
				"raw" => $result_flag,
				"query_string" => "year=".$_POST["year"]."&code=".$_POST["code"]."&number=".$_POST["number"]."&table=".$_POST["table"]
			);
			$log->info("XHR [clear_temp_data] 清除暫存資料成功");
			echo json_encode($result, 0);
		} else {
			$log->error("XHR [clear_temp_data] 清除暫存資料失敗");
			echoErrorJSONString("清除暫存資料失敗");
		}
		break;
	case "reg_upd_col":
		$log->info("XHR [reg_upd_col] 更新案件欄位【".$_POST["rm01"].", ".$_POST["rm02"].", ".$_POST["rm03"].", ".$_POST["col"].", ".$_POST["val"]."】請求");
		$result_flag = $query->updateCaseColumnData($_POST["rm01"].$_POST["rm02"].$_POST["rm03"], "MOICAS.CRSMS", $_POST["col"], $_POST["val"]);
		if ($result_flag) {
			$result = array(
				"status" => STATUS_CODE::SUCCESS_NORMAL,
				"data_count" => "0",
				"raw" => $result_flag,
				"query_string" => "RM01=".$_POST["rm01"]."&RM02=".$_POST["rm02"]."&RM03=".$_POST["rm03"]."&COL=".$_POST["col"]."&VAL=".$_POST["val"]
			);
			$log->info("XHR [reg_upd_col] 更新案件欄位成功");
			echo json_encode($result, 0);
		} else {
			$log->error("XHR [reg_upd_col] 更新案件欄位失敗");
			echoErrorJSONString("更新案件欄位失敗");
		}
		break;
	case "upd_case_column":
			$log->info("XHR [upd_case_column] 更新案件特定欄位【".$_POST["id"].", ".$_POST["table"].", ".$_POST["column"].", ".$_POST["value"]."】請求");
			$result_flag = $query->updateCaseColumnData($_POST["id"], $_POST["table"], $_POST["column"], $_POST["value"]);
			if ($result_flag) {
				$result = array(
					"status" => STATUS_CODE::SUCCESS_NORMAL,
					"data_count" => "0",
					"raw" => $result_flag,
					"query_string" => "id=".$_POST["id"]."&table=".$_POST["table"]."&column=".$_POST["column"]."&value=".$_POST["value"]
				);
				$log->info("XHR [upd_case_column] 更新".$_POST["table"].".".$_POST["column"]."欄位為「".$_POST["value"]."」成功");
				echo json_encode($result, 0);
			} else {
				$log->error("XHR [upd_case_column] 更新".$_POST["table"].".".$_POST["column"]."欄位為「".$_POST["value"]."」失敗");
				echoErrorJSONString("更新".$_POST["table"].".".$_POST["column"]."欄位為「".$_POST["value"]."」失敗");
			}
		break;
	case "zip_log":
		$log->info("XHR [zip_log] 壓縮LOG資料請求");
		zipLogs();
		$result = array(
			"status" => STATUS_CODE::SUCCESS_NORMAL,
			"data_count" => 0
		);
		$log->info("XHR [zip_log] 壓縮LOG成功");
		echo json_encode($result, 0);
		break;
	case "search_user":
		$log->info("XHR [search_user] 查詢使用者資料【".$_POST["keyword"]."】請求");
		$user_info = new UserInfo();
		$results = false;
		if (filter_var($_POST["keyword"], FILTER_VALIDATE_IP)) {
			$results = $user_info->searchByIP($_POST["keyword"]);
		}
		if (empty($results)) {
			$results = $user_info->searchByID($_POST["keyword"]);
			if (empty($results)) {
				$results = $user_info->searchByName($_POST["keyword"]);
			}
		}
		if (empty($results)) {
			echoErrorJSONString("查無 ".$_POST["keyword"]." 資料。");
			$log->info("XHR [user_info] 查無 ".$_POST["keyword"]." 資料。");
		} else {
			$result = array(
				"status" => STATUS_CODE::SUCCESS_NORMAL,
				"data_count" => count($results),
				"raw" => $results,
				"query_string" => "keyword=".$_POST["keyword"]
			);
			$log->info("XHR [user_info] 查詢 ".$_POST["keyword"]." 成功。");
			echo json_encode($result, 0);
		}
		break;
	case "user_info":
		$log->info("XHR [user_info] 查詢使用者資料【".$_POST["id"].", ".$_POST["name"]."】請求");
		$user_info = new UserInfo();
		$results = $user_info->searchByID($_POST["id"]);
		if (empty($results)) {
			$results = $user_info->searchByName($_POST["name"]);
		}
		if (empty($results)) {
			echoErrorJSONString("查無 ".$_POST["name"]." 資料。");
			$log->info("XHR [user_info] 查無 ".$_POST["name"]." 資料。");
		} else {
			$result = array(
				"status" => STATUS_CODE::SUCCESS_NORMAL,
				"data_count" => count($results),
				"raw" => $results,
				"query_string" => "id=".$_POST["id"]."&name=".$_POST["name"]
			);
			$log->info("XHR [user_info] 查詢 ".$_POST["name"]." 成功。");
			echo json_encode($result, 0);
		}
		break;
	case "send_message":
		$log->info("XHR [send_message] 送出訊息【".$_POST["title"].", ".$_POST["content"].", ".$_POST["who"]."】請求");
		$msg = new Message();
		$id = $msg->send($_POST["title"], $_POST["content"], $_POST["who"]);
		if ($id > 0) {
			$result = array(
				"status" => STATUS_CODE::SUCCESS_NORMAL,
				"data_count" => 1,
				"sn" => $id,
				"query_string" => "title=".$_POST["title"]."&content=".$_POST["content"]."&who=".$_POST["who"],
				"message" => "傳送成功 (sn: $id)"
			);
			$log->info("XHR [send_message] 給「".$_POST["who"]."」訊息「".$_POST["title"]."」已寫入內網資料庫【sn: $id 】");
			echo json_encode($result, 0);
		} else if ($id == -1) {
			echoErrorJSONString("現職人員找不到 ".$_POST["who"]." 故無法傳送訊息。");
		} else {
			echoErrorJSONString("新增 ".$_POST["title"]." 訊息失敗【${id}】。");
			$log->info("XHR [send_message] 新增「".$_POST["title"]."」訊息失敗【${id}】。");
		}
		break;
	case "unittest":
		echo '{"\u6536\u4ef6\u5b57\u865f":"108HB04064420","\u6536\u4ef6\u6642\u9593":"108-04-08 11:55:48","\u767b\u8a18\u539f\u56e0":"\u62b5\u7e73\u7a05\u6b3e","\u9650\u8fa6\u671f\u9650":"<span class=\'\'>108-04-11 11:55:48<\/span>","\u4f5c\u696d\u4eba\u54e1":"\u674e\u52dd\u96f2","\u8fa6\u7406\u60c5\u5f62":"\u521d\u5be9","\u6b0a\u5229\u4eba\u7d71\u7de8":"03732401","\u6b0a\u5229\u4eba\u59d3\u540d":"\u8ca1\u653f\u90e8\u570b\u6709\u8ca1\u7522\u7f72","\u7fa9\u52d9\u4eba\u7d71\u7de8":"A120895766","\u7fa9\u52d9\u4eba\u59d3\u540d":"\u59da\u932b\u7fd4","\u7fa9\u52d9\u4eba\u4eba\u6578":"4","\u624b\u6a5f\u865f\u78bc":"0938016860","\u4ee3\u7406\u4eba\u7d71\u7de8":"A120895766","\u4ee3\u7406\u4eba\u59d3\u540d":" \u59da\u932b\u7fd4","\u6bb5\u4ee3\u78bc":"0224","\u6bb5\u5c0f\u6bb5":"\u4e2d\u539f\u6bb5","\u5730\u865f":"1093-0001","\u5efa\u865f":"","\u4ef6\u6578":"2","\u767b\u8a18\u8655\u7406\u8a3b\u8a18":"","\u5730\u50f9\u8655\u7406\u8a3b\u8a18":"","\u8de8\u6240":null,"\u8cc7\u6599\u7ba1\u8f44\u6240":null,"\u8cc7\u6599\u6536\u4ef6\u6240":null,"tr_html":"<div>unittest</div>","raw":{"RM01":"108","RM02":"HB04","RM03":"064420","RM04":null,"RM05":null,"RM06":null,"RM07_1":"1080408","RM07_2":"115548","RM08":"1","RM09":"AZ","RM10":"03","RM11":"0224","RM12":"10930001","RM13":"1","RM14":"211","RM15":null,"RM16":null,"RM17":null,"RM18":"03732401","RM19":"\u8ca1\u653f\u90e8\u570b\u6709\u8ca1\u7522\u7f72","RM20":"1","RM21":"A120895766","RM22":"\u59da\u932b\u7fd4","RM23":"4","RM24":"A120895766","RM25":null,"RM26":"1","RM27":"24","RM28":null,"RM29_1":"1080411","RM29_2":"115548","RM30":"A","RM30_1":"HB1203","RM31":null,"RM33":null,"RM34":null,"RM35":null,"RM36":null,"RM37":null,"RM38":null,"RM39":null,"RM40":null,"RM41":null,"RM42":null,"RM43":null,"RM44_1":null,"RM44_2":null,"RM45":"HB1203","RM46_1":null,"RM46_2":null,"RM47":"HB0142","RM48_1":null,"RM48_2":null,"RM49":null,"RM49_TYPE":null,"RM49_DAY":null,"RM50":null,"RM51":null,"RM52":null,"RM52_TYPE":null,"RM52_DAY":null,"RM53_1":null,"RM53_2":null,"RM54_1":null,"RM54_2":null,"RM55":null,"RM56_1":null,"RM56_2":null,"RM57":null,"RM58_1":null,"RM58_2":null,"RM59":null,"RM60":null,"RM61":null,"RM62_1":null,"RM62_2":null,"RM63":null,"RM64":null,"RM32":"2","RM65":"A","RM65_1":"HB1135","RM66":null,"RM67":null,"RM65_2":null,"RM68":null,"RM80":null,"RM81":null,"RM82":null,"RM83":null,"RM84":null,"RM85":null,"RM86":null,"RM87":null,"RM88":null,"RM89":null,"RM25_2":null,"RM70":"U","SS_FLAG":null,"RM91":null,"RM91_1":null,"RM91_2":null,"RM90":null,"RM92":null,"RM93_1":null,"RM93_2":null,"RM93":null,"RM94":null,"RM95":null,"RM96":"HB1117","RM97":null,"RM98":null,"RM99":null,"RM100":null,"RM100_1":null,"RM101":null,"RM101_1":null,"RM91_3":null,"RM102":"0938016860","RM106":null,"RM106_1":null,"RM106_2":null,"RM107":null,"RM107_1":null,"RM107_2":null,"RM91_4":null,"RM24_OTHER":null,"RM25_OTHER":null,"RM97_OTHER":null,"RM108":null,"KCNT":"\u62b5\u7e73\u7a05\u6b3e","AB02":" \u59da\u932b\u7fd4","RM11_CNT":"\u4e2d\u539f\u6bb5"}}';
		break;
	default:
		$log->error("不支援的查詢型態【".$_POST["type"]."】");
		echoErrorJSONString("不支援的查詢型態【".$_POST["type"]."】", STATUS_CODE::UNSUPPORT_FAIL);
		break;
}
?>
