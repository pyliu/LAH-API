<?php
require_once('SQLiteUser.class.php');
require_once('System.class.php');
require_once("Ping.class.php");
require_once("OraDB.class.php");
require_once("SQLiteUser.class.php");
require_once("Logger.class.php");

function isValidTaiwanDate($tw_date) {
    // 檢查是否為 7 碼數字
    if (!preg_match('/^\d{7}$/', $tw_date)) {
        return false;
    }
    // 解析年、月、日
    $year = intval(substr($tw_date, 0, 3)) + 1911; // 轉換為西元年
    $month = intval(substr($tw_date, 3, 2));
    $day = intval(substr($tw_date, 5, 2));
    // 檢查月份是否有效
    if ($month < 1 || $month > 12) {
        return false;
    }
    // 檢查日期是否有效
    if ($day < 1 || $day > cal_days_in_month(CAL_GREGORIAN, $month, $year)) {
        return false;
    }
    return true;
}

function isValidTime($timeString) {
    // 檢查是否為 6 碼數字
    if (!preg_match('/^\d{6}$/', $timeString)) {
        return false;
    }
    // 提取時、分、秒
    $hour = substr($timeString, 0, 2);
    $minute = substr($timeString, 2, 2);
    $second = substr($timeString, 4, 2);
    // 檢查時分秒是否為有效值
    if ($hour < 0 || $hour > 23 || $minute < 0 || $minute > 59 || $second < 0 || $second > 59) {
        return false;
    }
    return true;
}

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
// reference from https://stackoverflow.com/questions/3656713/how-to-get-current-time-in-milliseconds-in-php
function milliseconds() {
    $mt = explode(' ', microtime());
    return intval( $mt[1] * 1E3 ) + intval( round( $mt[0] * 1E3 ) );
}
// reference from bard.google.com
function timestampToDate(int $time, string $roc = 'AD', string $format = 'Y-m-d H:i:s'): string {
    if (strlen($time) === 10) {
        if ($roc !== 'AD') {
            if ($format === 'Y-m-d H:i:s') {
                $roc_year = str_pad(date("Y", $time) - 1911, 3, '0', STR_PAD_LEFT);
                return $roc_year.date('-m-d H:i:s', $time);
            }
        }
        return date($format, $time);
    }
    // Convert milliseconds to seconds
    $seconds = $time / 1000;
    if ($roc !== 'AD') {
        $roc_year = str_pad(date("Y", $seconds) - 1911, 3, '0', STR_PAD_LEFT);
        return $roc_year.date('-m-d H:i:s', $seconds);
    }
    return date($format, $seconds);
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
function httpHeader($url, $timeout = false) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_HEADER, 1);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    if ($timeout !== false) {
        curl_setopt($ch, CURLOPT_TIMEOUT, intval($timeout) ?? 5);
    }
    $header = curl_exec($ch);
    curl_close($ch);

    $headers = explode("\r\n", $header);
    return $headers;
}

function sameArrayCompare($array1, $array2) {
    if ($array1 === $array2) {
      return true;
    }
    if (!is_array($array1) || !is_array($array2)) {
      return false;
    }
    if (count($array1) !== count($array2)) {
      return false;
    }
    foreach ($array1 as $key => $value) {
      if (!isset($array2[$key])) {
        return false;
      }
    //   if (!deepArrayCompare($array1[$key], $array2[$key])) {
    //     return false;
    //   }
    }
    return true;
}

