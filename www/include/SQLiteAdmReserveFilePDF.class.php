<?php
require_once('init.php');
require_once('SQLiteDBFactory.class.php');

class SQLiteAdmReserveFilePDF {
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
        $this->db = new SQLite3(SQLiteDBFactory::getAdmReserveFilePDFDB());
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

    public function exists($number) {
        // $number = str_pad($number, 6, '0', STR_PAD_LEFT);
        return $this->db->querySingle("SELECT id from adm_reserve_file_pdf WHERE number = '$number'");
    }

    public function getOne($id) {
        Logger::getInstance()->info(__METHOD__.": 取得 $id 資料");
        if($stmt = $this->db->prepare('SELECT * from adm_reserve_file_pdf WHERE id = :bv_id')) {
            $stmt->bindParam(':bv_id', $id);
            $result = $this->prepareArray($stmt);
            return count($result) > 0 ? $result[0] : false;
        }
        Logger::getInstance()->error(__METHOD__.": 無法取得 $id 資料！ (".SQLiteDBFactory::getAdmReserveFilePDFDB().")");
        return false;
    }

    public function getOneByNumber($number) {
        Logger::getInstance()->info(__METHOD__.": 取得 $number 資料");
        if($stmt = $this->db->prepare('SELECT * from adm_reserve_file_pdf WHERE number = :bv_number')) {
            $stmt->bindParam(':bv_number', $number);
            $result = $this->prepareArray($stmt);
            return count($result) > 0 ? $result[0] : false;
        }
        Logger::getInstance()->error(__METHOD__.": 無法取得 $number 資料！ (".SQLiteDBFactory::getAdmReserveFilePDFDB().")");
        return false;
    }

    public function search($st, $ed, $keyword = '') {
        $st_date = date("Y-m-d", $st);
        $ed_date = date("Y-m-d", $ed);
        Logger::getInstance()->info(__METHOD__.": 搜尋 $st_date ~ $ed_date 區間資料，關鍵字: $keyword");
        $result = array();
        if (empty($keyword)) {
            if($stmt = $this->db->prepare('SELECT * from adm_reserve_file_pdf WHERE createtime BETWEEN :bv_createtime_st AND :bv_createtime_ed order by createtime DESC')) {
                $stmt->bindParam(':bv_createtime_st', $st);
                // 在結束日的那天內都算，所以加上 86399 秒
                $stmt->bindValue(':bv_createtime_ed', $ed + 86399);
                $result = $this->prepareArray($stmt);
            } else {
                Logger::getInstance()->error(__METHOD__.": 無法取得 $st_date ~ $ed_date 資料！ (".SQLiteDBFactory::getAdmReserveFilePDFDB().")");
            }
        } else {
            if($stmt = $this->db->prepare('SELECT * from adm_reserve_file_pdf WHERE createtime BETWEEN :bv_createtime_st AND :bv_createtime_ed AND (note LIKE :bv_keyword OR fpname LIKE :bv_keyword OR pid LIKE :bv_keyword OR number LIKE :bv_keyword) order by createtime DESC')) {
                $stmt->bindParam(':bv_createtime_st', $st);
                // 在結束日的那天內都算，所以加上 86399 秒
                $stmt->bindValue(':bv_createtime_ed', $ed + 86399);
                $stmt->bindValue(':bv_keyword', "%$keyword%");
                $result = $this->prepareArray($stmt);
            } else {
                Logger::getInstance()->error(__METHOD__.": 無法取得 $st_date ~ $ed_date 內含 %$keyword% 資料！ (".SQLiteDBFactory::getAdmReserveFilePDFDB().")");
            }
        }
        return $result;
    }

    public function add($post) {
        // $post['number'] = str_pad($post['number'], 6, '0', STR_PAD_LEFT);
        // 依收件號判斷
        $id = $this->exists($post['number']);
        if ($id) {
            Logger::getInstance()->warning(__METHOD__.": 檔案預約收件資料已存在，將更新它。(id: $id)");
            $post['id'] = $id;
            return $this->update($post);
        } else {
            $stm = $this->db->prepare("
                INSERT INTO adm_reserve_file_pdf ('number', 'pid', 'pname', 'note', 'createtime', 'endtime')
                VALUES (:number, :pid, :pname, :note, :createtime, :endtime)
            ");
            $stm->bindValue(':number', $post['number']);
            $stm->bindParam(':pid', $post['pid']);
            $stm->bindParam(':pname', $post['pname']);
            $stm->bindParam(':note', $post['note']);
            $stm->bindValue(':createtime', time());
            $stm->bindValue(':endtime', time());

            return $stm->execute() === FALSE ? false : $this->getLastInsertedId();
        }
        return false;
    }

    public function update($post) {
        $id = $post['id'];
        $number = $post['number'];
        $pid = $post['pid'];
        $pname = $post['pname'];
        Logger::getInstance()->warning(__METHOD__.": 更新檔案預約資料。(id: $id, number: $number, pid: $pid, pname: $pname)");
        $stm = $this->db->prepare("UPDATE adm_reserve_file_pdf SET number = :number, pid = :pid, pname = :pname, note = :note, createtime = :createtime, endtime = :endtime WHERE id = :id");
        $stm->bindParam(':id', $id);
        $stm->bindParam(':number', $number);
        $stm->bindParam(':pid', $fid);
        $stm->bindParam(':pname', $pname);
        $stm->bindParam(':note', $post['note']);
        $stm->bindParam(':createtime', $post['createtime']);
        $stm->bindValue(':endtime', $post['endtime']);
        return $stm->execute() !== FALSE;
    }

    public function delete($params) {
        if (is_array($params)) {
            $number = $params['number'];
            $stm = $this->db->prepare("DELETE FROM adm_reserve_file_pdf WHERE number = :number");
            $stm->bindParam(':number', $number);
            return $stm->execute() !== FALSE;
        } else {
            // not array, treat it as string
            $id = $params;
            $stm = $this->db->prepare("DELETE FROM adm_reserve_file_pdf WHERE id = :id");
            $stm->bindParam(':id', $id);
            return $stm->execute() !== FALSE;
        }
    }
}
