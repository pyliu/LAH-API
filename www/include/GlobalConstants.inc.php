<?php
require_once('Logger.class.php');

abstract class STATUS_CODE {
    const SUCCESS_WITH_NO_RECORD = 3;
    const SUCCESS_WITH_MULTIPLE_RECORDS = 2;
	const SUCCESS_NORMAL = 1;
	const DEFAULT_FAIL = 0;
	const UNSUPPORT_FAIL = -1;
    const FAIL_WITH_LOCAL_NO_RECORD = -2;
    const FAIL_NOT_VALID_SERVER = -3;
    const FAIL_WITH_REMOTE_NO_RECORD = -4;
    const FAIL_NO_AUTHORITY = -5;
    const FAIL_JSON_ENCODE = -6;
    const FAIL_NOT_FOUND = -7;
    const FAIL_LOAD_ERROR = -8;
    const FAIL_TIMEOUT = -9;
    const FAIL_REMOTE_UNREACHABLE = -10;
    const FAIL_DB_ERROR = -11;
}

define('ROOT_DIR', dirname(dirname(__FILE__)));
define('EXPORT_DIR', ROOT_DIR.DIRECTORY_SEPARATOR.'export');
define('IMPORT_DIR', ROOT_DIR.DIRECTORY_SEPARATOR.'import');
define('LOG_DIR', ROOT_DIR.DIRECTORY_SEPARATOR.'log');
define('INC_DIR', ROOT_DIR.DIRECTORY_SEPARATOR."include");
define('USER_IMG_DIR', ROOT_DIR.DIRECTORY_SEPARATOR."assets".DIRECTORY_SEPARATOR."img".DIRECTORY_SEPARATOR."users");
define('XLSX_TPL_DIR', ROOT_DIR.DIRECTORY_SEPARATOR."assets".DIRECTORY_SEPARATOR."xlsx");
define('DB_DIR', ROOT_DIR.DIRECTORY_SEPARATOR."assets".DIRECTORY_SEPARATOR."db");
define('DEF_SQLITE_DB', DB_DIR.DIRECTORY_SEPARATOR."LAH.db");

define('CASE_STATUS',[
    "A" => "初審",
    "B" => "複審",
    "H" => "公告",
    "I" => "補正",
    "R" => "登錄",
    "C" => "校對",
    "U" => "異動完成",
    "F" => "結案",
    "X" => "補正初核",
    "Y" => "駁回初核",
    "J" => "撤回初核",
    "K" => "撤回",
    "Z" => "歸檔",
    "N" => "駁回",
    "L" => "公告初核",
    "E" => "請示",
    "D" => "展期"
]);

define('REG_NOTE', [
    "B" => "登錄開始",
    "R" => "登錄完成",
    "C" => "校對結束",
    "E" => "校對有誤",
    "S" => "異動開始",
    "F" => "異動完成",
    "G" => "異動有誤",
    "P" => "競合暫停"
]);

define('VAL_NOTE', [
    "0" => "登記移案",
    "B" => "登錄中",
    "R" => "登錄完成",
    "D" => "校對完成",
    "C" => "校對中",
    "E" => "校對有誤",
    "S" => "異動開始",
    "F" => "異動完成",
    "G" => "異動有誤"
]);

