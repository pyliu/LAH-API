<?php
require_once("init.php");
require_once("DynamicSQLite.class.php");
require_once("System.class.php");

class Checklist {
    private const CHECKLIST_SQLITE_DB = DB_DIR.DIRECTORY_SEPARATOR."checklist.db";

    private $db = null;
    private $config = null;

    private function getDB() {
        if ($this->db === null) {
            $dsl = new DynamicSQLite(CHECKLIST_SQLITE_DB);
            $dsl->initDB();
            $dsl->createTableBySQL('
                CREATE TABLE IF NOT EXISTS "daily" (
                    "date"	TEXT,
                    "target"	TEXT,
                    "screenshot"	TEXT,
                    "note"	TEXT,
                    PRIMARY KEY("date","target")
                )
            ');
            $this->db = new SQLite3(CHECKLIST_SQLITE_DB);
        }
        return $this->db;
    }

    private function getSystemConfig() {
        if ($this->config === null) {
            $this->config = new System();
        }
        return $this->config;
    }

    function __construct() { }

    function __destruct() { }


}
