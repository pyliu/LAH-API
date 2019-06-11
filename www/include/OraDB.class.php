<?php
require_once("Config.inc.php");
// 地所DB的內碼為「American_America.US7ASCII」
putenv('NLS_LANG=American_America.US7ASCII');

abstract class CONNECTION_TYPE {
    const MAIN = 0;
    const L1HWEB = 1;
    const TWEB = 2;
}

class OraDB {
    private $L1HWEB_DB;
    private $TWEB_DB;
    private $MAIN_DB;
    private $user;
    private $pass;
    private $nls = "US7ASCII";
    private $conn;
    private $stid;
    private $numrows;
    private $CONN_TYPE;

    public function connect($type = CONNECTION_TYPE::MAIN) {
        $conn_str = $this->MAIN_DB;
        $this->CONN_TYPE = CONNECTION_TYPE::MAIN;
        if ($type == CONNECTION_TYPE::L1HWEB) {
            $conn_str = $this->L1HWEB_DB;
            $this->CONN_TYPE = CONNECTION_TYPE::L1HWEB;
        } else if ($type == CONNECTION_TYPE::TWEB) {
            $conn_str = $this->TWEB_DB;
            $this->CONN_TYPE = CONNECTION_TYPE::TWEB;
        }
        
        // clean previous connection first
        $this->close();
        
        $this->conn = oci_connect($this->user, $this->pass, $conn_str, $this->nls);
        if (!$this->conn) {
            $e = oci_error();
            trigger_error(htmlentities($e['message'], ENT_QUOTES), E_USER_ERROR);
        }
    }

    public function close() {
        if ($this->stid) {
            oci_free_statement($this->stid);
            $this->stid = null;
        }
          
        if ($this->conn) {
            oci_close($this->conn);
            $this->conn = null;
        }
    }

    public function parse($str) {
        // release previous resource
        if ($this->stid) {
            oci_free_statement($this->stid);
        }
        // Prepare the statement
        $this->stid = oci_parse($this->conn, $str);
        if (!$this->stid) {
            $e = oci_error($this->conn);
            trigger_error(htmlentities($e['message'], ENT_QUOTES), E_USER_ERROR);
        }
    }

    public function execute() {
        // Perform the logic of the query
        $r = oci_execute($this->stid);
        if (!$r) {
            $e = oci_error($this->stid);
            trigger_error(htmlentities($e['message'], ENT_QUOTES), E_USER_ERROR);
        }

        return $this->stid;
    }

	public function fetch() {
        $result = oci_fetch_assoc($this->stid); // oci_fetch_assoc is faster than oci_fetch_array
        $convert = array();
        if (!empty($result)) {
            foreach ($result as $key=>$value) {
                $convert[$key] = empty($value) ? $value : iconv("big5", "utf-8", $value);
            }
        }
        return $convert;
    }
    
    public function fetchAll() {
        $this->numrows = 0;
        $results = array();
        while ($row = oci_fetch_assoc($this->stid)) {
            foreach ($row as $key=>$value) {
                $row[$key] = empty($value) ? $value : iconv("big5", "utf-8", $value);
            }
            $results[] = $row;
            $this->numrows++;
        }
        return $results;
    }

    public function getLatestQueryCount() {
        return $this->numrows;
    }

    public function getSTID() {
        return $this->stid;
    }
    
    public function bind($bind_var, $real_var) {
        oci_bind_by_name($this->stid, $bind_var, $real_var);
    }

    function __construct() {
        
        $this->L1HWEB_DB = SYSTEM_CONFIG["ORA_DB_L1HWEB"];
        $this->TWEB_DB = SYSTEM_CONFIG["ORA_DB_TWEB"];
        $this->MAIN_DB = SYSTEM_CONFIG["ORA_DB_MAIN"];
        $this->user = SYSTEM_CONFIG["ORA_DB_USER"];
        $this->pass = SYSTEM_CONFIG["ORA_DB_PASS"];

        $this->connect(CONNECTION_TYPE::MAIN);
    }

    function __destruct() {
        $this->close();
    }

    // get user name mapping
    static public function getDBUserList($refresh = false) {
        $tmp_path = sys_get_temp_dir();
        $file     = $tmp_path . "\\tyland_user.map";
        $time = filemtime($file);
        
        if ($refresh === true || $time === false || mktime() - $time > 86400) {
            $db = SYSTEM_CONFIG["ORA_DB_MAIN"];
            
            $conn = oci_connect(SYSTEM_CONFIG["ORA_DB_USER"], SYSTEM_CONFIG["ORA_DB_PASS"], $db, "US7ASCII");
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
            
            $result = array();
            while ($row = oci_fetch_array($stid, OCI_ASSOC + OCI_RETURN_NULLS)) {
                $result[$row["USER_ID"]] = iconv("big-5", "utf-8", $row["USER_NAME"]);
            }
            
            if ($stid) {
                oci_free_statement($stid);
            }
            if ($conn) {
                oci_close($conn);
            }
            
            // cache
            $content = serialize($result);
            file_put_contents($file, $content);
            
            return $result;
        }
        
        $content = @file_get_contents($file);
        return unserialize($content);
    }
}
?>
