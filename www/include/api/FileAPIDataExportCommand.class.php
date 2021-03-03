<?php
require_once(dirname(dirname(__FILE__)).DIRECTORY_SEPARATOR.'init.php');
require_once(INC_DIR.DIRECTORY_SEPARATOR."api".DIRECTORY_SEPARATOR."FileAPICommand.class.php");
require_once(INC_DIR.DIRECTORY_SEPARATOR."Query.class.php");
require_once(INC_DIR.DIRECTORY_SEPARATOR."Cache.class.php");
require_once(INC_DIR.DIRECTORY_SEPARATOR."System.class.php");

class FileAPIDataExportCommand extends FileAPICommand {
    private $code, $sections;
    private $name_map = array(
        'AI01101' => '建物所有權部',
        'AI01001' => '主建物及共同使用部分資料',
        'AI00901' => '建物分層及附屬資料',
        'AI00801' => '基地座落資料',
        'AI00701' => '建物標示部',
        'AI00401' => '土地所有權部',
        'AI02901_E' => '建物各部別之其他登記事項列印',
        'AI02901_B' => '土地各部別之其他登記事項列印',
        'AI00301' => '土地標示部',
        'AI00601_E' => '建物管理者資料',
        'AI00601_B' => '土地管理者資料'
    );

    private function export($data, $print_count = true) {

        global $today;  // from init.php
        $flat_section = 'HB'.implode('_HB', $this->sections);
        $output_name = $today.'_'.$this->code.'_'.$flat_section.'_'.$this->name_map[$this->code].'.txt';
        // set this session to let export_txt_data.php know the file has been created.
        $_SESSION[$this->code] = $output_name;
        
        $out = fopen(EXPORT_DIR.DIRECTORY_SEPARATOR.$output_name, 'w'); 
        if (is_array($data)) {
            $count = 0;
            foreach ($data as $row) {
                $count++;
                $flat_text = implode(",", array_values($row));
                fwrite($out, $flat_text."\n");
            }
            if ($print_count) {
                fwrite($out, mb_convert_encoding("##### TAG #####共產製 ".$count." 筆資料", "big5", "utf-8"));
                // fwrite($out, "##### TAG #####共產製 ".$count." 筆資料");
            }
        } else {
            fwrite($out, mb_convert_encoding("錯誤說明：傳入之參數非陣列格式無法匯出！\n", "big5", "utf-8"));
            fwrite($out, print_r($data, true));
        }
        fclose($out);
        echoJSONResponse($output_name.'已建立。', STATUS_CODE::SUCCESS_NORMAL, array(
            'filename' => $output_name
        ));
    }

    function __construct($code, $sections) {
        $this->code = $code;
        $this->sections = $sections;
    }

    function __destruct() {}

    public function execute() {
        $path = ROOT_DIR.DIRECTORY_SEPARATOR.'assets'.DIRECTORY_SEPARATOR.'files'.DIRECTORY_SEPARATOR.$this->code.'_sql.tpl';
        $content = @file_get_contents($path);
        if (empty($content)) {
            global $log;
            $msg = '無法讀取 '.$path.' 檔案。';
            $log->error(__METHOD__.': '.$msg);
            echoJSONResponse($msg, STATUS_CODE::FAIL_LOAD_ERROR);
            return false;
        }
        foreach ($this->sections as $k => $section) {
            $this->sections[$k] = str_pad($section, 4, '0', STR_PAD_LEFT);
        }
        $replacement = "'".implode("','", $this->sections)."'";
        $sql = str_replace('##REPLACEMENT##', $replacement, $content);
        
        global $log;
        $system = System::getInstance();
        $mock = $system->isMockMode();
        if ($mock) $log->warning("現在處於模擬模式(mock mode)，API僅會回應之前已被快取之最新的資料！");
        $cache = Cache::getInstance();
        $q = new Query();
		$data = $mock ? $cache->get('FileAPIDataExportCommand') : $q->getSelectSQLData($sql, true); // true - get raw big5 data; default is false.
        if (!$mock) $cache->set('FileAPIDataExportCommand', $data);
        
        $this->export($data);
    }
}
