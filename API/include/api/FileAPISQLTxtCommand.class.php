<?php
require_once("FileAPICommand.class.php");
require_once(INC_DIR.DIRECTORY_SEPARATOR."Query.class.php");

class FileAPISQLTxtCommand extends FileAPICommand {
    private $sql;
    
    private function writeTXTtmp($data, $print_count = true) {
        $out = fopen(ROOT_DIR.DIRECTORY_SEPARATOR."export/tmp.txt", 'w'); 
        if (is_array($data)) {
            $count = 0;
            foreach ($data as $row) {
                $count++;
                $flat_text = implode(",", array_values($row));
                fwrite($out, $flat_text."\n");
            }
            if ($print_count) {
                fwrite($out, mb_convert_encoding("##### TAG #####共產製 ".$count." 筆資料", ORACLE_ENCODING, "utf-8"));
                // fwrite($out, "##### TAG #####共產製 ".$count." 筆資料");
            }
        } else {
            fwrite($out, mb_convert_encoding("錯誤說明：傳入之參數非陣列格式無法匯出！\n", ORACLE_ENCODING, "utf-8"));
            fwrite($out, print_r($data, true));
        }
        fclose($out);
    }

    private function exportTxt($data, $print_count = true) {
        header("Content-Type: text/plain; charset=big5");
        header("Content-Transfer-Encoding: binary");
        ob_clean();
        flush();
        $out = fopen("php://output", 'w'); 
        if (is_array($data)) {
            $count = 0;
            foreach ($data as $row) {
                $count++;
                $flat_text = implode(",", array_values($row));
                fwrite($out, $flat_text."\n");
            }
            if ($print_count) {
                fwrite($out, mb_convert_encoding("##### TAG #####共產製 ".$count." 筆資料", ORACLE_ENCODING, "utf-8"));
                // fwrite($out, "##### TAG #####共產製 ".$count." 筆資料");
            }
        } else {
            fwrite($out, mb_convert_encoding("錯誤說明：傳入之參數非陣列格式無法匯出！\n", ORACLE_ENCODING, "utf-8"));
            fwrite($out, print_r($data, true));
        }
        fclose($out);
    }

    function __construct($sql) {
        $this->sql = $sql;
    }

    function __destruct() {}

    public function execute() {
        $system = System::getInstance();
        $mock = $system->isMockMode();
        if ($mock) Logger::getInstance()->warning("現在處於模擬模式(mock mode)，API僅會回應之前已被快取之最新的資料！");
        $cache = Cache::getInstance();
        $q = new Query();
        // true - get raw big5 data; default is false.
        $data = $mock ? $cache->get('FileAPISQLTxtCommand') : $q->getSelectSQLData($this->sql, true);
        if (!$mock) $cache->set('FileAPISQLTxtCommand', $data);
        //$this->exportTxt($data);
        $this->writeTXTtmp($data);
    }
}
