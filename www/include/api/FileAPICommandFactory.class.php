<?php
require_once(dirname(dirname(__FILE__)).DIRECTORY_SEPARATOR."init.php");
require_once("FileAPINotSupportCommand.class.php");
require_once("FileAPISQLCsvCommand.class.php");
require_once("FileAPISQLTxtCommand.class.php");
require_once("FileAPILogExportCommand.class.php");
require_once("FileAPIExcelExportCommand.class.php");
require_once("FileAPIDataExportCommand.class.php");

abstract class FileAPICommandFactory {
    public static function getCommand($type) {
        switch ($type) {
            case "file_sql_csv":
                Logger::getInstance()->info("輸出CSV檔案");
                Logger::getInstance()->info($_POST["sql"]);
                return new FileAPISQLCsvCommand($_POST["sql"]);
            case "file_sql_txt":
                Logger::getInstance()->info("輸出TXT檔案");
                Logger::getInstance()->info($_POST["sql"]);
                $_SESSION['export_tmp_txt_filename'] = $_POST["filename"] ?? 'tmp';
                return new FileAPISQLTxtCommand($_POST["sql"]);
            case "file_data_export":
                Logger::getInstance()->info("輸出 ".$_POST["code"]." TXT 檔案");
                return new FileAPIDataExportCommand($_POST["code"], $_POST['section']);
            case "file_log":
                Logger::getInstance()->info("輸出LOG檔案");
                Logger::getInstance()->info($_POST["date"]);
                return new FileAPILogExportCommand($_POST["date"]);
            case "file_xlsx":
                Logger::getInstance()->info("輸出XLSX檔案");
                return new FileAPIExcelExportCommand();
            case "file_inheritance_restriction_xlsx":
                Logger::getInstance()->info("輸出外國人管制清冊XLSX檔案");
                return new FileAPIExcelExportCommand();
            default:
                return new FileAPINotSupportCommand();
        }
    }
}
