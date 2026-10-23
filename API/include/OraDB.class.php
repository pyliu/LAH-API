<?php
require_once("GlobalConstants.inc.php");
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
    private $db_encoding = ORACLE_ENCODING;
    private $web_encoding = 'UTF-8';
    private $use_cli_bridge = false;
    private $cli_sql = '';
    private $cli_binds = array();
    private $cli_rows = array();
    private $cli_cursor = 0;
    private $cli_error = null;

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

    function __construct($type = CONNECTION_TYPE::MAIN) {
        $this->use_cli_bridge = !extension_loaded('oci8');
        if ($type === CONNECTION_TYPE::MAIN) {
            $type = self::getPointDBTarget();
        }
        $this->initSetting();
        $this->setConnType($type);
    }

    function __destruct() {
        $this->close();
    }

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
        if ($this->use_cli_bridge) {
            $this->connected = true;
            return $this->connected;
        }
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
                throw new \RuntimeException(htmlentities($e['message'], ENT_QUOTES));
            }
        }
        return $this->connected;
    }

    public function close() {
        if (!$this->use_cli_bridge) {
            if ($this->stid) {
                oci_free_statement($this->stid);
            }
            if ($this->conn) {
                oci_close($this->conn);
            }
        }
        $this->stid = null;
        $this->conn = null;
        $this->connected = false;
        $this->cli_sql = '';
        $this->cli_binds = array();
        $this->cli_rows = array();
        $this->cli_cursor = 0;
    }

    public function parse($str) {
        // lazy connect here
        $this->connect();
        if ($this->use_cli_bridge) {
            $this->cli_sql = $str;
            $this->cli_binds = array();
            $this->cli_rows = array();
            $this->cli_cursor = 0;
            $this->stid = true;
            return;
        }
        // release previous resource
        if ($this->stid) {
            oci_free_statement($this->stid);
        }
        // Prepare the statement
        $this->stid = oci_parse($this->conn, $str);
        if (!$this->stid) {
            $e = oci_error($this->conn);
            
            Logger::getInstance()->error(__METHOD__.": ".$e['message']);
            throw new \RuntimeException(htmlentities($e['message'], ENT_QUOTES));
        }
    }

    public function execute() {
        if ($this->use_cli_bridge) {
            $php7_cli = Env::get('PHP7_CLI_PATH', getenv('PHP7_CLI_PATH') ?: 'C:\\AppServ\\php7\\php.exe');
            $worker_script = __DIR__ . DIRECTORY_SEPARATOR . 'ora_cli_worker.php';
            $payload = json_encode(array(
                'user'     => $this->user,
                'pass'     => $this->pass,
                'conn_str' => $this->getConnString(),
                'nls'      => $this->nls,
                'sql'      => $this->cli_sql,
                'binds'    => $this->cli_binds
            ));
            $descriptors = array(
                0 => array('pipe', 'r'),
                1 => array('pipe', 'w'),
                2 => array('pipe', 'w')
            );
            $cmd = '"' . $php7_cli . '" "' . $worker_script . '"';
            $proc = @proc_open($cmd, $descriptors, $pipes, __DIR__);
            if (!is_resource($proc)) {
                $msg = "Failed to launch Oracle CLI worker: {$php7_cli}";
                $this->cli_error = array('message' => $msg);
                Logger::getInstance()->error(__METHOD__.": ".$msg);
                throw new \RuntimeException(htmlentities($msg, ENT_QUOTES));
            }
            fwrite($pipes[0], $payload);
            fclose($pipes[0]);
            $stdout = stream_get_contents($pipes[1]);
            fclose($pipes[1]);
            $stderr = stream_get_contents($pipes[2]);
            fclose($pipes[2]);
            proc_close($proc);

            $res = json_decode($stdout, true);
            if (!is_array($res) || empty($res['ok'])) {
                $msg = $res['error']['message'] ?? (trim($stderr) !== '' ? trim($stderr) : 'Oracle CLI bridge execution failed');
                $this->cli_error = $res['error'] ?? array('message' => $msg);
                Logger::getInstance()->error(__METHOD__.": ".$msg);
                throw new \RuntimeException(htmlentities($msg, ENT_QUOTES));
            }

            $this->cli_rows = array();
            if (!empty($res['rows']) && is_array($res['rows'])) {
                foreach ($res['rows'] as $enc_row) {
                    $dec_row = array();
                    foreach ($enc_row as $k => $v) {
                        $dec_row[$k] = is_string($v) ? base64_decode($v) : $v;
                    }
                    $this->cli_rows[] = $dec_row;
                }
            }
            $this->cli_cursor = 0;
            return $this->stid;
        }
        // Perform the logic of the query
        $r = oci_execute($this->stid);
        if (!$r) {
            $e = oci_error($this->stid);
            
            Logger::getInstance()->error(__METHOD__.": ".$e['message']);
            throw new \RuntimeException(htmlentities($e['message'], ENT_QUOTES));
        }
        return $this->stid;
    }

    public function fetch($raw = false) {
        if ($this->use_cli_bridge) {
            $result = ($this->cli_cursor < count($this->cli_rows))
                ? $this->cli_rows[$this->cli_cursor++]
                : false;
        } else {
            $result = oci_fetch_assoc($this->stid); // oci_fetch_assoc is faster than oci_fetch_array
        }
        if ($raw) {
            return $result;
        }
        $convert = array();
        if (!empty($result)) {
            foreach ($result as $key=>$value) {
                $convert[$key] = empty($value) ? $value : UConverter::transcode($value, $this->web_encoding, $this->db_encoding);
            }
        }
        return $convert;
    }
    
    public function fetchAll($raw = false) {
        $this->numrows = 0;
        $results = array();
        if ($this->use_cli_bridge) {
            while ($this->cli_cursor < count($this->cli_rows)) {
                $row = $this->cli_rows[$this->cli_cursor++];
                foreach ($row as $key=>$value) {
                    if ($raw) {
                        $row[$key] = $value;
                    } else {
                        $row[$key] = empty($value) ? $value : UConverter::transcode($value, $this->web_encoding, $this->db_encoding);
                    }
                }
                $results[] = $row;
                $this->numrows++;
            }
            return $results;
        }
        while ($row = oci_fetch_assoc($this->stid)) {
            foreach ($row as $key=>$value) {
                if ($raw) {
                    $row[$key] = $value;
                } else {
                    $row[$key] = empty($value) ? $value : UConverter::transcode($value, $this->web_encoding, $this->db_encoding);
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
        if ($this->use_cli_bridge) {
            $this->cli_binds[$bind_var] = array(
                'b64' => is_string($real_var),
                'val' => is_string($real_var) ? base64_encode($real_var) : $real_var
            );
            return;
        }
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

    public function getError() {
        if ($this->use_cli_bridge) {
            return $this->cli_error;
        }
        if ($this->stid) {
            return oci_error($this->stid);
        }
        if ($this->conn) {
            return oci_error($this->conn);
        }
        return oci_error();
    }
}
