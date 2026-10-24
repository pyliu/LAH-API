<?php
require_once("init.php");
require_once("DynamicSQLite.class.php");
require_once("System.class.php");

class Checklist {
    private function getDbPath() { return (class_exists("SQLiteDBFactory") ? SQLiteDBFactory::getDbFolder() : DB_DIR).DIRECTORY_SEPARATOR."checklist.db"; }

    private $db = null;

    private function getDB() {
        if ($this->db === null) {
            $dsl = new DynamicSQLite($this->getDbPath());
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
            $this->db = new SQLite3($this->getDbPath());
            $this->db->busyTimeout(5000);
            $this->db->exec("PRAGMA journal_mode = WAL");
        }
        return $this->db;
    }

    function __construct() { }

    function __destruct() { }

    public function debug() {
        $this->getDB();
    }

}
