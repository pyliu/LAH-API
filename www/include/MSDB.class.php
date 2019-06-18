<?php
require_once("init.php");
require_once("SQLSRV_DataBase.class.php");

class MSDB {
    private $sqlsrv_db;
    private $conn;
    private $svr_name;
    private $conn_info;
    private $last_result;


    public function connect() {
        $this->conn = sqlsrv_connect($this->svr_name, $this->conn_info);
        if(!$this->conn) {
            die( print_r( sqlsrv_errors(), true));
        }
    }

    public function close() {
        sqlsrv_close($this->conn);
    }

    public function query($sql) {
        return sqlsrv_query($this->conn, $sql);
    }

	public function fetch() {
        $this->last_result = $this->query("SELECT TOP 10 * FROM [dbo].[Message]");
        if($this->last_result === false) {
            die( print_r( sqlsrv_errors(), true) );
        } else {
            return sqlsrv_fetch_array($this->last_result, SQLSRV_FETCH_ASSOC);
        }
    }
    
    public function fetchAll() {
    }

    function __construct() {
        $this->sqlsrv_db = new SQLSRV_DataBase(SYSTEM_CONFIG["MS_DB_UID"], SYSTEM_CONFIG["MS_DB_PWD"], SYSTEM_CONFIG["MS_DB_DATABASE"], SYSTEM_CONFIG["MS_DB_SVR"]);


        $this->svr_name = SYSTEM_CONFIG["MS_DB_SVR"];
        $this->conn_info = array(
            "Database"=> SYSTEM_CONFIG["MS_DB_DATABASE"],
            "UID"=> SYSTEM_CONFIG["MS_DB_UID"],
            "PWD"=> SYSTEM_CONFIG["MS_DB_PWD"],
            "CharacterSet" => SYSTEM_CONFIG["MS_DB_CHARSET"]
        );
        $this->connect();
    }

    function __destruct() {
        $this->close();
    }

    public function isConnected() {
        return $this->sqlsrv_db->is_connected;
    }
}
?>
