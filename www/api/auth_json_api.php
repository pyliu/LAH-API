<?php
require_once(dirname(dirname(__FILE__))."/include/init.php");
require_once(INC_DIR."/System.class.php");
require_once(INC_DIR."/SQLiteUser.class.php");

$system = System::getInstance();

switch ($_POST["type"]) {
	case "login":
		// find current connected user
		$sqlite_user = new SQLiteUser();
		$queried_user = $sqlite_user->getUserByIP($_POST['req_ip']);
		$found_count = count($queried_user);
		if (empty($found_count)) {
			Logger::getInstance()->info("XHR [login] 找不到 ".$_POST['req_ip']." 使用者。");
		} else {
			if ($found_count > 1) {
				$queried_user = array($queried_user[$found_count - 1]);
			}
			$_SESSION["myinfo"] = $queried_user[0];
			Logger::getInstance()->info("XHR [login] 找到 ".$_POST['req_ip']." 使用者 ".$_SESSION["myinfo"]['id']." ".$_SESSION["myinfo"]['name']."。");
		}

		// $ips = getLocalhostIPs();
		// $message = "PHP 取得 API 伺服器 IP 位址 => ".preg_replace('/[\n\s]+/i', ' ', print_r($ips, true));
		// Logger::getInstance()->info("XHR [login] $message");
		// Logger::getInstance()->info("XHR [login] APACHE API 伺服器端點資訊： ".$_SERVER['SERVER_ADDR'].":".$_SERVER['SERVER_PORT']);

        echoJSONResponse('取得 '.$_POST['req_ip'].' 登入資訊', STATUS_CODE::SUCCESS_NORMAL, array(
			"server" => $_SERVER,
			"ips" => getLocalhostIPs(),
			"user" => $_SESSION["myinfo"],
			"configs" => array(
				'l3hweb_db_ip' => $system->getL3hwebDBIp(),
				'webap_ip' => $system->getWebAPIp(),
				'webap_postfix' => $system->getWebAPPostfix(),
				'mock' => $system->isMockMode(),
				'mssql' => $system->isMSSQLEnable(),
				'avatar' => $system->isAvatarEnable(),
				'officehours' => $system->isOfficeHoursEnable(),
				'authority' => $system->getAuthority($_POST['req_ip']),
				'master_password' => $system->get('MASTER_PASSWORD'),
				'site' => strtoupper($system->get('SITE')),
				'monitor' => array(
					'host' => $system->get('MONITOR_MAIL_HOST'),
					'account' => $system->get('MONITOR_MAIL_ACCOUNT'),
					'password' => $system->get('MONITOR_MAIL_PASSWORD'),
					'ssl' => $system->get('MONITOR_MAIL_SSL') === 'true'
				)
			)
        ));
		break;
	default:
		Logger::getInstance()->error("不支援的查詢型態【".$_POST["type"]."】");
		echoJSONResponse("不支援的查詢型態【".$_POST["type"]."】", STATUS_CODE::UNSUPPORT_FAIL);
		break;
}
