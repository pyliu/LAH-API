<?php
require_once(dirname(dirname(__FILE__))."/include/init.php");
require_once(INC_DIR."/SQLiteUser.class.php");
require_once(INC_DIR."/Cache.class.php");
require_once(INC_DIR."/System.class.php");
require_once(INC_DIR."/LXHWEB.class.php");

$cache = Cache::getInstance();
$system = System::getInstance();

switch ($_POST["type"]) {
    case "import_l3hweb_users":
        $lxhweb = new LXHWEB(CONNECTION_TYPE::L3HWEB);
        $mappings = $lxhweb->querySYSAUTH1UserNames();
        $len = count($mappings);
        Logger::getInstance()->info("XHR [import_l3hweb_users] 匯入同步異動使用者請求($len)。");
        
        $sqlite_user = new SQLiteUser();

        $succeed = 0;
        $failed = 0;

        foreach($mappings as $id => $name) {
            $def = array(
                'id' => $id,
                'name' => $name,
                'sex' => 1,
                'title' => '無資料',
                'work' => '無資料',
                'ext' => '153',
                'birthday' => '',
                'unit' => '未分配',
                'ip' => '192.168.xx.xx',
                'education' => '無資料',
                'exam' => '無資料',
                'cell' => '',
                'onboard_date' => ''
            );
            $result = $sqlite_user->addUser($def);
            if ($result === true) {
                $msg = "新增 ".($def['id'].', '.$def['name'])." 成功";
                Logger::getInstance()->info("XHR [import_l3hweb_users] ${msg}。");
                $succeed++;
            } else {
                Logger::getInstance()->error("XHR [import_l3hweb_users] 新增 ".($def['id'].', '.$def['name'])." 失敗 (已存在)");
                $failed++;
            }
        }
        
        echoJSONResponse("匯入同步異動使用者執行完成", STATUS_CODE::SUCCESS_NORMAL, array(
            "succeed" => $succeed,
            "failed" => $failed
        ));
        break;
    case "l3hweb_user_mappings":
        Logger::getInstance()->info("XHR [l3hweb_user_mappings] 查詢同步異動使用者對應表請求。");
        $lxhweb = new LXHWEB(CONNECTION_TYPE::L3HWEB);
        $mappings = $lxhweb->querySYSAUTH1UserNames();
        $len = count($mappings);
        Logger::getInstance()->info("XHR [l3hweb_user_mappings] 查詢同步異動使用者對應表成功($len)。");
        echoJSONResponse("查詢同步異動使用者對應表成功($len)", STATUS_CODE::SUCCESS_NORMAL, array(
            "raw" => $mappings
        ));
        break;
    case "search_user":
        Logger::getInstance()->info("XHR [search_user] 查詢使用者資料【".$_POST["keyword"]."】請求");
        $sqlite_user = new SQLiteUser();
        $results = false;
        if (filter_var($_POST["keyword"], FILTER_VALIDATE_IP)) {
            $results = $sqlite_user->getUserByIP($_POST["keyword"]);
        }
        if (empty($results)) {
            $results = $sqlite_user->getUser($_POST["keyword"]);
            if (empty($results)) {
                $results = $sqlite_user->getUserByName($_POST["keyword"]);
            }
        }
        if (empty($results)) {
            echoJSONResponse("查無 ".$_POST["keyword"]." 資料。");
            Logger::getInstance()->info("XHR [search_user] 查無 ".$_POST["keyword"]." 資料。");
        } else {
            Logger::getInstance()->info("XHR [search_user] 查詢 ".$_POST["keyword"]." 成功。");
            echoJSONResponse("查詢 ".$_POST["keyword"]." 成功", STATUS_CODE::SUCCESS_NORMAL, array(
                "data_count" => count($results),
                "raw" => $results,
                "query_string" => "keyword=".$_POST["keyword"]
            ));
        }
        break;
	case "user_mapping":
		$operators = $cache->getUserNames();
		$count = count($operators);
        Logger::getInstance()->info("XHR [user_mapping] 取得使用者對應表($count)。");
        echoJSONResponse("取得 $count 筆使用者資料。", STATUS_CODE::SUCCESS_NORMAL, array(
			"data_count" => $count,
			"data" => $operators
		));
		break;
    case "user_names":
        Logger::getInstance()->info("XHR [user_names] 查詢使用者名冊資料請求 (SQLite DB: dimension.db)");
        $sqlite_user = new SQLiteUser();
        $all_users = $sqlite_user->getAllUsers();
        if (empty($all_users)) {
            $msg = "查無使用者名冊資料。( dimension.db exists?? )";
            echoJSONResponse($msg);
            Logger::getInstance()->info("XHR [user_names] $msg");
        } else {
            Logger::getInstance()->info("XHR [user_names] 查詢使用者名冊資料成功。");
            echoJSONResponse('查詢使用者名冊資料成功', STATUS_CODE::SUCCESS_NORMAL, array(
                "data_count" => count($all_users),
                "raw" => $all_users
            ));
        }
        break;
    case "user_offboard":
        Logger::getInstance()->info("XHR [user_offboard] 設定使用者離職請求");
        $sqlite_user = new SQLiteUser();
        $result = $sqlite_user->offboardUser($_POST["id"]);
        if ($result === true) {
            $msg = $_POST['id'].' 設定離職成功';
            Logger::getInstance()->info("XHR [user_offboard] ${msg}。");
            echoJSONResponse($msg, STATUS_CODE::SUCCESS_NORMAL);
        } else {
            Logger::getInstance()->error("XHR [user_offboard] set user ".$_POST['id']." offboard failed.");
            echoJSONResponse($_POST['id'].' 設定離職失敗');
        }
        break;
    case "user_onboard":
        Logger::getInstance()->info("XHR [user_onboard] 設定使用者復職請求");
        $sqlite_user = new SQLiteUser();
        $result = $sqlite_user->onboardUser($_POST["id"]);
        if ($result === true) {
            $msg = $_POST['id'].' 設定復職成功';
            Logger::getInstance()->info("XHR [user_onboard] ${msg}。");
            echoJSONResponse($msg, STATUS_CODE::SUCCESS_NORMAL);
        } else {
            Logger::getInstance()->error("XHR [user_onboard] set user ".$_POST['id']." onboard failed.");
            echoJSONResponse($_POST['id'].' 設定復職失敗');
        }
        break;
    case "user_info":
        Logger::getInstance()->info("XHR [user_info] 查詢使用者資料【".$_POST["id"].", ".$_POST["name"].", ".$_POST["ip"]."】請求");
        $sqlite_user = new SQLiteUser();
        $results = $sqlite_user->getUser($_POST["id"]);
        if (empty($results)) {
            Logger::getInstance()->info("XHR [user_info] user id (".$_POST["id"].") not found ... try to use name (".$_POST["name"].") for searching.");
            $results = $sqlite_user->getUserByName($_POST["name"]);
        }
        if (empty($results)) {
            Logger::getInstance()->info("XHR [user_info] user name (".$_POST["name"].") not found ... try to use ip (".$_POST["ip"].") for searching.");
            $results = $sqlite_user->getUserByIP($_POST["ip"]);
            $len = count($results);
            if ($len > 1) {
                $last = $results[$len - 1];
                $results = array($last);
            }
        }
        if (empty($results)) {
            Logger::getInstance()->info("XHR [user_info] 查無 ".$_POST["name"] ?? $_POST["id"] ?? $_POST["ip"]." 資料。");
            echoJSONResponse("查無 ".$_POST["name"]." 資料。");
        } else {
            Logger::getInstance()->info("XHR [user_info] 查詢 ".($_POST["name"] ?? $_POST["id"] ?? $_POST["ip"])." 成功。");
            echoJSONResponse("查詢 ".($_POST["name"] ?? $_POST["id"] ?? $_POST["ip"])." 成功", STATUS_CODE::SUCCESS_NORMAL, array(
                "data_count" => count($results),
                "raw" => $results,
                "query_string" => "id=".$_POST["id"]."&name=".$_POST["name"]."&ip=".$_POST["ip"]
            ));
        }
        break;
    case "save_user_info":
        Logger::getInstance()->info("XHR [save_user_info] 儲存使用者資料【".print_r($_POST["data"], true)."】請求");
        $sqlite_user = new SQLiteUser();
        $result = $sqlite_user->saveUser($_POST["data"]);
        if ($result === true) {
            $msg = "儲存 ".($_POST['data']['id'].', '.$_POST['data']['name'])." 成功";
            Logger::getInstance()->info("XHR [save_user_info] ${msg}。");
            echoJSONResponse($msg, STATUS_CODE::SUCCESS_NORMAL);
        } else {
            Logger::getInstance()->error("XHR [save_user_info] save user info failed.");
            echoJSONResponse("儲存 ".($_POST['data']['id'].', '.$_POST['data']['name'])." 失敗");
        }
        break;
    case "add_user":
        Logger::getInstance()->info("XHR [add_user] 新增使用者資料【".print_r($_POST["data"], true)."】請求");
        $sqlite_user = new SQLiteUser();
        $result = $sqlite_user->addUser($_POST["data"]);
        if ($result === true) {
            $msg = "新增 ".($_POST['data']['id'].', '.$_POST['data']['name'])." 成功";
            Logger::getInstance()->info("XHR [add_user] ${msg}。");
            echoJSONResponse($msg, STATUS_CODE::SUCCESS_NORMAL);
        } else {
            Logger::getInstance()->error("XHR [add_user] add user failed.");
            echoJSONResponse("新增 ".($_POST['data']['id'].', '.$_POST['data']['name'])." 失敗");
        }
        break;
    case "org_data":
        $user_info = new SQLiteUser();
        $tree_data = $user_info->getTopTreeData();
        Logger::getInstance()->info($json["message"]);
        echoJSONResponse("XHR [org_data] 查詢組織資料成功。", STATUS_CODE::SUCCESS_NORMAL, array(
            "data_count" => 1,
            "raw" => $tree_data
        ));
        break;
    case "on_board_users":
        Logger::getInstance()->info("XHR [on_board_users] 取得所有正常使用者資料請求");
        $sqlite_user = new SQLiteUser();
        $results = $sqlite_user->getOnboardUsers();
        if (empty($results)) {
            Logger::getInstance()->info("XHR [on_board_users] 查無正常使用者資料。");
            echoJSONResponse("查無正常使用者資料。");
        } else {
            Logger::getInstance()->info("XHR [on_board_users] 查詢正常使用者資料成功。");
            echoJSONResponse('查詢正常使用者資料成功', STATUS_CODE::SUCCESS_NORMAL, array(
                "data_count" => count($results),
                "raw" => $results
            ));
        }
        break;
    case "off_board_users":
        Logger::getInstance()->info("XHR [off_board_users] 取得所有已禁用使用者資料請求");
        $sqlite_user = new SQLiteUser();
        $results = $sqlite_user->getOffboardUsers();
        if (empty($results)) {
            Logger::getInstance()->info("XHR [off_board_users] 查無已禁用使用者資料。");
            echoJSONResponse("查無已禁用使用者資料。");
        } else {
            Logger::getInstance()->info("XHR [off_board_users] 查詢離職使用者資料成功。");
            echoJSONResponse('查詢離職使用者資料成功', STATUS_CODE::SUCCESS_NORMAL, array(
                "data_count" => count($results),
                "raw" => $results
            ));
        }
        break;
    case "all_users":
        Logger::getInstance()->info("XHR [all_users] 取得所有使用者資料請求");
        $sqlite_user = new SQLiteUser();
        $results = $sqlite_user->getAllUsers();
        if (empty($results)) {
            Logger::getInstance()->info("XHR [all_users] 查無使用者資料。");
            echoJSONResponse("查無使用者資料。");
        } else {
            Logger::getInstance()->info("XHR [all_users] 查詢所有使用者資料成功。");
            echoJSONResponse('查詢所有使用者資料成功', STATUS_CODE::SUCCESS_NORMAL, array(
                "data_count" => count($results),
                "raw" => $results
            ));
        }
        break;
    case "department_users":
        Logger::getInstance()->info("XHR [department_users] 取得所有使用者資料請求");
        $dept = $_POST['department'];
        $valid = $_POST['valid'];
        $sqlite_user = new SQLiteUser();
        $results = $sqlite_user->getDepartmentUsers($dept, $valid);
        if (empty($results)) {
            Logger::getInstance()->info("XHR [department_users] 查無使用者資料。");
            echoJSONResponse("查無使用者資料。");
        } else {
            Logger::getInstance()->info("XHR [department_users] 查詢部門使用者資料成功。");
            echoJSONResponse('查詢部門使用者資料成功', STATUS_CODE::SUCCESS_NORMAL, array(
                "data_count" => count($results),
                "raw" => $results
            ));
        }
        break;
    case "authority_list":
        Logger::getInstance()->info("XHR [authority_list] 取得使用者授權列表請求");
        $sqlite_user = new SQLiteUser();
        $results = $sqlite_user->getAuthorityList();
        if (empty($results)) {
            Logger::getInstance()->info("XHR [authority_list] 查無使用者授權列表。");
            echoJSONResponse("查無使用者授權列表。");
        } else {
            Logger::getInstance()->info("XHR [authority_list] 查詢所有使用者授權列表成功。");
            echoJSONResponse('查詢所有使用者授權列表成功', STATUS_CODE::SUCCESS_NORMAL, array(
                "data_count" => count($results),
                "raw" => $results
            ));
        }
        break;
    case "remove_authority":
        Logger::getInstance()->info("XHR [remove_authority] 移除使用者授權請求");
        $result = $system->removeAuthority($_POST['user']);
        if ($result === true) {
            $message = "移除使用者授權 ".$_POST['user']['role_name']." ".$_POST['user']['role_ip']." 成功";
            Logger::getInstance()->info("XHR [remove_authority] ${message}。");
            echoJSONResponse($message, STATUS_CODE::SUCCESS_NORMAL);
        } else {
            $message = '移除使用者授權 '.$_POST['user']['role_name']." ".$_POST['user']['role_ip'].' 失敗';
            Logger::getInstance()->info("XHR [remove_authority] ${message}。");
            echoJSONResponse($message);
        }
        break;
    case "add_authority":
        Logger::getInstance()->info("XHR [add_authority] 增加使用者授權請求");
        $result = $system->addAuthority($_POST['role_id'], $_POST['ip']);
        if ($result === true) {
            $message = "增加使用者授權 ".$_POST['role_id']." ".$_POST['ip']." 成功";
            Logger::getInstance()->info("XHR [add_authority] ${message}。");
            echoJSONResponse($message, STATUS_CODE::SUCCESS_NORMAL);
        } else {
            $message = '增加使用者授權失敗';
            Logger::getInstance()->info("XHR [add_authority] ${message}。");
            echoJSONResponse($message);
        }
        break;
    case "upd_ext":
        Logger::getInstance()->info("XHR [upd_ext] 更新分機號碼【".$_POST["id"].", ".$_POST["ext"]."】請求");
        $sqlite_user = new SQLiteUser();
        $result = $sqlite_user->updateExt($_POST["id"], $_POST["ext"]);
        if ($result) {
            $msg = "更新分機號碼 ".$_POST["id"].", ".$_POST["ext"]." 成功。";
            Logger::getInstance()->info("XHR [upd_ext] ".$msg);
            echoJSONResponse($msg, STATUS_CODE::SUCCESS_NORMAL);
        } else {
            Logger::getInstance()->info("XHR [upd_ext] 更新分機號碼 ".$_POST["id"].", ".$_POST["ext"]." 失敗。");
            echoJSONResponse("更新分機號碼 ".$_POST["id"].", ".$_POST["ext"]." 失敗。");
        }
        break;
    case "upd_dept":
        Logger::getInstance()->info("XHR [upd_dept] 更新部門【".$_POST["id"].", ".$_POST["dept"]."】請求");
        $sqlite_user = new SQLiteUser();
        $result = $sqlite_user->updateDept($_POST["id"], $_POST["dept"]);
        if ($result) {
            $msg = "更新部門 ".$_POST["id"].", ".$_POST["dept"]." 成功。";
            Logger::getInstance()->info("XHR [upd_dept] ".$msg);
            echoJSONResponse($msg, STATUS_CODE::SUCCESS_NORMAL);
        } else {
            Logger::getInstance()->info("XHR [upd_dept] 更新部門 ".$_POST["id"].", ".$_POST["dept"]." 失敗。");
            echoJSONResponse("更新部門 ".$_POST["id"].", ".$_POST["dept"]." 失敗。");
        }
        break;
	case "my_info":
	case "authentication":
        $query_ip = empty($_POST['ip']) ? $client_ip : $_POST['ip'];
		Logger::getInstance()->info("XHR [my_info/authentication] 查詢 $query_ip 請求");
        $sqlite_user = new SQLiteUser();
        
        Logger::getInstance()->info("XHR [my_info/authentication] 查詢 by ip");

		$results = $sqlite_user->getUserByIP($query_ip);
		$len = count($results);
		if ($len > 1) {
            // find the one that is not disabled
            for ($i = 0; $i < $len; $i++) {
                if (!$results[$i]['authority'] & AUTHORITY::DISABLED) {
                    $results = array($results[$i]);
                    break;
                }
            }
            // not found valid user
            $len = count($results);
            if ($len > 1) {
                $last = $results[$len - 1];
                $results = array($last);
            }
		}
		if (empty($results)) {
			Logger::getInstance()->info("XHR [my_info/authentication] 查無 $query_ip 資料/授權");
            echoJSONResponse("查無 ".$query_ip." 資料/授權。", STATUS_CODE::FAIL_NOT_FOUND, array(
				"data_count" => 0,
				"info" => false,
				"authority" => System::getInstance()->calcAuthority(0)
			));
		} else {
			$_SESSION["myinfo"] = $results[0];
			Logger::getInstance()->info("XHR [my_info/authentication] 查詢 ".$query_ip." 成功。");
            echoJSONResponse("查詢 ".$query_ip." 成功。 (".$results[0]["id"].":".$results[0]["name"].")", STATUS_CODE::SUCCESS_NORMAL, array(
				"data_count" => count($results),
				"info" => $results[0],
				"authority" => System::getInstance()->calcAuthority($_SESSION["myinfo"]['authority'])
			));
		}
        break;
	case "ad_authentication":
        Logger::getInstance()->info("XHR [ad_authentication] AD 認證請求");
        $ad = new AdService();
        $result = $ad->authenticate($_POST["id"], $_POST["password"]);
        if ($result === true) {
            Logger::getInstance()->info("XHR [ad_authentication] AD 認證成功。");
            echoJSONResponse("AD 認證成功。", STATUS_CODE::SUCCESS_NORMAL, array(
                "raw" => $result
            ));
        } else {
            Logger::getInstance()->info("XHR [ad_authentication] AD 認證失敗。");
            echoJSONResponse("AD 認證失敗。");
        }
        break;
    case "ad_users_valid":
        Logger::getInstance()->info("XHR [ad_users_valid] 取得所有AD有效使用者資料請求");
        $ad = new AdService();
        $users = $ad->getValidUsers();
        if (empty($users)) {
            Logger::getInstance()->info("XHR [ad_users_valid] 查無AD有效使用者資料。");
            echoJSONResponse("查無AD有效使用者資料。");
        } else {
            Logger::getInstance()->info("XHR [ad_users_valid] 查詢AD有效使用者資料成功。");
            echoJSONResponse('查詢AD有效使用者資料成功', STATUS_CODE::SUCCESS_NORMAL, array(
                "data_count" => count($users),
                "raw" => $users
            ));
        }
        break;
    case "ad_users_locked":
        Logger::getInstance()->info("XHR [ad_users_locked] 查詢鎖定使用者資料請求");
        $ad = new AdService();
        $users = $ad->getLockedUsers();
        if (empty($users)) {
            Logger::getInstance()->info("XHR [ad_users_locked] 查無鎖定使用者資料。");
            echoJSONResponse("查無鎖定使用者資料。");
        } else {
            Logger::getInstance()->info("XHR [ad_users_locked] 查詢鎖定使用者資料成功。");
            echoJSONResponse('查詢鎖定使用者資料成功', STATUS_CODE::SUCCESS_NORMAL, array(
                "data_count" => count($users),
                "raw" => $users
            ));
        }
        break;
    case "ad_pw_reset":
    case "ad_user_unlock":
        $type = $_POST["type"];
        Logger::getInstance()->info("XHR [$type] 解鎖使用者資料請求");
        $ad = new AdService();
        $result = $ad->unlockUser($_POST["id"], $_POST["password"]);
        if ($result === true) {
            $msg = empty($_POST["password"]) ? "解鎖使用者資料成功" : "解鎖並重設使用者密碼成功";
            Logger::getInstance()->info("XHR [$type] $msg 。");
            echoJSONResponse($msg, STATUS_CODE::SUCCESS_NORMAL, array(
                "raw" => $result
            ));
        } else {
            $msg = empty($_POST["password"]) ? "解鎖使用者資料失敗。" : "解鎖並重設使用者密碼失敗";
            Logger::getInstance()->error("XHR [$type] $msg");
            echoJSONResponse($msg);
        }
        break;
    default:
		Logger::getInstance()->warning("不支援的查詢型態【".$_POST["type"]."】");
		echoJSONResponse("不支援的查詢型態【".$_POST["type"]."】", STATUS_CODE::UNSUPPORT_FAIL);
		break;
}
