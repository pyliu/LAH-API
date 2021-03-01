<?php
require_once("init.php");
require_once("SQLSRV_DataBase.class.php");
require_once("System.class.php");

class MSDB {
    private $dbo;
    private $system;

    private function prepareVal($value){
        if ( null === $value ) {
			return 'NULL';
		} elseif ( ctype_digit( str_replace( array( '.', '-' ), '', $value ) ) && substr_count( $value, '.' ) < 2 ) {
			return $value;
		} else {
            // can't use utf8_decode it will cause 中文亂碼！
			return "N'" . addslashes( $value ) . "'";
		}
    }
    
    function __construct($conn_info = array()) {
        $this->system = System::getInstance();
        if ($this->system->isMockMode() === true) return;
        
        if (empty($conn_info)) {
            if ($this->system->get("MS_DB_SVR") == "xxx.xxx.xxx.xxx") {
                die(__FILE__.": MSDB SVR not configured. Current: ".$this->system->get("MS_DB_SVR"));
            }
            // default connect via config
            $this->dbo = new SQLSRV_DataBase(
                $this->system->get("MS_DB_UID"),
                $this->system->get("MS_DB_PWD"),
                $this->system->get("MS_DB_DATABASE"),
                $this->system->get("MS_DB_SVR"),
                $this->system->get("MS_DB_CHARSET")
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
            if ($conn_info["MS_DB_SVR"] == "xxx.xxx.xxx.xxx") {
                die(__FILE__.": MSDB SVR not configured. Current: ".$conn_info["MS_DB_SVR"]);
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

    public function fetch($sql) {
        return $this->dbo->get_row($sql, "array");
    }
    
    public function fetchAll($sql) {
        return $this->dbo->get_results($sql, "array");
    }
    /**
	 * Insert wrapper function
	 * @return int
	 */
    public function insert($table, $data) {
        $fields = array();
        $values = array();

        foreach( $data AS $field => $value ) {
            $fields[] = $table . '.' . trim( $field );
            $values[] =$this->prepareVal( trim( $value ) );
        }

        $sql = "INSERT INTO " . $table . " ( " . implode(',', $fields) . " ) VALUES ( " . implode(',', $values) . " )";
        $result = $this->dbo->query( $sql, false );

        return $this->dbo->last_insert_id();
    }
    /**
	 * Update wrapper function
	 * @return void
	 */
    public function update($table, $what, $where) {
        $set   = '';
        $check = '';

        foreach( $what AS $field => $value ) {
            $field = trim( $field );
            $value = trim( $value );

            if ( ! empty( $set ) ) {
                $set .= ', ';
            }
            $set .=  $table . '.' . $field . ' = ';

            $set .= $this->prepareVal( $value );
        }

        foreach( $where AS $field => $value ) {
            $check .= ' AND ' . $table . '.' . $field;
            if ( null === $value ) {
                $check .= ' IS NULL';
            }
            elseif ( ctype_digit( str_replace( array( '.', '-' ), '', $value ) ) && substr_count( $value, '.' ) < 2 ) {
                $check .= ' = ' . $value;
            }
            else {
                $check .= " = '" . addslashes( $value ) . "'";
            }
        }

        $sql = "UPDATE " . $table . " SET " . $set . " WHERE 1 = 1 " . $check;
        $this->dbo->query( $sql, false );
    }

    public function delete($table, $where) {
        if (empty($where)) return false;
        $check = '';
        foreach( $where AS $field => $value ) {
            $check .= ' AND ' . $table . '.' . $field;
            if ( null === $value ) {
                $check .= ' IS NULL';
            }
            elseif ( ctype_digit( str_replace( array( '.', '-' ), '', $value ) ) && substr_count( $value, '.' ) < 2 ) {
                $check .= ' = ' . $value;
            }
            else {
                $check .= " = '" . addslashes( $value ) . "'";
            }
        }
        $sql = "DELETE FROM " . $table . " WHERE 1 = 1 " . $check;
        return $this->dbo->query( $sql, false );
    }
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
    /**
     * Return last row number affected
     * @return int
     */
    public function getLastNumRows() {
        return $this->dbo->num_rows;
    }
    /**
     * Return does any rows affected since last query
	 * @return bool
	 */
    public function hasRows() {
        return $this->dbo->has_rows;
    }
}
