<?php
require_once('init.php');
require_once('SQLiteDBFactory.class.php');

class SQLiteRegAddressUndisclosed {
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
        $this->db = new SQLite3(SQLiteDBFactory::getRegAddressUndisclosedDB());
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

    public function exists($applicant) {
        if ($stmt = $this->db->prepare('SELECT id FROM reg_address_undisclosed WHERE applicant = :bv_applicant')) {
            $stmt->bindParam(':bv_applicant', $applicant);
            $result = $this->prepareArray($stmt);
            return count($result) > 0 ? $result[0]['id'] : false;
        }
        return false;
    }

    public function getOne($id) {
        Logger::getInstance()->info(__METHOD__.": 取得 $id 資料");
        if($stmt = $this->db->prepare('SELECT * FROM reg_address_undisclosed WHERE id = :bv_id')) {
            $stmt->bindParam(':bv_id', $id);
            $result = $this->prepareArray($stmt);
            return count($result) > 0 ? $result[0] : false;
        }
        Logger::getInstance()->error(__METHOD__.": 無法取得 $id 資料！ (".SQLiteDBFactory::getRegAddressUndisclosedDB().")");
        return false;
    }

    public function getAll() {
        Logger::getInstance()->info(__METHOD__.": 取得全部資料");
        if ($stmt = $this->db->prepare('SELECT * FROM reg_address_undisclosed ORDER BY modifytime DESC')) {
            return $this->prepareArray($stmt);
        }
        Logger::getInstance()->error(__METHOD__.": 無法取得全部資料！ (".SQLiteDBFactory::getRegAddressUndisclosedDB().")");
        return array();
    }

    public function search($st, $ed, $keyword = '') {
        $st_date = date("Y-m-d", $st);
        $ed_date = date("Y-m-d", $ed);
        Logger::getInstance()->info(__METHOD__.": 搜尋 $st_date ~ $ed_date 區間資料，關鍵字: $keyword");
        $result = array();
        if (empty($keyword)) {
            if($stmt = $this->db->prepare('SELECT * from reg_address_undisclosed WHERE createtime BETWEEN :bv_createtime_st AND :bv_createtime_ed order by modifytime DESC')) {
                $stmt->bindParam(':bv_createtime_st', $st);
                // 在結束日的那天內都算，所以加上 86399 秒
                $stmt->bindValue(':bv_createtime_ed', $ed + 86399);
                $result = $this->prepareArray($stmt);
            } else {
                Logger::getInstance()->error(__METHOD__.": 無法取得 $st_date ~ $ed_date 資料！ (".SQLiteDBFactory::getRegAddressUndisclosedDB().")");
            }
        } else {
            if($stmt = $this->db->prepare('SELECT * FROM reg_address_undisclosed WHERE createtime BETWEEN :bv_createtime_st AND :bv_createtime_ed AND (note LIKE :bv_keyword OR applicant LIKE :bv_keyword OR receiving_caseno LIKE :bv_keyword) ORDER BY modifytime DESC')) {
                $stmt->bindParam(':bv_createtime_st', $st);
                // 在結束日的那天內都算，所以加上 86399 秒
                $stmt->bindValue(':bv_createtime_ed', $ed + 86399);
                $stmt->bindValue(':bv_keyword', "%$keyword%");
                $result = $this->prepareArray($stmt);
            } else {
                Logger::getInstance()->error(__METHOD__.": 無法取得 $st_date ~ $ed_date 內含 %$keyword% 資料！ (".SQLiteDBFactory::getRegAddressUndisclosedDB().")");
            }
        }
        return $result;
    }

    public function add($post) {
        $stm = $this->db->prepare("
            INSERT INTO reg_address_undisclosed ('applicant', 'receiving_type', 'receiving_caseno', 'note', 'createtime', 'modifytime')
            VALUES (:applicant, :receiving_type, :receiving_caseno, :note, :createtime, :modifytime)
        ");
        $stm->bindParam(':applicant', $post['applicant']);
        $stm->bindValue(':receiving_type', isset($post['receiving_type']) ? (int)$post['receiving_type'] : 0);
        $stm->bindValue(':receiving_caseno', isset($post['receiving_caseno']) ? $post['receiving_caseno'] : '');
        $stm->bindParam(':note', $post['note']);
        $stm->bindValue(':createtime', time());
        $stm->bindValue(':modifytime', time());

        return $stm->execute() === FALSE ? false : $this->getLastInsertedId();
    }

    public function update($post) {
        $id = $post['id'];
        // 先取出原記錄，以便支援部分欄位更新（未傳入的欄位保留原值）
        $record = $this->getOne($id);
        if ($record === false) {
            Logger::getInstance()->error(__METHOD__.": 找不到 id=$id 的資料，無法更新。");
            return false;
        }
        $applicant       = isset($post['applicant'])        ? $post['applicant']               : $record['applicant'];
        $receiving_type  = isset($post['receiving_type'])   ? (int)$post['receiving_type']      : (int)$record['receiving_type'];
        $receiving_caseno = isset($post['receiving_caseno']) ? $post['receiving_caseno']         : $record['receiving_caseno'];
        $note            = isset($post['note'])             ? $post['note']                    : $record['note'];
        Logger::getInstance()->warning(__METHOD__.": 更新地址隱匿資料。(id: $id, applicant: $applicant)");
        $stm = $this->db->prepare("UPDATE reg_address_undisclosed SET applicant = :applicant, receiving_type = :receiving_type, receiving_caseno = :receiving_caseno, note = :note, modifytime = :modifytime WHERE id = :id");
        $stm->bindParam(':id', $id);
        $stm->bindParam(':applicant', $applicant);
        $stm->bindValue(':receiving_type', $receiving_type);
        $stm->bindParam(':receiving_caseno', $receiving_caseno);
        $stm->bindParam(':note', $note);
        $stm->bindValue(':modifytime', time());
        return $stm->execute() !== FALSE;
    }

    public function delete($id) {
        $stm = $this->db->prepare("DELETE FROM reg_address_undisclosed WHERE id = :id");
        $stm->bindParam(':id', $id);
        return $stm->execute() !== FALSE;
    }
}
