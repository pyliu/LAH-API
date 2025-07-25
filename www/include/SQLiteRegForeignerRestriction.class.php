<?php
require_once('init.php');
require_once('SQLiteDBFactory.class.php');

class SQLiteRegForeignerRestriction {
    private $db;

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
        $this->db = new SQLite3(SQLiteDBFactory::getRegForeignerRestrictionDB());
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

    public function getLastInsertedId() {
        return $this->db->lastInsertRowID();
    }

    public function exists($pkey) {
        return $this->db->querySingle("SELECT pkey from reg_foreigner_restriction WHERE pkey = '$pkey'");
    }

    public function getOne($pkey) {
        Logger::getInstance()->info(__METHOD__.": 取得 $pkey 資料");
        if($stmt = $this->db->prepare('SELECT * from reg_foreigner_restriction WHERE pkey = :bv_pkey')) {
            $stmt->bindParam(':bv_pkey', $pkey);
            $result = $this->prepareArray($stmt);
            return count($result) > 0 ? $result[0] : false;
        }
        Logger::getInstance()->error(__METHOD__.": 無法取得 $pkey 資料！ (".SQLiteDBFactory::getRegForeignerRestrictionDB().")");
        return false;
    }

    public function add($post) {
        $id = $this->exists($post['pkey']);
        if ($id) {
            Logger::getInstance()->warning(__METHOD__.": 外國人資料已存在，將更新它。(id: $id)");
            return $this->update($post);
        } else {
            $stm = $this->db->prepare("
                INSERT INTO reg_foreigner_restriction ('pkey', 'nation', 'reg_date', 'reg_caseno', 'transfer_date', 'transfer_caseno', 'transfer_local_date', 'transfer_local_principle', 'restore_local_date', 'use_partition', 'logout', 'control', 'note')
                VALUES (:pkey, :nation, :reg_date, :reg_caseno, :transfer_date, :transfer_caseno, :transfer_local_date, :transfer_local_principle, :restore_local_date, :use_partition, :logout, :control, :note)
            ");
            $stm->bindParam(':pkey', $post['pkey']);
            $stm->bindParam(':nation', $post['nation']);
            $stm->bindParam(':reg_date', $post['reg_date']);
            $stm->bindParam(':reg_caseno', $post['reg_caseno']);
            $stm->bindParam(':transfer_date', $post['transfer_date']);
            $stm->bindParam(':transfer_caseno', $post['transfer_caseno']);
            $stm->bindParam(':transfer_local_date', $post['transfer_local_date']);
            $stm->bindParam(':transfer_local_principle', $post['transfer_local_principle']);
            $stm->bindParam(':restore_local_date', $post['restore_local_date']);
            $stm->bindParam(':use_partition', $post['use_partition']);
            $stm->bindParam(':logout', $post['logout']);
            $stm->bindParam(':control', $post['control']);
            $stm->bindParam(':note', $post['note']);
            return $stm->execute() === FALSE ? false : $this->getLastInsertedId();
        }
        return false;
    }

    public function update($post) {
        $pkey = $post['pkey'];
        Logger::getInstance()->warning(__METHOD__.": 更新外國人資料。(pkey: $pkey)");
        $stm = $this->db->prepare("
            UPDATE reg_foreigner_restriction SET
                nation = :nation,
                reg_date = :reg_date,
                reg_caseno = :reg_caseno,
                transfer_date = :transfer_date,
                transfer_caseno = :transfer_caseno,
                transfer_local_date = :transfer_local_date,
                transfer_local_principle = :transfer_local_principle,
                restore_local_date = :restore_local_date,
                use_partition = :use_partition,
                logout = :logout,
                control = :control,
                note = :note
            WHERE pkey = :pkey
        ");
        $stm->bindParam(':pkey', $pkey);
        $stm->bindParam(':nation', $post['nation']);
        $stm->bindParam(':reg_date', $post['reg_date']);
        $stm->bindParam(':reg_caseno', $post['reg_caseno']);
        $stm->bindParam(':transfer_date', $post['transfer_date']);
        $stm->bindParam(':transfer_caseno', $post['transfer_caseno']);
        $stm->bindParam(':transfer_local_date', $post['transfer_local_date']);
        $stm->bindParam(':transfer_local_principle', $post['transfer_local_principle']);
        $stm->bindParam(':restore_local_date', $post['restore_local_date']);
        $stm->bindParam(':use_partition', $post['use_partition']);
        $stm->bindParam(':logout', $post['logout']);
        $stm->bindParam(':control', $post['control']);
        $stm->bindParam(':note', $post['note']);
        return $stm->execute() !== FALSE;
    }

    public function delete($pkey) {
        $stm = $this->db->prepare("DELETE FROM reg_foreigner_restriction WHERE pkey = :bv_pkey");
        $stm->bindParam(':bv_pkey', $pkey);
        return $stm->execute() !== FALSE;
    }
}
