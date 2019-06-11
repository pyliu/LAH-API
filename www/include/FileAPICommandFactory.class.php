<?php
require_once("FileAPINotSupportCommand.class.php");
require_once("FileAPISQLCsvCommand.class.php");

abstract class FileAPICommandFactory {
    public static function getCommand($type) {
        switch ($_POST["type"]) {
            case "file_sql_csv":
                return new FileAPISQLCsvCommand($_POST["sql"]);
                break;
            default:
                return new FileAPINotSupportCommand();
                break;
        }
    }
}
?>
