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

    // 取得已存的發文字號資料
    public function getAllNumbers() {
        Logger::getInstance()->info(__METHOD__.": 取得目前資料庫中所有的發文字號");
        $result = array();
        if($stmt = $this->db->prepare('SELECT number from sur_destruction_tracking order by number')) {
            $result = $this->prepareArray($stmt);
        } else {
            Logger::getInstance()->error(__METHOD__.": 無法取得發文字號資料！ (".SQLiteDBFactory::getSurDestructionTrackingDB().")");
        }
        return $result;
    }

    public function getOne($id) {
        Logger::getInstance()->info(__METHOD__.": 取得 id:$id 資料");
        if($stmt = $this->db->prepare("SELECT * from sur_destruction_tracking WHERE id = :bv_id")) {
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
    // BY 申請日期
    public function searchByApplyDate($st, $ed, $keyword = '') {
        Logger::getInstance()->info(__METHOD__.": 搜尋申請日期 $st ~ $ed 區間資料，關鍵字: $keyword");
        $result = array();
        if (empty($keyword)) {
            if($stmt = $this->db->prepare('SELECT * from sur_destruction_tracking WHERE apply_date BETWEEN :bv_apply_date_st AND :bv_apply_date_ed order by apply_date DESC')) {
                $stmt->bindParam(':bv_apply_date_st', $st);
                $stmt->bindParam(':bv_apply_date_ed', $ed);
                $result = $this->prepareArray($stmt);
            } else {
                Logger::getInstance()->error(__METHOD__.": 無法取得 $st ~ $ed 資料！ (".SQLiteDBFactory::getSurDestructionTrackingDB().")");
            }
        } else {
            if($stmt = $this->db->prepare('SELECT * from sur_destruction_tracking WHERE apply_date BETWEEN :bv_apply_date_st AND :bv_apply_date_ed AND (note LIKE :bv_keyword OR address LIKE :bv_keyword OR occupancy_permit LIKE :bv_keyword OR construction_permit LIKE :bv_keyword) order by apply_date DESC')) {
                $stmt->bindParam(':bv_apply_date_st', $st);
                $stmt->bindParam(':bv_apply_date_ed', $ed);
                $stmt->bindValue(':bv_keyword', "%$keyword%");
                $result = $this->prepareArray($stmt);
            } else {
                Logger::getInstance()->error(__METHOD__.": 無法取得 $st ~ $ed 內含 %$keyword% 資料！ (".SQLiteDBFactory::getSurDestructionTrackingDB().")");
            }
        }
        return $result;
    }

    public function searchByBelowIssueDate($dateAgo) {
        $twYear = substr($dateAgo, 0, 4) - 1911;
        $twDateAgo = $twYear.substr($dateAgo, 4);
        Logger::getInstance()->info(__METHOD__.": 搜尋逕辦建物滅失案件資料 issue_date 小於 $twDateAgo 且無發文日期");
        $result = array();
        // using issue_date as criteria
        if($stmt = $this->db->prepare("
            SELECT * from sur_destruction_tracking
            WHERE 1=1
                AND issue_date < :bv_overdue_date_st
                AND done <> 'true'
            ORDER BY issue_date DESC
        ")) {
            $stmt->bindParam(':bv_overdue_date_st', $twDateAgo);
            $result = $this->prepareArray($stmt);
        } else {
            Logger::getInstance()->warning(__METHOD__.": 無法取得逕辦建物滅失案件資料！ (".SQLiteDBFactory::getSurDestructionTrackingDB().")");
        }
        return $result;
    }

    public function searchByConcerned() {
        // 6個月的前1周
        $fiveMonthsAnd23DaysAgo = date('Ymd', strtotime('-6 months +1 week'));
        return $this->searchByBelowIssueDate($fiveMonthsAnd23DaysAgo);
    }

    public function searchByOverdue() {
        $sixMonthsAgo = date('Ymd', strtotime('-6 months'));
        return $this->searchByBelowIssueDate($sixMonthsAgo);
    }
    // 取的PDF檔名
    public function getPDFFilename($id) {
        Logger::getInstance()->info(__METHOD__.": 建物滅失追蹤資料電子檔檔名。(id: $id)");
        $orig = $this->getOne($id);
        if ($orig === false) {
            Logger::getInstance()->warning(__METHOD__.": 找不到建物滅失追蹤資料，無法組成檔名。(id: $id)");
            return false;
        }
        $file = $orig['apply_date'].'_'.$orig['section_code'].'_'.$orig['land_number'].'_'.$orig['building_number'].".pdf";
        Logger::getInstance()->info(__METHOD__.": 合成電子檔檔名：$file");
        return $file;
    }
    // 變更PDF檔名
    public function renamePDFFilename($id, $orig_name) {
        Logger::getInstance()->info(__METHOD__.": 變更建物滅失追蹤資料電子檔檔名。(id: $id from $orig_name)");
        $now = $this->getOne($id);
        if ($now === false) {
            Logger::getInstance()->warning(__METHOD__.": 找不到建物滅失追蹤資料，無法變更檔名。(id: $id)");
            return false;
        }
        $parent_dir = UPLOAD_PDF_DIR.DIRECTORY_SEPARATOR.'sur_destruction_tracking';
        Logger::getInstance()->info(__METHOD__.": 電子檔存放目錄：$parent_dir");
        $target_name = $now['apply_date'].'_'.$now['section_code'].'_'.$now['land_number'].'_'.$now['building_number'].".pdf";
        Logger::getInstance()->info(__METHOD__.": 目標電子檔檔名：$target_name");
        $bool = rename($parent_dir.DIRECTORY_SEPARATOR.$orig_name, $parent_dir.DIRECTORY_SEPARATOR.$target_name);
        if ($bool) {
            Logger::getInstance()->info(__METHOD__.": 電子檔檔名已變更：$target_name ✔");
        } else {
            Logger::getInstance()->error(__METHOD__.": 電子檔檔名變更失敗：$orig_name 👉 $target_name ❌");
        }
        return $bool;
    }

    public function add($post) {
        $post['number'] = str_pad($post['number'], 10, '0', STR_PAD_LEFT);
        // 依收件號判斷
        $id = $this->exists($post['number']);
        if ($id) {
            Logger::getInstance()->info(__METHOD__.": 建物滅失追蹤資料已存在，將更新它。(id: $id)");
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
                    'updatetime',
                    'done'
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
                    :updatetime,
                    'false'
                )
            ");
            $stm->bindParam(':number', $post['number']);
            $stm->bindParam(':section_code', $post['section_code']);
            $stm->bindParam(':land_number', $post['land_number']);
            $stm->bindParam(':building_number', $post['building_number']);
            $stm->bindParam(':issue_date', $post['issue_date']);
            $stm->bindParam(':apply_date', $post['apply_date']);
            $stm->bindParam(':address', $post['address']);
            $stm->bindParam(':occupancy_permit', $post['occupancy_permit']);
            $stm->bindParam(':construction_permit', $post['construction_permit']);
            $stm->bindParam(':note', $post['note']);
            $updatetime = time();
            $stm->bindParam(':updatetime', $updatetime);

            return $stm->execute() === FALSE ? false : $this->getLastInsertedId();
        }
        return false;
    }

    public function update($post) {
        $id = $post['id'];
        Logger::getInstance()->info(__METHOD__.": 取得建物滅失追蹤資料電子檔原始檔名。(id: $id)");
        $ofile = $this->getPDFFilename($id);
        Logger::getInstance()->info(__METHOD__.": 更新建物滅失追蹤資料。(id: $id)");
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
                updatetime = :updatetime,
                done = :done
            WHERE id = :id"
        );

        $stm->bindParam(':id', $id);
        $stm->bindParam(':number', $post['number']);
        $stm->bindParam(':section_code', $post['section_code']);
        $stm->bindParam(':land_number', $post['land_number']);
        $stm->bindParam(':building_number', $post['building_number']);
        $stm->bindParam(':issue_date', $post['issue_date']);
        $stm->bindParam(':apply_date', $post['apply_date']);
        $stm->bindParam(':address', $post['address']);
        $stm->bindParam(':occupancy_permit', $post['occupancy_permit']);
        $stm->bindParam(':construction_permit', $post['construction_permit']);
        $stm->bindParam(':note', $post['note']);
        $stm->bindValue(':updatetime', time());
        $stm->bindValue(':done', boolval($post['done']) ? 'true' : 'false');

        $upd_result = $stm->execute() !== FALSE;

        if ($upd_result) {
            $this->renamePDFFilename($id, $ofile);
        }

        return $upd_result;
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
            $stm->bindParam(':id', $id);
            $bool = boolval($record['done']);
            // reverse done attribute
            $stm->bindValue(':done', $bool ? 'false' : 'true');
            return $stm->execute() !== FALSE;
        }
        return false;
    }

    public function setDone($id, $done) {
        Logger::getInstance()->warning(__METHOD__.": 設定建物滅失追蹤資料辦畢屬性。(id: $id)");
        $record = $this->getOne($id);
        if ($record !== false) {
            $stm = $this->db->prepare("
                UPDATE sur_destruction_tracking SET
                    done = :done
                WHERE id = :id"
            );
            $stm->bindParam(':id', $id);
            // $bool = $done === 'true' ? 'true' : 'false';
            // Logger::getInstance()->warning(__METHOD__.": 設定辦畢屬性 raw: $done VS bool: $bool");
            // reverse done attribute
            $stm->bindValue(':done', $done === 'true' ? 'true' : 'false');
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
            $filename = $orig['apply_date'].'_'.$orig['section_code'].'_'.$orig['land_number'].'_'.$orig['building_number'];
			$orig_file = UPLOAD_SUR_DESTRUCTION_TRACKING_PDF_DIR.DIRECTORY_SEPARATOR.$filename.".pdf";
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
