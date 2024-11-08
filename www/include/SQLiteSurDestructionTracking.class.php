<?php
require_once('init.php');
require_once('SQLiteDBFactory.class.php');

class SQLiteSurDestructionTracking {
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
        $this->db = new SQLite3(SQLiteDBFactory::getSurDestructionTrackingDB());
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
        $number = str_pad($number, 10, '0', STR_PAD_LEFT);
        return $this->db->querySingle("SELECT id from sur_destruction_tracking WHERE number = '$number'");
    }

    public function getOne($id) {
        Logger::getInstance()->info(__METHOD__.": 取得 $id 資料");
        if($stmt = $this->db->prepare('SELECT * from sur_destruction_tracking WHERE id = :bv_id')) {
            $stmt->bindParam(':bv_id', $id);
            $result = $this->prepareArray($stmt);
            return count($result) > 0 ? $result[0] : false;
        }
        Logger::getInstance()->error(__METHOD__.": 無法取得 $id 資料！ (".SQLiteDBFactory::getSurDestructionTrackingDB().")");
        return false;
    }
    // 以字號取的資料
    public function getOneByNumber($number) {
        Logger::getInstance()->info(__METHOD__.": 取得 $number 資料");
        if($stmt = $this->db->prepare('SELECT * from sur_destruction_tracking WHERE number = :bv_number')) {
            $stmt->bindParam(':bv_number', $number);
            $result = $this->prepareArray($stmt);
            return count($result) > 0 ? $result[0] : false;
        }
        Logger::getInstance()->error(__METHOD__.": 無法取得 $number 資料！ (".SQLiteDBFactory::getSurDestructionTrackingDB().")");
        return false;
    }
    // BY 發文日期
    public function searchByIssueDate($st, $ed, $keyword = '') {
        Logger::getInstance()->info(__METHOD__.": 搜尋發文日期 $st ~ $ed 區間資料，關鍵字: $keyword");
        $result = array();
        if (empty($keyword)) {
            if($stmt = $this->db->prepare('SELECT * from sur_destruction_tracking WHERE issue_date BETWEEN :bv_issue_date_st AND :bv_issue_date_ed order by issue_date DESC')) {
                $stmt->bindParam(':bv_issue_date_st', $st);
                $stmt->bindValue(':bv_issue_date_ed', $ed);
                $result = $this->prepareArray($stmt);
            } else {
                Logger::getInstance()->error(__METHOD__.": 無法取得 $st ~ $ed 資料！ (".SQLiteDBFactory::getSurDestructionTrackingDB().")");
            }
        } else {
            if($stmt = $this->db->prepare('SELECT * from sur_destruction_tracking WHERE issue_date BETWEEN :bv_issue_date_st AND :bv_issue_date_ed AND (note LIKE :bv_keyword OR address LIKE :bv_keyword OR occupancy_permit LIKE :bv_keyword OR construction_permit LIKE :bv_keyword) order by issue_date DESC')) {
                $stmt->bindParam(':bv_issue_date_st', $st);
                $stmt->bindValue(':bv_issue_date_ed', $ed);
                $stmt->bindValue(':bv_keyword', "%$keyword%");
                $result = $this->prepareArray($stmt);
            } else {
                Logger::getInstance()->error(__METHOD__.": 無法取得 $st ~ $ed 內含 %$keyword% 資料！ (".SQLiteDBFactory::getSurDestructionTrackingDB().")");
            }
        }
        return $result;
    }

    public function add($post) {
        $post['number'] = str_pad($post['number'], 10, '0', STR_PAD_LEFT);
        // 依收件號判斷
        $id = $this->exists($post['number']);
        if ($id) {
            Logger::getInstance()->warning(__METHOD__.": 建物滅失追蹤資料已存在，將更新它。(id: $id)");
            $post['id'] = $id;
            return $this->update($post);
        } else {
            $stm = $this->db->prepare("
                INSERT INTO sur_destruction_tracking (
                    'number',
                    'section_code',
                    'land_number',
                    'building_number',
                    'issue_date',
                    'apply_date',
                    'address',
                    'occupancy_permit',
                    'construction_permit',
                    'note',
                    'createtime'
                )
                VALUES (
                    :number,
                    :section_code,
                    :land_number,
                    :building_number,
                    :issue_date,
                    :apply_date,
                    :address,
                    :occupancy_permit,
                    :construction_permit,
                    :note,
                    :createtime
                )
            ");
            $stm->bindValue(':number', $post['number']);
            $stm->bindParam(':section_code', $post['section_code']);
            $stm->bindParam(':land_number', $post['land_number']);
            $stm->bindParam(':building_number', $post['building_number']);
            $stm->bindParam(':issue_date', $post['issue_date']);
            $stm->bindParam(':apply_date', $post['apply_date']);
            $stm->bindParam(':address', $post['address']);
            $stm->bindParam(':occupancy_permit', $post['occupancy_permit']);
            $stm->bindParam(':construction_permit', $post['construction_permit']);
            $stm->bindParam(':note', $post['note']);
            $stm->bindValue(':createtime', $post['createtime'] ?? time());

            return $stm->execute() === FALSE ? false : $this->getLastInsertedId();
        }
        return false;
    }

    public function update($post) {
        $id = $post['id'];
        Logger::getInstance()->warning(__METHOD__.": 更新建物滅失追蹤資料。(id: $id)");
        $stm = $this->db->prepare("
            UPDATE sur_destruction_tracking SET
                number = :number,
                section_code = :section_code,
                land_number = :land_number,
                building_number = :building_number,
                issue_date = :issue_date,
                apply_date = :apply_date,
                address = :address,
                occupancy_permit = :occupancy_permit,
                construction_permit = :construction_permit,
                note = :note,
                createtime = :createtime
            WHERE id = :id"
        );
        
        $stm->bindValue(':number', $post['number']);
        $stm->bindParam(':section_code', $post['section_code']);
        $stm->bindParam(':land_number', $post['land_number']);
        $stm->bindParam(':building_number', $post['building_number']);
        $stm->bindParam(':issue_date', $post['issue_date']);
        $stm->bindParam(':apply_date', $post['apply_date']);
        $stm->bindParam(':address', $post['address']);
        $stm->bindParam(':occupancy_permit', $post['occupancy_permit']);
        $stm->bindParam(':construction_permit', $post['construction_permit']);
        $stm->bindParam(':note', $post['note']);
        $stm->bindValue(':createtime', $post['createtime'] ?? time());
        $stm->bindValue(':done', boolval($post['done']) ? 1 : 0);

        return $stm->execute() !== FALSE;
    }

    public function delete($params) {
        $id = is_array($params) ? $params['id'] : $params;
        Logger::getInstance()->warning(__METHOD__.": 刪除建物滅失追蹤資料。(id: $id)");
        $stm = $this->db->prepare("DELETE FROM sur_destruction_tracking WHERE id = :id");
        $stm->bindParam(':id', $id);
        return $stm->execute() !== FALSE;
    }

    public function switchDone($id) {
        Logger::getInstance()->warning(__METHOD__.": 切換建物滅失追蹤資料辦畢屬性。(id: $id)");
        $record = $this->getOne($id);
        if ($record !== false) {
            $stm = $this->db->prepare("
                UPDATE sur_destruction_tracking SET
                    done = :done
                WHERE id = :id"
            );
            $bool = boolval($record['done']);
            // reverse done attribute
            $stm->bindValue(':done', $bool ? 0 : 1);
            return $stm->execute() !== FALSE;
        }
        return false;
    }

	public function removePDF($id) {
		$orig = $this->getOne($id);
        // remove database record
		$result = $this->delete($id);
		if ($result) {
			Logger::getInstance()->info(__METHOD__.": ✅ 建物滅失追蹤資料已移除");
			// continue to delete pdf file
			$orig_file = UPLOAD_SUR_DESTRUCTION_TRACKING_PDF_DIR.DIRECTORY_SEPARATOR.$orig['number'].".pdf";
            $unlink_result = @unlink($orig_file);
			if (!$unlink_result) {
                Logger::getInstance()->error("⚠ 刪除 $orig_file 檔案失敗!");
			}
			return true;
		} else {
			Logger::getInstance()->warning(__METHOD__.": ⚠️ 建物滅失追蹤資料移除失敗 ($id)");
		}
		return false;
	}
}
