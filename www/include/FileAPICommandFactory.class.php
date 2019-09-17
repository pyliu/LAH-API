<?
require_once("init.php");
require_once("FileAPINotSupportCommand.class.php");
require_once("FileAPISQLCsvCommand.class.php");
require_once("FileAPISQLTxtCommand.class.php");
require_once("FileAPILogExportCommand.class.php");

abstract class FileAPICommandFactory {
    public static function getCommand($type) {
        global $log;
        switch ($_POST["type"]) {
            case "file_sql_csv":
                $log->info("輸出CSV檔案");
                $log->info($_POST["sql"]);
                return new FileAPISQLCsvCommand($_POST["sql"]);
                break;
            case "file_sql_txt":
                $log->info("輸出TXT檔案");
                $log->info($_POST["sql"]);
                return new FileAPISQLTxtCommand($_POST["sql"]);
                break;
            case "file_log":
                $log->info("輸出LOG檔案");
                $log->info($_POST["date"]);
                return new FileAPILogExportCommand($_POST["date"]);
                break;
            default:
                return new FileAPINotSupportCommand();
                break;
        }
    }
}
?>
