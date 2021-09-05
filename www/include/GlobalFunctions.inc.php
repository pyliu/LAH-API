<?php
require_once('SQLiteUser.class.php');
require_once('System.class.php');
require_once("Ping.class.php");
require_once("OraDB.class.php");

// e.g. startsWith("abcde", "a")
function startsWith($string, $startString)
{
    $len = strlen($startString);
    return (substr($string, 0, $len) === $startString);
}

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

function zipLogs() {
    // Enter the name of directory
    $pathdir = dirname(dirname(__FILE__)).DIRECTORY_SEPARATOR."log";
    $dir = opendir($pathdir); 
    $today = date("Y-m-d");
    while($file = readdir($dir)) {
        // skip today
        if (stristr($file, $today)) {
            //Logger::getInstance()->info("Skipping today's log for compression.【${file}】");
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
                Logger::getInstance()->info("New zip file created.【${zipcreated}】");
                $zip->addFile($full_filename, $file);
                $zip->close();
            }
            Logger::getInstance()->info("remove log file.【".$pinfo["basename"]."】");
            @unlink($full_filename);
        }
    }
}

function zipExports() {
    // Enter the name of directory
    $pathdir = EXPORT_DIR ?? dirname(dirname(__FILE__)).DIRECTORY_SEPARATOR."export";
    $dir = opendir($pathdir); 
    $today = date("Y-m-d");
    while($file = readdir($dir)) {
        if ($file == 'tmp.txt') continue;
        // skip today
        if (stristr($file, $today)) {
            Logger::getInstance()->info("Skipping today's log for compression.【${file}】");
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
                Logger::getInstance()->info("New zip file created.【${zipcreated}】");
                $zip->addFile($full_filename, $file);
                $zip->close();
            }
            Logger::getInstance()->info("remove zipped file.【".$pinfo["basename"]."】");
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
	$str = json_encode(array_merge(array(
		"status" => $status,
        "data_count" => 0,
        "message" => $msg
    ), $in_array), 0);
    
    // Logger::getInstance()->info($str);
    
    if ($str === false) {
        Logger::getInstance()->warning(__METHOD__.": 轉換JSON字串失敗。");
        Logger::getInstance()->warning(__METHOD__.":".print_r($in_array, true));
        echo json_encode(array( "status" => STATUS_CODE::FAIL_JSON_ENCODE, "message" => "無法轉換陣列資料到JSON物件。" ));
    } else {
        echo $str;
        exit;
    }
}
