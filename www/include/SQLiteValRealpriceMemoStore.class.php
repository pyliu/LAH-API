<?php
require_once('init.php');
require_once('SQLiteDBFactory.class.php');
require_once('System.class.php');

class SQLiteValRealpriceMemoStore {
    private $db;
    private $tbl_name = 'val_realprice_memo_store';

    private function bindParams(&$stm, &$row) {
        if ($stm === false) {
            Logger::getInstance()->error(__METHOD__.": bindUserParams because of \$stm is false.");
            return false;
        }
        // 序號
        $stm->bindParam(':bv_case_no', $row['case_no']);
        // 申報日期
        $stm->bindParam(':bv_declare_date', $row['declare_date']);
        // 申報備註
        $stm->bindParam(':bv_declare_note', $row['declare_note']);
        // store timestamp
        $stm->bindValue(':bv_timestamp', time());

        return true;
    }

    private function prepareArray(&$stmt) {
        $result = $stmt->execute();
        $return = [];
        if ($result) {
            while($row = $result->fetchArray(SQLITE3_ASSOC)) {
                $return[] = $row;
            }
        } else {
            Logger::getInstance()->warning(__CLASS__."::".__METHOD__.": execute SQL unsuccessfully.");
        }
        return $return;
    }

    function __construct() {
        $this->db = new SQLite3(SQLiteDBFactory::getValRealpriceMemoStoreDB());
        // 對於高併發的讀寫場景，可以考慮將 SQLite 的日誌模式切換為「預寫式日誌 (Write-Ahead Logging)」。它對併發的處理更好，可以減少鎖定問題
        $this->db->exec("PRAGMA journal_mode = WAL");
        $this->db->exec("PRAGMA cache_size = 100000");
        $this->db->exec("PRAGMA temp_store = MEMORY");
        $this->db->exec("BEGIN TRANSACTION");
    }

    function __destruct() {
        $this->db->exec("END TRANSACTION");
        $this->db->close();
    }

    public function exists($case_no) {
        $ret = $this->db->querySingle("SELECT case_no from ".$this->tbl_name." WHERE case_no = '".trim($case_no)."'");
        return !empty($ret);
    }

    public function getValRealpriceMemoRecord($case_no) {
        if($stmt = $this->db->prepare("SELECT * from ".$this->tbl_name." WHERE case_no = :bv_case_no")) {
            $stmt->bindParam(':bv_case_no', $case_no);
            return $this->prepareArray($stmt);
        } else {
            Logger::getInstance()->error(__METHOD__.": 取得 $case_no 申報紀錄資料失敗！ (".SQLiteDBFactory::getValRealpriceMemoStoreDB().")");
        }
        return false;
    }

    public function replace(&$row) {
        $stm = $this->db->prepare("
            REPLACE INTO ".$this->tbl_name." ('case_no', 'declare_date', 'declare_note', 'timestamp')
            VALUES (:bv_case_no, :bv_declare_date, :bv_declare_note, :bv_timestamp)
        ");
        if ($this->bindParams($stm, $row)) {
            return $stm->execute() === FALSE ? false : true;
        }
        return false;
    }
}
