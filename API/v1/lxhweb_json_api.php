<?php
require_once(dirname(dirname(__FILE__))."/include/init.php");
require_once(INC_DIR."/System.class.php");
require_once(INC_DIR."/Cache.class.php");
require_once(INC_DIR."/LXHWEB.class.php");

$system = System::getInstance();
$cache = Cache::getInstance();
$lxhweb = null;

$reqSite = $_POST["site"] ?? '';

switch ($reqSite) {
	case 'L1HWEB':
		$lxhweb = new LXHWEB(CONNECTION_TYPE::L1HWEB);
		break;
	case 'L1HWEB_Alt':
		$lxhweb = new LXHWEB(CONNECTION_TYPE::L1HWEB_Alt);
		break;
	case 'L2HWEB':
		$lxhweb = new LXHWEB(CONNECTION_TYPE::L2HWEB);
		break;
	default:
		$lxhweb = new LXHWEB(CONNECTION_TYPE::L3HWEB);
}

$mock = $system->isMockMode();
$reqType = $_POST["type"] ?? '';

switch ($reqType) {
	case "lxhweb_config":
		/*
			'ORA_DB_L1HWEB_IP' => $configs['ORA_DB_L1HWEB_IP'],
            'ORA_DB_L1HWEB_PORT' => $configs['ORA_DB_L1HWEB_PORT'],
            'ORA_DB_L2HWEB_IP' => $configs['ORA_DB_L2HWEB_IP'],
            'ORA_DB_L2HWEB_PORT' => $configs['ORA_DB_L2HWEB_PORT'],
            'ORA_DB_L3HWEB_IP' => $configs['ORA_DB_L3HWEB_IP'],
            'ORA_DB_L3HWEB_PORT' => $configs['ORA_DB_L3HWEB_PORT'],
            'PING_INTERVAL_SECONDS' => $configs['PING_INTERVAL_SECONDS']
		*/
		$configs = $system->getLXHWEBConfigs();
		echoJSONResponse('共查詢到 '.count($configs).' 筆設定', STATUS_CODE::SUCCESS_NORMAL, array(
			'raw' => $configs
		));
		break;
	case "lxhweb_site_update_time":
		// Logger::getInstance()->info("XHR [lxhweb_site_update_time] 查詢各所同步異動更新時間 請求 ".$reqSite);
		$rows = $mock ? $cache->get('lxhweb_site_update_time') : $lxhweb->querySiteUpdateTime($reqSite);
		if (!$mock) $cache->set('lxhweb_site_update_time', $rows);
		$count = $rows === false ? 0 : count($rows);
		if (empty($count)) {
			echoJSONResponse('查無同步異動資料庫更新時間資料 '.$reqSite);
		} else {
			echoJSONResponse('共查詢到'.$count.'筆資料', STATUS_CODE::SUCCESS_NORMAL, array(
				'data_count' => $count,
				'raw' => $rows
			));
		}
		break;
	case "lxhweb_broken_table":
        Logger::getInstance()->info("XHR [lxhweb_broken_table] 檢測損毀之同步異動表格請求");
		$rows = $mock ? $cache->get('lxhweb_broken_table') : $lxhweb->queryBrokenTable();
		if (!$mock) $cache->set('lxhweb_broken_table', $rows);
		$count = $rows === false ? 0 : count($rows);
		if (empty($count)) {
			echoJSONResponse('查無已損毀的同步異動表格 '.$reqSite, STATUS_CODE::SUCCESS_WITH_NO_RECORD);
		} else {
			echoJSONResponse('共查詢到'.$count.'筆資料', STATUS_CODE::SUCCESS_NORMAL, array(
				'data_count' => $count,
				'raw' => $rows
			));
		}
		break;
	case "lxhweb_tbl_update_time":
        $office = $_POST["office"] ?? '';
        Logger::getInstance()->info("XHR [lxhweb_tbl_update_time] 查詢同步異動更新時間 請求 ".$reqSite.(!empty($office) ? " 所別: ".$office : ""));
		$target_office = !empty($office) ? $office : (strlen($reqSite) == 2 ? $reqSite : '');
		$cache_key = 'lxhweb_tbl_update_time_'.$reqSite.'_'.$target_office;
		$rows = $mock ? $cache->get($cache_key) : $lxhweb->queryTableUpdateTime($target_office);
		if (!$mock) $cache->set($cache_key, $rows);
		$count = $rows === false ? 0 : count($rows);
		if (empty($count)) {
			echoJSONResponse('查無 '.(!empty($office) ? $office : '').' 同步異動表格'.$reqSite.'更新時間資料');
		} else {
			echoJSONResponse('共查詢到'.$count.'筆資料', STATUS_CODE::SUCCESS_NORMAL, array(
				'data_count' => $count,
				'raw' => $rows
			));
		}
		break;
	case "l3hweb_pscrn_check":
        $office = $_POST["office"] ?? '';
		Logger::getInstance()->info("XHR [l3hweb_pscrn_check] 檢查地價案件跨所註記 請求 " . (!empty($reqSite) ? $reqSite : 'L3HWEB'));
		$rows = $mock ? $cache->get('l3hweb_pscrn_check') : $lxhweb->getMissingXNoteXValCases($office);
		if (!$mock) $cache->set('l3hweb_pscrn_check', $rows);
		$count = $rows === false ? 0 : count($rows);
		if (empty($count)) {
			echoJSONResponse('查無 '.$office.' 地價案件遺失跨所註記。');
		} else {
			echoJSONResponse('共查詢到'.$count.'筆資料', STATUS_CODE::SUCCESS_NORMAL, array(
				'data_count' => $count,
				'raw' => $rows
			));
		}
		break;
    default:
        Logger::getInstance()->error("不支援的查詢型態【".$reqType."】");
        echoJSONResponse("不支援的查詢型態【".$reqType."】", STATUS_CODE::UNSUPPORT_FAIL);
        break;
}
