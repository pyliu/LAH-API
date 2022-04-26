<?php
require_once("System.class.php");
// 地所DB的內碼為「American_America.US7ASCII」
putenv('NLS_LANG=American_America.US7ASCII');

/**
 * 220.1.33.2 L1HWEB
 * 220.1.33.3 L2HWEB
 * 220.1.33.5 L3HWEB
 * 220.1.33.5 L1HWEB
*/
abstract class CONNECTION_TYPE {
    const MAIN = 0;
    const TWEB = 1;
    const L1HWEB = 2;
    const L2HWEB = 3;
    const L3HWEB = 4;
    const L1HWEB_Alt = 5;
    const BK = 6;
}

class OraDB {
    private $L1HWEB_DB;
    private $L1HWEB_Alt_DB;
    private $L2HWEB_DB;
    private $L3HWEB_DB;
    private $TWEB_DB;
    private $MAIN_DB;
    private $BK_DB;
    private $user;
    private $pass;
    private $nls = "US7ASCII";
    private $conn;
    private $stid;
    private $numrows;
    private $CONN_TYPE;
    private $connected = false;
    private $db_encoding = 'BIG5';
    private $web_encoding = 'UTF-8';

    public static function getPointDBTarget() {
        $type = CONNECTION_TYPE::MAIN;
        // get config from system
        $system = System::getInstance();
        $target_str = $system->getOraConnectTarget();
        if ($target_str === 'TEST') {
            $type = CONNECTION_TYPE::TWEB;
        } else if ($target_str === 'BACKUP') {
            $type = CONNECTION_TYPE::BK;
        }
        return $type;
    }

    public function connect() {
        if (!$this->connected) {
            // clean previous connection first
            $this->close();
            $this->conn = oci_connect($this->user, $this->pass, $this->getConnString(), $this->nls);
            if ($this->conn) {
                $this->connected = true;
            } else {
                $this->connected = false;
                $e = oci_error();
                Logger::getInstance()->error(__METHOD__.": ".$e['message']);
                trigger_error(htmlentities($e['message'], ENT_QUOTES), E_USER_ERROR);
            }
        }
        return $this->connected;
    }

    public function close() {
        if ($this->stid) {
            oci_free_statement($this->stid);
        }
        if ($this->conn) {
            oci_close($this->conn);
        }
        $this->stid = null;
        $this->conn = null;
        $this->connected = false;
    }

    public function parse($str) {
        // lazy connect here
        $this->connect();
        // release previous resource
        if ($this->stid) {
            oci_free_statement($this->stid);
        }
        // Prepare the statement
        $this->stid = oci_parse($this->conn, $str);
        if (!$this->stid) {
            $e = oci_error($this->conn);
            
            Logger::getInstance()->error(__METHOD__.": ".$e['message']);
            trigger_error(htmlentities($e['message'], ENT_QUOTES), E_USER_ERROR);
        }
    }

    public function execute() {
        // Perform the logic of the query
        $r = oci_execute($this->stid);
        if (!$r) {
            $e = oci_error($this->stid);
            
            Logger::getInstance()->error(__METHOD__.": ".$e['message']);
            trigger_error(htmlentities($e['message'], ENT_QUOTES), E_USER_ERROR);
        }
        return $this->stid;
    }

    public function fetch($raw = false) {
        $result = oci_fetch_assoc($this->stid); // oci_fetch_assoc is faster than oci_fetch_array
        if ($raw) {
            return $result;
        }
        $convert = array();
        if (!empty($result)) {
            foreach ($result as $key=>$value) {
                $convert[$key] = empty($value) ? $value : $this->convert($value);
            }
        }
        return $convert;
    }
    
    public function fetchAll($raw = false) {
        $this->numrows = 0;
        $results = array();
        while ($row = oci_fetch_assoc($this->stid)) {
            foreach ($row as $key=>$value) {
                if ($raw) {
                    $row[$key] = $value;
                } else {
                    $row[$key] = empty($value) ? $value : $this->convert($value);
                }
            }
            $results[] = $row;
            $this->numrows++;
        }
        return $results;
    }

