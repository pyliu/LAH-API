<?php
require_once(dirname(dirname(__FILE__))."/include/init.php");

switch ($_POST["type"]) {
	case "list":
        Logger::getInstance()->info("準備搜尋海報資料夾 ".POSTER_IMG_DIR);
        $files = array_diff(scandir(POSTER_IMG_DIR), array('..', '.'));
        $found_jpgs = array();
        foreach ($files as $file) {
            if (endsWith($file, '.jpg') || endsWith($file, '.JPG')) {
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
		Logger::getInstance()->error("不支援的查詢型態【".$_POST["type"]."】");
		echoJSONResponse(__FILE__.": 不支援的查詢型態【".$_POST["type"]."】", STATUS_CODE::UNSUPPORT_FAIL);
		break;
}
