<?php
require_once("init.php");

class PrcAllCasesData {
    private $rows;

    private function convertCharset() {
        $convert = array();
        foreach ($this->rows as $key=>$value) {
            $convert[$key] = empty($value) ? $value : iconv("big5", "utf-8", $value);
        }
        return $convert;
    }

    private function getReceiveSerial($row) {
        // 收件年+字（代碼）+號（6碼）
        $offset = strlen($row["SS04_2"]) - 6;
        if ($offset < 0) {
            $offset = abs($offset);
            for ($i = 0; $i < $offset; $i++) {
                $row["SS04_2"] = "0".$row["SS04_2"];
            }
        }
        return $row["SS03"]."年 ".REG_WORD[$row["SS04_1"]]."(".$row["SS04_1"].")字 第 ".$row["SS04_2"]." 號";
    }

    function __construct($db_record) {
        $this->rows = $db_record;
    }

    function __destruct() {
        $this->rows = null;
    }

    public function getTableHtml() {
        $str = "<table id='case_results' border='1' class='table-hover text-center col-lg-12'>\n";
        $str .= "<thead id='case_table_header'><tr class='header'>".
            "<th id='fixed_th1'>收件字號</th>\n".
            "<th id='fixed_th2'>登記日期</th>\n".
            "<th id='fixed_th3'>登記原因</th>\n".
            "<th id='fixed_th4'>發生日期</th>\n".
            "<th id='fixed_th5'>案件情況</th>\n".
            "<th id='fixed_th6'>異動時間</th>\n".
            "<th id='fixed_th7'>異動訊息</th>\n".
            "<th id='fixed_th8'>資料管轄所</th>\n".
            "<th id='fixed_th9'>資料收件所</th>\n".
            "</tr></thead>\n";
        $str .= "<tbody>\n";

        foreach ($this->rows as $row) {
            $str .= "<tr >\n";

            $str .= "<td><a href='javascript:void(0)' class='prc_case_serial'>".$this->getReceiveSerial($row)."</a></td>\n".
            "<td>".$row["SS05"]."</td>\n".
            "<td>".$row["SS06_M"]."</td>\n".
            "<td>".$row["SS07"]."</td>\n".
            "<td>".$row["SP_CODE_M"]."</td>\n".
            "<td>".$row["SP_DATE_M"]."</td>\n".
            "<td>".$row["SS09"]."</td>\n".
            "<td>".$row["SS100_M"]."</td>\n".
            "<td>".$row["SS101_M"]."</td>\n";

            $str .= "</tr>\n";
        }
        
        $str .= "</tbody>\n";
        $str .= "</table>\n";

        return $str;
    }
}