    public function getLatestQueryCount() {
        return $this->numrows;
    }

    public function bind($bind_var, $real_var) {
        oci_bind_by_name($this->stid, $bind_var, $real_var);
    }

    public function setConnType($type) {
        switch($type) {
            case CONNECTION_TYPE::L1HWEB:
                $this->CONN_TYPE = CONNECTION_TYPE::L1HWEB;
                break;
            case CONNECTION_TYPE::L1HWEB_Alt:
                $this->CONN_TYPE = CONNECTION_TYPE::L1HWEB_Alt;
                break;
            case CONNECTION_TYPE::L2HWEB:
                $this->CONN_TYPE = CONNECTION_TYPE::L2HWEB;
                break;
            case CONNECTION_TYPE::L3HWEB:
                $this->CONN_TYPE = CONNECTION_TYPE::L3HWEB;
                break;
            case CONNECTION_TYPE::TWEB:
                $this->CONN_TYPE = CONNECTION_TYPE::TWEB;
                break;
            case CONNECTION_TYPE::BK:
                $this->CONN_TYPE = CONNECTION_TYPE::BK;
                break;
            default:
                $this->CONN_TYPE = CONNECTION_TYPE::MAIN;
        }
        // clear DB connection
        $this->close();
    }

    function __construct($type = CONNECTION_TYPE::MAIN) {
        if ($type === CONNECTION_TYPE::MAIN) {
            $type = self::getPointDBTarget();
        }
        $this->initSetting();
        $this->setConnType($type);
    }

    function __destruct() {
        $this->close();
    }

    private function initSetting() {
        $system = System::getInstance();
        $this->L1HWEB_DB = $system->getOraL1hwebDBConnStr();
        $this->L1HWEB_Alt_DB = $system->getOraL3hwebL1DBConnStr();
        $this->L2HWEB_DB = $system->getOraL2hwebDBConnStr();
        $this->L3HWEB_DB = $system->getOraL3hwebDBConnStr();
        $this->TWEB_DB = $system->getOraTestDBConnStr();
        $this->MAIN_DB = $system->getOraMainDBConnStr();
        $this->BK_DB = $system->getOraBackupDBConnStr();
        $this->user = $system->get("ORA_DB_USER");
        $this->pass = $system->get("ORA_DB_PASS");
        mb_regex_encoding($this->web_encoding);
        mb_substitute_character(0xFFFD); // 設定缺碼字改以 U+FFFD (a.k.a. "�") 取代
    }

    private function getConnString() {
        switch($this->CONN_TYPE) {
            case CONNECTION_TYPE::L1HWEB:
                return $this->L1HWEB_DB;
            case CONNECTION_TYPE::L1HWEB_Alt:
                return $this->L1HWEB_Alt_DB;
            case CONNECTION_TYPE::L2HWEB:
                return $this->L2HWEB_DB;
            case CONNECTION_TYPE::L3HWEB:
                return $this->L3HWEB_DB;
            case CONNECTION_TYPE::TWEB:
                return $this->TWEB_DB;
            case CONNECTION_TYPE::BK:
                return $this->BK_DB;
            default:
                return $this->MAIN_DB;
        }
    }

    private function convert($str) {
        if (!mb_check_encoding($str, $this->web_encoding)) {
            $converted = mb_convert_encoding($str, $this->web_encoding, $this->db_encoding);
            if ($str !== $converted) {
                Logger::getInstance()->info('ORIG: '.$str);
                Logger::getInstance()->info('CONV: '.$converted);
            }
            // if (preg_match("/[\x{FFFD}]/u", $converted, $matches)) {
            //     // detect invalid converted code, return original text
            //     return $str;
            // }
            // $converted = $this->replace_invalid_byte_sequence6($converted);
            // if ($additional_process) {
            //     $converted = preg_replace_callback(
            //         "/U\+([0-9A-F]{4})/i",
            //         function($matches) {
            //             foreach($matches as $match){
            //                 // find first one and return
            //                 return "&#".intval($match, 16).";";
            //             }
            //         }, 
            //         $converted
            //     );
            // }
            return $converted;
        }
        return $str;
    }

