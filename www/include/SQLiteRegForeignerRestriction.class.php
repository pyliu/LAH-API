<?php
require_once('init.php');
require_once('SQLiteDBFactory.class.php');

class SQLiteRegForeignerRestriction {
    private $db;

    private function prepareArray(&$stmt) {
        $result = $stmt->execute();
        $return = [];
        while($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $return[] = $row;
        }
        return $return;
    }

    function __construct() {
        $this->db = new SQLite3(SQLiteDBFactory::getRegForeignerRestrictionDB());
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

    public function exists($cid) {
        return $this->db->querySingle("SELECT cert_no from reg_foreigner_restriction WHERE cert_no = '$cid'");
    }

    public function getOne($cid) {
        Logger::getInstance()->info(__METHOD__.": 取得 $cid 資料");
        if($stmt = $this->db->prepare('SELECT * from reg_foreigner_restriction WHERE cert_no = :bv_cid')) {
            $stmt->bindParam(':bv_cid', $cid);
            $result = $this->prepareArray($stmt);
            return count($result) > 0 ? $result[0] : false;
        }
        Logger::getInstance()->error(__METHOD__.": 無法取得 $cid 資料！ (".SQLiteDBFactory::getRegForeignerRestrictionDB().")");
        return false;
    }

    public function add($post) {
        $id = $this->exists($post['cert_no']);
        if ($id) {
            Logger::getInstance()->warning(__METHOD__.": 外國人資料已存在，將更新它。(id: $id)");
            return $this->update($post);
        } else {
            $stm = $this->db->prepare("
                INSERT INTO reg_foreigner_restriction ('cert_no', 'nation', 'reg_date', 'reg_caseno', 'transfer_date', 'transfer_caseno', 'transfer_local_date', 'transfer_local_principle', 'restore_local_date', 'use_partition', 'note')
                VALUES (:cert_no, :nation, :reg_date, :reg_caseno, :transfer_date, :transfer_caseno, :transfer_local_date, :transfer_local_principle, :restore_local_date, :use_partition, :note)
            ");
            $stm->bindParam(':cert_no', $post['cert_no']);
            $stm->bindParam(':nation', $post['nation']);
            $stm->bindParam(':reg_date', $post['reg_date']);
            $stm->bindParam(':reg_caseno', $post['reg_caseno']);
            $stm->bindParam(':transfer_date', $post['transfer_date']);
            $stm->bindParam(':transfer_caseno', $post['transfer_caseno']);
            $stm->bindParam(':transfer_local_date', $post['transfer_local_date']);
            $stm->bindParam(':transfer_local_principle', $post['transfer_local_principle']);
            $stm->bindParam(':restore_local_date', $post['restore_local_date']);
            $stm->bindParam(':use_partition', $post['use_partition']);
            $stm->bindParam(':note', $post['note']);
            return $stm->execute() === FALSE ? false : $this->getLastInsertedId();
        }
        return false;
    }

    public function update($post) {
        $cert_no = $post['cert_no'];
        Logger::getInstance()->warning(__METHOD__.": 更新外國人資料。(cert_no: $cert_no)");
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
                note = :note
            WHERE cert_no = :cert_no
        ");
        $stm->bindParam(':cert_no', $cert_no);
        $stm->bindParam(':nation', $post['nation']);
        $stm->bindParam(':reg_date', $post['reg_date']);
        $stm->bindParam(':reg_caseno', $post['reg_caseno']);
        $stm->bindParam(':transfer_date', $post['transfer_date']);
        $stm->bindParam(':transfer_caseno', $post['transfer_caseno']);
        $stm->bindParam(':transfer_local_date', $post['transfer_local_date']);
        $stm->bindParam(':transfer_local_principle', $post['transfer_local_principle']);
        $stm->bindParam(':restore_local_date', $post['restore_local_date']);
        $stm->bindParam(':use_partition', $post['use_partition']);
        $stm->bindParam(':note', $post['note']);
        return $stm->execute() !== FALSE;
    }

    public function delete($cid) {
        $stm = $this->db->prepare("DELETE FROM reg_foreigner_restriction WHERE cert_no = :bv_cid");
        $stm->bindParam(':bv_cid', $cid);
        return $stm->execute() !== FALSE;
    }
}
