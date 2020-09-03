<?php
require_once("FileAPICommand.class.php");
require_once(ROOT_DIR.DIRECTORY_SEPARATOR."include/Query.class.php");

class FileAPISQLCsvCommand extends FileAPICommand {
    private $sql;
    function __construct($sql) {
        $this->sql = $sql;
        // parent class has $colsNameMapping var for translating column header
        $this->colsNameMapping = include(ROOT_DIR.DIRECTORY_SEPARATOR."include/config/Config.ColsNameMapping.CRSMS.php");
        $this->colsNameMapping += include(ROOT_DIR.DIRECTORY_SEPARATOR."include/config/Config.ColsNameMapping.CMSMS.php");
        $this->colsNameMapping += include(ROOT_DIR.DIRECTORY_SEPARATOR."include/config/Config.ColsNameMapping.CMSDS.php");
        $this->colsNameMapping += include(ROOT_DIR.DIRECTORY_SEPARATOR."include/config/Config.ColsNameMapping.CABRP.php");
        $this->colsNameMapping += include(ROOT_DIR.DIRECTORY_SEPARATOR."include/config/Config.ColsNameMapping.EXPAA.php");
        $this->colsNameMapping += include(ROOT_DIR.DIRECTORY_SEPARATOR."include/config/Config.ColsNameMapping.EXPAB.php");
        $this->colsNameMapping += include(ROOT_DIR.DIRECTORY_SEPARATOR."include/config/Config.ColsNameMapping.EXPAC.php");
        $this->colsNameMapping += include(ROOT_DIR.DIRECTORY_SEPARATOR."include/config/Config.ColsNameMapping.EXPBA.php");
        $this->colsNameMapping += include(ROOT_DIR.DIRECTORY_SEPARATOR."include/config/Config.ColsNameMapping.EXPBB.php");
        $this->colsNameMapping += include(ROOT_DIR.DIRECTORY_SEPARATOR."include/config/Config.ColsNameMapping.EXPCA.php");
        $this->colsNameMapping += include(ROOT_DIR.DIRECTORY_SEPARATOR."include/config/Config.ColsNameMapping.EXPCB.php");
        $this->colsNameMapping += include(ROOT_DIR.DIRECTORY_SEPARATOR."include/config/Config.ColsNameMapping.EXPCC.php");
        $this->colsNameMapping += include(ROOT_DIR.DIRECTORY_SEPARATOR."include/config/Config.ColsNameMapping.EXPD.php");
        $this->colsNameMapping += include(ROOT_DIR.DIRECTORY_SEPARATOR."include/config/Config.ColsNameMapping.EXPE.php");
        $this->colsNameMapping += include(ROOT_DIR.DIRECTORY_SEPARATOR."include/config/Config.ColsNameMapping.EXPF.php");
        $this->colsNameMapping += include(ROOT_DIR.DIRECTORY_SEPARATOR."include/config/Config.ColsNameMapping.EXPG.php");
        $this->colsNameMapping += include(ROOT_DIR.DIRECTORY_SEPARATOR."include/config/Config.ColsNameMapping.RKEYN.php"); 
        $this->colsNameMapping += include(ROOT_DIR.DIRECTORY_SEPARATOR."include/config/Config.ColsNameMapping.RLNID.php");
        $this->colsNameMapping += include(ROOT_DIR.DIRECTORY_SEPARATOR."include/config/Config.ColsNameMapping.PSCRN.php");
        $this->colsNameMapping += include(ROOT_DIR.DIRECTORY_SEPARATOR."include/config/Config.ColsNameMapping.OTHERS.php");
        // foreach ($this->colsNameMapping as $key => $value) {
        //     $this->colsNameMapping[$key] = mb_convert_encoding($value, "big5", "utf-8");
        // }
    }

    function __destruct() {}

    private function csv($data) {
        $out = fopen(ROOT_DIR.DIRECTORY_SEPARATOR."exports".DIRECTORY_SEPARATOR."tmp.csv", 'w'); 
        if (is_array($data)) {
            $count = 0;
            foreach ($data as $row) {
                $count++;
                $flat_text = '"'.implode("\",\"", array_values($row)).'"';
                fwrite($out, $flat_text."\n");
            }
        } else {
            fwrite($out, mb_convert_encoding("錯誤說明：傳入之參數非陣列格式無法匯出！\n", "big5", "utf-8"));
            fwrite($out, print_r($data, true));
        }
        fclose($out);
    }

    private function outputCSV($data, $skip_header = false) {
        header("Content-Type: text/csv; charset=big5");
        header("Content-Transfer-Encoding: binary");
        ob_clean();
        flush();
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
                $vals = array_values($row);
                // foreach ($vals as $key => $value) {
                //     $vals[$key] = mb_convert_encoding($value, "big5", "utf-8");
                // }

                fputcsv($out, $vals, ',', '"');
            }
        } else {
            fputcsv($out, array_values(array(iconv("utf-8", "big5", "錯誤說明"))), ',', '"');
            fputcsv($out, array_values(array(iconv("utf-8", "big5", "傳入之參數非陣列格式無法匯出！"))), ',', '"');
        }
        fclose($out);
    }

    public function execute() {
        $q = new Query();
        // true - get raw big5 data; default is false.
        $data = $q->getSelectSQLData($this->sql, true);
        $this->csv($data);
        //$this->outputCSV($data);
    }
}
?>
