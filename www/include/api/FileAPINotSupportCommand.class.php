<?php
require_once("FileAPICommand.class.php");
class FileAPINotSupportCommand extends FileAPICommand {
    function __construct() {}

    function __destruct() {}

    public function execute() {
        header("Content-Type: text/csv");
        $out = fopen("php://output", 'w');
        fputcsv($out, array_values(array(iconv("utf-8", "big5", "錯誤說明"))), ',', '"');
        fputcsv($out, array_values(array(iconv("utf-8", "big5", "不支援的匯出型態"))), ',', '"');
        fclose($out);
    }
}
