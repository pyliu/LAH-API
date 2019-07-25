<?php
require_once("./include/init.php");
require_once("./include/RegCaseData.class.php");
require_once("./include/PrcAllCasesData.class.php");
require_once("./include/Query.class.php");
require_once("./include/JSONAPICommandFactory.class.php");

function echoErrorJSONString($msg = "", $status = STATUS_CODE::DEFAULT_FAIL) {
	echo json_encode(array(
		"status" => $status,
		"data_count" => "0",
		"message" => empty($msg) ? "查無資料" : $msg
	), 0);
}

$query = new Query();

switch ($_POST["type"]) {
	case "x":
		$query_result = $query->getProblematicCrossCases();
		if (empty($query_result)) {
			echoErrorJSONString();
		} else {
			$row = $query_result;
			$result = array(
				"status" => STATUS_CODE::SUCCESS_NORMAL,
				"data_count" => "1",
				"收件字號" => $row["RM01"]."-".$row["RM02"]."-".$row["RM03"],
				"收件時間" => RegCaseData::toDate($row["RM07_1"])." ".RegCaseData::toDate($row["RM07_2"]),
				"raw" => $row
			);
			echo json_encode($result, 0);
		}
		break;
	case "fix_xcase":
		$result_flag = $query->fixProblematicCrossCases($_POST["id"]);
		if ($result_flag) {
			$result = array(
				"status" => STATUS_CODE::SUCCESS_NORMAL,
				"data_count" => "0",
				"raw" => $result_flag
			);
			echo json_encode($result, 0);
		} else {
			echoErrorJSONString("更新失敗【".$_POST["serial"]."】");
		}

		break;
	case "max":
		$year = $_POST["year"];
		$code = $_POST["code"];
		$max_num = $query->getMaxNumByYearWord($year, $code);
		echo json_encode(array(
			"status" => STATUS_CODE::SUCCESS_NORMAL,
			"message" => "查詢 ${year}-${code} 回傳值為 ${max_num}",
			"max" => $max_num
		), 0);
		break;
	case "ralid":
		$query_result = $query->getSectionRALIDCount($_POST["text"]);
		if (empty($query_result)) {
			echoErrorJSONString();
		} else {
			$result = array(
				"status" => STATUS_CODE::SUCCESS_NORMAL,
                "data_count" => count($query_result),
                "query_string" => $_POST["text"],
				"raw" => $query_result
			);
			echo json_encode($result, 0);
		}
		break;
	case "crsms":
		$query_result = $query->getCRSMSCasesByID($_POST["id"]);
		if (empty($query_result)) {
			echoErrorJSONString();
		} else {
			$result = array(
				"status" => STATUS_CODE::SUCCESS_NORMAL,
				"data_count" => count($query_result),
				"query_string" => $_POST["id"],
				"raw" => $query_result
			);
			echo json_encode($result, 0);
		}
		break;
	case "cmsms":
		$query_result = $query->getCMSMSCasesByID($_POST["id"]);
		if (empty($query_result)) {
			echoErrorJSONString();
		} else {
			$result = array(
				"status" => STATUS_CODE::SUCCESS_NORMAL,
				"data_count" => count($query_result),
				"query_string" => $_POST["id"],
				"raw" => $query_result
			);
			echo json_encode($result, 0);
		}
		break;
	case "easycard":
		$query_result = $query->getEasycardPayment($_POST["qday"]);
		if (empty($query_result)) {
			$msg = $_POST["qday"] ."查無悠遊卡交易異常資料！";
			if (empty($_POST["qday"])) {
				$msg = "一周內查無悠遊卡交易異常資料！【大於等於".$week_ago."】";
			}
			echoErrorJSONString($msg);
		} else {
			$result = array(
				"status" => STATUS_CODE::SUCCESS_NORMAL,
				"data_count" => count($query_result),
				"query_string" => $_POST["qday"],
				"raw" => $query_result
			);
			echo json_encode($result, 0);
		}
		break;
	case "fix_easycard":
		$result_flag = $query->fixEasycardPayment($_POST["qday"], $_POST["pc_num"]);
		if ($result_flag) {
			$result = array(
				"status" => STATUS_CODE::SUCCESS_NORMAL,
				"data_count" => "0",
				"raw" => $result_flag
			);
			echo json_encode($result, 0);
		} else {
			echoErrorJSONString("更新失敗【".$_POST["qday"].", ".$_POST["pc_num"]."】");
		}
		break;
	case "reg_case":
		if (empty($_POST["id"])) {
			echoErrorJSONString();
			break;
		}
		$row = $query->getRegCaseDetail($_POST["id"]);
		if (empty($row)) {
			echoErrorJSONString();
		} else {
			$data = new RegCaseData($row);
			echo $data->getJsonHtmlData();
		}
		break;
	case "prc_case":
		$rows = $query->getPrcCaseAll($_POST["id"]);
		if (empty($rows)) {
			echoErrorJSONString();
		} else {
			$data = new PrcAllCasesData($rows);
			echo json_encode(array(
				"status" => STATUS_CODE::SUCCESS_NORMAL,
				"data_count" => count($rows),
				"html" => $data->getTableHtml()
			), 0);
		}
		break;
	case "expac":
		$rows = $query->getExpacItems($_POST["year"], $_POST["num"]);
		if (empty($rows)) {
			echoErrorJSONString();
		} else {
			$result = array(
				"status" => STATUS_CODE::SUCCESS_NORMAL,
				"data_count" => count($rows),
				"query_string" => "year=".$_POST["year"]."&num=".$_POST["num"],
				"raw" => $rows
			);
			echo json_encode($result, 0);
		}
		break;
	case "mod_expac":
		$result_flag = $query->modifyExpacItem($_POST["year"], $_POST["num"], $_POST["code"], $_POST["amount"]);
		if ($result_flag) {
			$result = array(
				"status" => STATUS_CODE::SUCCESS_NORMAL,
				"data_count" => "0",
				"raw" => $result_flag
			);
			echo json_encode($result, 0);
		} else {
			echoErrorJSONString("更新失敗【".$_POST["qday"].", ".$_POST["pc_num"]."】");
		}
		break;
	case "expaa":
		$rows = $query->getExpaaData($_POST["qday"], $_POST["num"]);
		if (empty($rows)) {
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
			echo json_encode($result, 0);
		} else {
			$result = array(
				"status" => STATUS_CODE::SUCCESS_WITH_MULTIPLE_RECORDS,
				"data_count" => count($rows),
				"message" => "於 ".$_POST["qday"]." 找到 ".count($rows)." 筆資料",
				"query_string" => "qday=".$_POST["qday"],
				"raw" => $rows
			);
			echo json_encode($result, 0);
		}
		break;
	case "expaa_AA09_update":
	case "expaa_AA100_update":
		$column = $_POST["type"] == "expaa_AA09_update" ? "AA09" : "AA100";
		$result_flag = $query->updateExpaaData($column, $_POST["date"], $_POST["number"], $_POST["update_value"]);
		if ($result_flag) {
			$result = array(
				"status" => STATUS_CODE::SUCCESS_NORMAL,
				"data_count" => "0",
				"raw" => $result_flag,
				"message" => "更新 ${column} 成功。"
			);
			echo json_encode($result, 0);
		} else {
			echoErrorJSONString("更新規費欄位失敗【".$_POST["date"].", ".$_POST["number"].", ".$column.", ".$_POST["update_value"]."】");
		}
		break;
	case "diff_xcase":
		$rows = $query->getXCaseDiff($_POST["id"]);
		if ($rows === -1) {
			echoErrorJSONString("參數格式錯誤。【".$_POST["id"]."】");
		} else if ($rows === -2) {
			echoErrorJSONString("遠端查無資料。【".$_POST["id"]."】");
		} else if ($rows === -3) {
			echoErrorJSONString("本地查無資料。【".$_POST["id"]."】", STATUS_CODE::FAIL_WITH_LOCAL_NO_RECORD);
		} else if (is_array($rows) && empty($rows)) {
			echoErrorJSONString("遠端資料與本所一致【".$_POST["id"]."】。");
		} else {
			$result = array(
				"status" => STATUS_CODE::SUCCESS_NORMAL,
				"data_count" => count($rows),
				"query_string" => "id=".$_POST["id"],
				"raw" => $rows
			);
			echo json_encode($result, 0);
		}
		break;
	case "inst_xcase":
		$result_flag = $query->instXCase($_POST["id"]);
		if ($result_flag === true) {
			$result = array(
				"status" => STATUS_CODE::SUCCESS_NORMAL,
				"data_count" => "0",
				"raw" => $result_flag
			);
			echo json_encode($result, 0);
		} else if ($result_flag === -1) {
			echoErrorJSONString("傳入ID錯誤，新增失敗【".$_POST["id"]."】");
		} else if ($result_flag === -2) {
			echoErrorJSONString("遠端無案件資料，新增失敗【".$_POST["id"]."】");
		} else {
			echoErrorJSONString("新增失敗【".$_POST["id"]."】");
		}
		break;
	case "sync_xcase":
		$result_flag = $query->syncXCase($_POST["id"]);
		if ($result_flag) {
			$result = array(
				"status" => STATUS_CODE::SUCCESS_NORMAL,
				"data_count" => "0",
				"raw" => $result_flag
			);
			echo json_encode($result, 0);
		} else {
			echoErrorJSONString("同步失敗【".$_POST["id"]."】");
		}
		break;
	case "sync_xcase_column":
		$result_flag = $query->syncXCaseColumn($_POST["id"], $_POST["column"]);
		if ($result_flag) {
			$result = array(
				"status" => STATUS_CODE::SUCCESS_NORMAL,
				"data_count" => "0",
				"raw" => $result_flag
			);
			echo json_encode($result, 0);
		} else {
			echoErrorJSONString("同步失敗【".$_POST["id"].", ".$_POST["column"]."】");
		}
		break;
	case "announcement_data":
		$rows = $query->getAnnouncementData();
		$result = array(
			"status" => STATUS_CODE::SUCCESS_NORMAL,
			"data_count" => count($rows),
			"raw" => $rows
		);
		echo json_encode($result, 0);
		break;
	case "update_announcement_data":
		$result_flag = $query->updateAnnouncementData($_POST["code"], $_POST["day"], $_POST["flag"]);
		if ($result_flag) {
			$result = array(
				"status" => STATUS_CODE::SUCCESS_NORMAL,
				"data_count" => "0",
				"raw" => $result_flag
			);
			echo json_encode($result, 0);
		} else {
			echoErrorJSONString("更新公告期限失敗【".$_POST["code"].", ".$_POST["day"].", ".$_POST["flag"]."】");
		}
		break;
	case "clear_announcement_flag":
		$result_flag = $query->clearAnnouncementFlag();
		if ($result_flag) {
			$result = array(
				"status" => STATUS_CODE::SUCCESS_NORMAL,
				"data_count" => "0",
				"raw" => $result_flag
			);
			echo json_encode($result, 0);
		} else {
			echoErrorJSONString("清除先行准登失敗");
		}
		break;
	case "query_temp_data":
		$rows = $query->getCaseTemp($_POST["year"], $_POST["code"], $_POST["number"]);
		if (!empty($rows)) {
			$result = array(
				"status" => STATUS_CODE::SUCCESS_NORMAL,
				"data_count" => count($rows),	// how many tables that have temp data for this case
				"raw" => $rows
			);
			echo json_encode($result, 0);
		} else {
			echoErrorJSONString("本案件查無暫存資料。");
		}
		break;
	case "clear_temp_data":
		$result_flag = $query->clearCaseTemp($_POST["year"], $_POST["code"], $_POST["number"]);
		if ($result_flag) {
			$result = array(
				"status" => STATUS_CODE::SUCCESS_NORMAL,
				"data_count" => "0",
				"raw" => $result_flag,
				"query_string" => "year=".$_POST["year"]."&code=".$_POST["code"]."&number=".$_POST["number"]
			);
			echo json_encode($result, 0);
		} else {
			echoErrorJSONString("清除暫存資料失敗");
		}
		break;
	case "unittest":
		echo '{"\u6536\u4ef6\u5b57\u865f":"108HB04064420","\u6536\u4ef6\u6642\u9593":"108-04-08 11:55:48","\u767b\u8a18\u539f\u56e0":"\u62b5\u7e73\u7a05\u6b3e","\u9650\u8fa6\u671f\u9650":"<span class=\'\'>108-04-11 11:55:48<\/span>","\u4f5c\u696d\u4eba\u54e1":"\u674e\u52dd\u96f2","\u8fa6\u7406\u60c5\u5f62":"\u521d\u5be9","\u6b0a\u5229\u4eba\u7d71\u7de8":"03732401","\u6b0a\u5229\u4eba\u59d3\u540d":"\u8ca1\u653f\u90e8\u570b\u6709\u8ca1\u7522\u7f72","\u7fa9\u52d9\u4eba\u7d71\u7de8":"A120895766","\u7fa9\u52d9\u4eba\u59d3\u540d":"\u59da\u932b\u7fd4","\u7fa9\u52d9\u4eba\u4eba\u6578":"4","\u624b\u6a5f\u865f\u78bc":"0938016860","\u4ee3\u7406\u4eba\u7d71\u7de8":"A120895766","\u4ee3\u7406\u4eba\u59d3\u540d":" \u59da\u932b\u7fd4","\u6bb5\u4ee3\u78bc":"0224","\u6bb5\u5c0f\u6bb5":"\u4e2d\u539f\u6bb5","\u5730\u865f":"1093-0001","\u5efa\u865f":"","\u4ef6\u6578":"2","\u767b\u8a18\u8655\u7406\u8a3b\u8a18":"","\u5730\u50f9\u8655\u7406\u8a3b\u8a18":"","\u8de8\u6240":null,"\u8cc7\u6599\u7ba1\u8f44\u6240":null,"\u8cc7\u6599\u6536\u4ef6\u6240":null,"tr_html":"<div>unittest</div>","raw":{"RM01":"108","RM02":"HB04","RM03":"064420","RM04":null,"RM05":null,"RM06":null,"RM07_1":"1080408","RM07_2":"115548","RM08":"1","RM09":"AZ","RM10":"03","RM11":"0224","RM12":"10930001","RM13":"1","RM14":"211","RM15":null,"RM16":null,"RM17":null,"RM18":"03732401","RM19":"\u8ca1\u653f\u90e8\u570b\u6709\u8ca1\u7522\u7f72","RM20":"1","RM21":"A120895766","RM22":"\u59da\u932b\u7fd4","RM23":"4","RM24":"A120895766","RM25":null,"RM26":"1","RM27":"24","RM28":null,"RM29_1":"1080411","RM29_2":"115548","RM30":"A","RM30_1":"HB1203","RM31":null,"RM33":null,"RM34":null,"RM35":null,"RM36":null,"RM37":null,"RM38":null,"RM39":null,"RM40":null,"RM41":null,"RM42":null,"RM43":null,"RM44_1":null,"RM44_2":null,"RM45":"HB1203","RM46_1":null,"RM46_2":null,"RM47":"HB0142","RM48_1":null,"RM48_2":null,"RM49":null,"RM49_TYPE":null,"RM49_DAY":null,"RM50":null,"RM51":null,"RM52":null,"RM52_TYPE":null,"RM52_DAY":null,"RM53_1":null,"RM53_2":null,"RM54_1":null,"RM54_2":null,"RM55":null,"RM56_1":null,"RM56_2":null,"RM57":null,"RM58_1":null,"RM58_2":null,"RM59":null,"RM60":null,"RM61":null,"RM62_1":null,"RM62_2":null,"RM63":null,"RM64":null,"RM32":"2","RM65":"A","RM65_1":"HB1135","RM66":null,"RM67":null,"RM65_2":null,"RM68":null,"RM80":null,"RM81":null,"RM82":null,"RM83":null,"RM84":null,"RM85":null,"RM86":null,"RM87":null,"RM88":null,"RM89":null,"RM25_2":null,"RM70":"U","SS_FLAG":null,"RM91":null,"RM91_1":null,"RM91_2":null,"RM90":null,"RM92":null,"RM93_1":null,"RM93_2":null,"RM93":null,"RM94":null,"RM95":null,"RM96":"HB1117","RM97":null,"RM98":null,"RM99":null,"RM100":null,"RM100_1":null,"RM101":null,"RM101_1":null,"RM91_3":null,"RM102":"0938016860","RM106":null,"RM106_1":null,"RM106_2":null,"RM107":null,"RM107_1":null,"RM107_2":null,"RM91_4":null,"RM24_OTHER":null,"RM25_OTHER":null,"RM97_OTHER":null,"RM108":null,"KCNT":"\u62b5\u7e73\u7a05\u6b3e","AB02":" \u59da\u932b\u7fd4","RM11_CNT":"\u4e2d\u539f\u6bb5"}}';
		break;
	default:
		echoErrorJSONString("不支援的查詢型態【".$_POST["type"]."】", STATUS_CODE::UNSUPPORT_FAIL);
		break;
}
?>
