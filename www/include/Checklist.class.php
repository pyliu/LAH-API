<?php
require_once("init.php");
require_once("DynamicSQLite.class.php");
require_once("System.class.php");

class Checklist {
    private const CHECKLIST_SQLITE_DB = DB_DIR.DIRECTORY_SEPARATOR."checklist.db";

    private $db = null;

    private function getDB() {
        if ($this->db === null) {
            $dsl = new DynamicSQLite(self::CHECKLIST_SQLITE_DB);
            $dsl->initDB();
            $dsl->createTableBySQL('
                CREATE TABLE IF NOT EXISTS "daily" (
                    "date"	TEXT,
                    "track_item_id"	INTEGER,
                    "screenshot"	TEXT,
                    "note"	TEXT,
                    PRIMARY KEY("date","track_item_id")
                )
            ');
            $dsl->createTableBySQL('
                CREATE TABLE IF NOT EXISTS "track_item" (
                    "id"	INTEGER,
                    "name"	TEXT NOT NULL,
                    "desc"	TEXT,
                    PRIMARY KEY("id" AUTOINCREMENT)
                )
            ');
            $this->db = new SQLite3(self::CHECKLIST_SQLITE_DB);
        }
        return $this->db;
    }

    function __construct() { }

    function __destruct() { }

    public function debug() {
        $this->getDB();
    }

}
