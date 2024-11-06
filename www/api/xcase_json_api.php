<?php
require_once(dirname(dirname(__FILE__))."/include/init.php");
require_once(INC_DIR."/Cache.class.php");
require_once(INC_DIR."/System.class.php");
require_once(INC_DIR."/XCase.class.php");

$xcase = new XCase();
$cache = Cache::getInstance();
$system = System::getInstance();
$mock = $system->isMockMode();

switch ($_POST["type"]) {
	case "xcase-check":
		Logger::getInstance()->info("XHR [xcase-check] 查詢登記案件跨所註記遺失請求");
		$query_result = $mock ? $cache->get('xcase-check') : $xcase->getProblematicXCases();
			
		$case_ids = [];
		$rows = [];
		foreach ($query_result as $row) {
			// if (preg_match("/^H[A-Z]{2}1$/i", $row['RM02'])) {
				$case_ids[] = $row['RM01'].'-'.$row['RM02'].'-'.$row['RM03'];
				$rows[] = $row;
			// }
		}

		$cache->set('xcase-check', $rows);
		if (empty($case_ids)) {
			Logger::getInstance()->info("XHR [xcase-check] 查無資料");
			echoJSONResponse('查無資料');
		} else {
			$result = array(
				"status" => STATUS_CODE::SUCCESS_NORMAL,
				"case_ids" => $case_ids,
				"data_count" => count($rows),
				"raw" => $rows
			);
			
			Logger::getInstance()->info("XHR [xcase-check] 找到".count($rows)."件登記案件遺失註記");
			echo json_encode($result, 0);
		}
		break;
	case "val-xcase-check":
		Logger::getInstance()->info("XHR [val-xcase-check] 查詢地價案件跨所註記遺失請求");
		$query_result = $mock ? $cache->get('val-xcase-check') : $xcase->getPSCRNProblematicXCases();
			
		$case_ids = [];
		$rows = [];
		foreach ($query_result as $row) {
			if (preg_match("/^H[A-Z]{2}1$/i", $row['SS04_1'])) {
				$case_ids[] = $row['SS03'].'-'.$row['SS04_1'].'-'.$row['SS04_2'];
				$rows[] = $row;
			}
		}

		$cache->set('val-xcase-check', $rows);
		if (empty($case_ids)) {
			Logger::getInstance()->info("XHR [val-xcase-check] 查無資料");
			echoJSONResponse('查無資料');
		} else {
			$result = array(
				"status" => STATUS_CODE::SUCCESS_NORMAL,
				"case_ids" => $case_ids,
				"data_count" => count($rows),
				"raw" => $rows
			);
			
			Logger::getInstance()->info("XHR [val-xcase-check] 找到".count($rows)."件地價案件遺失註記");
			echo json_encode($result, 0);
		}
		break;
	case "fix_xcase":
		Logger::getInstance()->info("XHR [fix_xcase] 修正登記案件跨所註記遺失【".$_POST["id"]."】請求");
		$result_flag = $mock ? $cache->get('fix_xcase') : $xcase->fixProblematicXCases($_POST["id"]);
		$cache->set('fix_xcase', $result_flag);
		if ($result_flag) {
			$result = array(
				"status" => STATUS_CODE::SUCCESS_NORMAL,
				"data_count" => "0",
				"raw" => $result_flag
			);
			echo json_encode($result, 0);
		} else {
			Logger::getInstance()->error("XHR [fix_xcase] 更新失敗【".$_POST["id"]."】");
			echoJSONResponse("更新失敗【".$_POST["id"]."】");
		}
		break;
	case "fix_xcase_val":
		Logger::getInstance()->info("XHR [fix_xcase_val] 修正地價案件跨所註記遺失【".$_POST["id"]."】請求");
		$result_flag = $mock ? $cache->get('fix_xcase_val') : $xcase->fixPSCRNProblematicXCases($_POST["id"]);
		$cache->set('fix_xcase_val', $result_flag);
		if ($result_flag) {
			$result = array(
				"status" => STATUS_CODE::SUCCESS_NORMAL,
				"data_count" => "0",
				"raw" => $result_flag
			);
			echo json_encode($result, 0);
		} else {
			Logger::getInstance()->error("XHR [fix_xcase_val] 更新失敗【".$_POST["id"]."】");
			echoJSONResponse("更新失敗【".$_POST["id"]."】");
		}
		break;
	case "diff_xcase":
		Logger::getInstance()->info("XHR [diff_xcase] 查詢同步案件資料【".$_POST["id"]."】請求");
		$rows = $mock ? $cache->get('diff_xcase') : $xcase->getXCaseDiff($_POST["id"]);
		$cache->set('diff_xcase', $rows);
		if ($rows === -1) {
			Logger::getInstance()->warning("XHR [diff_xcase] 參數格式錯誤【".$_POST["id"]."】");
			echoJSONResponse("參數格式錯誤【".$_POST["id"]."】");
		} else if ($rows === -2) {
			Logger::getInstance()->warning("XHR [diff_xcase] 遠端查無資料【".$_POST["id"]."】");
			echoJSONResponse("遠端查無資料【".$_POST["id"]."】", STATUS_CODE::FAIL_WITH_REMOTE_NO_RECORD);
		} else if ($rows === -3) {
			Logger::getInstance()->warning("XHR [diff_xcase] 本地查無資料【".$_POST["id"]."】");
			echoJSONResponse("本地查無資料【".$_POST["id"]."】", STATUS_CODE::FAIL_WITH_LOCAL_NO_RECORD);
		} else if (is_array($rows) && empty($rows)) {
			Logger::getInstance()->info("XHR [diff_xcase] 遠端資料與本所一致【".$_POST["id"]."】");
			echoJSONResponse("遠端資料與本所一致【".$_POST["id"]."】");
		} else {
			$result = array(
				"status" => STATUS_CODE::SUCCESS_NORMAL,
				"data_count" => count($rows),
				"query_string" => "id=".$_POST["id"],
				"raw" => $rows
			);
			Logger::getInstance()->info("XHR [diff_xcase] 比對成功");
			echo json_encode($result, 0);
		}
		break;
	case "inst_xcase":
		Logger::getInstance()->info("XHR [inst_xcase] 插入遠端案件【".$_POST["id"]."】請求");
		$result_flag = $mock ? $cache->get('inst_xcase') : $xcase->instXCase($_POST["id"]);
		$cache->set('inst_xcase', $result_flag);
		if ($result_flag === true) {
			$result = array(
				"status" => STATUS_CODE::SUCCESS_NORMAL,
				"data_count" => "0",
				"raw" => $result_flag
			);
			Logger::getInstance()->info("XHR [inst_xcase] 新增成功");
			echo json_encode($result, 0);
		} else if ($result_flag === -1) {
			Logger::getInstance()->error("XHR [inst_xcase] 傳入ID錯誤，新增失敗【".$_POST["id"]."】");
			echoJSONResponse("傳入ID錯誤，新增失敗【".$_POST["id"]."】");
		} else if ($result_flag === -2) {
			Logger::getInstance()->error("XHR [inst_xcase] 遠端無案件資料，新增失敗【".$_POST["id"]."】");
			echoJSONResponse("遠端無案件資料，新增失敗【".$_POST["id"]."】");
		} else {
			Logger::getInstance()->error("XHR [inst_xcase] 新增失敗【".$_POST["id"]."】");
			echoJSONResponse("新增失敗【".$_POST["id"]."】");
		}
		break;
	case "sync_xcase":
		Logger::getInstance()->info("XHR [sync_xcase] 同步遠端案件【".$_POST["id"]."】請求");
		$result_flag = $mock ? $cache->get('sync_xcase') : $xcase->syncXCase($_POST["id"]);
		$cache->set('sync_xcase', $result_flag);
		if ($result_flag) {
			$result = array(
				"status" => STATUS_CODE::SUCCESS_NORMAL,
				"data_count" => "0",
				"raw" => $result_flag
			);
			Logger::getInstance()->info("XHR [sync_xcase] 同步成功【".$_POST["id"]."】");
			echo json_encode($result, 0);
		} else {
			Logger::getInstance()->error("XHR [sync_xcase] 同步失敗【".$_POST["id"]."】");
			echoJSONResponse("同步失敗【".$_POST["id"]."】");
		}
		break;
	case "sync_xcase_column":
		Logger::getInstance()->info("XHR [sync_xcase_column] 同步遠端案件之特定欄位【".$_POST["id"].", ".$_POST["column"]."】請求");
		$result_flag = $mock ? $cache->get('sync_xcase_column') : $xcase->syncXCaseColumn($_POST["id"], $_POST["column"]);
		$cache->set('sync_xcase_column', $result_flag);
		if ($result_flag) {
			$result = array(
				"status" => STATUS_CODE::SUCCESS_NORMAL,
				"data_count" => "0",
				"raw" => $result_flag
			);
			Logger::getInstance()->info("XHR [sync_xcase_column] 同步成功【".$_POST["id"].", ".$_POST["column"]."】");
			echo json_encode($result, 0);
		} else {
			Logger::getInstance()->error("XHR [sync_xcase_column] 同步失敗【".$_POST["id"].", ".$_POST["column"]."】");
			echoJSONResponse("同步失敗【".$_POST["id"].", ".$_POST["column"]."】");
		}
		break;
	case "check_xcase_fix_data":
		Logger::getInstance()->info("XHR [check_xcase_fix_data] 檢查遠端案件之補正連結資料【".$_POST["id"]."】請求");
		$xcase = new XCase();
		$response = $mock ? $cache->get('check_xcase_fix_data') : $xcase->getXCaseCRCLD($_POST["id"]);
		$cache->set('sync_xcase_fix_data', $response);
		$message = "局端有 ".$_POST["id"]."補正連結資料。";
		if ($response === -1) {
			Logger::getInstance()->info("XHR [check_xcase_fix_data] 局端L3資料庫無法連線【".$_POST["id"]."】");
			echoJSONResponse("局端L3資料庫無法連線【".$_POST["id"]."】");
		} else if ($response === -2) {
			Logger::getInstance()->info("XHR [check_xcase_fix_data] 案件代碼不正確【".$_POST["id"]."】");
			echoJSONResponse("案件代碼不正確【".$_POST["id"]."】");
		} else if ($response === -3) {
			Logger::getInstance()->info("XHR [check_xcase_fix_data] 局端無補正連結資料【".$_POST["id"]."】");
			echoJSONResponse("局端無補正連結資料【".$_POST["id"]."】");
		} else {
			Logger::getInstance()->error("XHR [check_xcase_fix_data] 局端案件補正連結取的成功【".$_POST["id"]."】");
			echoJSONResponse("局端案件補正連結取的成功【".$_POST["id"]."】", STATUS_CODE::SUCCESS_NORMAL, array(
				"raw" => mb_convert_encoding($response, 'UTF-8', 'BIG-5')
			));
		}
		break;
	case "sync_xcase_fix_data":
		Logger::getInstance()->info("XHR [sync_xcase_fix_data] 同步遠端案件之補正資料【".$_POST["id"]."】請求");
		$xcase = new XCase();
		$response = $mock ? $cache->get('sync_xcase_fix_data') : $xcase->syncXCaseFixData($_POST["id"]);
		$cache->set('sync_xcase_fix_data', $response);
		if ($response !== false) {
			Logger::getInstance()->info("XHR [sync_xcase_fix_data] 同步補正資料成功【".$_POST["id"]."】");
			echoJSONResponse("同步補正資料成功【".$_POST["id"]."】", STATUS_CODE::SUCCESS_NORMAL, array(
				"raw" => mb_convert_encoding($response, 'UTF-8', 'BIG-5')
			));
		} else {
			Logger::getInstance()->error("XHR [sync_xcase_fix_data] 同步補正資料失敗【".$_POST["id"]."】");
			echoJSONResponse("同步補正資料失敗【".$_POST["id"]."】");
		}
		break;
	case "get_xcase_fix_data":
		Logger::getInstance()->info("XHR [get_xcase_fix_data] 同步遠端案件之補正資料【".$_POST["id"]."】請求");
		$xcase = new XCase();
		$response = $mock ? $cache->get('sync_xcase_fix_data') : $xcase->getXCaseFixData($_POST["id"]);
		$cache->set('get_xcase_fix_data', $response);
		if ($response !== false) {
			Logger::getInstance()->info("XHR [get_xcase_fix_data] 取得遠端同步補正資料成功【".$_POST["id"]."】");
			echoJSONResponse("取得遠端同步補正資料成功【".$_POST["id"]."】", STATUS_CODE::SUCCESS_NORMAL, array(
				"raw" => mb_convert_encoding($response, 'UTF-8', 'BIG-5')
			));
		} else {
			Logger::getInstance()->error("XHR [get_xcase_fix_data] 取得遠端同步補正資料失敗【".$_POST["id"]."】");
			echoJSONResponse("取得遠端補正資料失敗【".$_POST["id"]."】");
		}
		break;
	default:
		Logger::getInstance()->error("不支援的查詢型態【".$_POST["type"]."】");
		echoJSONResponse("不支援的查詢型態【".$_POST["type"]."】", STATUS_CODE::UNSUPPORT_FAIL);
		break;
}
