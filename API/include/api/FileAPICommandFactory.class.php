<?php
require_once(dirname(dirname(__FILE__)).DIRECTORY_SEPARATOR."init.php");
require_once("FileAPINotSupportCommand.class.php");
require_once("FileAPISQLCsvCommand.class.php");
require_once("FileAPISQLTxtCommand.class.php");
require_once("FileAPILogExportCommand.class.php");
require_once("FileAPIExcelExportCommand.class.php");
require_once("FileAPIDataExportCommand.class.php");
require_once("FileAPIInheritanceRestrictionXlsxExportCommand.class.php");
require_once("FileAPISurTrackingXlsxExportCommand.class.php");
require_once("FileAPIDataCleanCommand.class.php");

abstract class FileAPICommandFactory {
    public static function getCommand($type) {
        switch ($type) {
            case "file_sql_csv":
                Logger::getInstance()->info("輸出CSV檔案");
                $sql = $_POST["sql"] ?? '';
                Logger::getInstance()->info($sql);
                return new FileAPISQLCsvCommand($sql);
            case "file_sql_txt":
                Logger::getInstance()->info("輸出TXT檔案");
                $sql = $_POST["sql"] ?? '';
                Logger::getInstance()->info($sql);
                $_SESSION['export_tmp_txt_filename'] = $_POST["filename"] ?? 'tmp';
                return new FileAPISQLTxtCommand($sql);
            case "file_data_export":
                $code = $_POST["code"] ?? '';
                Logger::getInstance()->info("輸出 ".$code." TXT 檔案");
                $section = $_POST['section'] ?? '';
                return new FileAPIDataExportCommand($code, $section);
            case "file_data_clean":
                Logger::getInstance()->info("清理已匯出地籍資料檔案");
                return new FileAPIDataCleanCommand();
            case "file_data_list":
                Logger::getInstance()->info("查詢已匯出地籍資料檔案清單");
                return new FileAPIDataCleanCommand();
            case "file_log":
                Logger::getInstance()->info("輸出LOG檔案");
                $date = $_POST["date"] ?? '';
                Logger::getInstance()->info($date);
                return new FileAPILogExportCommand($date);
            case "file_xlsx":
                Logger::getInstance()->info("輸出XLSX檔案");
                return new FileAPIExcelExportCommand();
            case "file_inheritance_restriction_xlsx":
                Logger::getInstance()->info("輸出外國人管制清冊XLSX檔案");
                return new FileAPIInheritanceRestrictionXlsxExportCommand();
            case "file_sur_tracking_xlsx":
                Logger::getInstance()->info("輸出測量案件管制清冊XLSX檔案");
                return new FileAPISurTrackingXlsxExportCommand();
            default:
                return new FileAPINotSupportCommand();
        }
    }
}
