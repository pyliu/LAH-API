<?php
require_once(dirname(dirname(__FILE__))."/include/init.php");
require_once(INC_DIR."/RegCaseData.class.php");
require_once(INC_DIR."/SurCaseData.class.php");
require_once(INC_DIR."/PrcAllCasesData.class.php");
require_once(INC_DIR."/Query.class.php");
require_once(INC_DIR."/Message.class.php");
require_once(INC_DIR."/WatchDog.class.php");
require_once(INC_DIR."/StatsSQLite3.class.php");
require_once(INC_DIR."/Cache.class.php");
require_once(INC_DIR."/Temperature.class.php");
require_once(INC_DIR."/System.class.php");
require_once(INC_DIR."/Ping.class.php");
require_once(INC_DIR."/SQLiteUser.class.php");

require_once(INC_DIR."/api/JSONAPICommandFactory.class.php");

$query = new Query();
$cache = Cache::getInstance();
$system = System::getInstance();
$mock = $system->isMockMode();

switch ($_POST["type"]) {
	case "stats_overdue_msg_total":
		$stats = new StatsSQLite3();
		$total = $stats->getTotal('overdue_msg_count');
		// $total = $mock ? $cache->get('overdue_msg_count') : $stats->getTotal('overdue_msg_count');
		// $cache->set('overdue_msg_count', $total);
		echo json_encode(array(
			"status" => STATUS_CODE::SUCCESS_NORMAL,
			"data_count" => 1,
			"total" => $total,
			"message" => "已傳送 $total 人次訊息。"
		), 0);
		break;
	case "ip":
		$log->info("XHR [ip] The client IP is $client_ip");
		echo json_encode(array(
			"status" => STATUS_CODE::SUCCESS_NORMAL,
			"ip" => $client_ip,
			"data_count" => "1",
			"message" => "client ip is ".$client_ip
		), 0);
		break;
	case "svr":
		// find current connected user
		$sqlite_user = new SQLiteUser();
		$client_ip = array_key_exists('client_ip', $_POST) ? $_POST['client_ip'] : '';
		$queried_user = $sqlite_user->getUserByIP($client_ip);
		$found_count = count($queried_user);
		if ($found_count > 1) {
			$queried_user = array($queried_user[$found_count - 1]);
		}
		if (empty($found_count)) {
			$log->info("XHR [svr] 找不到 $client_ip 使用者。");
		} else {
			$_SESSION["myinfo"] = $queried_user[0];
			$log->info("XHR [svr] 找到 $client_ip 使用者 ".$_SESSION["myinfo"]['id']." ".$_SESSION["myinfo"]['name']."。");
		}

		$ips = getLocalhostIPs();
		$count = count($ips);
		$message = "PHP 取得 API 伺服器 IP 位址 => ".preg_replace('/[\n\s]+/i', ' ', print_r($ips, true));

		$log->info("XHR [svr] APACHE API 伺服器端點資訊： ".$_SERVER['SERVER_ADDR'].":".$_SERVER['SERVER_PORT']);
		$log->info("XHR [svr] $message");

		echo json_encode(array(
			"status" => STATUS_CODE::SUCCESS_NORMAL,
			"message" => $message,
			"server" => $_SERVER,
			"ips" => $ips,
			"user" => array_key_exists('myinfo', $_SESSION) ? $_SESSION['myinfo'] : array(),
			"config" => array(
				'webap_ip' => $system->get('WEBAP_IP'),
				'mock' => $system->isMockMode(),
				'mssql' => $system->isMSSQLEnable(),
				'officehours' => $system->isOfficeHoursEnable(),
				"authority" => $system->getAuthority($client_ip),
				"master_password" => $system->get('MASTER_PASSWORD'),
				"site" => strtoupper($system->get('SITE')),
				"lxhweb" => $system->getLXHWEBConfigs(),
				"ip_maps" => array(
					"admin" => $system->getRoleAdminIps(),
					"chief" => $system->getRoleChiefIps(),
					"super" => $system->getRoleSuperIps(),
					"rae" => $system->getRoleRAEIps(),
					"ga" => $system->getRoleGAIps()
				)
			)
		), 0);
		break;
	case "configs":
		$log->info("XHR [configs] 查詢系統設定資料請求。");
		$configs = $system->getConfigs();
		$message = '查詢到'.count($configs).'筆資料';
		$log->info("XHR [configs] ${message}。");
		echoJSONResponse($message, STATUS_CODE::SUCCESS_NORMAL, array(
			'raw' => $configs
		));
		break;
	case "update_configs":
		$log->info("XHR [configs] 更新系統設定資料請求。");
		$success = $system->updateConfigs($_POST['configs']);
		$message = '成功更新 '.$success.' / '.count($_POST['configs']).'筆系統設定。';
		$status = $success > 0 ? STATUS_CODE::SUCCESS_NORMAL : STATUS_CODE::DEFAULT_FAIL;
		$log->info("XHR [configs] ${message}。");
		echoJSONResponse($message, $status, array(
			'success' => $success
		));
		break;
	case "ping":
		$log->info("XHR [ping] Ping ".$_POST["ip"]." request.");
		$ip = $_POST["ip"];
		$ping = new Ping($ip, 1, 255);	// ip, timeout, ttl
		$latency = 0;
		if ($_POST['port']) {
    		$ping->setPort($_POST['port']);
    		$latency = $ping->ping('fsockopen');
		} else {
			$latency = $ping->ping();
		}
		$response_code = ($latency > 999 || $latency == '') ? STATUS_CODE::FAIL_TIMEOUT : STATUS_CODE::SUCCESS_NORMAL;
		$message = "$ip 回應時間".(($latency > 999 || $latency == '') ? "逾時" : "為 $latency ms");
		echo json_encode(array(
			"status" => $response_code,
			"ip" => $ip,
			"latency" => empty($latency) ? "0" : $latency,
			"data_count" => "1",
			"message" => $message
		), 0);
		break;
	case "zip_log":
		$log->info("XHR [zip_log] 壓縮LOG資料請求");
		zipLogs();
		$result = array(
			"status" => STATUS_CODE::SUCCESS_NORMAL,
			"data_count" => 0
		);
		$log->info("XHR [zip_log] 壓縮LOG成功");
		echo json_encode($result, 0);
		break;
	case "remove_temperature":
		$log->info("XHR [remove_temperature] 刪除體溫【".$_POST["id"].", ".$_POST["datetime"]."】請求");
		$temperature = new Temperature();
		$result = $temperature->remove($_POST["id"], $_POST["datetime"]);
		// $result = $mock ? $cache->get('set_temperature') : $temperature->set($_POST["id"], $_POST["temperature"]);
		// $cache->set('set_temperature', $result);
		if ($result) {
			$json_array = array(
				"status" => STATUS_CODE::SUCCESS_NORMAL,
				"data_count" => 0,
				"result" => $result,
				"query_string" => "id=".$_POST["id"]."&datetime=".$_POST["datetime"],
				"message" => "刪除體溫紀錄成功(".$_POST["id"].", ".$_POST["datetime"].")"
			);
			$log->info("XHR [remove_temperature] ".$json_array["message"]);
			echo json_encode($json_array, 0);
		} else {
			echoJSONResponse("刪除 ".$_POST["id"].", ".$_POST["datetime"]." 體溫紀錄失敗。");
			$log->info("XHR [remove_temperature] 刪除 ".$_POST["id"]." 體溫紀錄失敗。");
		}
		break;	
	case "add_temperature":
		$log->info("XHR [add_temperature] 設定體溫【".$_POST["id"].", ".$_POST["temperature"]."】請求");
		$temperature = new Temperature();
		$result = $temperature->add($_POST["id"], $_POST["temperature"]);
		// $result = $mock ? $cache->get('add_temperature') : $temperature->add($_POST["id"], $_POST["temperature"]);
		// $cache->set('add_temperature', $result);
		if ($result) {
			$json_array = array(
				"status" => STATUS_CODE::SUCCESS_NORMAL,
				"data_count" => 1,
				"result" => $result,
				"query_string" => "id=".$_POST["id"]."&temperature=".$_POST["temperature"],
				"message" => "新增體溫紀錄成功(".$_POST["id"].", ".$_POST["temperature"].")"
			);
			$log->info("XHR [add_temperature] ".$json_array["message"]);
			echo json_encode($json_array, 0);
		} else {
			echoJSONResponse("已有 ".$_POST["id"]." 體溫紀錄，如需更新請刪除舊資料。");
			$log->warning("XHR [add_temperature] 新增 ".$_POST["id"]." 體溫紀錄失敗。");
		}
		break;
	case "temperatures_by_id_date":
		$log->info("XHR [temperatures_by_id_date] 取得體溫列表【".$_POST["id"].", ".$_POST['date']."】請求");
		$temperature = new Temperature();
		$results = $temperature->getByIdAndDate($_POST["id"], $_POST['date']);
		// $results = $mock ? $temperature->get('temperatures') : $temperature->get($_POST["id"]);
		// $cache->set('temperatures', $results);
		$log->info("XHR [temperatures_by_id_date] 取得 ".$_POST["id"]." ".count($results)." 筆體溫資料【".$_POST['date']."】");
		echo json_encode(array(
			"status" => STATUS_CODE::SUCCESS_NORMAL,
			"data_count" => count($results),
			"raw" => $results,
			"message" => "取得 ".$_POST["id"]." ".count($results)." 筆資料。【".$_POST['date']."】"
		), 0);
		break;
	case "temperatures_by_date":
		$log->info("XHR [temperatures_by_date] 取得體溫列表【".$_POST['date']."】請求");
		$temperature = new Temperature();
		$results = $temperature->getByDate($_POST['date']);
		// $results = $mock ? $temperature->get('temperatures') : $temperature->get($_POST["id"]);
		// $cache->set('temperatures', $results);
		$log->info("XHR [temperatures_by_date] 取得 ".$_POST['date']." ".count($results)." 筆體溫資料");
		echo json_encode(array(
			"status" => STATUS_CODE::SUCCESS_NORMAL,
			"data_count" => count($results),
			"raw" => $results,
			"message" => "取得 ".$_POST['date']." ".count($results)." 筆體溫資料"
		), 0);
		break;
	case "temperatures":
		$log->info("XHR [temperatures] 取得體溫列表【".$_POST["id"]."】請求");
		$temperature = new Temperature();
		$results = $temperature->get($_POST["id"]);
		$log->info("XHR [temperatures] 取得 ".count($results)." 筆體溫資料");
		echo json_encode(array(
			"status" => STATUS_CODE::SUCCESS_NORMAL,
			"data_count" => count($results),
			"raw" => $results,
			"message" => "取得 ".count($results)." 筆資料。"
		), 0);
		break;
	default:
		// Need to connect to DB, so ping it first
		$db_ip = $system->getOraTargetDBIP();
		$db_port = $system->getOraTargetDBPort();
		$latency = $system->ping($db_ip, $db_port, 1);
		if ($latency > 999 || $latency == '') {
			$db_target = $system->getOraConnectTarget();
			$msg = "API伺服器無法連線至DB，請確認網路可連線至 $db_target 資料庫. ($db_ip:$db_port)";
			$log->error($msg);
			echoJSONResponse($msg, STATUS_CODE::FAIL_REMOTE_UNREACHABLE);
		} else {
			switch ($_POST["type"]) {
				case "overdue_reg_cases":
					$log->info("XHR [overdue_reg_cases] 近15天逾期案件查詢請求");
					$log->info("XHR [overdue_reg_cases] reviewer ID is '".$_POST["reviewer_id"]."'");
					$rows = $mock ? $cache->get('overdue_reg_cases') : $query->queryOverdueCasesIn15Days($_POST["reviewer_id"]);
					$cache->set('overdue_reg_cases', $rows);
					if (empty($rows)) {
						$log->info("XHR [overdue_reg_cases] 近15天查無逾期資料");
						$result = array(
							"status" => STATUS_CODE::SUCCESS_WITH_NO_RECORD,
							"items" => array(),
							"items_by_id" => array(),
							"data_count" => 0,
							"message" => "15天內查無逾期資料"
						);
						echo json_encode($result, 0);
					} else {
						$items = [];
						$items_by_id = [];
						foreach ($rows as $row) {
							$regdata = new RegCaseData($row);
							$this_item = array(
								"收件字號" => $regdata->getReceiveSerial(),
								"登記原因" => $regdata->getCaseReason(),
								"辦理情形" => $regdata->getStatus(),
								"收件時間" => $regdata->getReceiveDate()." ".$regdata->getReceiveTime(),
								"限辦期限" => $regdata->getDueDate(),
								"初審人員" => $regdata->getFirstReviewer() . " " . $regdata->getFirstReviewerID(),
								"作業人員" => $regdata->getCurrentOperator()
							);
							$items[] = $this_item;
							$items_by_id[$regdata->getFirstReviewerID()][] = $this_item;
						}
						$result = array(
							"status" => STATUS_CODE::SUCCESS_NORMAL,
							"items" => $items,
							"items_by_id" => $items_by_id,
							"data_count" => count($items),
							"raw" => $rows
						);
						$log->info("XHR [overdue_reg_cases] 近15天找到".count($items)."件逾期案件");
						echo json_encode($result, 0);
					}
					break;
				case "almost_overdue_reg_cases":
					$log->info("XHR [almost_overdue_reg_cases] 即將逾期案件查詢請求");
					$log->info("XHR [almost_overdue_reg_cases] reviewer ID is '".$_POST["reviewer_id"]."'");
					$rows = $mock ? $cache->get('almost_overdue_reg_cases') : $query->queryAlmostOverdueCases($_POST["reviewer_id"]);
					$cache->set('almost_overdue_reg_cases', $rows);
					if (empty($rows)) {
						$log->info("XHR [almost_overdue_reg_cases] 近4小時內查無即將逾期資料");
						$result = array(
							"status" => STATUS_CODE::SUCCESS_WITH_NO_RECORD,
							"items" => array(),
							"items_by_id" => array(),
							"data_count" => 0,
							"message" => "近4小時內查無即將逾期資料"
						);
						echo json_encode($result, 0);
					} else {
						$items = [];
						$items_by_id = [];
						foreach ($rows as $row) {
							$regdata = new RegCaseData($row);
							$this_item = array(
								"收件字號" => $regdata->getReceiveSerial(),
								"登記原因" => $regdata->getCaseReason(),
								"辦理情形" => $regdata->getStatus(),
								"收件時間" => $regdata->getReceiveDate()." ".$regdata->getReceiveTime(),
								"限辦期限" => $regdata->getDueDate(),
								"初審人員" => $regdata->getFirstReviewer() . " " . $regdata->getFirstReviewerID(),
								"作業人員" => $regdata->getCurrentOperator()
							);
							$items[] = $this_item;
							$items_by_id[$regdata->getFirstReviewerID()][] = $this_item;
						}
						$result = array(
							"status" => STATUS_CODE::SUCCESS_NORMAL,
							"items" => $items,
							"items_by_id" => $items_by_id,
							"data_count" => count($items),
							"raw" => $rows
						);
						$log->info("XHR [almost_overdue_reg_cases] 近4小時內找到".count($items)."件即將逾期案件");
						echo json_encode($result, 0);
					}
					break;
				case "watchdog":
					$log->info("XHR [watchdog] 監控請求");
					// use http://localhost the client will be "::1"
					// if you want to enable the watchdog, open http://localhost/watchdog,html with chrome on server. (do not close it)
					if ($client_ip == "::1") {	// $client_ip from init.php
						$watchdog = new WatchDog();
						$done = $watchdog->do();
						if ($done) {
							$log->info("XHR [watchdog] 檢查完成");
							echo json_encode(array(
								"status" => STATUS_CODE::SUCCESS_NORMAL,
								"data_count" => 0,
								"raw" => $done
							), 0);
						} else {
							$log->warning("XHR [watchdog] 非上班時段停止執行。");
							echoJSONResponse("XHR [watchdog] 非上班時段停止執行。");
						}
					} else {
						$log->info("XHR [watchdog] 停止執行WATCHDOG，因為IP不為「::1」");
						echoJSONResponse("XHR [watchdog] 停止執行WATCHDOG，因為IP不為「::1」", STATUS_CODE::FAIL_NOT_VALID_SERVER);
					}
					break;
				case "xcase-check":
					$log->info("XHR [xcase-check] 查詢跨所註記遺失請求");
					$query_result = $mock ? $cache->get('xcase-check') : $query->getProblematicCrossCases();
					$cache->set('xcase-check', $query_result);
					if (empty($query_result)) {
						$log->info("XHR [xcase-check] 查無資料");
						echoJSONResponse('查無資料');
					} else {
						$rows = $query_result;
						$case_ids = [];
						foreach ($rows as $row) {
							$case_ids[] = $row['RM01'].'-'.$row['RM02'].'-'.$row['RM03'];
						}
						$result = array(
							"status" => STATUS_CODE::SUCCESS_NORMAL,
							"case_ids" => $case_ids,
							"data_count" => count($rows),
							"raw" => $rows
						);
						
						$log->info("XHR [xcase-check] 找到".count($rows)."件案件遺失註記");
						echo json_encode($result, 0);
					}
					break;
				case "ez-payment-check":
					$log->info("XHR [ez-payment-check] 查詢異常悠遊卡交易請求");
					$query_result = $mock ? $cache->get('ez-payment-check') : $query->getEasycardPayment();
					$cache->set('ez-payment-check', $query_result);
					if (empty($query_result)) {
						$msg = "一周內查無悠遊卡交易異常資料！【大於等於".$week_ago."】";
						$log->info("XHR [ez-payment-check] $msg");
						echoJSONResponse($msg);
					} else {
						$result = array(
							"status" => STATUS_CODE::SUCCESS_NORMAL,
							"data_count" => count($query_result),
							"raw" => $query_result
						);
						$log->info("XHR [ez-payment-check] 找到 ".count($query_result)." 筆異常資料");
						echo json_encode($result, 0);
					}
					break;
				case "sur-problem-check":
					$log->info("XHR [sur-problem-check] 查詢測量問題案件請求");
					$query_result = $mock ? $cache->get('sur-problem-check') : $query->getSurProblematicCases();
					$cache->set('sur-problem-check', $query_result);
					if (empty($query_result)) {
						$msg = "查無異常測量案件資料。";
						$log->info("XHR [sur-problem-check] $msg");
						echoJSONResponse($msg);
					} else {
						$case_ids = array();
						foreach ($query_result as $result) {
							$case_ids[] = $result['MM01'].$result['MM02'].$result['MM03'];
						}
						$result = array(
							"status" => STATUS_CODE::SUCCESS_NORMAL,
							"data_count" => count($query_result),
							"raw" => $query_result,
							"ids" => $case_ids
						);
						$log->info("XHR [sur-problem-check] 找到 ".count($query_result)." 筆異常案件資料");
						echo json_encode($result, 0);
					}
					break;
				case "fix_xcase":
					$log->info("XHR [fix_xcase] 修正跨所註記遺失【".$_POST["id"]."】請求");
					$result_flag = $mock ? $cache->get('fix_xcase') : $query->fixProblematicCrossCases($_POST["id"]);
					$cache->set('fix_xcase', $result_flag);
					if ($result_flag) {
						$result = array(
							"status" => STATUS_CODE::SUCCESS_NORMAL,
							"data_count" => "0",
							"raw" => $result_flag
						);
						echo json_encode($result, 0);
					} else {
						$log->error("XHR [fix_xcase] 更新失敗【".$_POST["id"]."】");
						echoJSONResponse("更新失敗【".$_POST["id"]."】");
					}
					break;
				case "max":
					$log->info("XHR [max] 查詢案件最大號【".$_POST["year"].", ".$_POST["code"]."】請求");
					$year = $_POST["year"];
					$code = $_POST["code"];
					$max_num = $mock ? $cache->get('max') : $query->getMaxNumByYearWord($year, $code);
					$cache->set('max', $max_num);
					$log->info("XHR [max] 查詢成功【查詢 ${year}-${code} 回傳值為 ${max_num}");
					echo json_encode(array(
						"status" => STATUS_CODE::SUCCESS_NORMAL,
						"message" => "查詢 ${year}-${code} 回傳值為 ${max_num}",
						"max" => $max_num
					), 0);
					break;
				case "ralid":
					$log->info("XHR [ralid] 查詢土地標示部資料【".$_POST["text"]."】請求");
					$query_result = $mock ? $cache->get('ralid') : $query->getSectionRALIDCount($_POST["text"]);
					$cache->set('ralid', $query_result);
					if (empty($query_result)) {
						$log->info("XHR [ralid] 查無資料");
						echoJSONResponse('查無資料');
					} else {
						$result = array(
							"status" => STATUS_CODE::SUCCESS_NORMAL,
							"data_count" => count($query_result),
							"query_string" => $_POST["text"],
							"raw" => $query_result
						);
						$log->info("XHR [ralid] 查到 ".$result["data_count"]." 筆資料");
						echo json_encode($result, 0);
					}
					break;
				case "cert_log":
					$log->info("XHR [cert_log] 查詢謄本紀錄資料【".$_POST["section_code"].", ".implode(',', $_POST["numbers"])."】請求");
					$query_result = $mock ? $cache->get('cert_log') : $query->getCertLog($_POST["section_code"], $_POST["numbers"]);
					$cache->set('cert_log', $query_result);
					if (empty($query_result)) {
						$log->info("XHR [cert_log] 查無資料");
						echoJSONResponse("查無資料", STATUS_CODE::SUCCESS_WITH_NO_RECORD);
					} else {
						$result = array(
							"status" => STATUS_CODE::SUCCESS_NORMAL,
							"data_count" => count($query_result),
							"raw" => $query_result
						);
						$log->info("XHR [cert_log] 查到 ".$result["data_count"]." 筆資料");
						echo json_encode($result, 0);
					}
					break;
				case "crsms":
					$log->info("XHR [crsms] 查詢登記案件資料【".$_POST["id"]."】請求");
					$query_result = $mock ? $cache->get('crsms') : $query->getCRSMSCasesByPID($_POST["id"]);
					$cache->set('crsms', $query_result);
					if (empty($query_result)) {
						$log->info("XHR [crsms] 查無資料");
						echoJSONResponse('查無資料');
					} else {
						$baked = array();
						foreach ($query_result as $key => $row) {
							$data = new RegCaseData($row);
							$baked[] = $data->getBakedData();
						}
						$result = array(
							"status" => STATUS_CODE::SUCCESS_NORMAL,
							"data_count" => count($baked),
							"query_string" => "id=".$_POST["id"],
							"baked" => $baked
						);
						$log->info("XHR [crsms] 查詢成功");
						echo json_encode($result, 0);
					}
					break;
				case "cmsms":
					$log->info("XHR [cmsms] 查詢測量案件資料【".$_POST["id"]."】請求");
					$query_result = $mock ? $cache->get('cmsms') : $query->getCMSMSCasesByPID($_POST["id"]);
					$cache->set('cmsms', $query_result);
					if (empty($query_result)) {
						$log->info("XHR [cmsms] 查無資料");
						echoJSONResponse('查無資料');
					} else {
						$result = array(
							"status" => STATUS_CODE::SUCCESS_NORMAL,
							"data_count" => count($query_result),
							"query_string" => $_POST["id"],
							"raw" => $query_result
						);
						$log->info("XHR [cmsms] 查詢成功");
						echo json_encode($result, 0);
					}
					break;
				case "fix_easycard":
					$log->info("XHR [fix_easycard] 修正悠遊卡交易【".$_POST["qday"].", ".$_POST["pc_num"]."】請求");
					$result_flag = $mock ? $cache->get('fix_easycard') : $query->fixEasycardPayment($_POST["qday"], $_POST["pc_num"]);
					$cache->set('fix_easycard', $result_flag);
					if ($result_flag) {
						$result = array(
							"status" => STATUS_CODE::SUCCESS_NORMAL,
							"data_count" => "0",
							"raw" => $result_flag
						);
						$log->info("XHR [fix_easycard] 更新成功");
						echo json_encode($result, 0);
					} else {
						$log->error("XHR [fix_easycard] 更新失敗【".$_POST["qday"].", ".$_POST["pc_num"]."】");
						echoJSONResponse("更新失敗【".$_POST["qday"].", ".$_POST["pc_num"]."】");
					}
					break;
				case "sur_case":
					if (empty($_POST["id"])) {
						$log->error("XHR [sur_case] 查詢ID為空值");
						echoJSONResponse('查詢ID為空值');
						break;
					}
					$log->info("XHR [sur_case] 查詢測量案件【".$_POST["id"]."】請求");
					$row = $mock ? $cache->get('sur_case') : $query->getSurCaseDetail($_POST["id"]);
					$cache->set('sur_case', $row);
					if (empty($row)) {
						$log->info("XHR [sur_case] 查無資料");
						echoJSONResponse('查無資料');
					} else {
						$data = new SurCaseData($row);
						$log->info("XHR [sur_case] 查詢成功");
						echo $data->getJsonHtmlData();
					}
					break;
				case "fix_sur_delay_case":
					if (empty($_POST["id"])) {
						$log->error("XHR [fix_sur_delay_case] 查詢ID為空值");
						echoJSONResponse('查無資料');
						break;
					}
					$log->info("XHR [fix_sur_delay_case] 修正測量延期案件【".$_POST["id"].", ".$_POST["UPD_MM22"].", ".$_POST["CLR_DELAY"].", ".$_POST["FIX_COUNT"]."】請求");
					$result_flag = $mock ? $cache->get('fix_sur_delay_case') : $query->fixSurDelayCase($_POST["id"], $_POST["UPD_MM22"], $_POST["CLR_DELAY"], $_POST["FIX_COUNT"]);
					$cache->set('fix_sur_delay_case', $result_flag);
					if ($result_flag) {
						$result = array(
							"status" => STATUS_CODE::SUCCESS_NORMAL,
							"data_count" => "0",
							"raw" => $result_flag
						);
						$log->info("XHR [fix_sur_delay_case] 更新成功");
						echo json_encode($result, 0);
					} else {
						$log->info("XHR [fix_sur_delay_case] 更新失敗【".$_POST["id"]."】");
						echoJSONResponse("更新失敗【".$_POST["id"]."】");
					}
					break;
				case "prc_case":
					$log->info("XHR [prc_case] 查詢地價案件【".$_POST["id"]."】請求");
					$rows = $mock ? $cache->get('prc_case') : $query->getPrcCaseAll($_POST["id"]);
					$cache->set('prc_case', $rows);
					if (empty($rows)) {
						$log->info("XHR [prc_case] 查無資料");
						echoJSONResponse('查無資料');
					} else {
						$data = new PrcAllCasesData($rows);
						$log->info("XHR [prc_case] 查詢成功");
						echo json_encode(array(
							"status" => STATUS_CODE::SUCCESS_NORMAL,
							"data_count" => count($rows),
							"html" => $data->getTableHtml()
						), 0);
					}
					break;
				case "expac":
					$log->info("XHR [expac] 查詢規費收費項目【".$_POST["year"].", ".$_POST["num"]."】請求");
					// make total number length is 7
					$rows = $mock ? $cache->get('expac') : $query->getExpacItems($_POST["year"], str_pad($_POST["num"], 7, '0', STR_PAD_LEFT));
					$cache->set('expac', $rows);
					if (empty($rows)) {
						$log->info("XHR [expac] 查無資料");
						echoJSONResponse('查無資料');
					} else {
						$result = array(
							"status" => STATUS_CODE::SUCCESS_NORMAL,
							"data_count" => count($rows),
							"query_string" => "year=".$_POST["year"]."&num=".$_POST["num"],
							"raw" => $rows
						);
						$log->info("XHR [expac] 查詢成功");
						echo json_encode($result, 0);
					}
					break;
				case "mod_expac":
					$log->info("XHR [mod_expac] 修正規費項目【".$_POST["year"].", ".$_POST["num"].", ".$_POST["code"].", ".$_POST["amount"]."】請求");
					// make total number length is 7
					$result_flag = $mock ? $cache->get('mod_expac') : $query->modifyExpacItem($_POST["year"], str_pad($_POST["num"], 7, '0', STR_PAD_LEFT), $_POST["code"], $_POST["amount"]);
					$cache->set('mod_expac', $result_flag);
					if ($result_flag) {
						$result = array(
							"status" => STATUS_CODE::SUCCESS_NORMAL,
							"data_count" => "0",
							"raw" => $result_flag
						);
						$log->info("XHR [mod_expac] 更新成功");
						echo json_encode($result, 0);
					} else {
						$log->error("XHR [mod_expac] 更新失敗【".$_POST["year"].", ".$_POST["num"].", ".$_POST["code"].", ".$_POST["amount"]."】");
						echoJSONResponse("更新失敗【".$_POST["year"].", ".$_POST["num"].", ".$_POST["code"].", ".$_POST["amount"]."】");
					}
					break;
				case "expaa":
					$log->info("XHR [expaa] 查詢規費資料【".$_POST["qday"].", ".(array_key_exists('num', $_POST) ? $_POST["num"] : '')."】請求");
					// make total number length is 7
					$rows = $mock ? $cache->get('expaa') : $query->getExpaaData($_POST["qday"], empty($_POST["num"]) ? "" : str_pad($_POST["num"], 7, '0', STR_PAD_LEFT));
					$cache->set('expaa', $rows);
					if (empty($rows)) {
						$log->info("XHR [expaa] 查無資料。【".$_POST["qday"].", ".$_POST["num"]."】");
						echoJSONResponse("查無資料。【".$_POST["qday"].", ".$_POST["num"]."】");
					} else if (count($rows) == 1 && $_POST["list_mode"] == "false") {
						$mapping = array();
						// AA39 is 承辦人員, AA89 is 修改人員代碼
						$users = $cache->getUserNames();
						foreach ($rows[0] as $key => $value) {
							if (is_null($value)) {
								continue;
							}
							$col_mapping = include(INC_DIR."/config/Config.ColsNameMapping.EXPAA.php");
							if (empty($col_mapping[$key])) {
								$mapping[$key] = $value;
							} else {
								$mapping[$col_mapping[$key]] = ($key == "AA39" || $key == "AA89") ? $users[$value]."【${value}】" : $value;
							}
						}
						$result = array(
							"status" => STATUS_CODE::SUCCESS_NORMAL,
							"data_count" => count($rows),
							"query_string" => "qday=".$_POST["qday"]."&num=".$_POST["num"],
							"raw" => $mapping
						);
						$log->info("XHR [expaa] 查詢 ".$_POST["qday"]." 電腦給號 ".$_POST["num"]." 成功");
						echo json_encode($result, 0);
					} else {
						$result = array(
							"status" => STATUS_CODE::SUCCESS_WITH_MULTIPLE_RECORDS,
							"data_count" => count($rows),
							"message" => "於 ".$_POST["qday"]." 找到 ".count($rows)." 筆資料",
							"query_string" => "qday=".$_POST["qday"],
							"raw" => $rows
						);
						$log->info("XHR [expaa] 於 ".$_POST["qday"]." 找到 ".count($rows)." 筆資料");
						echo json_encode($result, 0);
					}
					break;
				case "expaa_AA08_update":
				case "expaa_AA09_update":
				case "expaa_AA100_update":
					$column = $_POST["type"] == "expaa_AA09_update" ? "AA09" : ($_POST["type"] == "expaa_AA100_update" ? "AA100" : "AA08");
					$log->info("XHR [expaa_AA08_update/expaa_AA09_update/expaa_AA100_update] 修正規費資料【$column".", ".$_POST["date"].", ".$_POST["number"].", ".$_POST["update_value"]."】請求");
					$result_flag = $mock ? $cache->get("expaa_${column}_update") : $query->updateExpaaData($column, $_POST["date"], str_pad($_POST["number"], 7, '0', STR_PAD_LEFT), $_POST["update_value"]);
					$cache->set("expaa_${column}_update", $result_flag);
					if ($result_flag) {
						$result = array(
							"status" => STATUS_CODE::SUCCESS_NORMAL,
							"data_count" => "0",
							"raw" => $result_flag,
							"message" => "更新 ${column} 成功"
						);
						$log->info("XHR [expaa_AA08_update/expaa_AA09_update/expaa_AA100_update] 更新 ${column} 成功");
						echo json_encode($result, 0);
					} else {
						$log->error("XHR [expaa_AA08_update/expaa_AA09_update/expaa_AA100_update] 更新規費欄位失敗【".$_POST["date"].", ".$_POST["number"].", ".$column.", ".$_POST["update_value"]."】");
						echoJSONResponse("更新規費欄位失敗【".$_POST["date"].", ".$_POST["number"].", ".$column.", ".$_POST["update_value"]."】");
					}
					break;
				case "get_dummy_ob_fees":
					$log->info("XHR [get_dummy_ob_fees] 查詢作廢規費假資料 請求");
					$rows = $mock ? $cache->get('get_dummy_ob_fees') : $query->getDummyObFees();
					$cache->set('get_dummy_ob_fees', $rows);
					$len = count($rows);
					if ($len > 0) {
						$result = array(
							"status" => STATUS_CODE::SUCCESS_NORMAL,
							"data_count" => $len,
							"raw" => $rows,
							"message" => "更新 ${column} 成功"
						);
						$log->info("XHR [get_dummy_ob_fees] 取得 ${len} 件假資料");
						echo json_encode($result, 0);
					} else {
						$log->error("XHR [get_dummy_ob_fees] 本年度(${this_year})查無作廢規費假資料");
						echoJSONResponse("本年度(${this_year})查無作廢規費假資料");
					}
					break;
				case "add_dummy_ob_fees":
					$log->info("XHR [add_dummy_ob_fees] 新增作廢規費假資料 請求");
					$result_flag = $mock ? $cache->get('add_dummy_ob_fees') : $query->addDummyObFees($_POST["today"], $_POST["pc_number"], $_POST["operator"], $_POST["fee_number"], $_POST["reason"]);
					$cache->set('add_dummy_ob_fees', $result_flag);
					if ($result_flag) {
						$result = array(
							"status" => STATUS_CODE::SUCCESS_NORMAL,
							"data_count" => "0",
							"pc_number" => $_POST["pc_number"],
							"raw" => $result_flag,
							"message" => "新增假資料成功【".$_POST["today"].", ".$_POST["pc_number"]."】"
						);
						$log->info("XHR [add_dummy_ob_fees] 新增假資料成功");
						echo json_encode($result, 0);
					} else {
						$log->error("XHR [add_dummy_ob_fees] 新增假資料失敗【".$_POST["today"].", ".$_POST["pc_number"].", ".$_POST["operator"].", ".$_POST["fee_number"].", ".$_POST["reason"]."】");
						echoJSONResponse("新增假資料失敗【".$_POST["today"].", ".$_POST["pc_number"].", ".$_POST["operator"].", ".$_POST["fee_number"].", ".$_POST["reason"]."】");
					}
					break;
				case "diff_xcase":
					$log->info("XHR [diff_xcase] 查詢同步案件資料【".$_POST["id"]."】請求");
					$rows = $mock ? $cache->get('diff_xcase') : $query->getXCaseDiff($_POST["id"]);
					$cache->set('diff_xcase', $rows);
					if ($rows === -1) {
						$log->warning("XHR [diff_xcase] 參數格式錯誤【".$_POST["id"]."】");
						echoJSONResponse("參數格式錯誤【".$_POST["id"]."】");
					} else if ($rows === -2) {
						$log->warning("XHR [diff_xcase] 遠端查無資料【".$_POST["id"]."】");
						echoJSONResponse("遠端查無資料【".$_POST["id"]."】", STATUS_CODE::FAIL_WITH_REMOTE_NO_RECORD);
					} else if ($rows === -3) {
						$log->warning("XHR [diff_xcase] 本地查無資料【".$_POST["id"]."】");
						echoJSONResponse("本地查無資料【".$_POST["id"]."】", STATUS_CODE::FAIL_WITH_LOCAL_NO_RECORD);
					} else if (is_array($rows) && empty($rows)) {
						$log->info("XHR [diff_xcase] 遠端資料與本所一致【".$_POST["id"]."】");
						echoJSONResponse("遠端資料與本所一致【".$_POST["id"]."】");
					} else {
						$result = array(
							"status" => STATUS_CODE::SUCCESS_NORMAL,
							"data_count" => count($rows),
							"query_string" => "id=".$_POST["id"],
							"raw" => $rows
						);
						$log->info("XHR [diff_xcase] 比對成功");
						echo json_encode($result, 0);
					}
					break;
				case "inst_xcase":
					$log->info("XHR [inst_xcase] 插入遠端案件【".$_POST["id"]."】請求");
					$result_flag = $mock ? $cache->get('inst_xcase') : $query->instXCase($_POST["id"]);
					$cache->set('inst_xcase', $result_flag);
					if ($result_flag === true) {
						$result = array(
							"status" => STATUS_CODE::SUCCESS_NORMAL,
							"data_count" => "0",
							"raw" => $result_flag
						);
						$log->info("XHR [inst_xcase] 新增成功");
						echo json_encode($result, 0);
					} else if ($result_flag === -1) {
						$log->error("XHR [inst_xcase] 傳入ID錯誤，新增失敗【".$_POST["id"]."】");
						echoJSONResponse("傳入ID錯誤，新增失敗【".$_POST["id"]."】");
					} else if ($result_flag === -2) {
						$log->error("XHR [inst_xcase] 遠端無案件資料，新增失敗【".$_POST["id"]."】");
						echoJSONResponse("遠端無案件資料，新增失敗【".$_POST["id"]."】");
					} else {
						$log->error("XHR [inst_xcase] 新增失敗【".$_POST["id"]."】");
						echoJSONResponse("新增失敗【".$_POST["id"]."】");
					}
					break;
				case "sync_xcase":
					$log->info("XHR [sync_xcase] 同步遠端案件【".$_POST["id"]."】請求");
					$result_flag = $mock ? $cache->get('sync_xcase') : $query->syncXCase($_POST["id"]);
					$cache->set('sync_xcase', $result_flag);
					if ($result_flag) {
						$result = array(
							"status" => STATUS_CODE::SUCCESS_NORMAL,
							"data_count" => "0",
							"raw" => $result_flag
						);
						$log->info("XHR [sync_xcase] 同步成功【".$_POST["id"]."】");
						echo json_encode($result, 0);
					} else {
						$log->error("XHR [sync_xcase] 同步失敗【".$_POST["id"]."】");
						echoJSONResponse("同步失敗【".$_POST["id"]."】");
					}
					break;
				case "sync_xcase_column":
					$log->info("XHR [sync_xcase_column] 同步遠端案件之特定欄位【".$_POST["id"].", ".$_POST["column"]."】請求");
					$result_flag = $mock ? $cache->get('sync_xcase_column') : $query->syncXCaseColumn($_POST["id"], $_POST["column"]);
					$cache->set('sync_xcase_column', $result_flag);
					if ($result_flag) {
						$result = array(
							"status" => STATUS_CODE::SUCCESS_NORMAL,
							"data_count" => "0",
							"raw" => $result_flag
						);
						$log->info("XHR [sync_xcase_column] 同步成功【".$_POST["id"].", ".$_POST["column"]."】");
						echo json_encode($result, 0);
					} else {
						$log->error("XHR [sync_xcase_column] 同步失敗【".$_POST["id"].", ".$_POST["column"]."】");
						echoJSONResponse("同步失敗【".$_POST["id"].", ".$_POST["column"]."】");
					}
					break;
				case "announcement_data":
					$log->info("XHR [announcement_data] 查詢公告資料請求");
					$rows = $mock ? $cache->get('announcement_data') : $query->getAnnouncementData();
					$cache->set('announcement_data', $rows);
					$result = array(
						"status" => STATUS_CODE::SUCCESS_NORMAL,
						"data_count" => count($rows),
						"raw" => $rows
					);
					$log->info("XHR [announcement_data] 取得公告資料成功");
					echo json_encode($result, 0);
					break;
				case "update_announcement_data":
					$log->info("XHR [update_announcement_data] 更新公告資料【".$_POST["code"].",".$_POST["day"].",".$_POST["flag"]."】請求");
					$result_flag = $mock ? $cache->get('update_announcement_data') : $query->updateAnnouncementData($_POST["code"], $_POST["day"], $_POST["flag"]);
					$cache->set('update_announcement_data', $result_flag);
					if ($result_flag) {
						$result = array(
							"status" => STATUS_CODE::SUCCESS_NORMAL,
							"data_count" => "0",
							"raw" => $result_flag
						);
						$log->info("XHR [update_announcement_data] 更新公告期限成功");
						echo json_encode($result, 0);
					} else {
						$log->error("XHR [update_announcement_data] 更新公告期限失敗【".$_POST["code"].", ".$_POST["day"].", ".$_POST["flag"]."】");
						echoJSONResponse("更新公告期限失敗【".$_POST["code"].", ".$_POST["day"].", ".$_POST["flag"]."】");
					}
					break;
				case "clear_announcement_flag":
					$log->info("XHR [clear_announcement_flag] 清除先行准登旗標請求");
					$result_flag = $mock ? $cache->get('clear_announcement_flag') : $query->clearAnnouncementFlag();
					$cache->set('clear_announcement_flag', $result_flag);
					if ($result_flag) {
						$result = array(
							"status" => STATUS_CODE::SUCCESS_NORMAL,
							"data_count" => "0",
							"raw" => $result_flag
						);
						$log->info("XHR [clear_announcement_flag] 清除成功");
						echo json_encode($result, 0);
					} else {
						$log->error("XHR [clear_announcement_flag] 清除先行准登失敗");
						echoJSONResponse("清除先行准登失敗");
					}
					break;
				case "query_temp_data":
					$log->info("XHR [query_temp_data] 查詢暫存資料【".$_POST["year"].", ".$_POST["code"].", ".$_POST["number"]."】請求");
					$rows = $mock ? $cache->get('query_temp_data') : $query->getCaseTemp($_POST["year"], $_POST["code"], str_pad($_POST["number"], 6, '0', STR_PAD_LEFT));
					$cache->set('query_temp_data', $rows);
					if (!empty($rows)) {
						$result = array(
							"status" => STATUS_CODE::SUCCESS_NORMAL,
							"data_count" => count($rows),	// how many tables that have temp data for this case
							"raw" => $rows
						);
						$log->info("XHR [query_temp_data] 查詢成功");
						echo json_encode($result, 0);
					} else {
						$log->info("XHR [query_temp_data] ".$_POST["year"]."-".$_POST["code"]."-".$_POST["number"]." 查無暫存資料");
						echoJSONResponse("本案件查無暫存資料。");
					}
					break;
				case "clear_temp_data":
					$log->info("XHR [clear_temp_data] 清除暫存【".$_POST["year"].", ".$_POST["code"].", ".$_POST["number"].", ".$_POST["table"]."】請求");
					$result_flag = $mock ? $cache->get('clear_temp_data') : $query->clearCaseTemp($_POST["year"], $_POST["code"], str_pad($_POST["number"], 6, '0', STR_PAD_LEFT), $_POST["table"]);
					$cache->set('clear_temp_data', $result_flag);
					if ($result_flag) {
						$result = array(
							"status" => STATUS_CODE::SUCCESS_NORMAL,
							"data_count" => "0",
							"raw" => $result_flag,
							"query_string" => "year=".$_POST["year"]."&code=".$_POST["code"]."&number=".$_POST["number"]."&table=".$_POST["table"]
						);
						$log->info("XHR [clear_temp_data] 清除暫存資料成功");
						echo json_encode($result, 0);
					} else {
						$log->error("XHR [clear_temp_data] 清除暫存資料失敗");
						echoJSONResponse("清除暫存資料失敗");
					}
					break;
				case "upd_case_column":
					$log->info("XHR [upd_case_column] 更新案件特定欄位【".$_POST["id"].", ".$_POST["table"].", ".$_POST["column"].", ".$_POST["value"]."】請求");
					$result_flag = $mock ? $cache->get('upd_case_column') : $query->updateCaseColumnData($_POST["id"], $_POST["table"], $_POST["column"], $_POST["value"]);
					$cache->set('upd_case_column', $result_flag);
					if ($result_flag) {
						$result = array(
							"status" => STATUS_CODE::SUCCESS_NORMAL,
							"data_count" => "0",
							"raw" => $result_flag,
							"query_string" => "id=".$_POST["id"]."&table=".$_POST["table"]."&column=".$_POST["column"]."&value=".$_POST["value"]
						);
						$log->info("XHR [upd_case_column] 更新".$_POST["table"].".".$_POST["column"]."欄位為「".$_POST["value"]."」成功");
						echo json_encode($result, 0);
					} else {
						$log->error("XHR [upd_case_column] 更新".$_POST["table"].".".$_POST["column"]."欄位為「".$_POST["value"]."」失敗");
						echoJSONResponse("更新".$_POST["table"].".".$_POST["column"]."欄位為「".$_POST["value"]."」失敗");
					}
					break;
				case "code_data":
					$rows = $mock ? $cache->get('code_data') : $query->getCodeData($_POST["year"]);
					$cache->set('code_data', $rows);
					// 回傳欄位：YEAR, CODE, CODE_NAME, COUNT, CODE_TYPE
					$log->info("XHR [code_data] 取得 ".count($rows)." 筆資料");
					echo json_encode(array(
						"status" => STATUS_CODE::SUCCESS_NORMAL,
						"data_count" => count($rows),
						"raw" => $rows,
						"message" => "取得 ".count($rows)." 筆資料。"
					), 0);
					break;
				case "reg_code":
					$log->info("XHR [reg_code] 取得登記案件字列表請求");
					$log->info("XHR [reg_code] 取得 ".count(REG_CODE)." 群資料");
					echo json_encode(array(
						"status" => STATUS_CODE::SUCCESS_NORMAL,
						"data_count" => count(REG_CODE),
						"raw" => REG_CODE,
						"message" => "取得 ".count(REG_CODE)." 群資料。"
					), 0);
					break;
				case "reg_case":
					if (empty($_POST["id"])) {
						$log->error("XHR [reg_case] 查詢ID為空值");
						echoJSONResponse('查無資料');
						break;
					}
					$log->info("XHR [reg_case] 查詢登記案件【".$_POST["id"]."】請求");
					$row = $mock ? $cache->get('reg_case') : $query->getRegCaseDetail($_POST["id"]);
					$cache->set('reg_case', $row);
					if (empty($row)) {
						$log->info("XHR [reg_case] 查無資料");
						echoJSONResponse('查無資料');
					} else {
						$data = new RegCaseData($row);
						$log->info("XHR [reg_case] 查詢成功");
						$result = array(
							"status" => STATUS_CODE::SUCCESS_NORMAL,
							"data_count" => 1,
							"query_string" => "id=".$_POST["id"],
							"baked" => $data->getBakedData()
						);
						echo json_encode($result, 0);
					}
					break;
				case "reg_cases_by_day":
					if (empty($_POST["qday"])) {
						$_POST["qday"] = $today;
					}
					$log->info("XHR [reg_cases_by_day] 查詢登記案件 BY DAY【".$_POST["qday"]."】請求");
					$rows = $mock ? $cache->get('reg_cases_by_day') : $query->queryAllCasesByDate($_POST["qday"]);
					$cache->set('reg_cases_by_day', $rows);
					if (empty($rows)) {
						$log->info("XHR [reg_cases_by_day] 查無資料");
						echoJSONResponse('查無資料');
					} else {
						$baked = array();
						foreach ($rows as $row) {
							$data = new RegCaseData($row);
							$baked[] = $data->getBakedData();
						}
						$count = count($baked);
						$log->info("XHR [reg_cases_by_day] 查詢成功 ($count)");
						$result = array(
							"status" => STATUS_CODE::SUCCESS_NORMAL,
							"data_count" => $count,
							"query_string" => "qday=".$_POST["qday"],
							"baked" => $baked
						);
						echo json_encode($result, 0);
					}
					break;
				case "reg_reason_cases_by_month":
					if (empty($_POST["query_month"])) {
						$_POST["query_month"] = substr($today, 0, 5);
					}
					$reason_code = $_POST['reason_code'];
					$query_month = $_POST['query_month'];
					$log->info("XHR [reg_reason_cases_by_month] 查詢登記案件 BY MONTH【${reason_code}, ${query_month}】請求");
					$rows = $mock ? $cache->get('reg_reason_cases_by_month') : $query->queryReasonCasesByMonth($reason_code, $query_month);
					$cache->set('reg_reason_cases_by_month', $rows);
					if (empty($rows)) {
						$log->info("XHR [reg_reason_cases_by_month] 查無資料");
						echoJSONResponse("XHR [reg_reason_cases_by_month] 查無資料", STATUS_CODE::SUCCESS_WITH_NO_RECORD);
					} else {
						$baked = array();
						foreach ($rows as $row) {
							$data = new RegCaseData($row);
							$baked[] = $data->getBakedData();
						}
						$count = count($baked);
						$status = $count > 1 ? STATUS_CODE::SUCCESS_WITH_MULTIPLE_RECORDS : STATUS_CODE::SUCCESS_NORMAL;
						$log->info("XHR [reg_reason_cases_by_month] $reason_code 查到 $count 筆資料");
						echoJSONResponse("$reason_code 查到 $count 筆資料。", $status, array(
							"data_count" => $count,
							"query_string" => "query_month=".$query_month."&reason_code=".$reason_code,
							"baked" => $baked
						));
					}
					break;
				case "reg_court_cases_by_month":
					if (empty($_POST["query_month"])) {
						$_POST["query_month"] = substr($today, 0, 5);
					}
					$query_month = $_POST['query_month'];
					$log->info("XHR [reg_court_cases_by_month] 查詢登記法院囑託案件 BY MONTH【${reason_code}, ${query_month}】請求");
					$rows = $mock ? $cache->get('reg_court_cases_by_month') : $query->queryCourtCasesByMonth($query_month);
					$cache->set('reg_court_cases_by_month', $rows);
					if (empty($rows)) {
						$log->info("XHR [reg_court_cases_by_month] 查無資料");
						echoJSONResponse("XHR [reg_court_cases_by_month] 查無資料", STATUS_CODE::SUCCESS_WITH_NO_RECORD);
					} else {
						$baked = array();
						foreach ($rows as $row) {
							$data = new RegCaseData($row);
							$baked[] = $data->getBakedData();
						}
						$count = count($baked);
						$status = $count > 1 ? STATUS_CODE::SUCCESS_WITH_MULTIPLE_RECORDS : STATUS_CODE::SUCCESS_NORMAL;
						$log->info("XHR [reg_court_cases_by_month] 登記法院囑託案件查到 $count 筆資料");
						echoJSONResponse("登記法院囑託案件查到 $count 筆資料。", $status, array(
							"data_count" => $count,
							"query_string" => "query_month=".$query_month,
							"baked" => $baked
						));
					}
					break;
				case "reg_fix_cases_by_month":
					if (empty($_POST["query_month"])) {
						$_POST["query_month"] = substr($today, 0, 5);
					}
					$query_month = $_POST['query_month'];
					$log->info("XHR [reg_fix_cases_by_month] 查詢登記補正案件 BY MONTH【${query_month}】請求");
					$rows = $mock ? $cache->get('reg_fix_cases_by_month') : $query->queryFixCasesByMonth($query_month);
					$cache->set('reg_fix_cases_by_month', $rows);
					if (empty($rows)) {
						$log->info("XHR [reg_fix_cases_by_month] 查無資料");
						echoJSONResponse("XHR [reg_fix_cases_by_month] 查無資料", STATUS_CODE::SUCCESS_WITH_NO_RECORD);
					} else {
						$baked = array();
						foreach ($rows as $row) {
							$data = new RegCaseData($row);
							$baked[] = $data->getBakedData();
						}
						$count = count($baked);
						$status = $count > 1 ? STATUS_CODE::SUCCESS_WITH_MULTIPLE_RECORDS : STATUS_CODE::SUCCESS_NORMAL;
						$log->info("XHR [reg_fix_cases_by_month] 補正案件查到 $count 筆資料");
						echoJSONResponse("補正案件查到 $count 筆資料。", $status, array(
							"data_count" => $count,
							"query_string" => "query_month=".$query_month,
							"baked" => $baked
						));
					}
					break;
				case "reg_reject_cases_by_month":
					if (empty($_POST["query_month"])) {
						$_POST["query_month"] = substr($today, 0, 5);
					}
					$query_month = $_POST['query_month'];
					$log->info("XHR [reg_reject_cases_by_month] 查詢登記駁回案件 BY MONTH【${query_month}】請求");
					$rows = $mock ? $cache->get('reg_reject_cases_by_month') : $query->queryRejectCasesByMonth($query_month);
					$cache->set('reg_reject_cases_by_month', $rows);
					if (empty($rows)) {
						$log->info("XHR [reg_reject_cases_by_month] 查無資料");
						echoJSONResponse("XHR [reg_reject_cases_by_month] 查無資料", STATUS_CODE::SUCCESS_WITH_NO_RECORD);
					} else {
						$baked = array();
						foreach ($rows as $row) {
							$data = new RegCaseData($row);
							$baked[] = $data->getBakedData();
						}
						$count = count($baked);
						$status = $count > 1 ? STATUS_CODE::SUCCESS_WITH_MULTIPLE_RECORDS : STATUS_CODE::SUCCESS_NORMAL;
						$log->info("XHR [reg_reject_cases_by_month] 駁回案件查到 $count 筆資料");
						echoJSONResponse("駁回案件查到 $count 筆資料。", $status, array(
							"data_count" => $count,
							"query_string" => "query_month=".$query_month,
							"baked" => $baked
						));
					}
					break;
				case "reg_remote_cases_by_month":
					if (empty($_POST["query_month"])) {
						$_POST["query_month"] = substr($today, 0, 5);
					}
					$query_month = $_POST['query_month'];
					$log->info("XHR [reg_remote_cases_by_month] 查詢遠途先審案件 BY MONTH【${query_month}】請求");
					$rows = $mock ? $cache->get('reg_remote_cases_by_month') : $query->queryRegRemoteCasesByMonth($query_month);
					$cache->set('reg_remote_cases_by_month', $rows);
					if (empty($rows)) {
						$log->info("XHR [reg_remote_cases_by_month] 查無遠途先審案件資料");
						echoJSONResponse("XHR [reg_remote_cases_by_month] 查無遠途先審案件資料", STATUS_CODE::SUCCESS_WITH_NO_RECORD);
					} else {
						$count = count($rows);
						$status = $count > 1 ? STATUS_CODE::SUCCESS_WITH_MULTIPLE_RECORDS : STATUS_CODE::SUCCESS_NORMAL;
						$log->info("XHR [reg_remote_cases_by_month] 遠途先審案件查到 $count 筆資料");
						echoJSONResponse("遠途先審案件查到 $count 筆資料。", $status, array(
							"data_count" => $count,
							"query_string" => "query_month=".$query_month,
							"raw" => $rows
						));
					}
					break;
				case "reg_subcases_by_month":
					if (empty($_POST["query_month"])) {
						$_POST["query_month"] = substr($today, 0, 5);
					}
					$query_month = $_POST['query_month'];
					$log->info("XHR [reg_subcases_by_month] 查詢本所處理跨所子號案件 BY MONTH【${query_month}】請求");
					$rows = $mock ? $cache->get('reg_subcases_by_month') : $query->queryRegSubCasesByMonth($query_month);
					$cache->set('reg_subcases_by_month', $rows);
					if (empty($rows)) {
						$log->info("XHR [reg_subcases_by_month] 查無本所處理跨所子號案件資料");
						echoJSONResponse("XHR [reg_subcases_by_month] 查無本所處理跨所子號案件資料", STATUS_CODE::SUCCESS_WITH_NO_RECORD);
					} else {
						$count = count($rows);
						$status = $count > 1 ? STATUS_CODE::SUCCESS_WITH_MULTIPLE_RECORDS : STATUS_CODE::SUCCESS_NORMAL;
						$log->info("XHR [reg_subcases_by_month] 本所處理跨所子號案件查到 $count 筆資料");
						echoJSONResponse("本所處理跨所子號案件查到 $count 筆資料。", $status, array(
							"data_count" => $count,
							"query_string" => "query_month=".$query_month,
							"raw" => $rows
						));
					}
					break;
				case "reg_stats":
					if (empty($_POST["year_month"])) {
						$log->error("XHR [reg_stats] 查詢年月為空值");
						echoJSONResponse('查無資料');
						break;
					}
					$log->info("XHR [reg_stats] 查詢登記案件統計【".$_POST["year_month"]."】請求");
					$rows = $mock ? $cache->get('reg_stats') : $query->getRegCaseStatsMonthly($_POST["year_month"]);
					$cache->set('reg_stats', $rows);
					if (empty($rows)) {
						$log->info("XHR [reg_stats] 查無資料");
						echoJSONResponse('查無資料');
					} else {
						$log->info("XHR [reg_stats] 查詢成功");
						$result = array(
							"status" => STATUS_CODE::SUCCESS_NORMAL,
							"data_count" => count($rows),
							"raw" => $rows	// each record key is reason, count
						);
						echo json_encode($result, 0);
					}
					break;
				case "regf_by_month":
					if (empty($_POST["query_month"])) {
						$_POST["query_month"] = substr($today, 0, 5);
					}
					$query_month = $_POST['query_month'];
					$log->info("XHR [regf_by_month] 查詢外國人地權登記統計檔 BY MONTH【${query_month}】請求");
					$rows = $mock ? $cache->get('regf_by_month') : $query->queryRegfCasesByMonth($query_month);
					$cache->set('regf_by_month', $rows);
					if (empty($rows)) {
						$log->info("XHR [regf_by_month] 查無外國人地權登記統計檔資料");
						echoJSONResponse("XHR [regf_by_month] 查無外國人地權登記統計檔資料", STATUS_CODE::SUCCESS_WITH_NO_RECORD);
					} else {
						$count = count($rows);
						$status = $count > 1 ? STATUS_CODE::SUCCESS_WITH_MULTIPLE_RECORDS : STATUS_CODE::SUCCESS_NORMAL;
						$log->info("XHR [regf_by_month] 外國人地權登記統計檔查到 $count 筆資料");
						echoJSONResponse("外國人地權登記統計檔查到 $count 筆資料。", $status, array(
							"data_count" => $count,
							"query_string" => "query_month=".$query_month,
							"raw" => $rows
						));
					}
					break;
				case "reg_upd_col":
					$log->info("XHR [reg_upd_col] 更新案件欄位【".$_POST["rm01"].", ".$_POST["rm02"].", ".$_POST["rm03"].", ".$_POST["col"].", ".$_POST["val"]."】請求");
					$result_flag = $mock ? $cache->get('reg_upd_col') : $query->updateCaseColumnData($_POST["rm01"].$_POST["rm02"].$_POST["rm03"], "MOICAS.CRSMS", $_POST["col"], $_POST["val"]);
					$cache->set('reg_upd_col', $result_flag);
					if ($result_flag) {
						$result = array(
							"status" => STATUS_CODE::SUCCESS_NORMAL,
							"data_count" => "0",
							"raw" => $result_flag,
							"query_string" => "RM01=".$_POST["rm01"]."&RM02=".$_POST["rm02"]."&RM03=".$_POST["rm03"]."&COL=".$_POST["col"]."&VAL=".$_POST["val"]
						);
						$log->info("XHR [reg_upd_col] 更新案件欄位成功");
						echo json_encode($result, 0);
					} else {
						$log->error("XHR [reg_upd_col] 更新案件欄位失敗");
						echoJSONResponse("更新案件欄位失敗");
					}
					break;
				case "reg_rm30_E_case":
					$log->info("XHR [reg_rm30_E_case] 查詢登記請示案件請求");
					$rows = $mock ? $cache->get('reg_rm30_E_case') : $query->getRM30ECase();
					$cache->set('reg_rm30_E_case', $rows);
					if (empty($rows)) {
						$log->info("XHR [reg_rm30_E_case] 查無資料");
						echoJSONResponse('查無資料');
					} else {
						$total = count($rows);
						$log->info("XHR [reg_rm30_E_case] 查詢成功($total)");
						$baked = array();
						foreach ($rows as $row) {
							$data = new RegCaseData($row);
							$baked[] = $data->getBakedData();
						}
						$result = array(
							"status" => STATUS_CODE::SUCCESS_WITH_MULTIPLE_RECORDS,
							"message" => "查詢成功，找到 $total 筆請示中資料。",
							"data_count" => $total,
							"baked" => $baked
						);
						echo json_encode($result, 0);
					}
					break;
				case "expba_refund_cases_by_month":
					if (empty($_POST["query_month"])) {
						$_POST["query_month"] = substr($today, 0, 5);
					}
					$query_month = $_POST['query_month'];
					$log->info("XHR [expba_refund_cases_by_month] 查詢退費案件 BY MONTH【${query_month}】請求");
					$rows = $mock ? $cache->get('expba_refund_cases_by_month') : $query->queryEXPBARefundCasesByMonth($query_month);
					$cache->set('expba_refund_cases_by_month', $rows);
					if (empty($rows)) {
						$log->info("XHR [expba_refund_cases_by_month] 查無資料");
						echoJSONResponse("XHR [expba_refund_cases_by_month] 查無資料", STATUS_CODE::SUCCESS_WITH_NO_RECORD);
					} else {
						$count = count($rows);
						$status = $count > 1 ? STATUS_CODE::SUCCESS_WITH_MULTIPLE_RECORDS : STATUS_CODE::SUCCESS_NORMAL;
						$log->info("XHR [expba_refund_cases_by_month] 退費案件查到 $count 筆資料");
						echoJSONResponse("退費案件查到 $count 筆資料。", $status, array(
							"data_count" => $count,
							"query_string" => "query_month=".$query_month,
							"raw" => $rows
						));
					}
					break;
				case "sur_rain_cases_by_month":
					if (empty($_POST["query_month"])) {
						$_POST["query_month"] = substr($today, 0, 5);
					}
					$query_month = $_POST['query_month'];
					$log->info("XHR [sur_rain_cases_by_month] 查詢測量因雨延期案件 BY MONTH【${query_month}】請求");
					$rows = $mock ? $cache->get('sur_rain_cases_by_month') : $query->querySurRainCasesByMonth($query_month);
					$cache->set('sur_rain_cases_by_month', $rows);
					if (empty($rows)) {
						$log->info("XHR [sur_rain_cases_by_month] 查無資料");
						echoJSONResponse("XHR [sur_rain_cases_by_month] 查無資料", STATUS_CODE::SUCCESS_WITH_NO_RECORD);
					} else {
						$count = count($rows);
						$status = $count > 1 ? STATUS_CODE::SUCCESS_WITH_MULTIPLE_RECORDS : STATUS_CODE::SUCCESS_NORMAL;
						$log->info("XHR [sur_rain_cases_by_month] 測量因雨延期案件查到 $count 筆資料");
						echoJSONResponse("測量因雨延期案件查到 $count 筆資料。", $status, array(
							"data_count" => $count,
							"query_string" => "query_month=".$query_month,
							"raw" => $rows
						));
					}
					break;
				case "rlnid":
					$param = $_POST["id"];
					$log->info("XHR [rlnid] 取得權利人資訊【${param}】請求");
					$results = $mock ? $cache->get('rlnid') : $query->getRLNIDByID($param);
					$cache->set('rlnid', $results);
					if (empty($results)) {
						echoJSONResponse("查無 ${param} 權利人資訊。");
						$log->info("XHR [rlnid] 查無 ${param} 權利人資訊。");
					} else {
						$json = array(
							"status" => STATUS_CODE::SUCCESS_NORMAL,
							"data_count" => count($results),
							"raw" => $results,
							"query_string" => "id=".$_POST["id"],
							"message" => "XHR [rlnid] 查詢 ${param} 權利人資訊成功。(".count($results).")"
						);
						$log->info($json["message"]);
						echo json_encode($json, 0);
					}
					break;
				case "xlsx_params":
					$params = json_encode($_POST);
					$log->info("XHR [xlsx_params] 設定xlsx查詢參數【${params}】請求");
					function setSessions($params) {
						if (empty($_POST) || session_status() === PHP_SESSION_DISABLED) return false;
						foreach($_POST as $key => $value) {
							if ($value !== NULL) {
								$_SESSION[$key] = $value;
							}
						}
						return true;
					}
					$flag = setSessions($_POST);
					if ($flag) {
						$json = array(
							"status" => STATUS_CODE::SUCCESS_NORMAL,
							"data_count" => count($_POST),
							"raw" => $_POST,
							"message" => "XHR [xlsx_params] 設定 xlsx 查詢參數成功。(".count($_POST).")"
						);
						$log->info($json["message"]);
						echo json_encode($json, 0);
					} else {
						echoJSONResponse("設定 xlsx session 參數失敗。(${params})");
						$log->info("XHR [xlsx_params] 設定 xlsx session 參數失敗。");
						$log->info("XHR [xlsx_params] ${params}");
					}
					break;
				default:
					$log->error("不支援的查詢型態【".$_POST["type"]."】");
					echoJSONResponse("不支援的查詢型態【".$_POST["type"]."】", STATUS_CODE::UNSUPPORT_FAIL);
					break;
			}
		}
}
