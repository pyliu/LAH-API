<?php
require_once('init.php');
require_once('System.class.php');
require_once('DynamicSQLite.class.php');
class SQLiteUser {
    private $db;

    private function exists($id) {
        $ret = $this->db->querySingle("SELECT id from user WHERE id = '".trim($id)."'");
        return !empty($ret);
    }

    private function calculateAuthority($ip) {
        $authority = 0;
        $system = System::getInstance();
        if (in_array($ip, $system->getRoleSuperIps())) {
            $authority = $authority | AUTHORITY::SUPER;
        }
        if (in_array($ip, $system->getRoleAdminIps())) {
            $authority = $authority | AUTHORITY::ADMIN;
        }
        if (in_array($ip, $system->getRoleChiefIps())) {
            $authority = $authority | AUTHORITY::CHIEF;
        }
        if (in_array($ip, $system->getRoleRAEIps())) {
            $authority = $authority | AUTHORITY::RESEARCH_AND_EVALUATION;
        }
        if (in_array($ip, $system->getRoleGAIps())) {
            $authority = $authority | AUTHORITY::GENERAL_AFFAIRS;
        }
        return $authority;
    }

    private function bindUserParams(&$stm, &$row) {

        if ($stm === false) {
            global $log;
            $log->error(__METHOD__.": bindUserParams because of \$stm is false.");
            return;
        }

        $stm->bindParam(':id', $row['DocUserID']);
        $stm->bindParam(':name', $row['AP_USER_NAME']);
        $stm->bindValue(':sex', $row['AP_SEX'] == '男' ? 1 : 0);
        $stm->bindParam(':addr', $row['AP_ADR']);
        $stm->bindParam(':tel', $row['AP_TEL'], SQLITE3_TEXT);
        $stm->bindValue(':ext', empty($row['AP_EXT']) ? '153' : $row['AP_EXT'], SQLITE3_TEXT); // 總機 153
        $stm->bindParam(':cell', $row['AP_SEL'], SQLITE3_TEXT);
        $stm->bindParam(':unit', $row['AP_UNIT_NAME']);
        $stm->bindParam(':title', $row['AP_JOB']);
        $stm->bindValue(':work', empty($row['unitname2']) ? $row['AP_WORK'] : $row['unitname2']);
        $stm->bindParam(':exam', $row['AP_TEST']);
        $stm->bindParam(':education', $row['AP_HI_SCHOOL']);
        $stm->bindParam(':birthday', $row['AP_BIRTH']);

        $tokens = preg_split("/\s+/", $row['AP_ON_DATE']);
        if (count($tokens) == 3) {
            $rewrite = $tokens[2]."/".str_pad($tokens[0], 2, '0', STR_PAD_LEFT)."/".str_pad($tokens[1], 2, '0', STR_PAD_LEFT);
            $stm->bindParam(':onboard_date', $rewrite);
        } else {
            $stm->bindParam(':onboard_date', $row['AP_ON_DATE']);
        }
        
        if (empty($row['AP_OFF_DATE']) && $row['AP_OFF_JOB'] == 'Y') {
            $row['AP_OFF_DATE'] = '109/09/24';
        }

        $stm->bindParam(':offboard_date', $row['AP_OFF_DATE']);
        if (empty($row['AP_PCIP'])) {
            $stm->bindValue(':ip', '192.168.xx.xx');
        } else {
            $stm->bindParam(':ip', $row['AP_PCIP']);
        }

        // $stm->bindValue(':pw_hash', '827ddd09eba5fdaee4639f30c5b8715d');    // HB default
        
        $authority = $this->getAuthority($row['DocUserID']);
        $system = System::getInstance();
        // add admin privilege
        if (in_array($row['AP_PCIP'], $system->getRoleAdminIps())) {
            $authority = $authority | AUTHORITY::ADMIN;
        }
        // add chief privilege
        if (in_array($row['AP_PCIP'], $system->getRoleChiefIps())) {
            $authority = $authority | AUTHORITY::CHIEF;
        }
        $stm->bindParam(':authority', $authority);
    }

