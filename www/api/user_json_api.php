<?php
require_once(dirname(dirname(__FILE__))."/include/init.php");
require_once(INC_DIR."/SQLiteUser.class.php");

switch ($_POST["type"]) {
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
			$result = array(
				"status" => STATUS_CODE::FAIL_NOT_FOUND,
				"message" => "查無 ".$client_ip." 資料。",
				"data_count" => 0,
				"info" => false,
				"authority" => getMyAuthority()
			);
			$log->info("XHR [my_info] ".$result['message']);
			$log->info("XHR [authentication] 查無 $client_ip 授權");
			echo json_encode($result, 0);
		} else {
			$_SESSION["myinfo"] = $results[0];
			$result = array(
				"status" => STATUS_CODE::SUCCESS_NORMAL,
				"message" => "查詢 ".$client_ip." 成功。 (".$results[0]["id"].":".$results[0]["name"].")",
				"data_count" => count($results),
				"info" => $results[0],
				"authority" => getMyAuthority()
			);
			$log->info("XHR [my_info] ".$result['message']);
			$log->info("XHR [authentication] 查詢 ".$client_ip." 成功。 (".str_replace("\n", ' ', print_r($result['authority'], true)).")");
			echo json_encode($result, 0);
		}
		break;
    default:
        break;
}
?>
