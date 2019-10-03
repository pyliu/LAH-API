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
            $require_keys = array(
                "MS_DB_UID",
                "MS_DB_PWD",
                "MS_DB_DATABASE",
                "MS_DB_SVR",
                "MS_DB_CHARSET"
            );
            $argu_keys = array_keys($conn_info);
            foreach ($require_keys as $key) {
                if (!in_array($key, $argu_keys)) {
                    die(__FILE__.": MSDB connection needs ${key} value for connection.");
                }
            }
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
	 * Is a connection to the database exists?
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
    /**
     * Return lastest error return null if no error occurs
     * @return null/array
     */
    public function getLastError() {
        return sqlsrv_errors();
    }
}
?>
