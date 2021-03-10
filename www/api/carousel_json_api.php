<?php
require_once(__DIR__."/../include/init.php");

function startsWith( $haystack, $needle ) {
    $length = strlen( $needle );
    return substr( $haystack, 0, $length ) === $needle;
}

function endsWith( $haystack, $needle ) {
    $length = strlen( $needle );
    if( !$length ) {
        return true;
    }
    return substr( $haystack, -$length ) === $needle;
}

switch ($_POST["type"]) {
	case "list":
        $files = array_diff(scandir(__DIR__."/../assets/img/poster"), array('..', '.'));
        $found_jpgs = array();
        foreach ($files as $file) {
            if (endsWith($file, '.jpg') || endsWith($file, '.JPG')) {
                $found_jpgs[] = $file;
            }
        }
        $count = count($found_jpgs);
        echoJSONResponse("找到 $count 個 JPEG 檔案", STATUS_CODE::SUCCESS_NORMAL, array(
            'files' => $found_jpgs,
        ));
		break;
	default:
		Logger::getInstance()->error("不支援的查詢型態【".$_POST["type"]."】");
		echoJSONResponse(__FILE__.": 不支援的查詢型態【".$_POST["type"]."】", STATUS_CODE::UNSUPPORT_FAIL);
		break;
}