function getCrossCountyCodeMap() {
    return array(
        'A1' => array('AA', '古亭'),
        'A2' => array('AB', '建成'),
        'A3' => array('AC', '中山'),
        'A4' => array('AD', '松山'),
        'A5' => array('AE', '士林'),
        'A6' => array('AF', '大安'),
        'B1' => array('BA', '中山'),
        'B2' => array('BB', '中正'),
        'B3' => array('BC', '中興'),
        'B4' => array('BD', '豐原'),
        'B5' => array('BE', '大甲'),
        'B6' => array('BF', '清水'),
        'B7' => array('BG', '東勢'),
        'B8' => array('BH', '雅潭'),
        'B9' => array('BI', '大里'),
        'BZ' => array('BJ', '太平'),
        'BY' => array('BK', '龍井'),
        'C3' => array('CD', '基隆'),
        'D1' => array('DA', '台南'),
        'D2' => array('DB', '安南'),
        'D3' => array('DC', '東南'),
        'D4' => array('DD', '鹽水'),
        'D5' => array('DE', '白河'),
        'D6' => array('DF', '麻豆'),
        'D7' => array('DG', '佳里'),
        'D8' => array('DH', '新化'),
        'D9' => array('DI', '歸仁'),
        'DZ' => array('DJ', '玉井'),
        'DY' => array('DK', '永康'),
        'E1' => array('EA', '鹽埕'),
        'E2' => array('EB', '新興'),
        'E3' => array('EC', '前鎮'),
        'E4' => array('ED', '三民'),
        'E5' => array('EE', '楠梓'),
        'E6' => array('EF', '岡山'),
        'E7' => array('EG', '鳳山'),
        'E8' => array('EH', '旗山'),
        'E9' => array('EI', '仁武'),
        'EZ' => array('EJ', '路竹'),
        'EY' => array('EK', '美濃'),
        'EX' => array('EL', '大寮'),
        'F1' => array('FA', '板橋'),
        'F2' => array('FB', '新莊'),
        'F3' => array('FC', '新店'),
        'F4' => array('FD', '汐止'),
        'F5' => array('FE', '淡水'),
        'F6' => array('FF', '瑞芳'),
        'F7' => array('FG', '三重'),
        'F8' => array('FH', '中和'),
        'F9' => array('FI', '樹林'),
        'G1' => array('GA', '羅東'),
        'G2' => array('GB', '宜蘭'),
        'J1' => array('JB', '竹北'),
        'J2' => array('JC', '竹東'),
        'J3' => array('JD', '新湖'),
        'K1' => array('KA', '大湖'),
        'K2' => array('KB', '苗栗'),
        'K3' => array('KC', '通霄'),
        'K4' => array('KD', '竹南'),
        'K5' => array('KE', '銅鑼'),
        'K6' => array('KF', '頭份'),
        'M1' => array('MA', '南投'),
        'M2' => array('MB', '草屯'),
        'M3' => array('MC', '埔里'),
        'M4' => array('MD', '竹山'),
        'M5' => array('ME', '水里'),
        'N1' => array('NA', '彰化'),
        'N2' => array('NB', '和美'),
        'N3' => array('NC', '鹿港'),
        'N4' => array('ND', '員林'),
        'N5' => array('NE', '田中'),
        'N6' => array('NF', '北斗'),
        'N7' => array('NG', '二林'),
        'N8' => array('NH', '溪湖'),
        'P1' => array('PA', '斗六'),
        'P2' => array('PB', '斗南'),
        'P3' => array('PC', '西螺'),
        'P4' => array('PD', '虎尾'),
        'P5' => array('PE', '北港'),
        'P6' => array('PF', '台西'),
        'Q1' => array('QB', '朴子'),
        'Q2' => array('QC', '大林'),
        'Q3' => array('QD', '水上'),
        'Q4' => array('QE', '竹崎'),
        'T1' => array('TA', '屏東'),
        'T2' => array('TB', '里港'),
        'T3' => array('TC', '潮州'),
        'T4' => array('TD', '東港'),
        'T5' => array('TE', '恆春'),
        'T6' => array('TF', '枋寮'),
        'U1' => array('UA', '花蓮'),
        'U2' => array('UB', '鳳林'),
        'U3' => array('UC', '玉里'),
        'V1' => array('VA', '台東'),
        'V2' => array('VB', '成功'),
        'V3' => array('VC', '關山'),
        'V4' => array('VD', '太麻里'),
        'I1' => array('IA', '嘉義市'),
        'O1' => array('OA', '新竹市'),
        'W1' => array('WA', '金門'),
        'X1' => array('XA', '澎湖'),
        'Z1' => array('ZA', '連江')
    );
}

function getCPUInfo() {
    $cmd = 'wmic cpu get /format:list';
    $output = [];
    exec($cmd, $output);

    $cpuInfo = [];
    foreach ($output as $line) {
        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line);
            $cpuInfo[trim($key)] = trim($value);
        }
    }
    return $cpuInfo;
}

function getDividedCaseId ($text) {// 加上 ^ 和 $，表示必須「從頭到尾完全符合」這個格式
    // 格式：3位數字 + 4位英數 + 6位數字
    $pattern = '/^(\d{3})([A-Z0-9]{4})(\d{6})$/';
    // 先檢查是否符合格式
    if (preg_match($pattern, $text)) {
        // 符合：回傳格式化後的字串 (114-HDA1-020670)
        return preg_replace($pattern, '$1-$2-$3', $text);
    } else {
        // 不符合：回傳 false 或 null，代表這是無效的 ID
        return false;
    }
}

function getMDCaseLink($text) {
    $host_ip = getLocalhostIP();
    $case_query_base_url = "http://".$host_ip.":8080/reg/case";
    $clean_case_id = getDividedCaseId($text);
    if ($clean_case_id) {
        // 如果 $display_text 存在 (非 false)，代表是合格的 Case ID -> 產生連結
        return "[$clean_case_id]($case_query_base_url/$clean_case_id)";
    } else {
        // 如果是不合格的 ID (例如空值、亂碼、一般文字) -> 直接回傳原始文字，不加連結
        return $text;
    }
}