    private function replace(&$row) {
        $stm = $this->db->prepare("
            REPLACE INTO user ('id', 'name', 'sex', 'addr', 'tel', 'ext', 'cell', 'unit', 'title', 'work', 'exam', 'education', 'onboard_date', 'offboard_date', 'ip', 'pw_hash', 'authority', 'birthday')
            VALUES (:id, :name, :sex, :addr, :tel, :ext, :cell, :unit, :title, :work, :exam, :education, :onboard_date, :offboard_date, :ip, '827ddd09eba5fdaee4639f30c5b8715d', :authority, :birthday)
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

    private function getDimensionDB() {
        $db_path = DIMENSION_SQLITE_DB;
        $sqlite = new DynamicSQLite($db_path);
        $sqlite->initDB();
        $sqlite->createTableBySQL('
            CREATE TABLE IF NOT EXISTS "user" (
                "id"	TEXT NOT NULL,
                "name"	TEXT NOT NULL,
                "sex"	INTEGER NOT NULL DEFAULT 0,
                "addr"	TEXT,
                "tel"	TEXT,
                "ext"	NUMERIC NOT NULL DEFAULT 153,
                "cell"	TEXT,
                "unit"	TEXT NOT NULL DEFAULT \'行政課\',
                "title"	TEXT,
                "work"	TEXT,
                "exam"	TEXT,
                "education"	TEXT,
                "onboard_date"	TEXT,
                "offboard_date"	TEXT,
                "ip"	TEXT NOT NULL DEFAULT \'192.168.xx.xx\',
                "pw_hash"	TEXT NOT NULL DEFAULT \'827ddd09eba5fdaee4639f30c5b8715d\',
                "authority"	INTEGER NOT NULL DEFAULT 0,
                "birthday"	TEXT,
                PRIMARY KEY("id")
            )
        ');
        return $db_path;
    }

    function __construct() {
        $db_path = $this->getDimensionDB();
        $this->db = new SQLite3($db_path);
        $this->db->exec("PRAGMA cache_size = 100000");
        $this->db->exec("PRAGMA temp_store = MEMORY");
        $this->db->exec("BEGIN TRANSACTION");
    }

    function __destruct() {
        $this->db->exec("END TRANSACTION");
        $this->db->close();
    }

    public function import(&$row) {
        if (empty($row['DocUserID'])) {
            global $log;
            $log->warning(__METHOD__.": DocUserID is empty. Import user procedure can not be proceeded.");
            $log->warning(__METHOD__.": ".print_r($row, true));
            return false;
        }
        return $this->replace($row);
    }

    public function getAuthority($id) {
        return  $this->db->querySingle("SELECT authority from user WHERE id = '".trim($id)."'") ?? AUTHORITY::NORMAL;
    }

    public function getAllUsers() {
        if($stmt = $this->db->prepare("SELECT * FROM user WHERE 1 = 1 ORDER BY id")) {
            return $this->prepareArray($stmt);
        } else {
            global $log;
            $log->error(__METHOD__.": 取得所有使用者資料失敗！");
        }
        return false;
    }

    public function getOnboardUsers() {
        if($stmt = $this->db->prepare("SELECT * FROM user WHERE offboard_date IS NULL OR offboard_date = '' ORDER BY id")) {
            return $this->prepareArray($stmt);
        } else {
            global $log;
            $log->error(__METHOD__.": 取得在職使用者資料失敗！");
        }
        return false;
    }

    public function getOffboardUsers() {
        if($stmt = $this->db->prepare("SELECT * FROM user WHERE offboard_date IS NOT NULL AND offboard_date <> '' ORDER BY id")) {
            return $this->prepareArray($stmt);
        } else {
            global $log;
            $log->error(__METHOD__.": 取得離職使用者資料失敗！");
        }
        return false;
    }

    public function getChiefs() {
        if($stmt = $this->db->prepare("SELECT * FROM user WHERE (offboard_date is NULL OR offboard_date = '') AND authority & :chief_bit ORDER BY id")) {
            $stmt->bindValue(':chief_bit', AUTHORITY::CHIEF, SQLITE3_INTEGER);
            return $this->prepareArray($stmt);
        } else {
            global $log;
            $log->error(__METHOD__.": 取得主管資料失敗！");
        }
        return false;
    }

    public function getChief($unit) {
        if($stmt = $this->db->prepare("SELECT * FROM user WHERE (offboard_date is NULL OR offboard_date = '') AND authority & :chief_bit AND unit = :unit ORDER BY id")) {
            $stmt->bindValue(':chief_bit', AUTHORITY::CHIEF, SQLITE3_INTEGER);
            $stmt->bindParam(':unit', $unit, SQLITE3_TEXT);
            return $this->prepareArray($stmt);
        } else {
            global $log;
            $log->error(__METHOD__.": 取得${unit}主管資料失敗！");
        }
        return false;
    }

    public function getStaffs($unit) {
        if($stmt = $this->db->prepare("SELECT * FROM user WHERE (offboard_date is NULL or offboard_date = '') AND unit = :unit ORDER BY id")) {
            $stmt->bindParam(':unit', $unit, SQLITE3_TEXT);
            return $this->prepareArray($stmt);
        } else {
            global $log;
            $log->error(__METHOD__.": 取得${unit}人員資料失敗！");
        }
        return false;
    }

    public function getTreeData($unit) {
        $chief = $this->getChief($unit)[0];
        $chief['staffs'] = array();
        $staffs = $this->getStaffs($unit);
        foreach ($staffs as $staff) {
            if ($staff['id'] == $chief['id']) continue;
            $chief['staffs'][] = $staff;
        }
        return $chief;
    }

    public function getTopTreeData() {
        $director = $this->getTreeData('主任室');
        $secretary = $this->getTreeData('秘書室');
        $director['staffs'][] = &$secretary;
        $secretary['staffs'][] = $this->getTreeData('登記課');
        $secretary['staffs'][] = $this->getTreeData('測量課');
        $secretary['staffs'][] = $this->getTreeData('地價課');
        $secretary['staffs'][] = $this->getTreeData('行政課');
        $secretary['staffs'][] = $this->getTreeData('資訊課');
        $secretary['staffs'][] = $this->getTreeData('會計室');
        $secretary['staffs'][] = $this->getTreeData('人事室');
        return $director;
    }

    public function importXlsxUser(&$xlsx_row) {
        /*
            [0] => 使用者代碼
            [1] => 使用者姓名
            [2] => 性別
            [3] => 地址
            [4] => 電話
            [5] => 分機
            [6] => 手機
            [7] => 部門
            [8] => 職稱
            [9] => 工作
            [10] => 考試
            [11] => 教育程度
            [12] => 報到日期
            [13] => 離職日期
            [14] => IP
            [15] => 生日
        */
        global $log;
        if (empty($xlsx_row[0])) {
            $log->warning(__METHOD__.': id is a required param, it\'s empty.');
            return false;
        }
        switch ($xlsx_row[2]) {
            case '女':
            case '0':
                $xlsx_row[2] = 0;
                break;
            default:
                $xlsx_row[2] = 1;
        }
        if($stmt = $this->db->prepare("
          REPLACE INTO user ('id', 'name', 'sex', 'addr', 'tel', 'ext', 'cell', 'unit', 'title', 'work', 'exam', 'education', 'onboard_date', 'offboard_date', 'ip', 'pw_hash', 'authority', 'birthday')
          VALUES (:id, :name, :sex, :addr, :tel, :ext, :cell, :unit, :title, :work, :exam, :education, :onboard_date, :offboard_date, :ip, '827ddd09eba5fdaee4639f30c5b8715d', :authority, :birthday)
        ")) {
            $stmt->bindParam(':id', $xlsx_row[0]);
            $stmt->bindParam(':name', $xlsx_row[1]);
            $stmt->bindParam(':sex', $xlsx_row[2]);
            $stmt->bindParam(':addr', $xlsx_row[3]);
            $stmt->bindParam(':tel', $xlsx_row[4]);
            $stmt->bindParam(':ext', $xlsx_row[5]);
            $stmt->bindParam(':cell', $xlsx_row[6]);
            $stmt->bindParam(':unit', $xlsx_row[7]);
            $stmt->bindParam(':title', $xlsx_row[8]);
            $stmt->bindParam(':work', $xlsx_row[9]);
            $stmt->bindParam(':exam', $xlsx_row[10]);
            $stmt->bindParam(':education', $xlsx_row[11]);
            $stmt->bindParam(':onboard_date', $xlsx_row[12]);
            $stmt->bindValue(':offboard_date', $xlsx_row[13]);
            $stmt->bindParam(':ip', $xlsx_row[14]);
            $stmt->bindValue(':authority', $this->calculateAuthority($xlsx_row[14]));
            $stmt->bindParam(':birthday', $xlsx_row[15]);
            return $stmt->execute() === FALSE ? false : true;
        } else {
            $log->warning(__METHOD__.": 新增/更新使用者(".$xlsx_row[0].", ".$xlsx_row[1].")資料失敗！");
        }
        return false;
    }

    public function addUser($data) {
        global $log;
        if (empty($data['id'])) {
            $log->warning(__METHOD__.': id is a required param, it\'s empty.');
            return false;
        }
        if ($data['sex'] != 1) {
            $data['sex'] = 0;
        }
        if($stmt = $this->db->prepare("
          INSERT INTO user ('id', 'name', 'sex', 'addr', 'tel', 'ext', 'cell', 'unit', 'title', 'work', 'exam', 'education', 'onboard_date', 'offboard_date', 'ip', 'pw_hash', 'authority', 'birthday')
          VALUES (:id, :name, :sex, :addr, :tel, :ext, :cell, :unit, :title, :work, :exam, :education, :onboard_date, :offboard_date, :ip, '827ddd09eba5fdaee4639f30c5b8715d', :authority, :birthday)
        ")) {
            $stmt->bindParam(':id', $data['id']);
            $stmt->bindParam(':name', $data['name']);
            $stmt->bindParam(':sex', $data['sex']);
            $stmt->bindParam(':addr', $data['addr']);
            $stmt->bindParam(':tel', $data['tel']);
            $stmt->bindParam(':ext', $data['ext']);
            $stmt->bindParam(':cell', $data['cell']);
            $stmt->bindParam(':unit', $data['unit']);
            $stmt->bindParam(':title', $data['title']);
            $stmt->bindParam(':work', $data['work']);
            $stmt->bindParam(':exam', $data['exam']);
            $stmt->bindParam(':education', $data['education']);
            $stmt->bindParam(':onboard_date', $data['onboard_date']);
            $stmt->bindValue(':offboard_date', '');
            $stmt->bindParam(':ip', $data['ip']);
            $stmt->bindValue(':authority', $this->calculateAuthority($data['ip']));
            $stmt->bindParam(':birthday', $data['birthday']);
            return $stmt->execute() === FALSE ? false : true;
        } else {
            $log->warning(__METHOD__.": 新增使用者(".$data['id'].", ".$data['name'].")資料失敗！");
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

    public function saveUser($data) {
        global $log;
        if (empty($data['id'])) {
            $log->warning(__METHOD__.': id is a required param, it\'s empty.');
            return false;
        }
        if ($data['sex'] != 1) {
            $data['sex'] = 0;
        }
        if($stmt = $this->db->prepare("
            UPDATE user SET
                name = :name,
                sex = :sex,
                ext = :ext,
                cell = :cell,
                unit = :unit,
                title = :title,
                work = :work,
                exam = :exam,
                education = :education,
                ip = :ip,
                authority = :authority
            WHERE id = :id
        ")) {
            $stmt->bindParam(':id', $data['id']);
            $stmt->bindParam(':name', $data['name']);
            $stmt->bindParam(':sex', $data['sex']);
            $stmt->bindParam(':ext', $data['ext']);
            $stmt->bindParam(':cell', $data['cell']);
            $stmt->bindParam(':unit', $data['unit']);
            $stmt->bindParam(':title', $data['title']);
            $stmt->bindParam(':work', $data['work']);
            $stmt->bindParam(':exam', $data['exam']);
            $stmt->bindParam(':education', $data['education']);
            $stmt->bindParam(':ip', $data['ip']);
            $stmt->bindValue(':authority', $this->calculateAuthority($data['ip']));
            return $stmt->execute() === FALSE ? false : true;
        } else {
            $log->warning(__METHOD__.": 更新使用者(".$data['id'].")資料失敗！");
        }
        return false;
    }

    public function onboardUser($id) {
        global $log;
        if (empty($id)) {
            $log->warning(__METHOD__.': id is a required param, it\'s empty.');
            return false;
        }

        $today = new Datetime("now");
        $today = ltrim($today->format("Y/m/d"), "0");	// ex: 2021/01/21

        if($stmt = $this->db->prepare("
            UPDATE user SET
                offboard_date = :offboard_date,
                onboard_date = :onboard_date
            WHERE id = :id
        ")) {
            $stmt->bindParam(':id', $id);
            $stmt->bindParam(':onboard_date', $today);
            $stmt->bindValue(':offboard_date', '');
            return $stmt->execute() === FALSE ? false : true;
        } else {
            $log->warning(__METHOD__.": 復職使用者(".$id.")失敗！");
        }
        return false;
    }

    public function offboardUser($id) {
        global $log;
        if (empty($id)) {
            $log->warning(__METHOD__.': id is a required param, it\'s empty.');
            return false;
        }

        $today = new Datetime("now");
        $today = ltrim($today->format("Y/m/d"), "0");	// ex: 2021/01/21

        if($stmt = $this->db->prepare("
            UPDATE user SET
                offboard_date = :offboard_date
            WHERE id = :id
        ")) {
            $stmt->bindParam(':id', $id);
            $stmt->bindParam(':offboard_date', $today);
            return $stmt->execute() === FALSE ? false : true;
        } else {
            $log->warning(__METHOD__.": 離職使用者(".$id.")失敗！");
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

    public function updateExt($id, $ext) {
        if ($stm = $this->db->prepare("UPDATE user SET ext = :ext WHERE id = :id")) {
            $stm->bindParam(':ext', $ext);
            $stm->bindParam(':id', $id);
            return $stm->execute() === FALSE ? false : true;
        } else {
            global $log;
            $log->error(__METHOD__.": 更新分機(${id}, ${ext})資料失敗！");
            return false;
        }
    }

    public function getAuthorityList() {
        if($stmt = $this->db->prepare("
            SELECT
                a.role_id, a.ip AS role_ip,
                r.name AS role_name,
                u.*
            FROM authority a 
            LEFT JOIN role r ON a.role_id = r.id
            LEFT JOIN user u ON a.ip = u.ip AND (u.offboard_date = '')
            WHERE 1=1 ORDER BY r.name, a.ip
        ")) {
            return $this->prepareArray($stmt);
        } else {
            global $log;
            $log->error(__METHOD__.": 取得人員授權資料失敗！");
        }
        return false;
    }
}
