<?php
require_once("init.php");
require_once("SQLSRV_DataBase.class.php");

class MSDB {
    private $dbo;

	public function fetch($sql) {
        return $this->dbo->get_row($sql, "array");
    }
    
    public function fetchAll($sql) {
        return $this->dbo->get_results($sql, "array");
    }

    function __construct($conn_info = array()) {
        if (empty($conn_info)) {
            // default connect via config
            $this->dbo = new SQLSRV_DataBase(
                SYSTEM_CONFIG["MS_DB_UID"],
                SYSTEM_CONFIG["MS_DB_PWD"],
                SYSTEM_CONFIG["MS_DB_DATABASE"],
                SYSTEM_CONFIG["MS_DB_SVR"],
                SYSTEM_CONFIG["MS_DB_CHARSET"]
            );
        } else {
            $this->dbo = new SQLSRV_DataBase(
                $conn_info["MS_DB_UID"],
                $conn_info["MS_DB_PWD"],
                $conn_info["MS_DB_DATABASE"],
                $conn_info["MS_DB_SVR"],
                $conn_info["MS_DB_CHARSET"]
            );
        }
    }

    function __destruct() {}
    /**
	 * Return the last ran query in its entirety
	 * @return string
	 */
    public function getLastQuery() {
        return $this->dbo->get_last_query();
    }
    /**
	 * If a connection ot the database exists
	 * @return bool
	 */
    public function isConnected() {
        return $this->dbo->is_connected;
    }
    /**
	 * @return array|bool
	 */
    public function hasError() {
        return $this->dbo->hasError();
    }
}
?>