define('OFFICE', [
    "HA" => "桃園",
    "HB" => "中壢",
    "HC" => "大溪",
    "HD" => "楊梅",
    "HE" => "蘆竹",
    "HF" => "八德",
    "HG" => "平鎮",
    "HH" => "龜山",
    'AA' => '古亭',
    'AB' => '建成',
    'AC' => '中山',
    'AD' => '松山',
    'AE' => '士林',
    'AF' => '大安',
    'BA' => '中山',
    'BB' => '中正',
    'BC' => '中興',
    'BD' => '豐原',
    'BE' => '大甲',
    'BF' => '清水',
    'BG' => '東勢',
    'BH' => '雅潭',
    'BI' => '大里',
    'BJ' => '太平',
    'BK' => '龍井',
    'CD' => '基隆',
    'DA' => '台南',
    'DB' => '安南',
    'DC' => '東南',
    'DD' => '鹽水',
    'DE' => '白河',
    'DF' => '麻豆',
    'DG' => '佳里',
    'DH' => '新化',
    'DI' => '歸仁',
    'DJ' => '玉井',
    'DK' => '永康',
    'EA' => '鹽埕',
    'EB' => '新興',
    'EC' => '前鎮',
    'ED' => '三民',
    'EE' => '楠梓',
    'EF' => '岡山',
    'EG' => '鳳山',
    'EH' => '旗山',
    'EI' => '仁武',
    'EJ' => '路竹',
    'EK' => '美濃',
    'EL' => '大寮',
    'FA' => '板橋',
    'FB' => '新莊',
    'FC' => '新店',
    'FD' => '汐止',
    'FE' => '淡水',
    'FF' => '瑞芳',
    'FG' => '三重',
    'FH' => '中和',
    'FI' => '樹林',
    'GA' => '羅東',
    'GB' => '宜蘭',
    'IA' => '嘉義市',
    'JB' => '竹北',
    'JC' => '竹東',
    'JD' => '新湖',
    'KA' => '大湖',
    'KB' => '苗栗',
    'KC' => '通霄',
    'KD' => '竹南',
    'KE' => '銅鑼',
    'KF' => '頭份',
    'MA' => '南投',
    'MB' => '草屯',
    'MC' => '埔里',
    'MD' => '竹山',
    'ME' => '水里',
    'NA' => '彰化',
    'NB' => '和美',
    'NC' => '鹿港',
    'ND' => '員林',
    'NE' => '田中',
    'NF' => '北斗',
    'NG' => '二林',
    'NH' => '溪湖',
    'OA' => '新竹市',
    'PA' => '斗六',
    'PB' => '斗南',
    'PC' => '西螺',
    'PD' => '虎尾',
    'PE' => '北港',
    'PF' => '台西',
    'QB' => '朴子',
    'QC' => '大林',
    'QD' => '水上',
    'QE' => '竹崎',
    'TA' => '屏東',
    'TB' => '里港',
    'TC' => '潮州',
    'TD' => '東港',
    'TE' => '恆春',
    'TF' => '枋寮',
    'UA' => '花蓮',
    'UB' => '鳳林',
    'UC' => '玉里',
    'VA' => '台東',
    'VB' => '成功',
    'VC' => '關山',
    'VD' => '太麻里',
    'WA' => '金門',
    'XA' => '澎湖',
    'ZA' => '連江'
]);

require_once('SQLiteCaseCode.class.php');
$scc = new SQLiteCaseCode();

// 準備測量收件字
$codes = $scc->getSurCode();
if ($codes) {
    $altered = [];
    foreach ($codes as $row) {
        $altered[$row['KCDE_2']] = $row['KCNT'];
    }
    define('SUR_WORD', $altered);
} else {
    Logger::getInstance()->warning('取得 SUR_WORD 資料失敗，請確認 CaseCode.db 中資料使否正確匯入。');
}

// 準備地價收件字
$codes = $scc->getValCode();
if ($codes) {
    $altered = [];
    foreach ($codes as $row) {
        $altered[$row['KCDE_2']] = $row['KCNT'];
    }
    define('PRC_WORD', $altered);
} else {
    Logger::getInstance()->warning('取得 PRC_WORD 資料失敗，請確認 CaseCode.db 中資料使否正確匯入。');
}

// 準備本所登記收件字
$codes = $scc->getRegHostCode();
$altered = [];
if ($codes) {
    foreach ($codes as $row) {
        $altered[$row['KCDE_2']] = $row['KCNT'];
    }
} else {
    Logger::getInstance()->warning('取得 本所收件字 資料失敗，請確認 CaseCode.db 中資料使否正確匯入。');
}

require_once('System.class.php');
$site_code = System::getInstance()->getSiteCode() ?? 'HA';  // HA
$site_code_tail = $site_code[1];    // A
$site_short_name = '？';
switch ($site_code) {
    case 'HA': $site_short_name = '桃'; break;
    case 'HB': $site_short_name = '壢'; break;
    case 'HC': $site_short_name = '溪'; break;
    case 'HD': $site_short_name = '楊'; break;
    case 'HE': $site_short_name = '蘆'; break;
    case 'HF': $site_short_name = '德'; break;
    case 'HG': $site_short_name = '平'; break;
    case 'HH': $site_short_name = '山'; break;
}
$site_name = OFFICE[$site_code];    // 桃園
$site_idx = ord($site_code_tail) - ord('A') + 1;  // 1

