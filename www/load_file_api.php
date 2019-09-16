<?
require_once("./include/init.php");

function echoErrorJSONString($msg = "", $status = STATUS_CODE::DEFAULT_FAIL) {
	echo json_encode(array(
		"status" => $status,
		"data_count" => "0",
		"message" => empty($msg) ? "找不到檔案" : $msg
	), 0);
}

switch ($_POST["type"]) {
    case "load_select_sql":
        $log->info("XHR [load_select_sql] 查詢請求【".$_POST["file_name"]."】");
        $path = "./assets/files/".$_POST["file_name"];
        if (file_exists($path)) {
            $result = array(
                "status" => STATUS_CODE::SUCCESS_NORMAL,
                "data" => file_get_contents($path),
                "query_string" => "file_name=".$_POST["file_name"]."&type=".$_POST["type"]
            );
            $log->info("XHR [load_select_sql] 讀取成功【".$path."】");
            echo json_encode($result, 0);
        } else {
            $log->error("XHR [load_select_sql] 找不到檔案【".$path."】");
            echoErrorJSONString("找不到檔案【".$path."】");
        }
        break;
    default:
        $log->error("XHR [".$_POST["type"]."] 不支援的讀取型態");
        echoErrorJSONString("不支援的讀取型態【".$_POST["type"]."】", STATUS_CODE::UNSUPPORT_FAIL);
        break;
}
?>
