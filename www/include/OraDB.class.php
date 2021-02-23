<?php
require_once("System.class.php");
require_once("Ping.class.php");
require_once("LXHWEB.class.php");
require_once("SQLiteSYSAUTH1.class.php");
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

    public static function getPointDBTarget() {
        $type = CONNECTION_TYPE::MAIN;
        // get config from system
        $system = new System();
        $target_str = $system->getOraConnectTarget();
        if ($target_str === 'TEST') {
            $type = CONNECTION_TYPE::TWEB;
        } else if ($target_str === 'BACKUP') {
            $type = CONNECTION_TYPE::BK;
        }
        return $type;
    }

    public static function queryOraUsers($refresh = false) {
        if ($refresh === true) {
            global $log;
            $system = new System();
        
            // check if l3hweb is reachable
            $l3hweb_ip = $system->get('ORA_DB_L3HWEB_IP');
            $l3hweb_port = $system->get('ORA_DB_L3HWEB_PORT');
            $latency = self::pingDomain($l3hweb_ip, $l3hweb_port);
        
            // not reachable use local DB instead
            if ($latency > 999 || $latency == '') {
                $log->warning(__METHOD__.": $l3hweb_ip:$l3hweb_port is not reachable, use local DB instead.");
                $result = array();
                // check if the main db is reachable
                $main_db_ip = $system->get('ORA_DB_HXWEB_IP');
                $main_db_port = $system->get('ORA_DB_HXWEB_PORT');
                $latency = pingDomain($main_db_ip, $main_db_port);
                if ($latency > 999 || $latency == '') {
                    return $result;
                }
        
                $db = $system->getOraMainDBConnStr();
                $log->info(__METHOD__.": query system ORA_DB_HXHEB database users.");
                $log->info(__METHOD__.": $db");
                
                $conn = oci_connect($system->get("ORA_DB_USER"), $system->get("ORA_DB_PASS"), $db, "US7ASCII");
                if (!$conn) {
                    $e = oci_error();
                    trigger_error(htmlentities($e['message'], ENT_QUOTES), E_USER_ERROR);
                }
                // Prepare the statement
                $stid = oci_parse($conn, "SELECT * FROM SSYSAUTH1");
                if (!$stid) {
                    $e = oci_error($conn);
                    trigger_error(htmlentities($e['message'], ENT_QUOTES), E_USER_ERROR);
                }
                
                // Perform the logic of the query
                $r = oci_execute($stid);
                if (!$r) {
                    $e = oci_error($stid);
                    trigger_error(htmlentities($e['message'], ENT_QUOTES), E_USER_ERROR);
                }
                while ($row = oci_fetch_array($stid, OCI_ASSOC + OCI_RETURN_NULLS)) {
                    $result[$row["USER_ID"]] = mb_convert_encoding(preg_replace('/\d+/', "", $row["USER_NAME"]), "UTF-8", "BIG5");
                }
                if ($stid) {
                    oci_free_statement($stid);
                }
                if ($conn) {
                    oci_close($conn);
                }
                return $result;
            } else {
                $lxhweb = new LXHWEB(CONNECTION_TYPE::L3HWEB);
                return $lxhweb->querySYSAUTH1UserNames();
            }
        } else {
            // cached data
            $sysauth1 = new SQLiteSYSAUTH1();
            $cached = $sysauth1->getAllUsers();
            $result = array();
            foreach ($cached as $row) {
                $result[$row["USER_ID"]] = $row["USER_NAME"];
            }
            return $result;
        }
    }    

    private static function pingDomain($domain, $port = 80){
        $ping = new Ping($domain);
        $ping->setPort($port);
        $latency = $ping->ping('fsockopen');
        if (empty($latency)) {
            $latency = $ping->ping('socket');
        }
        return $latency;
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
                global $log;
                if ($log) {
                    $log->error(__METHOD__.": ".$e['message']);
                }
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
            global $log;
            if ($log) {
                $log->error(__METHOD__.": ".$e['message']);
            }
            trigger_error(htmlentities($e['message'], ENT_QUOTES), E_USER_ERROR);
        }
    }

    public function execute() {
        // Perform the logic of the query
        $r = oci_execute($this->stid);
        if (!$r) {
            $e = oci_error($this->stid);
            global $log;
            if ($log) {
                $log->error(__METHOD__.": ".$e['message']);
            }
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
                $convert[$key] = empty($value) ? $value : $this->convert($value, "big5", "utf-8");
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
                    $row[$key] = empty($value) ? $value : $this->convert($value, "big5", "utf-8");
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
        $system = new System();
        $this->L1HWEB_DB = $system->getOraL1hwebDBConnStr();
        $this->L1HWEB_Alt_DB = $system->getOraL3hwebL1DBConnStr();
        $this->L2HWEB_DB = $system->getOraL2hwebDBConnStr();
        $this->L3HWEB_DB = $system->getOraL3hwebDBConnStr();
        $this->TWEB_DB = $system->getOraTestDBConnStr();
        $this->MAIN_DB = $system->getOraMainDBConnStr();
        $this->BK_DB = $system->getOraBackupDBConnStr();
        $this->user = $system->get("ORA_DB_USER");
        $this->pass = $system->get("ORA_DB_PASS");
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

    private function convert($str, $src_charset, $dest_charset) {
        mb_regex_encoding($dest_charset); // 宣告 要進行 regex 的多位元編碼轉換格式 為 $dest_charset
        mb_substitute_character('long'); // 宣告 缺碼字改以U+16進位碼為標記取代
        $str = mb_convert_encoding($str, $dest_charset, $src_charset);
        $str = preg_replace_callback(
            "/U\+([0-9A-F]{4})/",
            function($matches) {
                foreach($matches as $match){
                    // find first one and return
                    return "&#".intval($match, 16).";";
                }
            }, 
            $str
        );
        return $str;
    }
}