define('REG_CODE', [
    "本所" => $altered,
    "本所收件" => [
        "HA${site_code_tail}1" => "${site_short_name}桃登跨",
        "HB${site_code_tail}1" => "${site_short_name}壢登跨",
        "HF${site_code_tail}1" => "${site_short_name}德登跨",
        "HC${site_code_tail}1" => "${site_short_name}溪登跨",
        "HG${site_code_tail}1" => "${site_short_name}平登跨",
        "HD${site_code_tail}1" => "${site_short_name}楊登跨",
	    "HE${site_code_tail}1" => "${site_short_name}蘆登跨",
	    "HH${site_code_tail}1" => "${site_short_name}山登跨"
    ],
    "他所收件" => [
        "${site_code}A1" => "桃${site_short_name}登跨",
        "${site_code}B1" => "壢${site_short_name}登跨",
        "${site_code}G1" => "平${site_short_name}登跨",
        "${site_code}E1" => "蘆${site_short_name}登跨",
        "${site_code}H1" => "山${site_short_name}登跨",
        "${site_code}F1" => "德${site_short_name}登跨",
        "${site_code}D1" => "楊${site_short_name}登跨",
        "${site_code}C1" => "溪${site_short_name}登跨"
    ],
    "跨縣市本所收件" => [
        '台北市' => [
            'H'.$site_idx.'AA' => '跨縣市（'.$site_name.'古亭）',
            'H'.$site_idx.'AB' => '跨縣市（'.$site_name.'建成）',
            'H'.$site_idx.'AC' => '跨縣市（'.$site_name.'中山）',
            'H'.$site_idx.'AD' => '跨縣市（'.$site_name.'松山）',
            'H'.$site_idx.'AE' => '跨縣市（'.$site_name.'士林）',
            'H'.$site_idx.'AF' => '跨縣市（'.$site_name.'大安）'
        ],
        '台中市' => [
            'H'.$site_idx.'BA' => '跨縣市（'.$site_name.'中山）',
            'H'.$site_idx.'BB' => '跨縣市（'.$site_name.'中正）',
            'H'.$site_idx.'BC' => '跨縣市（'.$site_name.'中興）',
            'H'.$site_idx.'BD' => '跨縣市（'.$site_name.'豐原）',
            'H'.$site_idx.'BE' => '跨縣市（'.$site_name.'大甲）',
            'H'.$site_idx.'BF' => '跨縣市（'.$site_name.'清水）',
            'H'.$site_idx.'BG' => '跨縣市（'.$site_name.'東勢）',
            'H'.$site_idx.'BH' => '跨縣市（'.$site_name.'雅潭）',
            'H'.$site_idx.'BI' => '跨縣市（'.$site_name.'大里）',
            'H'.$site_idx.'BJ' => '跨縣市（'.$site_name.'太平）',
            'H'.$site_idx.'BK' => '跨縣市（'.$site_name.'龍井）'
        ],
        '基隆市' => [
            'H'.$site_idx.'CD' => '跨縣市（'.$site_name.'基隆）'
        ],
        '台南市' => [
            'H'.$site_idx.'DA' => '跨縣市（'.$site_name.'台南）',
            'H'.$site_idx.'DB' => '跨縣市（'.$site_name.'安南）',
            'H'.$site_idx.'DC' => '跨縣市（'.$site_name.'東南）',
            'H'.$site_idx.'DD' => '跨縣市（'.$site_name.'鹽水）',
            'H'.$site_idx.'DE' => '跨縣市（'.$site_name.'白河）',
            'H'.$site_idx.'DF' => '跨縣市（'.$site_name.'麻豆）',
            'H'.$site_idx.'DG' => '跨縣市（'.$site_name.'佳里）',
            'H'.$site_idx.'DH' => '跨縣市（'.$site_name.'新化）',
            'H'.$site_idx.'DI' => '跨縣市（'.$site_name.'歸仁）',
            'H'.$site_idx.'DJ' => '跨縣市（'.$site_name.'玉井）',
            'H'.$site_idx.'DK' => '跨縣市（'.$site_name.'永康）'
        ],
        '高雄市' => [
            'H'.$site_idx.'EA' => '跨縣市（'.$site_name.'鹽埕）',
            'H'.$site_idx.'EB' => '跨縣市（'.$site_name.'新興）',
            'H'.$site_idx.'EC' => '跨縣市（'.$site_name.'前鎮）',
            'H'.$site_idx.'ED' => '跨縣市（'.$site_name.'三民）',
            'H'.$site_idx.'EE' => '跨縣市（'.$site_name.'楠梓）',
            'H'.$site_idx.'EF' => '跨縣市（'.$site_name.'岡山）',
            'H'.$site_idx.'EG' => '跨縣市（'.$site_name.'鳳山）',
            'H'.$site_idx.'EH' => '跨縣市（'.$site_name.'旗山）',
            'H'.$site_idx.'EI' => '跨縣市（'.$site_name.'仁武）',
            'H'.$site_idx.'EJ' => '跨縣市（'.$site_name.'路竹）',
            'H'.$site_idx.'EK' => '跨縣市（'.$site_name.'美濃）',
            'H'.$site_idx.'EL' => '跨縣市（'.$site_name.'大寮）'
        ],
        '新北市' => [
            'H'.$site_idx.'FA' => '跨縣市（'.$site_name.'板橋）',
            'H'.$site_idx.'FB' => '跨縣市（'.$site_name.'新莊）',
            'H'.$site_idx.'FC' => '跨縣市（'.$site_name.'新店）',
            'H'.$site_idx.'FD' => '跨縣市（'.$site_name.'汐止）',
            'H'.$site_idx.'FE' => '跨縣市（'.$site_name.'淡水）',
            'H'.$site_idx.'FF' => '跨縣市（'.$site_name.'瑞芳）',
            'H'.$site_idx.'FG' => '跨縣市（'.$site_name.'三重）',
            'H'.$site_idx.'FH' => '跨縣市（'.$site_name.'中和）',
            'H'.$site_idx.'FI' => '跨縣市（'.$site_name.'樹林）'
        ],
        '宜蘭縣' => [
            'H'.$site_idx.'GA' => '跨縣市（'.$site_name.'羅東）',
            'H'.$site_idx.'GB' => '跨縣市（'.$site_name.'宜蘭）'
        ],
        '新竹縣' => [
            'H'.$site_idx.'JB' => '跨縣市（'.$site_name.'竹北）',
            'H'.$site_idx.'JC' => '跨縣市（'.$site_name.'竹東）',
            'H'.$site_idx.'JD' => '跨縣市（'.$site_name.'新湖）'
        ],
        '苗栗縣' => [
            'H'.$site_idx.'KA' => '跨縣市（'.$site_name.'大湖）',
            'H'.$site_idx.'KB' => '跨縣市（'.$site_name.'苗栗）',
            'H'.$site_idx.'KC' => '跨縣市（'.$site_name.'通霄）',
            'H'.$site_idx.'KD' => '跨縣市（'.$site_name.'竹南）',
            'H'.$site_idx.'KE' => '跨縣市（'.$site_name.'銅鑼）',
            'H'.$site_idx.'KF' => '跨縣市（'.$site_name.'頭份）'
        ],
        '南投縣' => [
            'H'.$site_idx.'MA' => '跨縣市（'.$site_name.'南投）',
            'H'.$site_idx.'MB' => '跨縣市（'.$site_name.'草屯）',
            'H'.$site_idx.'MC' => '跨縣市（'.$site_name.'埔里）',
            'H'.$site_idx.'MD' => '跨縣市（'.$site_name.'竹山）',
            'H'.$site_idx.'ME' => '跨縣市（'.$site_name.'水里）'
        ],
        '彰化縣' => [
            'H'.$site_idx.'NA' => '跨縣市（'.$site_name.'彰化）',
            'H'.$site_idx.'NB' => '跨縣市（'.$site_name.'和美）',
            'H'.$site_idx.'NC' => '跨縣市（'.$site_name.'鹿港）',
            'H'.$site_idx.'ND' => '跨縣市（'.$site_name.'員林）',
            'H'.$site_idx.'NE' => '跨縣市（'.$site_name.'田中）',
            'H'.$site_idx.'NF' => '跨縣市（'.$site_name.'北斗）',
            'H'.$site_idx.'NG' => '跨縣市（'.$site_name.'二林）',
            'H'.$site_idx.'NH' => '跨縣市（'.$site_name.'溪湖）'
        ],
        '雲林縣' => [
            'H'.$site_idx.'PA' => '跨縣市（'.$site_name.'斗六）',
            'H'.$site_idx.'PB' => '跨縣市（'.$site_name.'斗南）',
            'H'.$site_idx.'PC' => '跨縣市（'.$site_name.'西螺）',
            'H'.$site_idx.'PD' => '跨縣市（'.$site_name.'虎尾）',
            'H'.$site_idx.'PE' => '跨縣市（'.$site_name.'北港）',
            'H'.$site_idx.'PF' => '跨縣市（'.$site_name.'台西）'
        ],
        '嘉義縣' => [
            'H'.$site_idx.'QB' => '跨縣市（'.$site_name.'朴子）',
            'H'.$site_idx.'QC' => '跨縣市（'.$site_name.'大林）',
            'H'.$site_idx.'QD' => '跨縣市（'.$site_name.'水上）',
            'H'.$site_idx.'QE' => '跨縣市（'.$site_name.'竹崎）'
        ],
        '屏東縣' => [
            'H'.$site_idx.'TA' => '跨縣市（'.$site_name.'屏東）',
            'H'.$site_idx.'TB' => '跨縣市（'.$site_name.'里港）',
            'H'.$site_idx.'TC' => '跨縣市（'.$site_name.'潮州）',
            'H'.$site_idx.'TD' => '跨縣市（'.$site_name.'東港）',
            'H'.$site_idx.'TE' => '跨縣市（'.$site_name.'恆春）',
            'H'.$site_idx.'TF' => '跨縣市（'.$site_name.'枋寮）'
        ],
        '花蓮縣' => [
            'H'.$site_idx.'UA' => '跨縣市（'.$site_name.'花蓮）',
            'H'.$site_idx.'UB' => '跨縣市（'.$site_name.'鳳林）',
            'H'.$site_idx.'UC' => '跨縣市（'.$site_name.'玉里）'
        ],
        '台東縣' => [
            'H'.$site_idx.'VA' => '跨縣市（'.$site_name.'台東）',
            'H'.$site_idx.'VB' => '跨縣市（'.$site_name.'成功）',
            'H'.$site_idx.'VC' => '跨縣市（'.$site_name.'關山）',
            'H'.$site_idx.'VD' => '跨縣市（'.$site_name.'太麻里）'
        ],
        '嘉義市' => ['H'.$site_idx.'IA' => '跨縣市（'.$site_name.'嘉義市）'],
        '新竹市' => ['H'.$site_idx.'OA' => '跨縣市（'.$site_name.'新竹市）'],
        '金門縣' => ['H'.$site_idx.'WA' => '跨縣市（'.$site_name.'金門）'],
        '澎湖縣' => ['H'.$site_idx.'XA' => '跨縣市（'.$site_name.'澎湖）'],
        '連江縣' => ['H'.$site_idx.'ZA' => '跨縣市（'.$site_name.'連江）']
    ],
    "跨縣市他所收件" => [
        '台北市' => [
            'A1'.$site_code => '跨縣市（古亭'.$site_name.'）',
            'A2'.$site_code => '跨縣市（建成'.$site_name.'）',
            'A3'.$site_code => '跨縣市（中山'.$site_name.'）',
            'A4'.$site_code => '跨縣市（松山'.$site_name.'）',
            'A5'.$site_code => '跨縣市（士林'.$site_name.'）',
            'A6'.$site_code => '跨縣市（大安'.$site_name.'）'
        ],
        '台中市' => [
            'B1'.$site_code => '跨縣市（中山'.$site_name.'）',
            'B2'.$site_code => '跨縣市（中正'.$site_name.'）',
            'B3'.$site_code => '跨縣市（中興'.$site_name.'）',
            'B4'.$site_code => '跨縣市（豐原'.$site_name.'）',
            'B5'.$site_code => '跨縣市（大甲'.$site_name.'）',
            'B6'.$site_code => '跨縣市（清水'.$site_name.'）',
            'B7'.$site_code => '跨縣市（東勢'.$site_name.'）',
            'B8'.$site_code => '跨縣市（雅潭'.$site_name.'）',
            'B9'.$site_code => '跨縣市（大里'.$site_name.'）',
            'BZ'.$site_code => '跨縣市（太平'.$site_name.'）',
            'BY'.$site_code => '跨縣市（龍井'.$site_name.'）'
        ],
        '基隆市' => [
            'C3'.$site_code => '跨縣市（基隆'.$site_name.'）',
        ],
        '台南市' => [
            'D1'.$site_code => '跨縣市（台南'.$site_name.'）',
            'D2'.$site_code => '跨縣市（安南'.$site_name.'）',
            'D3'.$site_code => '跨縣市（東南'.$site_name.'）',
            'D4'.$site_code => '跨縣市（鹽水'.$site_name.'）',
            'D5'.$site_code => '跨縣市（白河'.$site_name.'）',
            'D6'.$site_code => '跨縣市（麻豆'.$site_name.'）',
            'D7'.$site_code => '跨縣市（佳里'.$site_name.'）',
            'D8'.$site_code => '跨縣市（新化'.$site_name.'）',
            'D9'.$site_code => '跨縣市（歸仁'.$site_name.'）',
            'DZ'.$site_code => '跨縣市（玉井'.$site_name.'）',
            'DY'.$site_code => '跨縣市（永康'.$site_name.'）'
        ],
        '高雄市' => [
            'E1'.$site_code => '跨縣市（鹽埕'.$site_name.'）',
            'E2'.$site_code => '跨縣市（新興'.$site_name.'）',
            'E3'.$site_code => '跨縣市（前鎮'.$site_name.'）',
            'E4'.$site_code => '跨縣市（三民'.$site_name.'）',
            'E5'.$site_code => '跨縣市（楠梓'.$site_name.'）',
            'E6'.$site_code => '跨縣市（岡山'.$site_name.'）',
            'E7'.$site_code => '跨縣市（鳳山'.$site_name.'）',
            'E8'.$site_code => '跨縣市（旗山'.$site_name.'）',
            'E9'.$site_code => '跨縣市（仁武'.$site_name.'）',
            'EZ'.$site_code => '跨縣市（路竹'.$site_name.'）',
            'EY'.$site_code => '跨縣市（美濃'.$site_name.'）',
            'EX'.$site_code => '跨縣市（大寮'.$site_name.'）'
        ],
        '新北市' => [
            'F1'.$site_code => '跨縣市（板橋'.$site_name.'）',
            'F2'.$site_code => '跨縣市（新莊'.$site_name.'）',
            'F3'.$site_code => '跨縣市（新店'.$site_name.'）',
            'F4'.$site_code => '跨縣市（汐止'.$site_name.'）',
            'F5'.$site_code => '跨縣市（淡水'.$site_name.'）',
            'F6'.$site_code => '跨縣市（瑞芳'.$site_name.'）',
            'F7'.$site_code => '跨縣市（三重'.$site_name.'）',
            'F8'.$site_code => '跨縣市（中和'.$site_name.'）',
            'F9'.$site_code => '跨縣市（樹林'.$site_name.'）'
        ],
        '宜蘭縣' => [
            'G1'.$site_code => '跨縣市（羅東'.$site_name.'）',
            'G2'.$site_code => '跨縣市（宜蘭'.$site_name.'）'
        ],
        '新竹縣' => [
            'J1'.$site_code => '跨縣市（竹北'.$site_name.'）',
            'J2'.$site_code => '跨縣市（竹東'.$site_name.'）',
            'J3'.$site_code => '跨縣市（新湖'.$site_name.'）'
        ],
        '苗栗縣' => [
            'K1'.$site_code => '跨縣市（大湖'.$site_name.'）',
            'K2'.$site_code => '跨縣市（苗栗'.$site_name.'）',
            'K3'.$site_code => '跨縣市（通霄'.$site_name.'）',
            'K4'.$site_code => '跨縣市（竹南'.$site_name.'）',
            'K5'.$site_code => '跨縣市（銅鑼'.$site_name.'）',
            'K6'.$site_code => '跨縣市（頭份'.$site_name.'）'
        ],
        '南投縣' => [
            'M1'.$site_code => '跨縣市（南投'.$site_name.'）',
            'M2'.$site_code => '跨縣市（草屯'.$site_name.'）',
            'M3'.$site_code => '跨縣市（埔里'.$site_name.'）',
            'M4'.$site_code => '跨縣市（竹山'.$site_name.'）',
            'M5'.$site_code => '跨縣市（水里'.$site_name.'）'
        ],
        '彰化縣' => [
            'N1'.$site_code => '跨縣市（彰化'.$site_name.'）',
            'N2'.$site_code => '跨縣市（和美'.$site_name.'）',
            'N3'.$site_code => '跨縣市（鹿港'.$site_name.'）',
            'N4'.$site_code => '跨縣市（員林'.$site_name.'）',
            'N5'.$site_code => '跨縣市（田中'.$site_name.'）',
            'N6'.$site_code => '跨縣市（北斗'.$site_name.'）',
            'N7'.$site_code => '跨縣市（二林'.$site_name.'）',
            'N8'.$site_code => '跨縣市（溪湖'.$site_name.'）'
        ],
        '雲林縣' => [
            'P1'.$site_code => '跨縣市（斗六'.$site_name.'）',
            'P2'.$site_code => '跨縣市（斗南'.$site_name.'）',
            'P3'.$site_code => '跨縣市（西螺'.$site_name.'）',
            'P4'.$site_code => '跨縣市（虎尾'.$site_name.'）',
            'P5'.$site_code => '跨縣市（北港'.$site_name.'）',
            'P6'.$site_code => '跨縣市（台西'.$site_name.'）'
        ],
        '嘉義縣' => [
            'Q1'.$site_code => '跨縣市（朴子'.$site_name.'）',
            'Q2'.$site_code => '跨縣市（大林'.$site_name.'）',
            'Q3'.$site_code => '跨縣市（水上'.$site_name.'）',
            'Q4'.$site_code => '跨縣市（竹崎'.$site_name.'）'
        ],
        '屏東縣' => [
            'T1'.$site_code => '跨縣市（屏東'.$site_name.'）',
            'T2'.$site_code => '跨縣市（里港'.$site_name.'）',
            'T3'.$site_code => '跨縣市（潮州'.$site_name.'）',
            'T4'.$site_code => '跨縣市（東港'.$site_name.'）',
            'T5'.$site_code => '跨縣市（恆春'.$site_name.'）',
            'T6'.$site_code => '跨縣市（枋寮'.$site_name.'）'
        ],
        '花蓮縣' => [
            'U1'.$site_code => '跨縣市（花蓮'.$site_name.'）',
            'U2'.$site_code => '跨縣市（鳳林'.$site_name.'）',
            'U3'.$site_code => '跨縣市（玉里'.$site_name.'）'
        ],
        '台東縣' => [
            'V1'.$site_code => '跨縣市（台東'.$site_name.'）',
            'V2'.$site_code => '跨縣市（成功'.$site_name.'）',
            'V3'.$site_code => '跨縣市（關山'.$site_name.'）',
            'V4'.$site_code => '跨縣市（太麻里'.$site_name.'）'
        ],
        '嘉義市' => ['I1'.$site_code => '跨縣市（嘉義市'.$site_name.'）'],
        '新竹市' => ['O1'.$site_code => '跨縣市（新竹市'.$site_name.'）'],
        '金門縣' => ['W1'.$site_code => '跨縣市（金門'.$site_name.'）'],
        '澎湖縣' => ['X1'.$site_code => '跨縣市（澎湖'.$site_name.'）'],
        '金門縣' => ['Z1'.$site_code => '跨縣市（連江'.$site_name.'）']
    ]
]);

