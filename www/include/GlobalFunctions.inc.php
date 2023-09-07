<?php
require_once('SQLiteUser.class.php');
require_once('System.class.php');
require_once("Ping.class.php");
require_once("OraDB.class.php");
require_once("SQLiteUser.class.php");
require_once("Logger.class.php");

function convertMBNumberString($input) {
    if (empty($input)) {
        return '';
    }
    $len = mb_strlen($input);
    $number = '';
    for ($i = 0; $i < $len; $i++) {
        $char = mb_substr($input, $i, 1);
        switch ($char) {
            case '０':
                $number .= '0';
                break;
            case '１':
                $number .= '1';
                break;
            case '２':
                $number .= '2';
                break;
            case '３':
                $number .= '3';
                break;
            case '４':
                $number .= '4';
                break;
            case '５':
                $number .= '5';
                break;
            case '６':
                $number .= '6';
                break;
            case '７':
                $number .= '7';
                break;
            case '８':
                $number .= '8';
                break;
            case '９':
                $number .= '9';
                break;
            default:
                break;
        }
    }
    return $number;
}

function resizeImage($filename, $max_width = 1920, $max_height = 1080, $type = 'jpg') {
    list($orig_width, $orig_height) = getimagesize($filename);
    $width = $orig_width;
    $height = $orig_height;
    // uploaded image height is over max width
    if ($height > $max_height) {
        // scale width by height ratio
        $width = ($max_height / $height) * $width;
        // specified height as max height
        $height = $max_height;
    }
    // the calculated width still higher that max width
    if ($width > $max_width) {
        // scale height also
        $height = ($max_width / $width) * $height;
        // set width to max width
        $width = $max_width;
    }
    // create plain canvas
    $image_p = imagecreatetruecolor($width, $height);
    // load image
    switch ($type) {
        case 'png':
        case 'PNG':
            $image = @imagecreatefrompng($filename);
            break;
        case 'gif':
        case 'GIF':
            $image = @imagecreatefromgif($filename);
            break;
        case 'string':
            $image = @imagecreatefromstring($filename);
            break;
        default:
            $image = @imagecreatefromjpeg($filename);
    }
    // copy & re-sampling
    imagecopyresampled($image_p, $image, 0, 0, 0, 0, $width, $height, $orig_width, $orig_height);
    // clean the loaded image
    imagedestroy($image);
    return $image_p;
}

function base64EncodedImage($imageFile) {
    $imageInfo = getimagesize($imageFile);
    $imageData = file_get_contents($imageFile);
    return array(
        'uri' => 'data:' . $imageInfo['mime'] . ';base64,',
        'encoded' => base64_encode($imageData)
    );
}

// e.g. startsWith("abcde", "a")
function startsWith($string, $startString) {
    $len = strlen($startString);
    return (substr($string, 0, $len) === $startString);
}

function endsWith($haystack, $needle) {
    $length = strlen( $needle );
    if( !$length ) {
        return true;
    }
    return substr( $haystack, -$length ) === $needle;
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
 * Handle Query User if SESSION data not found
 */
function prepareSessionMyInfo() {
    global $client_ip;
    if (empty($client_ip)) {
        $client_ip = getRealIPAddr();
    }
    session_start();
    if (empty($_SESSION['myinfo'])) {
        $sqlite_user = new SQLiteUser();
        $queried_user = $sqlite_user->getUserByIP($client_ip);
        $found_count = count($queried_user);
        if (empty($found_count)) {
            Logger::getInstance()->info(__FILE__."：找不到 ".$client_ip." 使用者。");
        } else {
            if ($found_count > 1) {
                $queried_user = array($queried_user[$found_count - 1]);
            }
            $_SESSION["myinfo"] = $queried_user[0];
            Logger::getInstance()->info(__FILE__."：找到 ".$client_ip." 使用者 ".$_SESSION["myinfo"]['id']." ".$_SESSION["myinfo"]['name']."。");
        }
    }
}

function utf8ize($mixed) {
    if (is_array($mixed)) {
        foreach ($mixed as $key => $value) {
            $mixed[$key] = utf8ize($value);
        }
    } elseif (is_string($mixed)) {
        return mb_convert_encoding($mixed, "UTF-8", "UTF-8");
    }
    return $mixed;
}
/**
 * print the json string
 */
function echoJSONResponse($msg, $status = STATUS_CODE::DEFAULT_FAIL, $in_array = array()) {
    // auto count the number of popular using data key
    if (is_array($in_array['raw'])) {
        $in_array['data_count'] = count($in_array['raw']);
    } else if (is_array($in_array['data'])) {
        $in_array['data_count'] = count($in_array['data']);
    } else if (is_array($in_array['items'])) {
        $in_array['data_count'] = count($in_array['items']);
    }
    $value = array_merge(array(
		"status" => $status,
        "data_count" => 0,
        "message" => $msg
    ), $in_array);
	$str = json_encode($value, 0);
    
    if ($str === false && $value && json_last_error() == JSON_ERROR_UTF8) {
        $str = json_encode(utf8ize($value), 0);
    }
    
    if ($str === false) {
        Logger::getInstance()->warning(__METHOD__.": 轉換JSON字串失敗。");
        Logger::getInstance()->warning(__METHOD__.":".print_r($in_array, true));
        echo json_encode(array( "status" => STATUS_CODE::FAIL_JSON_ENCODE, "message" => "無法轉換陣列資料到JSON物件。".json_last_error() ));
    } else {
        echo $str;
        exit;
    }
}
/**
 * get http headers
 */
function httpHeader($url) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_HEADER, 1);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

    $header = curl_exec($ch);
    curl_close($ch);

    $headers = explode("\r\n", $header);
    return $headers;
}
