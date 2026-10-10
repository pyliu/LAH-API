<?php
require_once('init.php');
require_once('SQLiteDBFactory.class.php');

class SQLiteRegPropertyAlert {
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
        $this->db = new SQLite3(SQLiteDBFactory::getRegPropertyAlertDB());
        // 對於高併發的讀寫場景，可以考慮將 SQLite 的日誌模式切換為「預寫式日誌 (Write-Ahead Logging)」。它對併發的處理更好，可以減少鎖定問題
        $this->db->exec("PRAGMA journal_mode = WAL");
        $this->db->exec("PRAGMA cache_size = 100000");
        $this->db->exec("PRAGMA temp_store = MEMORY");
        // 若 serial_no, cellphone, sms_status 欄位不存在則新增（向下相容既有資料）
        $col_result = $this->db->query("PRAGMA table_info(reg_property_alert)");
        $columns = [];
        if ($col_result) {
            while ($col_row = $col_result->fetchArray(SQLITE3_ASSOC)) {
                $columns[] = $col_row['name'];
            }
        }
        if (!in_array('serial_no', $columns)) {
            $this->db->exec("ALTER TABLE reg_property_alert ADD COLUMN serial_no TEXT DEFAULT ''");
        }
        if (!in_array('cellphone', $columns)) {
            $this->db->exec("ALTER TABLE reg_property_alert ADD COLUMN cellphone TEXT DEFAULT ''");
        }
        if (!in_array('sms_status', $columns)) {
            $this->db->exec("ALTER TABLE reg_property_alert ADD COLUMN sms_status INTEGER DEFAULT 0");
        }
        if (!in_array('receiver', $columns)) {
            $this->db->exec("ALTER TABLE reg_property_alert ADD COLUMN receiver TEXT DEFAULT ''");
        }
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
        if ($stmt = $this->db->prepare('SELECT id FROM reg_property_alert WHERE applicant = :bv_applicant')) {
            $stmt->bindParam(':bv_applicant', $applicant);
            $result = $this->prepareArray($stmt);
            return count($result) > 0 ? $result[0]['id'] : false;
        }
        return false;
    }

    public function getOne($id) {
        Logger::getInstance()->info(__METHOD__.": 取得 $id 資料");
        if($stmt = $this->db->prepare('SELECT * FROM reg_property_alert WHERE id = :bv_id')) {
            $stmt->bindParam(':bv_id', $id);
            $result = $this->prepareArray($stmt);
            return count($result) > 0 ? $result[0] : false;
        }
        Logger::getInstance()->error(__METHOD__.": 無法取得 $id 資料！ (".SQLiteDBFactory::getRegPropertyAlertDB().")");
        return false;
    }

    public function getAll() {
        Logger::getInstance()->info(__METHOD__.": 取得全部資料");
        if ($stmt = $this->db->prepare('SELECT * FROM reg_property_alert ORDER BY modifytime DESC')) {
            return $this->prepareArray($stmt);
        }
        Logger::getInstance()->error(__METHOD__.": 無法取得全部資料！ (".SQLiteDBFactory::getRegPropertyAlertDB().")");
        return array();
    }

    public function search($st, $ed, $keyword = '') {
        $st_date = date("Y-m-d", $st);
        $ed_date = date("Y-m-d", $ed);
        Logger::getInstance()->info(__METHOD__.": 搜尋 $st_date ~ $ed_date 區間資料，關鍵字: $keyword");
        $result = array();
        if (empty($keyword)) {
            if($stmt = $this->db->prepare('SELECT * from reg_property_alert WHERE createtime BETWEEN :bv_createtime_st AND :bv_createtime_ed order by modifytime DESC')) {
                $stmt->bindParam(':bv_createtime_st', $st);
                // 在結束日的那天內都算，所以加上 86399 秒
                $stmt->bindValue(':bv_createtime_ed', $ed + 86399);
                $result = $this->prepareArray($stmt);
            } else {
                Logger::getInstance()->error(__METHOD__.": 無法取得 $st_date ~ $ed_date 資料！ (".SQLiteDBFactory::getRegPropertyAlertDB().")");
            }
        } else {
            if($stmt = $this->db->prepare('SELECT * FROM reg_property_alert WHERE createtime BETWEEN :bv_createtime_st AND :bv_createtime_ed AND (note LIKE :bv_keyword OR applicant LIKE :bv_keyword OR receiving_caseno LIKE :bv_keyword OR serial_no LIKE :bv_keyword OR cellphone LIKE :bv_keyword OR receiver LIKE :bv_keyword) ORDER BY modifytime DESC')) {
                $stmt->bindParam(':bv_createtime_st', $st);
                // 在結束日的那天內都算，所以加上 86399 秒
                $stmt->bindValue(':bv_createtime_ed', $ed + 86399);
                $stmt->bindValue(':bv_keyword', "%$keyword%");
                $result = $this->prepareArray($stmt);
            } else {
                Logger::getInstance()->error(__METHOD__.": 無法取得 $st_date ~ $ed_date 內含 %$keyword% 資料！ (".SQLiteDBFactory::getRegPropertyAlertDB().")");
            }
        }
        return $result;
    }

    private function generateSerialNo() {
        $roc_year = (int)date('Y') - 1911;
        $month_day = date('md'); // e.g. "0806"
        $today_start = mktime(0, 0, 0);
        $today_end   = mktime(23, 59, 59);
        $count_stm = $this->db->prepare('SELECT COUNT(*) AS cnt FROM reg_property_alert WHERE createtime BETWEEN :bv_start AND :bv_end');
        $count_stm->bindValue(':bv_start', $today_start);
        $count_stm->bindValue(':bv_end',   $today_end);
        $count_result = $count_stm->execute();
        $cnt = 0;
        if ($count_result) {
            $row = $count_result->fetchArray(SQLITE3_ASSOC);
            $cnt = $row ? (int)$row['cnt'] : 0;
        }
        return $roc_year . $month_day . str_pad($cnt + 1, 3, '0', STR_PAD_LEFT);
    }

    public function add($post) {
        $serial_no = $this->generateSerialNo();
        $cellphone = isset($post['cellphone']) ? trim($post['cellphone']) : '';
        $sms_status = isset($post['sms_status']) ? (int)$post['sms_status'] : 0;
        $receiver = isset($post['receiver']) ? trim($post['receiver']) : '';
        $stm = $this->db->prepare("
            INSERT INTO reg_property_alert ('applicant', 'receiving_type', 'receiving_caseno', 'cellphone', 'sms_status', 'note', 'serial_no', 'receiver', 'createtime', 'modifytime')
            VALUES (:applicant, :receiving_type, :receiving_caseno, :cellphone, :sms_status, :note, :serial_no, :receiver, :createtime, :modifytime)
        ");
        $stm->bindParam(':applicant', $post['applicant']);
        $stm->bindValue(':receiving_type', isset($post['receiving_type']) ? (int)$post['receiving_type'] : 0);
        $stm->bindValue(':receiving_caseno', isset($post['receiving_caseno']) ? $post['receiving_caseno'] : '');
        $stm->bindParam(':cellphone', $cellphone);
        $stm->bindValue(':sms_status', $sms_status);
        $stm->bindParam(':note', $post['note']);
        $stm->bindParam(':serial_no', $serial_no);
        $stm->bindParam(':receiver', $receiver);
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
        $applicant        = isset($post['applicant'])         ? $post['applicant']               : $record['applicant'];
        $receiving_type   = isset($post['receiving_type'])    ? (int)$post['receiving_type']      : (int)$record['receiving_type'];
        $receiving_caseno = isset($post['receiving_caseno'])  ? $post['receiving_caseno']         : $record['receiving_caseno'];
        $cellphone        = isset($post['cellphone'])         ? trim($post['cellphone'])          : ($record['cellphone'] ?? '');
        $sms_status       = isset($post['sms_status'])        ? (int)$post['sms_status']           : (int)($record['sms_status'] ?? 0);
        $note             = isset($post['note'])              ? $post['note']                     : $record['note'];
        $receiver         = isset($post['receiver'])         ? trim($post['receiver'])          : ($record['receiver'] ?? '');
        Logger::getInstance()->warning(__METHOD__.": 更新地籍異動即時通資料。(id: $id, applicant: $applicant)");
        $stm = $this->db->prepare("UPDATE reg_property_alert SET applicant = :applicant, receiving_type = :receiving_type, receiving_caseno = :receiving_caseno, cellphone = :cellphone, sms_status = :sms_status, note = :note, receiver = :receiver, modifytime = :modifytime WHERE id = :id");
        $stm->bindParam(':id', $id);
        $stm->bindParam(':applicant', $applicant);
        $stm->bindValue(':receiving_type', $receiving_type);
        $stm->bindParam(':receiving_caseno', $receiving_caseno);
        $stm->bindParam(':cellphone', $cellphone);
        $stm->bindValue(':sms_status', $sms_status);
        $stm->bindParam(':note', $note);
        $stm->bindParam(':receiver', $receiver);
        $stm->bindValue(':modifytime', time());
        return $stm->execute() !== FALSE;
    }

    public function delete($id) {
        $stm = $this->db->prepare("DELETE FROM reg_property_alert WHERE id = :id");
        $stm->bindParam(':id', $id);
        return $stm->execute() !== FALSE;
    }

    /**
     * 取得有填寫手機且簡訊狀態未成功的待比對資料 (sms_status NOT IN (1, 3))
     * 0: 未發送, 1: 成功, 2: 失敗, 3: 忽略
     * @return array
     */
    public function getPendingSmsRecords() {
        if ($stmt = $this->db->prepare("SELECT * FROM reg_property_alert WHERE cellphone IS NOT NULL AND TRIM(cellphone) != '' AND (sms_status IS NULL OR sms_status NOT IN (1, 3)) ORDER BY createtime ASC")) {
            return $this->prepareArray($stmt);
        }
        Logger::getInstance()->error(__METHOD__.": 無法取得待比對簡訊資料！");
        return array();
    }

    /**
     * 更新指定記錄之簡訊狀態
     * @param int $id
     * @param int $sms_status
     * @return bool
     */
    public function updateSmsStatus($id, $sms_status) {
        $stm = $this->db->prepare("UPDATE reg_property_alert SET sms_status = :sms_status, modifytime = :modifytime WHERE id = :id");
        $stm->bindParam(':id', $id);
        $stm->bindValue(':sms_status', (int)$sms_status);
        $stm->bindValue(':modifytime', time());
        return $stm->execute() !== FALSE;
    }
}
