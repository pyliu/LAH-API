<?php
require_once(dirname(dirname(__FILE__))."/include/init.php");
require_once(INC_DIR.DIRECTORY_SEPARATOR."System.class.php");
require_once(INC_DIR.DIRECTORY_SEPARATOR."SQLiteSYSAUTH1.class.php");
require_once(INC_DIR.DIRECTORY_SEPARATOR."SQLiteRKEYN.class.php");
require_once(INC_DIR.DIRECTORY_SEPARATOR."SQLiteRKEYNALL.class.php");

$auth = System::getInstance()->calcAuthority($_SESSION['myinfo']['authority']);
if (!$auth['isAdmin']) {
    Logger::getInstance()->error("$client_ip 不是 Admin 使用者無法存取此API(".__FILE__.")");
    echoJSONResponse("非 Admin 無法執行命令。", STATUS_CODE::DEFAULT_FAIL);
    exit;
}

$system = System::getInstance();

switch ($_POST["type"]) {
    case "switch_mock_flag":
        $result = $system->isMockMode();
        $msg = $result ? '目前是MOCK模式' : '目前不是MOCK模式';
		//Logger::getInstance()->info("XHR [switch_mock_flag] ".$msg);
		echoJSONResponse($msg, STATUS_CODE::SUCCESS_NORMAL, array('mock_flag' => $result));
        break;
    case "switch_mssql_flag":
        $result = $system->isMSSQLEnable();
        $msg = $result ? '目前是啟用MSSQL模式' : '目前是停用MSSQL模式';
		//Logger::getInstance()->info("XHR [switch_mssql_flag] ".$msg);
		echoJSONResponse($msg, STATUS_CODE::SUCCESS_NORMAL, array('mssql_flag' => $result));
        break;
    case "switch_office_hours_flag":
        $result = $system->isOfficeHoursEnable();
        $msg = $result ? '目前是啟用工作天檢查模式' : '目前是停用工作天檢查模式';
        //Logger::getInstance()->info("XHR [switch_office_hours_flag] ".$msg);
        echoJSONResponse($msg, STATUS_CODE::SUCCESS_NORMAL, array('office_hours_flag' => $result));
        break;
    case "switch_enable_mock":
        $result = $system->enableMockMode();
        $msg = $result ? '啟用MOCK模式成功' : '啟用MOCK模式失敗';
        Logger::getInstance()->info("XHR [switch_enable_mock] ".$msg);
        echoJSONResponse($msg, $result ? STATUS_CODE::SUCCESS_NORMAL : STATUS_CODE::DEFAULT_FAIL);
        break;
    case "switch_disable_mock":
        $result = $system->disableMockMode();
        $msg = $result ? '停用MOCK模式成功' : '停用MOCK模式失敗';
        Logger::getInstance()->info("XHR [switch_disable_mock] ".$msg);
        echoJSONResponse($msg, $result ? STATUS_CODE::SUCCESS_NORMAL : STATUS_CODE::DEFAULT_FAIL);
        break;
    case "switch_set_mssql_mode":
        $on_off = $_POST['flag'] == 'true';
        $result = $system->setMSSQLConnection($on_off);
        $on_off_msg = '設定MSSQL('.($on_off ? '啟用' : '停用').')';
        $msg = $result ? $on_off_msg.'成功' : $on_off_msg.'失敗';
        Logger::getInstance()->info("XHR [switch_set_mssql_mode] ".$msg);
        echoJSONResponse($msg, $result ? STATUS_CODE::SUCCESS_NORMAL : STATUS_CODE::DEFAULT_FAIL);
        break;
    case "switch_set_office_hours_mode":
        $on_off = $_POST['flag'] == 'true';
        $result = $system->setOfficeHoursEnable($on_off);
        $on_off_msg = '設定工作天檢查('.($on_off ? '啟用' : '停用').')';
        $msg = $result ? $on_off_msg.'成功' : $on_off_msg.'失敗';
        Logger::getInstance()->info("XHR [switch_set_office_hours_mode] ".$msg);
        echoJSONResponse($msg, $result ? STATUS_CODE::SUCCESS_NORMAL : STATUS_CODE::DEFAULT_FAIL);
        break;
    case "import_l3hweb_sysauth1":
        Logger::getInstance()->info("XHR [import_l3hweb_sysauth1] 匯入 L3HWEB SYSAUTH1 使用者名稱請求。");
        $sysauth1 = new SQLiteSYSAUTH1();
        $count = $sysauth1->importFromL3HWEBDB();
        $msg = "已匯入 $count 筆資料。";
        Logger::getInstance()->info("XHR [import_l3hweb_sysauth1] ".$msg);
        echoJSONResponse($msg, $count !== false ? STATUS_CODE::SUCCESS_NORMAL : STATUS_CODE::DEFAULT_FAIL);
        break;
    case "import_rkeyn":
        Logger::getInstance()->info("XHR [import_rkeyn] 匯入 RKEYN 代碼檔請求。");
        $sqlite_sr = new SQLiteRKEYN();
        $count = $sqlite_sr->importFromOraDB();
        $msg = "已匯入 $count 筆資料。";
        Logger::getInstance()->info("XHR [import_rkeyn] ".$msg);
        echoJSONResponse($msg, $count !== false ? STATUS_CODE::SUCCESS_NORMAL : STATUS_CODE::DEFAULT_FAIL);
        break;
    case "import_rkeyn_all":
        Logger::getInstance()->info("XHR [import_rkeyn_all] 匯入 RKEYN_ALL 代碼檔請求。");
        $sqlite_sra = new SQLiteRKEYNALL();
        $count = $sqlite_sra->importFromOraDB();
        $msg = "已匯入 $count 筆資料。";
        Logger::getInstance()->info("XHR [import_rkeyn_all] ".$msg);
        echoJSONResponse($msg, $count !== false ? STATUS_CODE::SUCCESS_NORMAL : STATUS_CODE::DEFAULT_FAIL);
        break;
    default:
        Logger::getInstance()->error("不支援的查詢型態【".$_POST["type"]."】");
        echoJSONResponse("不支援的查詢型態【".$_POST["type"]."】", STATUS_CODE::UNSUPPORT_FAIL);
        break;
}
