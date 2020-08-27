<?php
require_once("config/Config.inc.php");

function GetDBUserMapping($refresh = false) {
    if (SYSTEM_CONFIG["MOCK_MODE"] === true) {
        $content = @file_get_contents(dirname(dirname(__FILE__))."/assets/cache/user_mapping.cache");
        return unserialize($content);
    }

    $tmp_path = sys_get_temp_dir();
    $file = $tmp_path . "\\tyland_user.map";
    $time = @filemtime($file);
    
    if ($refresh === true || $time === false || mktime() - $time > 86400) {
        $db = SYSTEM_CONFIG["ORA_DB_MAIN"];
        
        $conn = oci_connect(SYSTEM_CONFIG["ORA_DB_USER"], SYSTEM_CONFIG["ORA_DB_PASS"], $db, "US7ASCII");
        if (!$conn) {
            $e = oci_error();
            trigger_error(htmlentities($e['message'], ENT_QUOTES), E_USER_ERROR);
        }
        // Prepare the statement
        $stid = oci_parse($conn, "SELECT * FROM SSYSAUTH1");
        if (!$stid) {
            $e = oci_error($conn);
            trigger_error(htmlentities($e['message'], ENT_QUOTES), E_USER_ERROR);
        }
        
        // Perform the logic of the query
        $r = oci_execute($stid);
        if (!$r) {
            $e = oci_error($stid);
            trigger_error(htmlentities($e['message'], ENT_QUOTES), E_USER_ERROR);
        }
        
        $result = array();
        while ($row = oci_fetch_array($stid, OCI_ASSOC + OCI_RETURN_NULLS)) {
            $result[$row["USER_ID"]] = mb_convert_encoding(preg_replace('/\d+/', "", $row["USER_NAME"]), "UTF-8", "BIG5");
        }
        
        if ($stid) {
            oci_free_statement($stid);
        }
        if ($conn) {
            oci_close($conn);
        }
        try {
            /**
             * Also get user info from internal DB
             */
            require_once("MSDB.class.php");
            $tdoc_db = new MSDB(array(
                "MS_DB_UID" => SYSTEM_CONFIG["MS_TDOC_DB_UID"],
                "MS_DB_PWD" => SYSTEM_CONFIG["MS_TDOC_DB_PWD"],
                "MS_DB_DATABASE" => SYSTEM_CONFIG["MS_TDOC_DB_DATABASE"],
                "MS_DB_SVR" => SYSTEM_CONFIG["MS_TDOC_DB_SVR"],
                "MS_DB_CHARSET" => SYSTEM_CONFIG["MS_TDOC_DB_CHARSET"]
            ));
            $users_results = $tdoc_db->fetchAll("SELECT * FROM AP_USER");
            foreach($users_results as $this_user) {
                $user_id =trim($this_user["DocUserID"]);
                if (empty($user_id)) {
                    continue;
                }
                $result[$user_id] = preg_replace('/\d+/', "", trim($this_user["AP_USER_NAME"]));
            }
        } catch (\Throwable $th) {
            //throw $th;
            global $log;
            $log->error("取得內網使用者失敗。【".$th->getMessage()."】");

        } finally {
            // cache
            $content = serialize($result);
            file_put_contents($file, $content);
        }
        
        return $result;
    }
    
    $content = @file_get_contents($file);
    return unserialize($content);
}

function zipLogs() {
    global $log;
    // Enter the name of directory
    $pathdir = dirname(dirname(__FILE__)).DIRECTORY_SEPARATOR."logs";
    $dir = opendir($pathdir); 
    $today = date("Y-m-d");
    while($file = readdir($dir)) {
        // skip today
        if (stristr($file, $today)) {
            $log->info("Skipping today's log for compression.【${file}】");
            continue;
        }
        $full_filename = $pathdir.DIRECTORY_SEPARATOR.$file;
        if(is_file($full_filename)) {
            $pinfo = pathinfo($full_filename);
            if ($pinfo["extension"] != "log") {
                continue;
            }
            $zipcreated = $pinfo["dirname"].DIRECTORY_SEPARATOR.$pinfo["filename"].".zip";
            $zip = new ZipArchive();
            if($zip->open($zipcreated, ZipArchive::CREATE | ZipArchive::OVERWRITE) === TRUE) {
                $log->info("New zip file created.【${zipcreated}】");
                $zip->addFile($full_filename, $file);
                $zip->close();
            }
            $log->info("remove log file.【".$pinfo["basename"]."】");
            @unlink($full_filename);
        }
    }
}

function zipExports() {
    global $log;
    // Enter the name of directory
    $pathdir = dirname(dirname(__FILE__)).DIRECTORY_SEPARATOR."exports";
    $dir = opendir($pathdir); 
    $today = date("Y-m-d");
    while($file = readdir($dir)) {
        if ($file == 'tmp.txt') continue;
        // skip today
        if (stristr($file, $today)) {
            $log->info("Skipping today's log for compression.【${file}】");
            continue;
        }
        $full_filename = $pathdir.DIRECTORY_SEPARATOR.$file;
        if(is_file($full_filename)) {
            $pinfo = pathinfo($full_filename);
            if ($pinfo["extension"] == "zip") {
                continue;
            }
            $zipcreated = $pinfo["dirname"].DIRECTORY_SEPARATOR.$pinfo["filename"].".zip";
            $zip = new ZipArchive();
            if($zip->open($zipcreated, ZipArchive::CREATE | ZipArchive::OVERWRITE) === TRUE) {
                $log->info("New zip file created.【${zipcreated}】");
                $zip->addFile($full_filename, $file);
                $zip->close();
            }
            $log->info("remove zipped file.【".$pinfo["basename"]."】");
            @unlink($full_filename);
        }
    }
}
/**
 * Find the local host IP
 * @return string
 */
function getLocalhostIP() {
    // find this server ip
    $host_ip = gethostname();
    $host_ips = gethostbynamel($host_ip);
    foreach ($host_ips as $this_ip) {
        if (preg_match("/220\.1\.35/", $this_ip)) {
            $host_ip = $this_ip;
            break;
        }
    }
    return $host_ip;
}
/**
 * Get client real IP
 */
function getRealIPAddr() {
    if (!empty($_SERVER['HTTP_CLIENT_IP']))   //check ip from share internet
    {
      $ip=$_SERVER['HTTP_CLIENT_IP'];
    }
    elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR']))   //to check ip is pass from proxy
    {
      $ip=$_SERVER['HTTP_X_FORWARDED_FOR'];
    }
    else
    {
      $ip=$_SERVER['REMOTE_ADDR'];
    }
    return $ip;
}
/**
 * print the json string
 */
function echoJSONResponse($msg, $status = STATUS_CODE::DEFAULT_FAIL, $in_array = array()) {
	$str = json_encode(array_merge(array(
		"status" => $status,
        "message" => $msg
    ), $in_array), 0);
    if ($str === false) {
        global $log;
        $log->warning(__METHOD__.": 轉換JSON字串失敗。");
        $log->warning(__METHOD__.":".print_r($in_array, true));
        echo json_encode(array( "status" => STATUS_CODE::FAIL_JSON_ENCODE, "message" => "無法轉換陣列資料到JSON物件。" ));
    } else {
        echo $str;
    }
}
?>