    private function replace_invalid_byte_sequence($str) {
        return mb_convert_encoding($str, $this->db_encoding, $this->db_encoding);
    }

    private function replace_invalid_byte_sequence2($str) {
        return htmlspecialchars_decode(htmlspecialchars($str, ENT_SUBSTITUTE, $this->db_encoding));
    }
    // UConverter offers both procedual and object-oriented API.
    private function replace_invalid_byte_sequence3($str) {
        return UConverter::transcode($str, $this->db_encoding, $this->db_encoding, array("to_subst" => "�"));
    }

    private function replace_invalid_byte_sequence4($str) {
        return (new UConverter($this->db_encoding, $this->db_encoding))->convert($str);
    }

    private function replace_invalid_byte_sequence6($str) {

        $size = strlen($str);
        $substitute = "\xEF\xBF\xBD";
        $ret = '';
    
        $pos = 0;
        $char;
        $char_size;
        $valid;
    
        while ($this->utf8_get_next_char($str, $size, $pos, $char, $char_size, $valid)) {
            $ret .= $valid ? $char : $substitute;
        }
    
        return $ret;
    }
    
    private function utf8_get_next_char($str, $str_size, &$pos, &$char, &$char_size, &$valid)
    {
        $valid = false;
    
        if ($str_size <= $pos) {
            return false;
        }
    
        if ($str[$pos] < "\x80") {
    
            $valid = true;
            $char_size =  1;
    
        } else if ($str[$pos] < "\xC2") {
    
            $char_size = 1;
    
        } else if ($str[$pos] < "\xE0")  {
    
            if (!isset($str[$pos+1]) || $str[$pos+1] < "\x80" || "\xBF" < $str[$pos+1]) {
    
                $char_size = 1;
    
            } else {
    
                $valid = true;
                $char_size = 2;
    
            }
    
        } else if ($str[$pos] < "\xF0") {
    
            $left = "\xE0" === $str[$pos] ? "\xA0" : "\x80";
            $right = "\xED" === $str[$pos] ? "\x9F" : "\xBF";
    
            if (!isset($str[$pos+1]) || $str[$pos+1] < $left || $right < $str[$pos+1]) {
    
                $char_size = 1;
    
            } else if (!isset($str[$pos+2]) || $str[$pos+2] < "\x80" || "\xBF" < $str[$pos+2]) {
    
                $char_size = 2;
    
            } else {
    
                $valid = true;
                $char_size = 3;
    
           }
    
        } else if ($str[$pos] < "\xF5") {
    
            $left = "\xF0" === $str[$pos] ? "\x90" : "\x80";
            $right = "\xF4" === $str[$pos] ? "\x8F" : "\xBF";
    
            if (!isset($str[$pos+1]) || $str[$pos+1] < $left || $right < $str[$pos+1]) {
    
                $char_size = 1;
    
            } else if (!isset($str[$pos+2]) || $str[$pos+2] < "\x80" || "\xBF" < $str[$pos+2]) {
    
                $char_size = 2;
    
            } else if (!isset($str[$pos+3]) || $str[$pos+3] < "\x80" || "\xBF" < $str[$pos+3]) {
    
                $char_size = 3;
    
            } else {
    
                $valid = true;
                $char_size = 4;
    
            }
    
        } else {
    
            $char_size = 1;
    
        }
    
        $char = substr($str, $pos, $char_size);
        $pos += $char_size;
    
        return true;
    }
}
