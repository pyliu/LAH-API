<?php
require_once('init.php');
define('DIMENSION_SQLITE_DB', ROOT_DIR.DIRECTORY_SEPARATOR."assets".DIRECTORY_SEPARATOR."db".DIRECTORY_SEPARATOR."dimension.db");

class SQLiteUser {
    private $db;

    private function exists($id) {
        global $log;
        $ret = $this->db->querySingle("SELECT id from user WHERE id = '".trim($id)."'");
        return !empty($ret);
    }

    private function bindUserParams(&$stm, &$row) {
        $stm->bindParam(':id', $row['DocUserID']);
        $stm->bindParam(':name', $row['AP_USER_NAME']);
        $stm->bindValue(':sex', $row['AP_SEX'] == '男' ? 1 : 0);
        $stm->bindParam(':addr', $row['AP_ADR']);
        $stm->bindParam(':tel', $row['AP_TEL']);
        $stm->bindParam(':cell', $row['AP_SEL']);
        $stm->bindParam(':unit', $row['AP_UNIT_NAME']);
        $stm->bindParam(':title', $row['AP_JOB']);
        $stm->bindParam(':work', $row['AP_WORK']);
        $stm->bindParam(':exam', $row['AP_TEST']);
        $stm->bindParam(':education', $row['AP_HI_SCHOOL']);
        $stm->bindParam(':birthday', $row['AP_BIRTH']);

        global $log;
        $tokens = preg_split("/\s+/", $row['AP_ON_DATE']);
        if (count($tokens) == 3) {
            $rewrite = $tokens[2]."/".str_pad($tokens[0], 2, '0', STR_PAD_LEFT)."/".str_pad($tokens[1], 2, '0', STR_PAD_LEFT);
            $stm->bindParam(':onboard_date', $rewrite);
        } else {
            $stm->bindParam(':onboard_date', $row['AP_ON_DATE']);
            //$log->info($row['AP_ON_DATE']);
        }
        
        $stm->bindParam(':offboard_date', $row['AP_OFF_DATE']);
        $stm->bindParam(':ip', $row['AP_PCIP']);
        // $stm->bindValue(':pw_hash', '827ddd09eba5fdaee4639f30c5b8715d');    // HB default
        // $stm->bindValue(':authority', 0);
    }

    private function inst(&$row) {
        $stm = $this->db->prepare("
            INSERT INTO user ('id', 'name', 'sex', 'addr', 'tel', 'cell', 'unit', 'title', 'work', 'exam', 'education', 'onboard_date', 'offboard_date', 'ip', 'pw_hash', 'authority', 'birthday')
            VALUES (:id, :name, :sex, :addr, :tel, :cell, :unit, :title, :work, :exam, :education, :onboard_date, :offboard_date, :ip, '827ddd09eba5fdaee4639f30c5b8715d', 0, :birthday)
        ");
        $this->bindUserParams($stm, $row);
        return $stm->execute() === FALSE ? false : true;
    }

    private function update(&$row) {
        $stm = $this->db->prepare("
            UPDATE user SET
                name = :name,
                sex = :sex,
                addr = :addr,
                tel = :tel,
                cell = :cell,
                unit = :unit,
                title = :title,
                work = :work,
                exam = :exam,
                education = :education,
                onboard_date = :onboard_date, 
                offboard_date = :offboard_date,
                ip = :ip,
                birthday = :birthday
            WHERE id = :id
        ");
        $this->bindUserParams($stm, $row);
        return $stm->execute() === FALSE ? false : true;
    }

    private function prepareArray(&$stmt) {
        $result = $stmt->execute();
        $return = [];
        while($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $return[] = $row;
        }
        return $return;
    }

    function __construct() {
        $this->db = new SQLite3(DIMENSION_SQLITE_DB);
    }

    function __destruct() { $this->db->close(); }

    public function import(&$row) {
        if (empty($row['DocUserID'])) {
            global $log;
            $log->warning(__METHOD__.": DocUserID is empty. Import user procedure can not be proceeded.");
            $log->warning(__METHOD__.": ".print_r($row, true));
            return false;
        }
        if ($this->exists($row['DocUserID'])) {
            // update
            return $this->update($row);
        } else {
            // insert
            return $this->inst($row);
        }
    }

    public function getOnboardUsers() {
        if($stmt = $this->db->prepare("SELECT * FROM user WHERE offboard_date is NULL or offboard_date = '' ORDER BY id")) {
            return $this->prepareArray($stmt);
        } else {
            global $log;
            $log->error(__METHOD__.": 取得在職使用者資料失敗！");
        }
        return false;
    }

    public function getOffboardUsers() {
        if($stmt = $this->db->prepare("SELECT * FROM user WHERE offboard_date != '' ORDER BY id")) {
            return $this->prepareArray($stmt);
        } else {
            global $log;
            $log->error(__METHOD__.": 取得離職使用者資料失敗！");
        }
        return false;
    }

    public function getAllUsers() {
        if($stmt = $this->db->prepare("SELECT * FROM user ORDER BY id")) {
            return $this->prepareArray($stmt);
        } else {
            global $log;
            $log->error(__METHOD__.": 取得全部使用者資料失敗！");
        }
        return false;
    }

    public function getUser($id) {
        if($stmt = $this->db->prepare("SELECT * FROM user WHERE id = :id")) {
            $stmt->bindParam(':id', $id);
            return $this->prepareArray($stmt);
        } else {
            global $log;
            $log->error(__METHOD__.": 取得使用者($id)資料失敗！");
        }
        return false;
        
    }

    public function getUserByName($name) {
        if($stmt = $this->db->prepare("SELECT * FROM user WHERE name = :name")) {
            $stmt->bindParam(':name', $name);
            return $this->prepareArray($stmt);
        } else {
            global $log;
            $log->error(__METHOD__.": 取得使用者($name)資料失敗！");
        }
        return false;
        
    }

    public function getUserByIP($ip) {
        if($stmt = $this->db->prepare("SELECT * FROM user WHERE ip = :ip")) {
            $stmt->bindParam(':ip', $ip);
            return $this->prepareArray($stmt);
        } else {
            global $log;
            $log->error(__METHOD__.": 取得使用者($ip)資料失敗！");
        }
        return false;
        
    }
}
?>
