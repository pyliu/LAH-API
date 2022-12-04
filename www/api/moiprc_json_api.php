<?php
require_once(dirname(dirname(__FILE__))."/include/init.php");
require_once(INC_DIR."/MOIEXP.class.php");
require_once(INC_DIR."/Cache.class.php");
require_once(INC_DIR."/System.class.php");

$moiexp = new MOIEXP();
$cache = Cache::getInstance();
$system = System::getInstance();
$mock = $system->isMockMode();

switch ($_POST["type"]) {
	case "val_realprice_memo":
		require_once(INC_DIR.DIRECTORY_SEPARATOR.'SQLiteValRealpriceMemoStore.class.php');
		$sqlite_db = new SQLiteValRealpriceMemoStore();
		$case_no = $_POST["case_no"];
		Logger::getInstance()->info("XHR [val_realprice_memo] 查詢實價登錄備註資料【${case_no}】請求");

		// this query goes to SQLite DB, it does not need cache result
		$result = $sqlite_db->getValRealpriceMemoRecord($case_no);

		if (empty($result)) {
			$message = "$case_no 查無實價登錄備註資料";
			Logger::getInstance()->info("XHR [val_realprice_memo] $message");
			echoJSONResponse($message, STATUS_CODE::SUCCESS_WITH_NO_RECORD);
		} else {
			$message = "查詢 $case_no 實價登錄備註資料成功";
			Logger::getInstance()->info("XHR [val_realprice_memo] $message");
			echoJSONResponse($message, STATUS_CODE::SUCCESS_NORMAL, array(
				'data_count' => 1,
				'raw' => $result[0]
			));
		}
		break;
	case "upd_val_realprice_memo":
		require_once(INC_DIR.DIRECTORY_SEPARATOR.'SQLiteValRealpriceMemoStore.class.php');
		$sqlite_db = new SQLiteValRealpriceMemoStore();
		$case_no = $_POST["case_no"];
		$declare_date = $_POST["declare_date"];
		$declare_note = $_POST['declare_note'];
		if (empty($case_no)) {
			$message = "更新 $case_no 實價登錄備註資料失敗";
			Logger::getInstance()->info("XHR [upd_val_realprice_memo] $message (無申報序號)");
			echoJSONResponse($message);
		} else {
			$row = array(
				'case_no' => $case_no,
				'declare_date' => $declare_date,
				'declare_note' => $declare_note
			);
			$result = $sqlite_db->replace($row);
			$message = "更新 $case_no 實價登錄備註資料".($result ? '成功' : '失敗');
			echoJSONResponse($message, $result ? STATUS_CODE::SUCCESS_NORMAL : STATUS_CODE::DEFAULT_FAIL);
		}
		break;
	default:
		Logger::getInstance()->error("不支援的查詢型態【".$_POST["type"]."】");
		echoJSONResponse("不支援的查詢型態【".$_POST["type"]."】", STATUS_CODE::UNSUPPORT_FAIL);
		break;
}
