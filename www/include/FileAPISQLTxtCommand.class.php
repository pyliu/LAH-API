<?php
require_once("FileAPICommand.class.php");
require_once("Query.class.php");

class FileAPISQLTxtCommand extends FileAPICommand {
    private $sql;
    function __construct($sql) {
        $this->sql = $sql;
    }

    function __destruct() {}

    private function txt($data, $print_count = true) {
        header("Content-Type: text/txt");
        $out = fopen("php://output", 'w'); 
        if (is_array($data)) {
            foreach ($data as $row) {
                array_walk($row, array($this, "cleanData"));
                fwrite($out, implode(",", array_values($row))."\n");
            }
            if ($print_count) {
                fwrite($out, iconv("utf-8", "big5", "##### TAG #####共產製 ".count($data)." 筆資料"));
            }
        } else {
            fwrite($out, iconv("utf-8", "big5", "錯誤說明：傳入之參數非陣列格式無法匯出！\n"));
            fwrite($out, print_r($data, true));
        }
        fclose($out);
    }

    public function execute() {
        $q = new Query();
        $data = $q->getSelectSQLData($this->sql);
        $this->txt($data);
    }
}
?>
