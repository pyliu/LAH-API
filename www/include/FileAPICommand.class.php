<?php
require_once("GlobalConstants.inc.php");

abstract class FileAPICommand {
    
    protected $colsNameMapping;
    
    abstract public function execute();
    
    protected function cleanData(&$str) {
        if ($str == 't') $str = 'TRUE';
        if ($str == 'f') $str = 'FALSE';
        if (preg_match("/^0/", $str) || preg_match("/^\+?\d{8,}$/", $str) || preg_match("/^\d{4}.\d{1,2}.\d{1,2}/", $str)) {
            // number converts to string forcely
            $str = "$str";
        }
        if (strstr($str, '"')) $str = '"' . str_replace('"', '""', $str) . '"';
        $str = iconv("utf-8", "big5", $str);
    }

    protected function mapColumns($input) {
        return array_key_exists($input, $this->colsNameMapping) ? iconv("utf-8", "big5", $this->colsNameMapping[$input]) : $input;
    }

    protected function outputCSV($data, $skip_header = false) {
        header("Content-Type: text/csv");
        $out = fopen("php://output", 'w'); 
        if (is_array($data)) {
            $firstline_flag = false;
            foreach ($data as $row) {
                if (!$skip_header && !$firstline_flag) {
                    if (!is_array($row)) {
                        fputcsv($out, array_values(array(iconv("utf-8", "big5", "錯誤說明"))), ',', '"');
                        fputcsv($out, array_values(array(iconv("utf-8", "big5", "第一列的資料非陣列，無法轉換為CSV檔案"))), ',', '"');
                        break;
                    }
                    $firstline = array_map(array($this, "mapColumns"), array_keys($row));
                    //$firstline = array_map("self::mapColumns", array_keys($row));
                    fputcsv($out, $firstline, ',', '"');
                    $firstline_flag = true;
                }
                array_walk($row, array($this, "cleanData"));
                //array_walk($row, "self::cleanData");
                fputcsv($out, array_values($row), ',', '"');
            }
        } else {
            fputcsv($out, array_values(array(iconv("utf-8", "big5", "錯誤說明"))), ',', '"');
            fputcsv($out, array_values(array(iconv("utf-8", "big5", "傳入之參數非陣列格式無法匯出！"))), ',', '"');
        }
        fclose($out);
    }
}
?>
