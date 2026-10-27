<?php
require_once(dirname(dirname(__FILE__))."/include/init.php");

function echoErrorJSONString($msg = "", $status = STATUS_CODE::DEFAULT_FAIL) {
	echo json_encode(array(
		"status" => $status,
		"data_count" => "0",
		"message" => empty($msg) ? "找不到檔案" : $msg
	), 0);
}

if (!function_exists('removeLoadLog')) {
    function removeLoadLog($item) {
        if (empty($item)) {
            return false;
        }
        return !stristr($item, "load_log");
    }
}

$reqType = $_POST["type"] ?? '';

switch ($reqType) {
    case "load_select_sql":
        $file_name = $_POST["file_name"] ?? '';
        Logger::getInstance()->info("XHR [load_select_sql] 查詢請求【".$file_name."】");
        $path = ROOT_DIR."/assets/files/".$file_name;
        if (!empty($file_name) && file_exists($path)) {
            $content = file_get_contents($path);
            $result = array(
                "status" => STATUS_CODE::SUCCESS_NORMAL,
                "data" => $content,
                "query_string" => "file_name=".$file_name."&type=".$reqType
            );
            Logger::getInstance()->info("XHR [load_select_sql] 讀取成功【".$path."】");
            echo json_encode($result, 0);
        } else {
            Logger::getInstance()->error("XHR [load_select_sql] 找不到檔案【".$path."】");
            echoErrorJSONString("找不到檔案【".$path."】");
        }
        break;
    case "load_log":
        $log_filename = $_POST["log_filename"] ?? '';
        $path = !empty($log_filename) ? LOG_DIR.DIRECTORY_SEPARATOR.$log_filename : '';
        if (!empty($path) && file_exists($path)) {
            $all = array_filter(explode("\n", file_get_contents($path)), "removeLoadLog");  // line by line
            $slice_offset = isset($_POST["slice_offset"]) ? intval($_POST["slice_offset"]) : null;
            $data = $slice_offset !== null ? array_slice($all, $slice_offset) : $all;
            $result = array(
                "status" => STATUS_CODE::SUCCESS_NORMAL,
                "data_count" => count($data),
                "total_count" => count($all),
                "data" => array_values($data),
                "query_string" => "log_filename=".$log_filename."&type=".$reqType."&slice_offset=".($slice_offset ?? '')
            );
            echo json_encode($result, 0);
        } else {
            Logger::getInstance()->error("XHR [load_log] 找不到檔案【".$path."】");
            echoErrorJSONString("找不到檔案【".$path."】");
        }
        break;
    default:
        Logger::getInstance()->error("XHR [".$reqType."] 不支援的讀取型態");
        echoErrorJSONString("不支援的讀取型態【".$reqType."】", STATUS_CODE::UNSUPPORT_FAIL);
        break;
}
