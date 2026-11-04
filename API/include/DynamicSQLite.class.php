<?php
/**
 * @author Christian Tang
 */

class DynamicSQLite {
    private $database;
    private $databaseName;

    public function __construct($databaseName) {
        $this->databaseName = $databaseName;
    }

    public function initDB() {
        try {
            $this->database = new PDO('sqlite:' . $this->databaseName);
            $this->database->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->database->setAttribute(PDO::ATTR_TIMEOUT, 10);
            $this->database->exec("PRAGMA busy_timeout = 10000;");
            $this->database->exec("PRAGMA journal_mode = WAL;");
        } catch (PDOException $e) {
            if (class_exists('Logger')) {
                Logger::getInstance()->error(__METHOD__ . ": 連線 SQLite 資料庫失敗 (" . $this->databaseName . "): " . $e->getMessage());
            }
        }
    }

    public function createTableBySQL($str) {
        if (!$this->database) {
            return false;
        }
        try {
            return $this->database->exec($str);
        } catch (PDOException $e) {
            if (class_exists('Logger')) {
                Logger::getInstance()->warning(__METHOD__ . ": 執行 SQL 失敗 (" . $this->databaseName . "): " . $e->getMessage());
            }
            return false;
        }
    }

    function createTable($table) {
        if (!$this->database) {
            return false;
        }
        try {
            $sTable = 'CREATE TABLE IF NOT EXISTS ' . $table->name . ' (';
            $sFields = '';
            foreach ($table->fields as $f) {
                if ($sFields != '') {
                    $sFields .= ',';
                }
                $sFields .= $f->name . ' ' . $f->type;
            }
            $sFields .= ')';
            $sTable .= $sFields;

            return $this->database->exec($sTable);
        } catch (PDOException $e) {
            if (class_exists('Logger')) {
                Logger::getInstance()->warning(__METHOD__ . ": 建表失敗 (" . $table->name . "): " . $e->getMessage());
            }
            return false;
        }
    }

    function insert($table, $array) {
        if (!$this->database) {
            return false;
        }
        try {
            $sColumns = implode(',', array_keys($array));
            $sValues = implode(',', array_fill(0, count($array), '?'));

            $stmt = $this->database->prepare("INSERT INTO $table ({$sColumns}) VALUES ({$sValues})");
            return $stmt->execute(array_values($array));
        } catch (PDOException $e) {
            if (class_exists('Logger')) {
                Logger::getInstance()->error(__METHOD__ . ": 寫入資料失敗 (" . $this->databaseName . "): " . $e->getMessage());
            }
            return false;
        }
    }
}

class SQLiteTable {
    var $name;
    var $fields;

    public function __construct($tableName) {
        $this->name = $tableName;
    }

    public function addField($fieldName, $fieldType) {
        $field = new SQLiteField($fieldName, $fieldType);
        $this->fields[] = $field;
    }
}

class SQLiteField {
    var $name;
    var $type;

    public function __construct($name, $type) {
        $this->name = $name;
        $this->type = $type;
    }
}
