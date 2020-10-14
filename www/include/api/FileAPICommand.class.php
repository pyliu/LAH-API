<?php
require_once(dirname(dirname(__FILE__)).DIRECTORY_SEPARATOR."GlobalConstants.inc.php");

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
        $converted = mb_convert_encoding($str, "big5", "utf-8");
        if (!empty($converted)) {
            $str = $converted;
        }
    }

    protected function mapColumns($input, $convert = true) {
        return array_key_exists($input, $this->colsNameMapping) ? ($convert ? mb_convert_encoding($this->colsNameMapping[$input], "big5", "utf-8") : $this->colsNameMapping[$input]) : $input;
    }
}