$alerted2 = [];
$cross_host = $scc->getRegCrossHostCode();
foreach ($cross_host as $row) { $alerted2[$row['KCDE_2']] = $row['KCNT']; }
$cross_other = $scc->getRegCrossOtherCode();
foreach ($cross_other as $row) { $alerted2[$row['KCDE_2']] = $row['KCNT']; }
$cross_county_other = $scc->getRegCrossCountyOtherCode();
foreach ($cross_county_other as $row) { $alerted2[$row['KCDE_2']] = $row['KCNT']; }
$cross_county_host = $scc->getRegCrossCountyHostCode();
foreach ($cross_county_host as $row) { $alerted2[$row['KCDE_2']] = $row['KCNT']; }

// $altered is pprocessed before
define('REG_WORD', array_merge($altered, $alerted2));

define('REG_REASON', [
    "01" => "總登記",
    "02" => "第一次登記",
    "03" => "地籍圖重測",
    "04" => "土地重劃",
    "05" => "回復",
    "06" => "分割",
    "07" => "逕為分割",
    "08" => "判決分割",
    "09" => "和解分割",
    "10" => "調解分割",
    "11" => "合併",
    "12" => "更正",
    "14" => "更正編定",
    "15" => "使用編定",
    "16" => "變更編定",
    "17" => "等則調整",
    "18" => "地目變更",
    "19" => "逕為地目變更",
    "20" => "部分滅失",
    "21" => "滅失",
    "22" => "區段徵收",
    "23" => "地目等則調整",
    "24" => "行政區域調整",
    "25" => "段界調整",
    "26" => "地籍整理",
    "28" => "門牌整編",
    "29" => "基地號變更",
    "30" => "增建",
    "31" => "改建",
    "33" => "查封",
    "34" => "塗銷查封",
    "35" => "判決共有物分割",
    "36" => "和解共有物分割",
    "37" => "調解共有物分割",
    "38" => "共有物分割",
    "39" => "法人合併",
    "40" => "住址更正",
    "41" => "更名",
    "42" => "夫妻聯合財產更名",
    "43" => "姓名更正",
    "44" => "統一編號更正",
    "46" => "管理者變更",
    "48" => "住址變更",
    "49" => "假扣押",
    "50" => "假處分",
    "51" => "破產登記",
    "52" => "禁止處分",
    "53" => "塗銷預告登記",
    "54" => "塗銷假扣押",
    "55" => "塗銷假處分",
    "56" => "塗銷破產登記",
    "57" => "塗銷禁止處分",
    "58" => "預告登記",
    "59" => "書狀補給",
    "60" => "書狀換給",
    "61" => "判決回復所有權",
    "62" => "耕作權期間屆滿",
    "63" => "典權回贖除斥期滿",
    "64" => "買賣",
    "65" => "贈與",
    "66" => "遺贈",
    "67" => "拍賣",
    "68" => "繼承",
    "69" => "拋棄",
    "70" => "徵收",
    "71" => "放領",
    "72" => "照價收買",
    "73" => "交換",
    "74" => "判決繼承",
    "75" => "和解繼承",
    "76" => "調解繼承",
    "80" => "判決移轉",
    "81" => "和解移轉",
    "82" => "調解移轉",
    "83" => "設定",
    "85" => "權利價值變更",
    "86" => "存續期間變更",
    "87" => "清償日期變更",
    "88" => "利息變更",
    "89" => "地租變更",
    "90" => "義務人變更",
    "91" => "債務人及債務額比例變更",
    "92" => "權利範圍變更",
    "93" => "部分清償",
    "94" => "部分拋棄",
    "95" => "轉典",
    "96" => "讓與",
    "97" => "判決設定",
    "98" => "和解設定",
    "99" => "調解設定",
    "AB" => "判決塗銷",
    "AC" => "和解塗銷",
    "AD" => "調解塗銷",
    "AE" => "混同",
    "AF" => "清償",
    "AG" => "擔保物減少",
    "AH" => "擔保物增加",
    "AJ" => "界址調整",
    "AK" => "解除編定",
    "AL" => "註銷編定",
    "AM" => "補辦編定",
    "AN" => "補註用地別",
    "AP" => "和解回復所有權",
    "AQ" => "調解回復所有權",
    "AR" => "法定",
    "AS" => "權利分割",
    "AT" => "權利合併",
    "AU" => "未登記建物查封",
    "AX" => "註記",
    "AY" => "塗銷註記",
    "AZ" => "抵繳稅款",
    "BA" => "解散",
    "BB" => "發還",
    "BC" => "收歸國有",
    "BD" => "無償撥用",
    "BE" => "自耕保留地持分交換",
    "BF" => "地上權期間屆滿",
    "BG" => "時效取得",
    "BH" => "分割繼承",
    "BJ" => "遺產管理人登記",
    "BK" => "遺囑執行人登記",
    "BL" => "保全處分",
    "BM" => "破產管理人登記",
    "BN" => "撤銷",
    "BP" => "訴願決定撤銷",
    "BQ" => "撤銷徵收",
    "BR" => "次序變更",
    "BS" => "權利內容等變更",
    "BT" => "存續期間屆滿",
    "BU" => "截止記載",
    "BV" => "失蹤人財產管理人",
    "BW" => "有償撥用",
    "BX" => "查封部分塗銷",
    "BY" => "法院囑託塗銷",
    "BZ" => "共有型態變更",
    "CA" => "回贖",
    "CB" => "建物主要用途變更",
    "CC" => "地建號更正",
    "CD" => "墾竣",
    "CE" => "廢止撥用",
    "CF" => "法院囑託回復",
    "CG" => "接管",
    "CH" => "修建",
    "CJ" => "出生日期更正",
    "CL" => "逕為塗銷",
    "CM" => "名義更正",
    "CN" => "遺漏更正",
    "CP" => "違約金變更",
    "CQ" => "調處移轉",
    "CR" => "執行命令",
    "CS" => "代表人變更",
    "CT" => "塗銷遺產管理人登記",
    "CU" => "信託",
    "CV" => "受託人變更",
    "CW" => "塗銷信託",
    "CX" => "信託歸屬",
    "CY" => "信託取得",
    "CZ" => "塗銷保全處分",
    "DA" => "領回抵價地",
    "DB" => "夫妻贈與",
    "DC" => "分區調整",
    "DD" => "剩餘財產差額分配",
    "DE" => "賸餘財產分派",
    "DF" => "持分合併",
    "DG" => "耕地租約終止",
    "DH" => "地籍圖修正測量",
    "DI" => "領回土地",
    "DJ" => "預為抵押權",
    "DK" => "面積更正",
    "DL" => "逕為合併",
    "DM" => "調處分割",
    "DN" => "調處共有物分割",
    "DO" => "徵收失效",
    "DP" => "轉換",
    "DQ" => "持分分割",
    "DR" => "法人分割",
    "DS" => "塗銷地目",
    "DT" => "權利變換",
    "DU" => "法人收購",
    "DV" => "改設醫療法人",
    "DW" => "遺囑繼承",
    "DX" => "遺產清理人登記",
    "DY" => "次序讓與",
    "DZ" => "次序相對拋棄",
    "EA" => "次序絕對拋棄",
    "EB" => "擔保債權確定期日變更",
    "EC" => "流抵約定變更",
    "ED" => "其他擔保範圍約定變更",
    "EE" => "擔保債權種類及範圍變更",
    "EF" => "限定擔保債權金額變更",
    "EG" => "分割讓與",
    "EH" => "權利種類變更",
    "EI" => "地籍清理塗銷",
    "EJ" => "地籍清理部分塗銷",
    "EK" => "地籍清理擔保物減少",
    "EL" => "囑託塗銷",
    "EM" => "退股",
    "EN" => "地籍清理權利範圍變更",
    "EO" => "地籍清理權利內容等變更",
    "EP" => "設定目的變更",
    "EQ" => "預付地租情形變更",
    "ER" => "使用方法變更",
    "ES" => "讓與或設定抵押權限制變更",
    "ET" => "絕賣條款變更",
    "EU" => "典物轉典或出租限制變更",
    "EV" => "絕賣",
    "EW" => "終止",
    "EX" => "法定塗銷",
    "EY" => "酌給遺產",
    "EZ" => "退稅",
    "FA" => "廢止徵收",
    "FB" => "暫時處分",
    "FC" => "塗銷暫時處分",
    "FD" => "農育權期間屆滿",
    "FE" => "減資退還股款",
    "FF" => "權利取得",
    "ZZ" => "分割轉載"
]);

unset($scc);
unset($codes);
unset($altered);
unset($altered2);
unset($cross_host);
unset($cross_other);
unset($cross_county_other);
unset($cross_county_host);
unset($site_code);
unset($site_code_tail);
unset($site_short_name);
unset($site_name);
unset($site_idx);
