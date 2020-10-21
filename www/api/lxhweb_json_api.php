<?php
require_once(dirname(dirname(__FILE__))."/include/init.php");
require_once(ROOT_DIR."/include/System.class.php");
require_once(ROOT_DIR."/include/Cache.class.php");
require_once(ROOT_DIR."/include/LXHWEB.class.php");

$system = new System();
$cache = new Cache();
$lxhweb = null;
switch($_POST["site"]) {
	case 'L1HWEB':
		$lxhweb = new LXHWEB(CONNECTION_TYPE::L1HWEB);
		break;
	case 'L2HWEB':
		$lxhweb = new LXHWEB(CONNECTION_TYPE::L2HWEB);
		break;
	default:
		$lxhweb = new LXHWEB(CONNECTION_TYPE::L3HWEB);
}

$mock = $system->isMockMode();

switch ($_POST["type"]) {
	case "lxhweb_site_update_time":
		// $log->info("XHR [lxhweb_site_update_time] 查詢各所同步異動更新時間 請求 ".$_POST["site"]);
		$rows = $mock ? $cache->get('lxhweb_site_update_time') : $lxhweb->querySiteUpdateTime();
		if (!$mock) $cache->set('lxhweb_site_update_time', $rows);
		$count = $rows === false ? 0 : count($rows);
		if (empty($count)) {
			echoJSONResponse('查無同步異動資料庫更新時間資料');
		} else {
			echoJSONResponse('共查詢到'.$count.'筆資料', STATUS_CODE::SUCCESS_NORMAL, array(
				'data_count' => $count,
				'raw' => $rows
			));
		}
		break;
	case "lxhweb_broken_table":
        $log->info("XHR [lxhweb_broken_table] 檢測損毀之同步異動表格請求");
		$rows = $mock ? $cache->get('lxhweb_broken_table') : $lxhweb->queryBrokenTable();
		if (!$mock) $cache->set('lxhweb_broken_table', $rows);
		$count = $rows === false ? 0 : count($rows);
		if (empty($count)) {
			echoJSONResponse('查無已損毀的同步異動表格', STATUS_CODE::SUCCESS_WITH_NO_RECORD);
		} else {
			echoJSONResponse('共查詢到'.$count.'筆資料', STATUS_CODE::SUCCESS_NORMAL, array(
				'data_count' => $count,
				'raw' => $rows
			));
		}
		break;
	case "lxhweb_tbl_update_time":
        $log->info("XHR [lxhweb_tbl_update_time] 查詢同步異動更新時間 請求 ".$_POST["site"]);
		$rows = $mock ? $cache->get('lxhweb_tbl_update_time') : $lxhweb->queryTableUpdateTime($_POST["site"]);
		if (!$mock) $cache->set('lxhweb_tbl_update_time', $rows);
		$count = $rows === false ? 0 : count($rows);
		if (empty($count)) {
			echoJSONResponse('查無'.$_POST["site"].'同步異動表格更新時間資料');
		} else {
			echoJSONResponse('共查詢到'.$count.'筆資料', STATUS_CODE::SUCCESS_NORMAL, array(
				'data_count' => $count,
				'raw' => $rows
			));
		}
		break;
    default:
        $log->error("不支援的查詢型態【".$_POST["type"]."】");
        echoJSONResponse("不支援的查詢型態【".$_POST["type"]."】", STATUS_CODE::UNSUPPORT_FAIL);
        break;
}
