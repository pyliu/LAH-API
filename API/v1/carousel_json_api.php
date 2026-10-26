<?php
require_once(dirname(dirname(__FILE__))."/include/init.php");

switch ($_POST["type"] ?? '') {
	case "list":
        Logger::getInstance()->info("準備搜尋海報資料夾 ".POSTER_IMG_DIR);
        $scan_result = is_dir(POSTER_IMG_DIR) ? scandir(POSTER_IMG_DIR) : [];
        $files = array_diff($scan_result ?: [], array('..', '.'));
        $regex = '/\.(jpe?g)(?:[\?\#].*)?$/i';
        $found_jpgs = array();
        foreach ($files as $file) {
            if (preg_match($regex, $file)) {
                $found_jpgs[] = $file;
            }
        }
        $count = count($found_jpgs);
        Logger::getInstance()->info("找到 $count 個 JPEG 檔案");
        echoJSONResponse("找到 $count 個 JPEG 檔案", STATUS_CODE::SUCCESS_NORMAL, array(
            'files' => $found_jpgs,
        ));
		break;
	default:
		$req_type = $_POST["type"] ?? '';
		Logger::getInstance()->error("不支援的查詢型態【".$req_type."】");
		echoJSONResponse(__FILE__.": 不支援的查詢型態【".$req_type."】", STATUS_CODE::UNSUPPORT_FAIL);
		break;
}
