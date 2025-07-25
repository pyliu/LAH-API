<?php
require_once(dirname(dirname(__FILE__)).DIRECTORY_SEPARATOR."include".DIRECTORY_SEPARATOR."init.php");
require_once(INC_DIR.DIRECTORY_SEPARATOR."Cache.class.php");
require_once(INC_DIR.DIRECTORY_SEPARATOR."System.class.php");
require_once(INC_DIR.DIRECTORY_SEPARATOR."MOICAS.class.php");
require_once(INC_DIR.DIRECTORY_SEPARATOR."MOICAS.class.php");
require_once(INC_DIR.DIRECTORY_SEPARATOR."RegCaseData.class.php");

$cache = Cache::getInstance();
$system = System::getInstance();
$moicas = new MOICAS();
$mock = $system->isMockMode();

switch ($_POST["type"]) {
	case "crsms_records_by_clock":
		Logger::getInstance()->info("XHR [crsms_records_by_clock] check CMCRD empty temp record request.");
		$rows = $mock ? $cache->get('crsms_records_by_clock') : $moicas->getCRSMSRecordsByClock($_POST['st'], $_POST['ed'], $_POST['clock']);
		$cache->set('crsms_records_by_clock', $rows);
		$message = is_array($rows) ? "目前查到 CRSMS 裡有 ".count($rows)." 筆資料" : '查詢 CRSMS 失敗';
		$status_code = is_array($rows) ? STATUS_CODE::SUCCESS_NORMAL : STATUS_CODE::FAIL_DB_ERROR;
		Logger::getInstance()->info("XHR [crsms_records_by_clock] $message");

		$baked = array();
		foreach ($rows as $row) {
			$data = new RegCaseData($row);
			$baked[] = $data->getBakedData();
		}

		echoJSONResponse($message, $status_code, array(
			"raw" => $rows,
			"baked" => $baked
		));
		break;
	case "sur_notify_application_tmp_check":
		Logger::getInstance()->info("XHR [sur_notify_application_tmp_check] check CMCRD empty temp record request.");
		$rows = $mock ? $cache->get('sur_notify_application_tmp_check') : $moicas->getCMCRDTempRecords($_POST['year']);
		$cache->set('sur_notify_application_tmp_check', $rows);
		$message = is_array($rows) ? "目前查到CMCRD裡有 ".count($rows)." 筆暫存('Y%')資料" : '查詢CMCRD失敗';
		$status_code = is_array($rows) ? STATUS_CODE::SUCCESS_NORMAL : STATUS_CODE::FAIL_DB_ERROR;
		Logger::getInstance()->info("XHR [sur_notify_application_tmp_check] $message");
		echoJSONResponse($message, $status_code, array(
			"raw" => $rows
		));
		break;
	case "remove_dangling_sur_notify_application_tmp_record":
		Logger::getInstance()->info("XHR [remove_dangling_sur_notify_application_tmp_record] remove CMCRD temp record request.");
		//removeDanglingCMCRDRecords
		$result = $mock ? $cache->get('remove_dangling_sur_notify_application_tmp_record') : $moicas->removeDanglingCMCRDRecords($_POST['year']);
		$cache->set('remove_dangling_sur_notify_application_tmp_record', $result);
		$message = "清除CMCRD裡懸浮的暫存('Y%')資料".($result ? '成功' : '失敗');
		$status_code = $result ? STATUS_CODE::SUCCESS_NORMAL : STATUS_CODE::FAIL_DB_ERROR;
		Logger::getInstance()->info("XHR [remove_dangling_sur_notify_application_tmp_record] $message");
		echoJSONResponse($message, $status_code, array(
			"raw" => $result
		));
		break;
	case "remove_sur_notify_application_tmp_record":
		Logger::getInstance()->info("XHR [remove_sur_notify_application_tmp_record] remove CMCRD temp record request.");

		$year = $_POST['MC01'];
		$no = $_POST['MC02'];

		$result = $mock ? $cache->get('remove_cmcrd_tmp_record') : $moicas->removeCMCRDRecords($year, $no);
		$cache->set('remove_cmcrd_tmp_record', $result);
		$message = $result !== false ? '刪除 '.$year.'-'.$no.' CMCRD 暫存檔成功' : '刪除 CMCRD 暫存檔失敗【'.$_POST['MC01'].', '.$_POST['MC02'].'】';

		$message .= " | ";

		$result = $mock ? $cache->get('remove_cmcld_tmp_record') : $moicas->removeCMCLDRecords($year, $no);
		$cache->set('remove_cmcld_tmp_record', $result);
		$message .= $result !== false ? '刪除 '.$year.'-'.$no.' CMCLD 連結檔成功' : '刪除 CMCLD 連結檔失敗【'.$_POST['MC01'].', '.$_POST['MC02'].'】';

		if (!empty($_POST['CODE'])) {
			$year = $_POST['YEAR'];
			$code = $_POST['CODE'];
			$num = str_pad($_POST['NUM'], 6, '0');
			$message .= " | ";

			$result = $mock ? $cache->get('set_cmsms_MM22_A') : $moicas->setCMSMS_MM22_A($year, $code, $num);
			$cache->set('set_cmsms_MM22_A', $result);
			$tmp = '設定 '.$year.'-'.$code.'-'.$num.' 辦理情形為「外業作業」';
			$message .= $result !== false ? $tmp.'成功' : $tmp.'失敗';
		}

		$status_code = $result ? STATUS_CODE::SUCCESS_NORMAL : STATUS_CODE::DEFAULT_FAIL;
		Logger::getInstance()->info("XHR [remove_sur_notify_application_tmp_record] $message");
		echoJSONResponse($message, $status_code, array(
			"raw" => $result
		));
		break;
	case "fix_rm38_rm39_problem":
		Logger::getInstance()->info("XHR [fix_reg_wrong_change] fix CRSMS RM38、RM39 wrong change case request.");
		$year = $_POST['year'];
		$code = $_POST['code'];
		$num = $_POST['number'];
		$trindx_fix = true;
		$result = $mock ? $cache->get('fix_reg_wrong_change') : $moicas->fixRegWrongChangeCase($year, $code, $num, $trindx_fix);
		$cache->set('fix_reg_wrong_change', $result);
		$status_code = $result ? STATUS_CODE::SUCCESS_NORMAL : STATUS_CODE::DEFAULT_FAIL;
		$message = '更新 '.$year.'-'.$code.'-'.$num.' RM38/RM39 ';
		$message .= $result !== false ? '成功' : '失敗';
		Logger::getInstance()->info("XHR [fix_reg_wrong_change] $message");
		echoJSONResponse($message, $status_code, array(
			"raw" => $result
		));
		break;
	case "crsms_update_by_date":
			Logger::getInstance()->info("XHR [crsms_update_by_date] get CRSMS update by date request.");
			$moicas = new MOICAS();
			$rows = $moicas->getCRSMSUpdateCase($_POST['qday']);
			$response_code = STATUS_CODE::SUCCESS_NORMAL;
			$message = $_POST['qday'].'已找到'.count($rows).'件CRSMS案件時間異動更新紀錄';
			$baked = array();
			foreach ($rows as $key => $row) {
					$data = new RegCaseData($row);
					$baked[] = $data->getBakedData();
			}
			Logger::getInstance()->info("XHR [crsms_update_by_date] $message");
			echoJSONResponse($message, $response_code, array( "baked" => $baked ));
		break;
	case "crsmslog":
			Logger::getInstance()->info("XHR [crsmslog] get CRSMSLog request.");
			$moicas = new MOICAS();
			$rows = $moicas->getConcernCRSMSLog($_POST['qday'], $_POST['qtime'] ?? '000000');
			$response_code = STATUS_CODE::SUCCESS_NORMAL;
			$message = $_POST['qday'].'已找到'.count($rows).'異動更新紀錄';
			$baked = array();
			foreach ($rows as $key => $row) {
					$data = new RegCaseData($row);
					$baked[] = $data->getBakedData();
			}
			Logger::getInstance()->info("XHR [crsmslog] $message");
			echoJSONResponse($message, $response_code, array( "baked" => $baked ));
			break;
	case "cusmm_by_date":
			Logger::getInstance()->info("XHR [cusmm_by_date] get CUSMM by date request.");
			$moicas = new MOICAS();
			$rows = $moicas->getCUSMMByDate($_POST['begin'], $_POST['end']);
			$response_code = count($rows) > 0 ? STATUS_CODE::SUCCESS_NORMAL : STATUS_CODE::DEFAULT_FAIL;
			$message = $_POST['begin'].' ~ '.$_POST['end'].' 已找到'.count($rows).'筆謄本調閱紀錄';
			Logger::getInstance()->info("XHR [cusmm_by_date] $message");
			echoJSONResponse($message, $response_code, array( "raw" => $rows ));
			break;
	case "cusmm_by_pid":
			Logger::getInstance()->info("XHR [cusmm_by_pid] get CUSMM by PID request.");
			$moicas = new MOICAS();
			$rows = $moicas->getCUSMMByQuery($_POST['pid']);
			$response_code = count($rows) > 0 ? STATUS_CODE::SUCCESS_NORMAL : STATUS_CODE::DEFAULT_FAIL;
			$message = '已找到'.count($rows).'筆 '.$_POST['pid'].' 有關的謄本調閱紀錄';
			Logger::getInstance()->info("XHR [cusmm_by_pid] $message");
			echoJSONResponse($message, $response_code, array( "raw" => $rows ));
			break;
	case "workdays":
			Logger::getInstance()->info("XHR [workdays] get workdays request.");
			$moicas = new MOICAS();
			$rows = $moicas->getWorkdays($_POST['year']);
			$rows = $mock ? $cache->get('workdays') : $moicas->getWorkdays($_POST['year']);
			$cache->set('workdays', $rows);
			$response_code = count($rows) > 0 ? STATUS_CODE::SUCCESS_NORMAL : STATUS_CODE::DEFAULT_FAIL;
			$message = $_POST['year'].' 已找到'.count($rows).'筆 '.$_POST['pid'].' 工作天紀錄';
			Logger::getInstance()->info("XHR [workdays] $message");
			echoJSONResponse($message, $response_code, array( "raw" => $rows ));
			break;
	case "crsms_rm45_case":
		Logger::getInstance()->info("XHR [crsms_rm45_case] get initial review case request.");
		$moicas = new MOICAS();
		$rows = $mock ? $cache->get('crsms_rm45_case') : $moicas->getInitialReviewCase($_POST['st'], $_POST['ed'], $_POST['user_id']);
		$cache->set('crsms_rm45_case', $rows);
		
		$baked = array();
		foreach ($rows as $row) {
			$data = new RegCaseData($row);
			$baked[] = $data->getBakedData();
		}
		$count = count($baked);

		$response_code = $count > 0 ? STATUS_CODE::SUCCESS_NORMAL : STATUS_CODE::DEFAULT_FAIL;
		$message = $_POST['st'].' ~ '.$_POST['ed'].' 已找到'.$count.'筆 '.$_POST['user_id'].' 初審案件紀錄';
		Logger::getInstance()->info("XHR [crsms_rm45_case] $message");
		echoJSONResponse($message, $response_code, array( "baked" => $baked ));
		break;
	case "crsms_rm47_case":
		Logger::getInstance()->info("XHR [crsms_rm47_case] get final review case request.");
		$moicas = new MOICAS();
		$rows = $mock ? $cache->get('crsms_rm47_case') : $moicas->getFinalReviewCase($_POST['st'], $_POST['ed'], $_POST['user_id']);
		$cache->set('crsms_rm47_case', $rows);
		
		$baked = array();
		foreach ($rows as $row) {
			$data = new RegCaseData($row);
			$baked[] = $data->getBakedData();
		}
		$count = count($baked);

		$response_code = $count > 0 ? STATUS_CODE::SUCCESS_NORMAL : STATUS_CODE::DEFAULT_FAIL;
		$message = $_POST['st'].' ~ '.$_POST['ed'].' 已找到'.$count.'筆 '.$_POST['user_id'].' 複審案件紀錄';
		Logger::getInstance()->info("XHR [crsms_rm47_case] $message");
		echoJSONResponse($message, $response_code, array( "baked" => $baked ));
		break;
	case "crsms_rm106_case":
			Logger::getInstance()->info("XHR [crsms_rm106_case] get chief review case request.");
			$moicas = new MOICAS();
			$rows = $mock ? $cache->get('crsms_rm106_case') : $moicas->getChiefReviewCase($_POST['st'], $_POST['ed'], $_POST['user_id']);
			$cache->set('crsms_rm106_case', $rows);
			
			$baked = array();
			foreach ($rows as $row) {
				$data = new RegCaseData($row);
				$baked[] = $data->getBakedData();
			}
			$count = count($baked);

			$response_code = $count > 0 ? STATUS_CODE::SUCCESS_NORMAL : STATUS_CODE::DEFAULT_FAIL;
			$message = $_POST['st'].' ~ '.$_POST['ed'].' 已找到'.$count.'筆 '.$_POST['user_id'].' 課長案件紀錄';
			Logger::getInstance()->info("XHR [crsms_rm106_case] $message");
			echoJSONResponse($message, $response_code, array( "baked" => $baked ));
			break;
	default:
		Logger::getInstance()->error("不支援的查詢型態【".$_POST["type"]."】");
		echoJSONResponse("不支援的查詢型態【".$_POST["type"]."】", STATUS_CODE::UNSUPPORT_FAIL);
		break;
}
