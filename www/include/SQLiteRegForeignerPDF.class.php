<?php
require_once('init.php');
require_once('SQLiteDBFactory.class.php');

class SQLiteRegForeignerPDF {
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
        $this->db = new SQLite3(SQLiteDBFactory::getRegForeignerPDFDB());
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

    public function exists($year, $number, $fid) {
        $number = str_pad($number, 6, '0', STR_PAD_LEFT);
        return $this->db->querySingle("SELECT id from reg_foreigner_pdf WHERE year = '$year' and number = '$number' and fid = '$fid'");
    }

    public function getOne($id) {
        Logger::getInstance()->info(__METHOD__.": 取得 $id 資料");
        if($stmt = $this->db->prepare('SELECT * from reg_foreigner_pdf WHERE id = :bv_id')) {
            $stmt->bindParam(':bv_id', $id);
            $result = $this->prepareArray($stmt);
            return count($result) > 0 ? $result[0] : false;
        }
        Logger::getInstance()->error(__METHOD__.": 無法取得 $id 資料！ (".SQLiteDBFactory::getRegForeignerPDFDB().")");
        return false;
    }

    public function search($st, $ed, $keyword = '') {
        $st_date = date("Y-m-d", $st);
        $ed_date = date("Y-m-d", $ed);
        Logger::getInstance()->info(__METHOD__.": 搜尋 $st_date ~ $ed_date 區間資料，關鍵字: $keyword");
        $result = array();
        if (empty($keyword)) {
            if($stmt = $this->db->prepare('SELECT * from reg_foreigner_pdf WHERE createtime BETWEEN :bv_createtime_st AND :bv_createtime_ed order by modifytime DESC')) {
                $stmt->bindParam(':bv_createtime_st', $st);
                // 在結束日的那天內都算，所以加上 86399 秒
                $stmt->bindValue(':bv_createtime_ed', $ed + 86399);
                $result = $this->prepareArray($stmt);
            } else {
                Logger::getInstance()->error(__METHOD__.": 無法取得 $st_date ~ $ed_date 資料！ (".SQLiteDBFactory::getRegForeignerPDFDB().")");
            }
        } else {
            if($stmt = $this->db->prepare('SELECT * from reg_foreigner_pdf WHERE createtime BETWEEN :bv_createtime_st AND :bv_createtime_ed AND (note LIKE :bv_keyword OR fname LIKE :bv_keyword OR fid LIKE :bv_keyword OR number LIKE :bv_keyword) order by modifytime DESC')) {
                $stmt->bindParam(':bv_createtime_st', $st);
                // 在結束日的那天內都算，所以加上 86399 秒
                $stmt->bindValue(':bv_createtime_ed', $ed + 86399);
                $stmt->bindValue(':bv_keyword', "%$keyword%");
                $result = $this->prepareArray($stmt);
            } else {
                Logger::getInstance()->error(__METHOD__.": 無法取得 $st_date ~ $ed_date 內含 %$keyword% 資料！ (".SQLiteDBFactory::getRegForeignerPDFDB().")");
            }
        }
        return $result;
    }

    public function add($post) {
        $post['number'] = str_pad($post['number'], 6, '0', STR_PAD_LEFT);
        $id = $this->exists($post['year'], $post['number'], $post['fid']);
        if ($id) {
            Logger::getInstance()->warning(__METHOD__.": 外國人資料已存在，將更新它。(id: $id)");
            $post['id'] = $id;
            return $this->update($post);
        } else {
            $stm = $this->db->prepare("
                INSERT INTO reg_foreigner_pdf ('year', 'number', 'fid', 'fname', 'note', 'createtime', 'modifytime')
                VALUES (:year, :number, :fid, :fname, :note, :createtime, :modifytime)
            ");
            $stm->bindParam(':year', $post['year']);
            $stm->bindValue(':number', $post['number']);
            $stm->bindParam(':fid', $post['fid']);
            $stm->bindParam(':fname', $post['fname']);
            $stm->bindParam(':note', $post['note']);
            $stm->bindValue(':createtime', time());
            $stm->bindValue(':modifytime', time());

            return $stm->execute() === FALSE ? false : $this->getLastInsertedId();
        }
        return false;
    }

    public function update($post) {
        $id = $post['id'];
        $year = $post['year'];
        $number = str_pad($post['number'], 6, '0', STR_PAD_LEFT);
        $fid = $post['fid'];
        Logger::getInstance()->warning(__METHOD__.": 更新外國人資料。(id: $id, year: $year, number: $number, fid: $fid)");
        $stm = $this->db->prepare("UPDATE reg_foreigner_pdf SET year = :year, number = :number, fid = :fid, fname = :fname, note = :note, modifytime = :modifytime WHERE id = :id");
        $stm->bindParam(':id', $id);
        $stm->bindParam(':year', $year);
        $stm->bindParam(':number', $number);
        $stm->bindParam(':fid', $fid);
        $stm->bindParam(':fname', $post['fname']);
        $stm->bindParam(':note', $post['note']);
        $stm->bindValue(':modifytime', time());
        return $stm->execute() !== FALSE;
    }

    public function delete($params) {
        if (is_array($params)) {
            $year = $params['year'];
            $number = str_pad($params['number'], 6, '0', STR_PAD_LEFT);
            $fid = $params['fid'];
            $stm = $this->db->prepare("DELETE FROM reg_foreigner_pdf WHERE year = :year and number = :number and fid = :fid");
            $stm->bindParam(':year', $year);
            $stm->bindParam(':number', $number);
            $stm->bindParam(':fid', $fid);
            return $stm->execute() !== FALSE;
        } else {
            // not array, treat it as string
            $id = $params;
            $stm = $this->db->prepare("DELETE FROM reg_foreigner_pdf WHERE id = :id");
            $stm->bindParam(':id', $id);
            return $stm->execute() !== FALSE;
        }
    }
}
