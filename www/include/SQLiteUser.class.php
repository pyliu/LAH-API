<?php
require_once('init.php');
define('DIMENSION_SQLITE_DB', ROOT_DIR.DIRECTORY_SEPARATOR."assets".DIRECTORY_SEPARATOR."db".DIRECTORY_SEPARATOR."dimension.db");

class SQLiteUser {
    private $db;

    private function exists($id) {
        $ret = $this->db->querySingle("SELECT id from user WHERE id = '$id'");
        return !empty($ret);
    }

    private function bindUserParam(&$stm, &$row) {
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
        $stm->bindParam(':onboard_date', $row['AP_ON_DATE']);
        $stm->bindParam(':offboard_date', $row['AP_OFF_DATE']);
        $stm->bindParam(':ip', $row['AP_PCIP']);
        $stm->bindParam(':pw_hash', '827ddd09eba5fdaee4639f30c5b8715d');
    }

    private function inst(&$row) {
        $stm = $this->db->prepare("
            INSERT INTO user ('id', 'name', 'sex', 'addr', 'tel', 'cell', 'unit', 'title', 'work', 'exam', 'education', 'onboard_date', 'offboard_date', 'ip', 'pw_hash')
            VALUES (:id, :name, :sex, :addr, :tel, :cell, :unit, :title, :work, :exam, :education, :onboard_date, :offboard_date, :ip, :pw_hash)
        ");
        $this->bindUserParam($stm, $row);
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
                pw_hash = :pw_hash
            WHERE id = :id
        ");
        $this->bindUserParam($stm, $row);
        return $stm->execute() === FALSE ? false : true;
    }

    function __construct() {
        $this->db = new SQLite3(DIMENSION_SQLITE_DB);
    }

    function __destruct() { $this->db->close(); }

    public function import(&$row) {
        if (empty($row['DocUserID'])) {
            global $log;
            $log->warning(__METHOD__.": DocUserID is empty update user procedure can not be proceeded.");
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

}
?>
