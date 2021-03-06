<?php
require_once('SQLiteUser.class.php');
require_once('SQLiteSYSAUTH1.class.php');
require_once('System.class.php');
require_once("Ping.class.php");
require_once("OraDB.class.php");

// Function to check response time
function pingDomain($domain, $port = 0, $timeout = 1){
    if (System::getInstance()->isMockMode()) {
        return 87;
    }
    $ping = new Ping($domain, $timeout);
    $port = intval($port);
    if ($port < 1 || $port > 65535) {
        $latency = $ping->ping();
    } else {
        $ping->setPort($port);
        $latency = $ping->ping('fsockopen');
    }
    return $latency;
}

function getMyAuthority() {
    global $client_ip;

    // check authority by ip address
    $system = System::getInstance();
    $res = $system->getAuthority($client_ip);
    if ($res['isSuper']) array_walk($res, function(&$value) { $value = true; });

    // check user authority from DB
    if (isset($_SESSION['myinfo'])) {
        if (boolval($_SESSION['myinfo']["authority"] & AUTHORITY::SUPER)) {
            array_walk($res, function(&$value) { $value = true; });
        } else {
            $res["isAdmin"] = $res["isAdmin"] || boolval($_SESSION['myinfo']["authority"] & AUTHORITY::ADMIN);
            $res["isChief"] = $res["isChief"] || boolval($_SESSION['myinfo']["authority"] & AUTHORITY::CHIEF);
            $res["isSuper"] = $res["isSuper"] || boolval($_SESSION['myinfo']["authority"] & AUTHORITY::SUPER);
            $res["isRAE"] = $res["isRAE"] || boolval($_SESSION['myinfo']["authority"] & AUTHORITY::RESEARCH_AND_EVALUATION);
            $res["isGA"] = $res["isGA"] || boolval($_SESSION['myinfo']["authority"] & AUTHORITY::GENERAL_AFFAIRS);
            $res["isAccounting"] = $res["isAccounting"] || boolval($_SESSION['myinfo']["authority"] & AUTHORITY::ACCOUNTING);
            $res["isHR"] = $res["isHR"] || boolval($_SESSION['myinfo']["authority"] & AUTHORITY::HUMAN_RESOURCE);
        }
    }
    
    return $res;
}

function zipLogs() {
    global $log;
    // Enter the name of directory
    $pathdir = dirname(dirname(__FILE__)).DIRECTORY_SEPARATOR."log";
    $dir = opendir($pathdir); 
    $today = date("Y-m-d");
    while($file = readdir($dir)) {
        // skip today
        if (stristr($file, $today)) {
            //$log->info("Skipping today's log for compression.【${file}】");
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
    $pathdir = EXPORT_DIR ?? dirname(dirname(__FILE__)).DIRECTORY_SEPARATOR."export";
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
    $host_ip = '127.0.0.1';
    $host_ips = gethostbynamel(gethostname());
    foreach ($host_ips as $this_ip) {
        if (preg_match("/220\.1\./", $this_ip)) {
            $host_ip = $this_ip;
            break;
        }
    }
    return $host_ip;
}
/**
 * Find the local host IPs
 * @return array of IPs
 */
function getLocalhostIPs() {
    return gethostbynamel(gethostname());
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
    global $log;
	$str = json_encode(array_merge(array(
		"status" => $status,
        "message" => $msg
    ), $in_array), 0);
    
    // $log->info($str);
    
    if ($str === false) {
        $log->warning(__METHOD__.": 轉換JSON字串失敗。");
        $log->warning(__METHOD__.":".print_r($in_array, true));
        echo json_encode(array( "status" => STATUS_CODE::FAIL_JSON_ENCODE, "message" => "無法轉換陣列資料到JSON物件。" ));
    } else {
        echo $str;
        exit;
    }
}
