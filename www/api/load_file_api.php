<?php
require_once(dirname(dirname(__FILE__))."/include/init.php");

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
        $path = ROOT_DIR."/assets/files/".$_POST["file_name"];
        $content = file_get_contents($path);
        if (file_exists($path)) {
            $result = array(
                "status" => STATUS_CODE::SUCCESS_NORMAL,
                "data" => $content,
                "query_string" => "file_name=".$_POST["file_name"]."&type=".$_POST["type"]
            );
            $log->info("XHR [load_select_sql] 讀取成功【".$path."】");
            echo json_encode($result, 0);
        } else {
            $log->error("XHR [load_select_sql] 找不到檔案【".$path."】");
            echoErrorJSONString("找不到檔案【".$path."】");
        }
        break;
    case "load_log":
        //$log->info("XHR [load_log] 查詢請求【".$_POST["log_filename"]."】");
        $path = LOG_DIR.DIRECTORY_SEPARATOR.$_POST["log_filename"];
        if (file_exists($path)) {
            function removeLoadLog($item) {
                if (empty($item)) {
                    return false;
                }
                return !stristr($item, "load_log");
            }
            $all = array_filter(explode("\n", file_get_contents($path)), "removeLoadLog");  // line by line
            $data = $_POST["slice_offset"] ? array_slice($all, $_POST["slice_offset"]) : $all;
            $result = array(
                "status" => STATUS_CODE::SUCCESS_NORMAL,
                "data_count" => count($data),
                "total_count" => count($all),
                "data" => $data,
                "query_string" => "log_filename=".$_POST["log_filename"]."&type=".$_POST["type"]."&slice_offset=".$_POST["slice_offset"]
            );
            //$log->info("XHR [load_log] 讀取成功【".$path."】");
            echo json_encode($result, 0);
        } else {
            $log->error("XHR [load_log] 找不到檔案【".$path."】");
            echoErrorJSONString("找不到檔案【".$path."】");
        }
        break;
    default:
        $log->error("XHR [".$_POST["type"]."] 不支援的讀取型態");
        echoErrorJSONString("不支援的讀取型態【".$_POST["type"]."】", STATUS_CODE::UNSUPPORT_FAIL);
        break;
}
