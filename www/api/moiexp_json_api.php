<?php
require_once(dirname(dirname(__FILE__))."/include/init.php");
require_once(INC_DIR."/MOIEXP.class.php");
require_once(INC_DIR."/Cache.class.php");
require_once(INC_DIR."/System.class.php");

$query = new MOIEXP();
$cache = Cache::getInstance();
$system = System::getInstance();
$mock = $system->isMockMode();

switch ($_POST["type"]) {
	case "expe":
		Logger::getInstance()->info("XHR [expe] 查詢規費收費類別請求");
		// make total number length is 7
		$rows = $mock ? $cache->get('expe') : $query->getChargeItems();
		$cache->set('expe', $rows);
		if (empty($rows)) {
			Logger::getInstance()->info("XHR [expe] 查無規費收費類別資料");
			echoJSONResponse('查無規費收費類別資料');
		} else {
			$result = array(
				"status" => STATUS_CODE::SUCCESS_NORMAL,
				"data_count" => count($rows),
				"raw" => $rows
			);
			Logger::getInstance()->info("XHR [expe] 查詢成功");
			echo json_encode($result, 0);
		}
		break;
	case "expac":
		Logger::getInstance()->info("XHR [expac] 查詢規費收費項目【".$_POST["year"].", ".$_POST["num"]."】請求");
		// make total number length is 7
		$rows = $mock ? $cache->get('expac') : $query->getExpacItems($_POST["year"], str_pad($_POST["num"], 7, '0', STR_PAD_LEFT));
		$cache->set('expac', $rows);
		if (empty($rows)) {
			Logger::getInstance()->info("XHR [expac] 查無資料");
			echoJSONResponse('查無資料');
		} else {
			$result = array(
				"status" => STATUS_CODE::SUCCESS_NORMAL,
				"data_count" => count($rows),
				"query_string" => "year=".$_POST["year"]."&num=".$_POST["num"],
				"raw" => $rows
			);
			Logger::getInstance()->info("XHR [expac] 查詢成功");
			echo json_encode($result, 0);
		}
		break;
	case "expk":
		Logger::getInstance()->info("XHR [expk] 查詢規費付款項目請求");
		// make total number length is 7
		$rows = $mock ? $cache->get('expk') : $query->getExpkItems();
		$cache->set('expk', $rows);
		if (empty($rows)) {
			Logger::getInstance()->info("XHR [expk] 查無資料");
			echoJSONResponse('查無規費付款項目資料');
		} else {
			$result = array(
				"status" => STATUS_CODE::SUCCESS_NORMAL,
				"data_count" => count($rows),
				"raw" => $rows
			);
			Logger::getInstance()->info("XHR [expk] 查詢成功");
			echo json_encode($result, 0);
		}
		break;
	case "mod_expac":
		Logger::getInstance()->info("XHR [mod_expac] 修正規費項目【".$_POST["year"].", ".$_POST["num"].", ".$_POST["code"].", ".$_POST["amount"]."】請求");
		// make total number length is 7
		$result_flag = $mock ? $cache->get('mod_expac') : $query->modifyExpacItem($_POST["year"], str_pad($_POST["num"], 7, '0', STR_PAD_LEFT), $_POST["code"], $_POST["amount"]);
		$cache->set('mod_expac', $result_flag);
		if ($result_flag) {
			$result = array(
				"status" => STATUS_CODE::SUCCESS_NORMAL,
				"data_count" => "0",
				"raw" => $result_flag
			);
			Logger::getInstance()->info("XHR [mod_expac] 更新成功");
			echo json_encode($result, 0);
		} else {
			Logger::getInstance()->error("XHR [mod_expac] 更新失敗【".$_POST["year"].", ".$_POST["num"].", ".$_POST["code"].", ".$_POST["amount"]."】");
			echoJSONResponse("更新失敗【".$_POST["year"].", ".$_POST["num"].", ".$_POST["code"].", ".$_POST["amount"]."】");
		}
		break;
	case "expaa":
		Logger::getInstance()->info("XHR [expaa] 查詢規費資料【".$_POST["qday"].", ".(array_key_exists('num', $_POST) ? $_POST["num"] : '')."】請求");
		// make total number length is 7
		$rows = $mock ? $cache->get('expaa') : $query->getExpaaData($_POST["qday"], empty($_POST["num"]) ? "" : str_pad($_POST["num"], 7, '0', STR_PAD_LEFT));
		$cache->set('expaa', $rows);
		if (empty($rows)) {
			Logger::getInstance()->info("XHR [expaa] 查無資料。【".$_POST["qday"].", ".$_POST["num"]."】");
			echoJSONResponse("查無資料。【".$_POST["qday"].", ".$_POST["num"]."】");
		} else if (count($rows) == 1 && $_POST["list_mode"] === "false") {
			$mapping = array();
			// AA39 is 承辦人員, AA89 is 修改人員代碼
			$users = $cache->getUserNames();
			foreach ($rows[0] as $key => $value) {
				if (is_null($value)) {
					continue;
				}
				$col_mapping = include(INC_DIR."/config/Config.ColsNameMapping.EXPAA.php");
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
			Logger::getInstance()->info("XHR [expaa] 查詢 ".$_POST["qday"]." 電腦給號 ".$_POST["num"]." 成功");
			echo json_encode($result, 0);
		} else {
			$result = array(
				"status" => STATUS_CODE::SUCCESS_WITH_MULTIPLE_RECORDS,
				"data_count" => count($rows),
				"message" => "於 ".$_POST["qday"]." 找到 ".count($rows)." 筆資料",
				"query_string" => "qday=".$_POST["qday"],
				"raw" => $rows
			);
			Logger::getInstance()->info("XHR [expaa] 於 ".$_POST["qday"]." 找到 ".count($rows)." 筆資料");
			echo json_encode($result, 0);
		}
		break;
	case "expaa_AA08_update":
	case "expaa_AA09_update":
	case "expaa_AA100_update":
		$column = $_POST["type"] == "expaa_AA09_update" ? "AA09" : ($_POST["type"] == "expaa_AA100_update" ? "AA100" : "AA08");
		Logger::getInstance()->info("XHR [expaa_AA08_update/expaa_AA09_update/expaa_AA100_update] 修正規費資料【$column".", ".$_POST["date"].", ".$_POST["number"].", ".$_POST["update_value"]."】請求");
		$result_flag = $mock ? $cache->get("expaa_${column}_update") : $query->updateExpaaData($column, $_POST["date"], str_pad($_POST["number"], 7, '0', STR_PAD_LEFT), $_POST["update_value"]);
		$cache->set("expaa_${column}_update", $result_flag);
		if ($result_flag) {
			$result = array(
				"status" => STATUS_CODE::SUCCESS_NORMAL,
				"data_count" => "0",
				"raw" => $result_flag,
				"message" => "更新 ${column} 成功"
			);
			Logger::getInstance()->info("XHR [expaa_AA08_update/expaa_AA09_update/expaa_AA100_update] 更新 ${column} 成功");
			echo json_encode($result, 0);
		} else {
			Logger::getInstance()->error("XHR [expaa_AA08_update/expaa_AA09_update/expaa_AA100_update] 更新規費欄位失敗【".$_POST["date"].", ".$_POST["number"].", ".$column.", ".$_POST["update_value"]."】");
			echoJSONResponse("更新規費欄位失敗【".$_POST["date"].", ".$_POST["number"].", ".$column.", ".$_POST["update_value"]."】");
		}
		break;
	case "get_dummy_ob_fees":
		Logger::getInstance()->info("XHR [get_dummy_ob_fees] 查詢作廢規費假資料 請求");
		$rows = $mock ? $cache->get('get_dummy_ob_fees') : $query->getDummyObFees();
		$cache->set('get_dummy_ob_fees', $rows);
		$len = count($rows);
		if ($len > 0) {
			$result = array(
				"status" => STATUS_CODE::SUCCESS_NORMAL,
				"data_count" => $len,
				"raw" => $rows,
				"message" => "更新 ${column} 成功"
			);
			Logger::getInstance()->info("XHR [get_dummy_ob_fees] 取得 ${len} 件假資料");
			echo json_encode($result, 0);
		} else {
			Logger::getInstance()->error("XHR [get_dummy_ob_fees] 本年度(${this_year})查無作廢規費假資料");
			echoJSONResponse("本年度(${this_year})查無作廢規費假資料");
		}
		break;
	case "add_dummy_ob_fees":
		Logger::getInstance()->info("XHR [add_dummy_ob_fees] 新增作廢規費假資料 請求");
		$result_flag = $mock ? $cache->get('add_dummy_ob_fees') : $query->addDummyObFees($_POST["today"], $_POST["pc_number"], $_POST["operator"], $_POST["fee_number"], $_POST["reason"]);
		$cache->set('add_dummy_ob_fees', $result_flag);
		if ($result_flag) {
			$result = array(
				"status" => STATUS_CODE::SUCCESS_NORMAL,
				"data_count" => "0",
				"pc_number" => $_POST["pc_number"],
				"raw" => $result_flag,
				"message" => "新增假資料成功【".$_POST["today"].", ".$_POST["pc_number"]."】"
			);
			Logger::getInstance()->info("XHR [add_dummy_ob_fees] 新增假資料成功");
			echo json_encode($result, 0);
		} else {
			Logger::getInstance()->error("XHR [add_dummy_ob_fees] 新增假資料失敗【".$_POST["today"].", ".$_POST["pc_number"].", ".$_POST["operator"].", ".$_POST["fee_number"].", ".$_POST["reason"]."】");
			echoJSONResponse("新增假資料失敗【".$_POST["today"].", ".$_POST["pc_number"].", ".$_POST["operator"].", ".$_POST["fee_number"].", ".$_POST["reason"]."】");
		}
		break;
	default:
		Logger::getInstance()->error("不支援的查詢型態【".$_POST["type"]."】");
		echoJSONResponse("不支援的查詢型態【".$_POST["type"]."】", STATUS_CODE::UNSUPPORT_FAIL);
		break;
}
