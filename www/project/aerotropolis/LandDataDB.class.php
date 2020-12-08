<?php
require_once('init.php');
require_once(ROOT_DIR.DIRECTORY_SEPARATOR.'DynamicSQLite.class.php');

class LandDataDB {
    private $db;

    private function getLandDataDB() {
        $db_path = DB_PATH.DIRECTORY_SEPARATOR.'land_data.db';
        $sqlite = new DynamicSQLite($db_path);
        $sqlite->initDB();
        $sqlite->createTableBySQL('
            CREATE TABLE IF NOT EXISTS "data_mapping" (
                "code"	TEXT NOT NULL,
                "number"	TEXT NOT NULL,
                "name"	TEXT NOT NULL,
                "content"	TEXT,
                PRIMARY KEY("code","number")
            )
        ');
        $sqlite->createTableBySQL('
            CREATE INDEX IF NOT EXISTS "name" ON "data_mapping" (
                "name"
            )
        ');
        $sqlite->createTableBySQL('
            CREATE TABLE IF NOT EXISTS "people_data_mapping" (
                "houshold"	TEXT NOT NULL,
                "pid"	TEXT NOT NULL,
                "pname"	TEXT NOT NULL,
                "owned_number"	TEXT NOT NULL,
                PRIMARY KEY("pid","houshold","owned_number")
            )
        ');
        $sqlite->createTableBySQL('
            CREATE INDEX IF NOT EXISTS "pname" ON "people_data_mapping" (
                "pname"
            )
        ');
        return $db_path;
    }

    function __construct() {
        $path = $this->getLandDataDB();
        $this->db = new SQLite3($path);
        $this->db->exec("PRAGMA cache_size = 100000");
        $this->db->exec("PRAGMA temp_store = MEMORY");
        $this->db->exec("BEGIN TRANSACTION");
    }

    function __destruct() {
        $this->db->exec("END TRANSACTION");
        $this->db->close();
    }

    public function addLandData($code, $name, $number, $content) {
        $stm = $this->db->prepare("REPLACE INTO data_mapping ('code', 'number', 'name', 'content') VALUES (:code, :number, :name, :content)");
        $stm->bindParam(':code', $code);
        $stm->bindParam(':number', $number);
        $stm->bindParam(':name', $name);
        $stm->bindParam(':content', $content);
        return $stm->execute() === FALSE ? false : true;
    }

    public function removeLandData($code) {
        $stm = $this->db->prepare("DELETE FROM data_mapping WHERE code = :code");
        $stm->bindParam(':code', $code);
        return $stm->execute() === FALSE ? false : true;
    }

    public function addPeopleMapping($household, $pid, $pname, $owned_number) {
        $stm = $this->db->prepare("REPLACE INTO people_data_mapping ('houshold', 'pid', 'pname', 'owned_number') VALUES (:household, :pid, :pname, :owned_number)");
        $stm->bindParam(':household', $household);
        $stm->bindParam(':pid', $pid);
        $stm->bindParam(':pname', $pname);
        $stm->bindParam(':owned_number', $owned_number);
        return $stm->execute() === FALSE ? false : true;
    }
}
