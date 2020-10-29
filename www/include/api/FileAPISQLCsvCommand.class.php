<?php
require_once(dirname(dirname(__FILE__)).DIRECTORY_SEPARATOR.'init.php');
require_once(INC_DIR.DIRECTORY_SEPARATOR.'api'.DIRECTORY_SEPARATOR.'FileAPICommand.class.php');
require_once(INC_DIR.DIRECTORY_SEPARATOR.'Query.class.php');

class FileAPISQLCsvCommand extends FileAPICommand {
    private $sql;
    function __construct($sql) {
        $this->sql = $sql;
        // parent class has $colsNameMapping var for translating column header
        $this->colsNameMapping = include(INC_DIR.DIRECTORY_SEPARATOR.'config'.DIRECTORY_SEPARATOR."Config.ColsNameMapping.CRSMS.php");
        $this->colsNameMapping += include(INC_DIR.DIRECTORY_SEPARATOR.'config'.DIRECTORY_SEPARATOR."Config.ColsNameMapping.CMSMS.php");
        $this->colsNameMapping += include(INC_DIR.DIRECTORY_SEPARATOR.'config'.DIRECTORY_SEPARATOR."Config.ColsNameMapping.CMSDS.php");
        $this->colsNameMapping += include(INC_DIR.DIRECTORY_SEPARATOR.'config'.DIRECTORY_SEPARATOR."Config.ColsNameMapping.CABRP.php");
        $this->colsNameMapping += include(INC_DIR.DIRECTORY_SEPARATOR.'config'.DIRECTORY_SEPARATOR."Config.ColsNameMapping.EXPAA.php");
        $this->colsNameMapping += include(INC_DIR.DIRECTORY_SEPARATOR.'config'.DIRECTORY_SEPARATOR."Config.ColsNameMapping.EXPAB.php");
        $this->colsNameMapping += include(INC_DIR.DIRECTORY_SEPARATOR.'config'.DIRECTORY_SEPARATOR."Config.ColsNameMapping.EXPAC.php");
        $this->colsNameMapping += include(INC_DIR.DIRECTORY_SEPARATOR.'config'.DIRECTORY_SEPARATOR."Config.ColsNameMapping.EXPBA.php");
        $this->colsNameMapping += include(INC_DIR.DIRECTORY_SEPARATOR.'config'.DIRECTORY_SEPARATOR."Config.ColsNameMapping.EXPBB.php");
        $this->colsNameMapping += include(INC_DIR.DIRECTORY_SEPARATOR.'config'.DIRECTORY_SEPARATOR."Config.ColsNameMapping.EXPCA.php");
        $this->colsNameMapping += include(INC_DIR.DIRECTORY_SEPARATOR.'config'.DIRECTORY_SEPARATOR."Config.ColsNameMapping.EXPCB.php");
        $this->colsNameMapping += include(INC_DIR.DIRECTORY_SEPARATOR.'config'.DIRECTORY_SEPARATOR."Config.ColsNameMapping.EXPCC.php");
        $this->colsNameMapping += include(INC_DIR.DIRECTORY_SEPARATOR.'config'.DIRECTORY_SEPARATOR."Config.ColsNameMapping.EXPD.php");
        $this->colsNameMapping += include(INC_DIR.DIRECTORY_SEPARATOR.'config'.DIRECTORY_SEPARATOR."Config.ColsNameMapping.EXPE.php");
        $this->colsNameMapping += include(INC_DIR.DIRECTORY_SEPARATOR.'config'.DIRECTORY_SEPARATOR."Config.ColsNameMapping.EXPF.php");
        $this->colsNameMapping += include(INC_DIR.DIRECTORY_SEPARATOR.'config'.DIRECTORY_SEPARATOR."Config.ColsNameMapping.EXPG.php");
        $this->colsNameMapping += include(INC_DIR.DIRECTORY_SEPARATOR.'config'.DIRECTORY_SEPARATOR."Config.ColsNameMapping.RKEYN.php"); 
        $this->colsNameMapping += include(INC_DIR.DIRECTORY_SEPARATOR.'config'.DIRECTORY_SEPARATOR."Config.ColsNameMapping.RLNID.php");
        $this->colsNameMapping += include(INC_DIR.DIRECTORY_SEPARATOR.'config'.DIRECTORY_SEPARATOR."Config.ColsNameMapping.PSCRN.php");
        $this->colsNameMapping += include(INC_DIR.DIRECTORY_SEPARATOR.'config'.DIRECTORY_SEPARATOR."Config.ColsNameMapping.OTHERS.php");
    }

    function __destruct() {}

    private function writeCSVtmp($data) {
        $out = fopen(EXPORT_DIR.DIRECTORY_SEPARATOR."tmp.csv", 'w'); 
        if (is_array($data)) {
            $count = 0;
            foreach ($data as $row) {
                // heading
                if ($count == 0) {
                    if (!is_array($row)) {
                        fputcsv($out, array_values(array(iconv("utf-8", "big5", "錯誤說明"))), ',', '"');
                        fputcsv($out, array_values(array(iconv("utf-8", "big5", "第一列的資料非陣列，無法轉換為CSV檔案"))), ',', '"');
                        break;
                    }
                    $firstline = array_map(array($this, "mapColumns"), array_keys($row));
                    fputcsv($out, $firstline, ',', '"');
                }
                // normal content
                fputcsv($out, array_values($row), ',', '"');
                $count++;
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
        $this->writeCSVtmp($data);
        //$this->outputCSV($data);
    }
}
