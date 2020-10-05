<?php
require_once(dirname(dirname(__FILE__))."/include/init.php");
require_once(INC_DIR."/SQLiteUser.class.php");
require_once(INC_DIR."/Cache.class.php");
require_once(INC_DIR."/System.class.php");

$cache = new Cache();
$system = new System();

$mock = $system->isMockMode();

switch ($_POST["type"]) {
	case "user_mapping":
		$operators = getUserNames();
		$count = count($operators);
        $log->info("XHR [user_mapping] 取得使用者對應表($count)。");
        echoJSONResponse("取得 $count 筆使用者資料。", STATUS_CODE::SUCCESS_NORMAL, array(
			"data_count" => $count,
			"data" => $operators
		));
		break;
    case "user_names":
        $log->info("XHR [user_names] 查詢使用者名冊資料請求 (SQLite DB: dimension.db)");
        $sqlite_user = new SQLiteUser();
        $all_users = $sqlite_user->getAllUsers();
        if (empty($all_users)) {
            $msg = "查無使用者名冊資料。( dimension.db exists?? )";
            echoJSONResponse($msg);
            $log->info("XHR [user_names] $msg");
        } else {
            $log->info("XHR [user_names] 查詢使用者名冊資料成功。");
            echoJSONResponse('查詢使用者名冊資料成功', STATUS_CODE::SUCCESS_NORMAL, array(
                "data_count" => count($all_users),
                "raw" => $all_users
            ));
        }
        break;
    case "search_user":
        $log->info("XHR [search_user] 查詢使用者資料【".$_POST["keyword"]."】請求");
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
            $log->info("XHR [search_user] 查無 ".$_POST["keyword"]." 資料。");
        } else {
            $log->info("XHR [search_user] 查詢 ".$_POST["keyword"]." 成功。");
            echoJSONResponse("查詢 ".$_POST["keyword"]." 成功", STATUS_CODE::SUCCESS_NORMAL, array(
                "data_count" => count($results),
                "raw" => $results,
                "query_string" => "keyword=".$_POST["keyword"]
            ));
        }
        break;
    case "user_info":
        $log->info("XHR [user_info] 查詢使用者資料【".$_POST["id"].", ".$_POST["name"].", ".$_POST["ip"]."】請求");
        $sqlite_user = new SQLiteUser();
        $results = $sqlite_user->getUser($_POST["id"]);
        if (empty($results)) {
            $log->info("XHR [user_info] user id (".$_POST["id"].") not found ... try to use name (".$_POST["name"].") for searching.");
            $results = $sqlite_user->getUserByName($_POST["name"]);
        }
        if (empty($results)) {
            $log->info("XHR [user_info] user name (".$_POST["name"].") not found ... try to use ip (".$_POST["ip"].") for searching.");
            $results = $sqlite_user->getUserByIP($_POST["ip"]);
            $len = count($results);
            if ($len > 1) {
                $last = $results[$len - 1];
                $results = array($last);
            }
        }
        if (empty($results)) {
            $log->info("XHR [user_info] 查無 ".$_POST["name"] ?? $_POST["id"] ?? $_POST["ip"]." 資料。");
            echoJSONResponse("查無 ".$_POST["name"]." 資料。");
        } else {
            $log->info("XHR [user_info] 查詢 ".($_POST["name"] ?? $_POST["id"] ?? $_POST["ip"])." 成功。");
            echoJSONResponse("查詢 ".($_POST["name"] ?? $_POST["id"] ?? $_POST["ip"])." 成功", STATUS_CODE::SUCCESS_NORMAL, array(
                "data_count" => count($results),
                "raw" => $results,
                "query_string" => "id=".$_POST["id"]."&name=".$_POST["name"]."&ip=".$_POST["ip"]
            ));
        }
        break;
    case "org_data":
        $user_info = new SQLiteUser();
        $tree_data = $user_info->getTopTreeData();
        $log->info($json["message"]);
        echoJSONResponse("XHR [org_data] 查詢組織資料成功。", STATUS_CODE::SUCCESS_NORMAL, array(
            "data_count" => 1,
            "raw" => $tree_data
        ));
        break;
    case "on_board_users":
        $log->info("XHR [on_board_users] 取得所有在職使用者資料請求");
        $sqlite_user = new SQLiteUser();
        $results = $sqlite_user->getOnboardUsers();
        if (empty($results)) {
            $log->info("XHR [on_board_users] 查無在職使用者資料。");
            echoJSONResponse("查無在職使用者資料。");
        } else {
            $log->info("XHR [on_board_users] 查詢在職使用者資料成功。");
            echoJSONResponse('查詢在職使用者資料成功', STATUS_CODE::SUCCESS_NORMAL, array(
                "data_count" => count($results),
                "raw" => $results
            ));
        }
        break;
    case "upd_ext":
        $log->info("XHR [upd_ext] 更新分機號碼【".$_POST["id"].", ".$_POST["ext"]."】請求");
        $sqlite_user = new SQLiteUser();
        $result = $sqlite_user->updateExt($_POST["id"], $_POST["ext"]);
        if ($result) {
            $msg = "更新分機號碼 ".$_POST["id"].", ".$_POST["ext"]." 成功。";
            $log->info("XHR [upd_ext] ".$msg);
            echoJSONResponse($msg, STATUS_CODE::SUCCESS_NORMAL);
        } else {
            $log->info("XHR [upd_ext] 更新分機號碼 ".$_POST["id"].", ".$_POST["ext"]." 失敗。");
            echoJSONResponse("更新分機號碼 ".$_POST["id"].", ".$_POST["ext"]." 失敗。");
        }
        break;
	case "my_info":
	case "authentication":
		$log->info("XHR [my_info/authentication] 查詢 $client_ip 請求");
        $sqlite_user = new SQLiteUser();
        
        $log->info("XHR [my_info/authentication] 查詢 by ip");

		$results = $sqlite_user->getUserByIP($client_ip);
		$len = count($results);
		if ($len > 1) {
			$last = $results[$len - 1];
			$results = array($last);
		}
		if (empty($results)) {
			$log->info("XHR [my_info/authentication] 查無 $client_ip 資料/授權");
            echoJSONResponse("查無 ".$client_ip." 資料/授權。", STATUS_CODE::FAIL_NOT_FOUND, array(
				"data_count" => 0,
				"info" => false,
				"authority" => getMyAuthority()
			));
		} else {
			$_SESSION["myinfo"] = $results[0];
			$log->info("XHR [my_info/authentication] 查詢 ".$client_ip." 成功。 (".str_replace("\n", ' ', print_r($result['authority'], true)).")");
            echoJSONResponse("查詢 ".$client_ip." 成功。 (".$results[0]["id"].":".$results[0]["name"].")", STATUS_CODE::SUCCESS_NORMAL, array(
				"data_count" => count($results),
				"info" => $results[0],
				"authority" => getMyAuthority()
			));
		}
		break;
    default:
		$log->error("不支援的查詢型態【".$_POST["type"]."】");
		echoErrorJSONString("不支援的查詢型態【".$_POST["type"]."】", STATUS_CODE::UNSUPPORT_FAIL);
		break;
}
?>
