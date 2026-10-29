<?php
require_once(dirname(dirname(__FILE__))."/include/init.php");
require_once(INC_DIR."/Cache.class.php");
require_once(INC_DIR."/System.class.php");
require_once(INC_DIR."/XCase.class.php");
require_once(INC_DIR."/Notification.class.php");

if (!headers_sent()) {
    header('Content-Type: application/json; charset=utf-8');
}

$xcase = new XCase();
$cache = Cache::getInstance();
$system = System::getInstance();
$mock = $system->isMockMode();
$type = $_POST["type"] ?? '';
$id = $_POST["id"] ?? '';
$column = $_POST["column"] ?? '';
$effective_ip = $client_ip ?? $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

switch ($type) {
	case "xcase-check":
		Logger::getInstance()->info("XHR [xcase-check] 查詢登記案件跨所註記遺失請求");
		$query_result = $mock ? ($cache->get('xcase-check') ?: []) : $xcase->getProblematicXCases();
		if (!is_array($query_result)) $query_result = [];
			
		$case_ids = [];
		$rows = [];
		foreach ($query_result as $row) {
			if (is_array($row)) {
				$rm01 = $row['RM01'] ?? '';
				$rm02 = $row['RM02'] ?? '';
				$rm03 = $row['RM03'] ?? '';
				$case_ids[] = $rm01.'-'.$rm02.'-'.$rm03;
				$rows[] = $row;
			}
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
		$query_result = $mock ? ($cache->get('val-xcase-check') ?: []) : $xcase->getPSCRNProblematicXCases();
		if (!is_array($query_result)) $query_result = [];
			
		$case_ids = [];
		$rows = [];
		foreach ($query_result as $row) {
			if (is_array($row) && preg_match("/^H[A-Z]{2}1$/i", $row['SS04_1'] ?? '')) {
				$case_ids[] = ($row['SS03'] ?? '').'-'.($row['SS04_1'] ?? '').'-'.($row['SS04_2'] ?? '');
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
		Logger::getInstance()->info("XHR [fix_xcase] 修正登記案件跨所註記遺失【".$id."】請求");
		$result_flag = $mock ? ($cache->get('fix_xcase') ?? true) : $xcase->fixProblematicXCases($id);
		$cache->set('fix_xcase', $result_flag);
		if ($result_flag) {
			try {
				$notify = new Notification();
				$channel = 'inf';
				Logger::getInstance()->info('新增「修正登記案件跨所註記」訊息至 '.$channel.' 頻道。');
				$md_case_link = getMDCaseLink($id);
				$notify->addMessage($channel, array(
						'title' => '修正登記案件跨所註記',
						'content' => "##### ✔ 已修正 $md_case_link 登記案件之跨所註記。",
						'priority' => 3,
						'sender' => '系統管理者',
						'from_ip' => $effective_ip
				));
			} catch (\Throwable $e) {
				Logger::getInstance()->error("XHR [fix_xcase] 無法新增通知訊息至 inf 頻道。【".$id."】 ".$e->getMessage());
			}
			$result = array(
				"status" => STATUS_CODE::SUCCESS_NORMAL,
				"data_count" => "0",
				"raw" => $result_flag
			);
			echo json_encode($result, 0);
		} else {
			Logger::getInstance()->error("XHR [fix_xcase] 更新失敗【".$id."】");
			echoJSONResponse("更新失敗【".$id."】");
		}
		break;
	case "fix_xcase_val":
		Logger::getInstance()->info("XHR [fix_xcase_val] 修正地價案件跨所註記遺失【".$id."】請求");
		$result_flag = $mock ? ($cache->get('fix_xcase_val') ?? true) : $xcase->fixPSCRNProblematicXCases($id);
		$cache->set('fix_xcase_val', $result_flag);
		if ($result_flag) {
			try {
				$notify = new Notification();
				$channel = 'inf';
				Logger::getInstance()->info('新增「修正地價案件跨所註記」訊息至 '.$channel.' 頻道。');
				$md_case_link = getMDCaseLink($id);
				$notify->addMessage($channel, array(
						'title' => '修正地價案件跨所註記',
						'content' => "##### ✔ 已修正 $md_case_link 地價案件之跨所註記。",
						'priority' => 3,
						'sender' => '系統管理者',
						'from_ip' => $effective_ip
				));
			} catch (\Throwable $e) {
				Logger::getInstance()->error("XHR [fix_xcase_val] 無法新增通知訊息至 inf 頻道。【".$id."】 ".$e->getMessage());
			}
			$result = array(
				"status" => STATUS_CODE::SUCCESS_NORMAL,
				"data_count" => "0",
				"raw" => $result_flag
			);
			echo json_encode($result, 0);
		} else {
			Logger::getInstance()->error("XHR [fix_xcase_val] 更新失敗【".$id."】");
			echoJSONResponse("更新失敗【".$id."】");
		}
		break;
	case "diff_xcase":
		Logger::getInstance()->info("XHR [diff_xcase] 查詢同步案件資料【".$id."】請求");
		$rows = $mock ? ($cache->get('diff_xcase') ?? []) : $xcase->getXCaseDiff($id);
		$cache->set('diff_xcase', $rows);
		if ($rows === -1) {
			Logger::getInstance()->warning("XHR [diff_xcase] 參數格式錯誤【".$id."】");
			echoJSONResponse("參數格式錯誤【".$id."】");
		} else if ($rows === -2) {
			Logger::getInstance()->warning("XHR [diff_xcase] 遠端查無資料【".$id."】");
			echoJSONResponse("遠端查無資料【".$id."】", STATUS_CODE::FAIL_WITH_REMOTE_NO_RECORD);
		} else if ($rows === -3) {
			Logger::getInstance()->warning("XHR [diff_xcase] 本地查無資料【".$id."】");
			echoJSONResponse("本地查無資料【".$id."】", STATUS_CODE::FAIL_WITH_LOCAL_NO_RECORD);
		} else if (is_array($rows) && empty($rows)) {
			Logger::getInstance()->info("XHR [diff_xcase] 遠端資料與本所一致【".$id."】");
			echoJSONResponse("遠端資料與本所一致【".$id."】");
		} else {
			$result = array(
				"status" => STATUS_CODE::SUCCESS_NORMAL,
				"data_count" => is_array($rows) ? count($rows) : 0,
				"query_string" => "id=".$id,
				"raw" => $rows
			);
			Logger::getInstance()->info("XHR [diff_xcase] 比對成功");
			echo json_encode($result, 0);
		}
		break;
	case "find_xcase_writeback_failures":
		Logger::getInstance()->info("XHR [find_xcase_writeback_failures] 查詢跨所回寫失敗案件請求");
		$info = $mock ? ($cache->get('find_xcase_writeback_failures') ?: []) : $xcase->findFailureXCases();
		$cache->set('find_xcase_writeback_failures', $info);

		$found = [];
		if (is_array($info)) {
			foreach ($info as $codeArray) {
				$foundIds = (is_array($codeArray) && isset($codeArray['foundIds']) && is_array($codeArray['foundIds'])) ? $codeArray['foundIds'] : [];
				$tmp = array_merge($found, $foundIds);
				$unique_array = array_unique($tmp);
				$found = array_values($unique_array);
			}
		}

		$status = empty($found) ? STATUS_CODE::SUCCESS_WITH_NO_RECORD : STATUS_CODE::SUCCESS_WITH_MULTIPLE_RECORDS;
		$message = "找到 ".count($found)." 筆跨所回寫失敗案件";
		echoJSONResponse($message, $status, array(
			"found" => $found,
			"raw" => $info
		));
		break;
	case "inst_xcase":
		Logger::getInstance()->info("XHR [inst_xcase] 插入遠端案件【".$id."】請求");
		$result_flag = $mock ? ($cache->get('inst_xcase') ?? true) : $xcase->instXCase($id);
		$cache->set('inst_xcase', $result_flag);
		if ($result_flag === true) {
			try {
				$notify = new Notification();
				$channel = 'inf';
				Logger::getInstance()->info('XHR [inst_xcase] 新增「修正跨所案件未回寫」訊息至 '.$channel.' 頻道。');
				$md_case_link = getMDCaseLink($id);
				$notify->addMessage($channel, array(
						'title' => '修正跨所案件未回寫',
						'content' => "##### ✔ 已修正 $md_case_link 未回寫跨所登記案件。",
						'priority' => 3,
						'sender' => '系統管理者',
						'from_ip' => $effective_ip
				));
			} catch (\Throwable $e) {
				Logger::getInstance()->error("XHR [inst_xcase] 無法新增通知訊息至 inf 頻道。【".$id."】 ".$e->getMessage());
			}
			$result = array(
				"status" => STATUS_CODE::SUCCESS_NORMAL,
				"data_count" => "0",
				"raw" => $result_flag
			);
			Logger::getInstance()->info("XHR [inst_xcase] 新增成功");
			echo json_encode($result, 0);
		} else if ($result_flag === -1) {
			Logger::getInstance()->error("XHR [inst_xcase] 傳入ID錯誤，新增失敗【".$id."】");
			echoJSONResponse("傳入ID錯誤，新增失敗【".$id."】");
		} else if ($result_flag === -2) {
			Logger::getInstance()->error("XHR [inst_xcase] 遠端無案件資料，新增失敗【".$id."】");
			echoJSONResponse("遠端無案件資料，新增失敗【".$id."】");
		} else {
			Logger::getInstance()->error("XHR [inst_xcase] 新增失敗【".$id."】");
			echoJSONResponse("新增失敗【".$id."】");
		}
		break;
	case "sync_xcase":
		Logger::getInstance()->info("XHR [sync_xcase] 同步遠端案件【".$id."】請求");
		$result_flag = $mock ? ($cache->get('sync_xcase') ?? true) : $xcase->syncXCase($id);
		$cache->set('sync_xcase', $result_flag);
		if ($result_flag) {
			$result = array(
				"status" => STATUS_CODE::SUCCESS_NORMAL,
				"data_count" => "0",
				"raw" => $result_flag
			);
			Logger::getInstance()->info("XHR [sync_xcase] 同步成功【".$id."】");
			echo json_encode($result, 0);
		} else {
			Logger::getInstance()->error("XHR [sync_xcase] 同步失敗【".$id."】");
			echoJSONResponse("同步失敗【".$id."】");
		}
		break;
	case "sync_xcase_column":
		Logger::getInstance()->info("XHR [sync_xcase_column] 同步遠端案件之特定欄位【".$id.", ".$column."】請求");
		$result_flag = $mock ? ($cache->get('sync_xcase_column') ?? true) : $xcase->syncXCaseColumn($id, $column);
		$cache->set('sync_xcase_column', $result_flag);
		if ($result_flag) {
			$result = array(
				"status" => STATUS_CODE::SUCCESS_NORMAL,
				"data_count" => "0",
				"raw" => $result_flag
			);
			Logger::getInstance()->info("XHR [sync_xcase_column] 同步成功【".$id.", ".$column."】");
			echo json_encode($result, 0);
		} else {
			Logger::getInstance()->error("XHR [sync_xcase_column] 同步失敗【".$id.", ".$column."】");
			echoJSONResponse("同步失敗【".$id.", ".$column."】");
		}
		break;
	case "check_xcase_fix_data":
		Logger::getInstance()->info("XHR [check_xcase_fix_data] 檢查遠端案件之補正連結資料【".$id."】請求");
		$response = $mock ? ($cache->get('check_xcase_fix_data') ?? ['cl01' => '113', 'cl02' => 'HB04', 'cl03' => '001234']) : $xcase->getXCaseCRCLD($id);
		$cache->set('check_xcase_fix_data', $response);
		if ($response === -1) {
			Logger::getInstance()->info("XHR [check_xcase_fix_data] 局端L3資料庫無法連線【".$id."】");
			echoJSONResponse("局端L3資料庫無法連線【".$id."】");
		} else if ($response === -2) {
			Logger::getInstance()->info("XHR [check_xcase_fix_data] 案件代碼不正確【".$id."】");
			echoJSONResponse("案件代碼不正確【".$id."】");
		} else if ($response === -3) {
			Logger::getInstance()->info("XHR [check_xcase_fix_data] 局端無補正連結資料【".$id."】");
			echoJSONResponse("局端無補正連結資料【".$id."】");
		} else {
			Logger::getInstance()->info("XHR [check_xcase_fix_data] 局端案件補正連結取得成功【".$id."】");
			$converted = (is_array($response) || is_string($response)) ? mb_convert_encoding($response, 'UTF-8', 'BIG-5') : $response;
			echoJSONResponse("局端案件補正連結取得成功【".$id."】", STATUS_CODE::SUCCESS_NORMAL, array(
				"raw" => $converted
			));
		}
		break;
	case "sync_xcase_fix_data":
		Logger::getInstance()->info("XHR [sync_xcase_fix_data] 同步遠端案件之補正資料【".$id."】請求");
		$response = $mock ? ($cache->get('sync_xcase_fix_data') ?? ['cr01' => '113']) : $xcase->syncXCaseFixData($id);
		$cache->set('sync_xcase_fix_data', $response);
		if ($response !== false) {
			Logger::getInstance()->info("XHR [sync_xcase_fix_data] 同步補正資料成功【".$id."】");
			$converted = (is_array($response) || is_string($response)) ? mb_convert_encoding($response, 'UTF-8', 'BIG-5') : $response;
			echoJSONResponse("同步補正資料成功【".$id."】", STATUS_CODE::SUCCESS_NORMAL, array(
				"raw" => $converted
			));
		} else {
			Logger::getInstance()->error("XHR [sync_xcase_fix_data] 同步補正資料失敗【".$id."】");
			echoJSONResponse("同步補正資料失敗【".$id."】");
		}
		break;
	case "get_xcase_fix_data":
		Logger::getInstance()->info("XHR [get_xcase_fix_data] 取得遠端案件之補正資料【".$id."】請求");
		$response = $mock ? ($cache->get('get_xcase_fix_data') ?? ['cr01' => '113']) : $xcase->getXCaseFixData($id);
		$cache->set('get_xcase_fix_data', $response);
		if ($response !== false) {
			Logger::getInstance()->info("XHR [get_xcase_fix_data] 取得遠端同步補正資料成功【".$id."】");
			$converted = (is_array($response) || is_string($response)) ? mb_convert_encoding($response, 'UTF-8', 'BIG-5') : $response;
			echoJSONResponse("取得遠端同步補正資料成功【".$id."】", STATUS_CODE::SUCCESS_NORMAL, array(
				"raw" => $converted
			));
		} else {
			Logger::getInstance()->error("XHR [get_xcase_fix_data] 取得遠端補正資料失敗【".$id."】");
			echoJSONResponse("取得遠端補正資料失敗【".$id."】");
		}
		break;
	case "get_local_fix_data":
		Logger::getInstance()->info("XHR [get_local_fix_data] 取得本地案件之補正資料【".$id."】請求");
		$local_crcld = $mock ? ($cache->get('get_local_crcld') ?? ['cl01' => '113']) : $xcase->getLocalCRCLD($id);
		$cache->set('get_local_crcld', $local_crcld);
		if (is_array($local_crcld)) {
			$response = $mock ? ($cache->get('get_local_fix_data') ?? ['cr01' => '113']) : $xcase->getLocalCRCRD($local_crcld);
			$cache->set('get_local_fix_data', $response);
			if ($response !== false) {
				Logger::getInstance()->info("XHR [get_local_fix_data] 取得本地同步補正資料成功【".$id."】");
				$converted = (is_array($response) || is_string($response)) ? mb_convert_encoding($response, 'UTF-8', 'BIG-5') : $response;
				echoJSONResponse("取得本地同步補正資料成功【".$id."】", STATUS_CODE::SUCCESS_NORMAL, array(
					"raw" => $converted
				));
			} else {
				Logger::getInstance()->error("XHR [get_local_fix_data] 取得本地補正資料失敗【".$id."】");
				echoJSONResponse("取得遠端補正資料失敗【".$id."】");
			}
		} else {
			$message = "取得本地補正連結資料失敗【".$id."】";
			if ($local_crcld === -2) {
				$message = "本地資料庫無【".$id."】案件之連結資料";
			}
			Logger::getInstance()->error("XHR [get_local_fix_data] $message");
			echoJSONResponse($message);
		}
		break;
	default:
		$type_str = $_POST["type"] ?? '';
		Logger::getInstance()->error("不支援的查詢型態【".$type_str."】");
		echoJSONResponse("不支援的查詢型態【".$type_str."】", STATUS_CODE::UNSUPPORT_FAIL);
		break;
}
