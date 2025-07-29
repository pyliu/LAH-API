<?php
require_once('init.php');
require_once('System.class.php');
require_once('IPResolver.class.php');
require_once('DynamicSQLite.class.php');
require_once('Cache.class.php');

class SQLiteUser {
    private $db;

    private function exists($id) {
        $ret = $this->db->querySingle("SELECT id from user WHERE id = '".trim($id)."'");
        return !empty($ret);
    }

    private function bindUserParams(&$stm, &$row) {

        if ($stm === false) {
            Logger::getInstance()->error(__METHOD__.": bindUserParams because of \$stm is false.");
            return;
        }
        
        // remove additional prefix by previous data in ora db
        $row['AP_USER_NAME'] = preg_replace("/(桃園所|中壢所|大溪所|楊梅所|蘆竹所|八德所|平鎮所|龜山所|桃園|中壢|大溪|楊梅|蘆竹|八德|平鎮|龜山)/i", '', $row['AP_USER_NAME']);
        
        $stm->bindParam(':id', $row['DocUserID']);
        $stm->bindParam(':name', $row['AP_USER_NAME']);
        $stm->bindValue(':sex', $row['AP_SEX'] == '男' ? 1 : 0);
        $stm->bindParam(':addr', $row['AP_ADR']);
        $stm->bindParam(':tel', $row['AP_TEL'], SQLITE3_TEXT);
        $stm->bindValue(':ext', empty($row['AP_EXT']) ? '411' : $row['AP_EXT'], SQLITE3_TEXT); // 總機 411
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
        
        // clean up AP_OFF_DATE when AP_OFF_JOB flag is N
        if ($row['AP_OFF_JOB'] !== 'Y') {
            $row['AP_OFF_DATE'] = '';
        } else if (empty($row['AP_OFF_DATE']) && $row['AP_OFF_JOB'] == 'Y') {
            $tw_date = new Datetime("now");
            $tw_date->modify("-1911 year");
            $today = ltrim($tw_date->format("Y/m/d"), "0");	// ex: 110/03/11
            $row['AP_OFF_DATE'] = $today;
        }
        $stm->bindParam(':offboard_date', $row['AP_OFF_DATE']);

        if (empty($row['AP_PCIP'])) {
            $stm->bindValue(':ip', '192.168.xx.xx');
        } else {
            $stm->bindParam(':ip', $row['AP_PCIP']);
        }

        // $stm->bindValue(':pw_hash', '827ddd09eba5fdaee4639f30c5b8715d');    // HB default
        $stm->bindValue(':authority', $this->getAuthority($row['DocUserID']));
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
        if ($result) {
            while($row = $result->fetchArray(SQLITE3_ASSOC)) {
                $return[] = $row;
            }
        } else {
            Logger::getInstance()->warning(__CLASS__."::".__METHOD__.": execute SQL unsuccessfully.");
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

    public function import(&$row) {
        if (empty($row['DocUserID'])) {
            Logger::getInstance()->warning(__METHOD__.": DocUserID is empty. Import user procedure can not be proceeded.");
            Logger::getInstance()->warning(__METHOD__.": ".print_r($row, true));
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
            
            Logger::getInstance()->error(__METHOD__.": 取得所有使用者資料失敗！");
        }
        return false;
    }

    public function getDepartmentUsers($dept, $valid = 'on_board_users') {
        $all = $dept === '全所' || $dept === 'all';
        $no_valid = $valid === 'all_users';
        $sql = "SELECT * FROM user WHERE ".($all ? "1=1" : "unit = :bv_unit");
        if ($valid === 'on_board_users' || $valid === 'true' || $valid === true) {
            $sql .= " AND ((authority & :disabled_bit) <> :disabled_bit AND offboard_date = '') ";
        } else if ($valid === 'off_board_users' || $valid === false || $valid === 'false') {
            $sql .= " AND ((authority & :disabled_bit) = :disabled_bit OR offboard_date <> '') ";
        }
        $sql .= " ORDER BY id";
        Logger::getInstance()->info($all);
        Logger::getInstance()->info($no_valid);
        Logger::getInstance()->info($dept);
        Logger::getInstance()->info($valid);
        Logger::getInstance()->info($sql);
        if($stmt = $this->db->prepare($sql)) {
            if (!$no_valid) {
                $stmt->bindValue(':disabled_bit', AUTHORITY::DISABLED, SQLITE3_INTEGER);
            }
            if (!$all) {
                $stmt->bindParam(':bv_unit', $dept);
            }
            return $this->prepareArray($stmt);
        } else {
            Logger::getInstance()->error(__METHOD__.": 取得部門使用者資料失敗！");
        }
        return false;
    }

    public function getOnboardUsers() {
        if($stmt = $this->db->prepare("SELECT * FROM user WHERE (authority & :disabled_bit) <> :disabled_bit AND offboard_date = '' ORDER BY id")) {
            $stmt->bindValue(':disabled_bit', AUTHORITY::DISABLED, SQLITE3_INTEGER);
            return $this->prepareArray($stmt);
        } else {
            Logger::getInstance()->error(__METHOD__.": 取得在職使用者資料失敗！");
        }
        return false;
    }

    public function getDisabledUsers() {
        if($stmt = $this->db->prepare("SELECT * FROM user WHERE (authority & :disabled_bit) = :disabled_bit OR offboard_date <> '' ORDER BY id")) {
            $stmt->bindValue(':disabled_bit', AUTHORITY::DISABLED, SQLITE3_INTEGER);
            return $this->prepareArray($stmt);
        } else {
            Logger::getInstance()->error(__METHOD__.": 取得離職使用者資料失敗！");
        }
        return false;
    }

    public function getOffboardUsers() {
        return $this->getDisabledUsers();
    }

    public function getAdmins() {
        if($stmt = $this->db->prepare("SELECT * FROM user WHERE (authority & :admin_bit) = :admin_bit AND (authority & :disabled_bit) <> :disabled_bit ORDER BY id")) {
            $stmt->bindValue(':admin_bit', AUTHORITY::ADMIN, SQLITE3_INTEGER);
            $stmt->bindValue(':disabled_bit', AUTHORITY::DISABLED, SQLITE3_INTEGER);
            return $this->prepareArray($stmt);
        } else {
            Logger::getInstance()->error(__METHOD__.": 取得管理者資料失敗！");
        }
        return false;
    }
    
    public function getChiefs() {
        if($stmt = $this->db->prepare("SELECT * FROM user WHERE (authority & :chief_bit) = :chief_bit AND (authority & :disabled_bit) <> :disabled_bit ORDER BY id")) {
            $stmt->bindValue(':chief_bit', AUTHORITY::CHIEF, SQLITE3_INTEGER);
            $stmt->bindValue(':disabled_bit', AUTHORITY::DISABLED, SQLITE3_INTEGER);
            return $this->prepareArray($stmt);
        } else {
            Logger::getInstance()->error(__METHOD__.": 取得主管資料失敗！");
        }
        return false;
    }

    public function getChief($unit) {
        if($stmt = $this->db->prepare("SELECT * FROM user WHERE (authority & :chief_bit) = :chief_bit AND (authority & :disabled_bit) <> :disabled_bit AND unit = :unit ORDER BY id")) {
            $stmt->bindValue(':chief_bit', AUTHORITY::CHIEF, SQLITE3_INTEGER);
            $stmt->bindValue(':disabled_bit', AUTHORITY::DISABLED, SQLITE3_INTEGER);
            $stmt->bindParam(':unit', $unit, SQLITE3_TEXT);
            // always return items in array, so return first element
            return $this->prepareArray($stmt)[0];
        } else {
            Logger::getInstance()->error(__METHOD__.": 取得 ${unit} 主管資料失敗！");
        }
        return false;
    }

    public function getStaffs($unit) {
        if($stmt = $this->db->prepare("SELECT * FROM user WHERE (authority & :disabled_bit) <> :disabled_bit AND unit = :unit ORDER BY id")) {
            $stmt->bindParam(':unit', $unit, SQLITE3_TEXT);
            $stmt->bindValue(':disabled_bit', AUTHORITY::DISABLED, SQLITE3_INTEGER);
            return $this->prepareArray($stmt);
        } else {
            
            Logger::getInstance()->error(__METHOD__.": 取得${unit}人員資料失敗！");
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
        
        if (empty($xlsx_row[0])) {
            Logger::getInstance()->warning(__METHOD__.': id is a required param, it\'s empty.');
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
            $stmt->bindValue(':authority', 0);  // TBD
            $stmt->bindParam(':birthday', $xlsx_row[15]);
            return $stmt->execute() === FALSE ? false : true;
        } else {
            Logger::getInstance()->warning(__METHOD__.": 新增/更新使用者(".$xlsx_row[0].", ".$xlsx_row[1].")資料失敗！");
        }
        return false;
    }

    public function addUser($data) {
        
        if (empty($data['id'])) {
            Logger::getInstance()->warning(__METHOD__.': id is a required param, it\'s empty.');
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
            $stmt->bindValue(':authority', $data['authority']);
            $stmt->bindParam(':birthday', $data['birthday']);
            return $stmt->execute() === FALSE ? false : true;
        } else {
            Logger::getInstance()->warning(__METHOD__.": 新增使用者(".$data['id'].", ".$data['name'].")資料失敗！");
        }
        return false;
    }

    public function autoImportUser($data) {
        if (empty($data['entry_id'])) {
            Logger::getInstance()->warning(__METHOD__.': id(entry_id) is a required param, it\'s empty.');
            return false;
        }
        $unit = IPResolver::parseUnit($data['note']);
        if ($this->exists($data['entry_id'])) {
            Logger::getInstance()->info(__METHOD__.': 更新使用者資訊 ('.$data['entry_id'].', '.$data['entry_desc'].', '.$unit.', '.$data['ip'].')');
            $operators = Cache::getInstance()->getUserNames();
            $mapped_name = $operators[$data['entry_id']] ?? $data['entry_desc'];
            // update
            if($stmt = $this->db->prepare("
                UPDATE user SET
                    name = :name,
                    offboard_date = :offboard_date
                WHERE id = :id
            ")) {
                $stmt->bindParam(':id', $data['entry_id']);
                $stmt->bindParam(':name', $mapped_name);
                // $stmt->bindParam(':unit', $unit);
                // $stmt->bindParam(':ip', $data['ip']);
                $stmt->bindValue(':offboard_date', '');
                return $stmt->execute() === FALSE ? false : true;
            }
        } else {
            Logger::getInstance()->info(__METHOD__.': 新增使用者資訊 ('.$data['entry_id'].', '.$data['entry_desc'].', '.$unit.', '.$data['ip'].')');
            // insert
            return $this->addUser(IPResolver::packUserData($data));
        }
    }

    public function getUser($id) {
        if($stmt = $this->db->prepare("SELECT * FROM user WHERE id = :id")) {
            $stmt->bindParam(':id', $id);
            return $this->prepareArray($stmt);
        } else {
            
            Logger::getInstance()->error(__METHOD__.": 取得使用者($id)資料失敗！");
        }
        return false;
        
    }

    public function saveUser($data) {
        
        if (empty($data['id'])) {
            Logger::getInstance()->warning(__METHOD__.': id is a required param, it\'s empty.');
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
                authority = :authority,
                onboard_date = :onboard_date,
                birthday = :birthday
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
            $stmt->bindValue(':authority', $data['authority']);
            $stmt->bindParam(':onboard_date', $data['onboard_date']);
            $stmt->bindParam(':onboard_date', $data['birthday']);
            return $stmt->execute() === FALSE ? false : true;
        } else {
            Logger::getInstance()->warning(__METHOD__.": 更新使用者(".$data['id'].")資料失敗！");
        }
        return false;
    }

    public function onboardUser($id) {
        
        if (empty($id)) {
            Logger::getInstance()->warning(__METHOD__.': id is a required param, it\'s empty.');
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
            Logger::getInstance()->warning(__METHOD__.": 復職使用者(".$id.")失敗！");
        }
        return false;
    }

    public function offboardUser($id) {
        
        if (empty($id)) {
            Logger::getInstance()->warning(__METHOD__.': id is a required param, it\'s empty.');
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
            Logger::getInstance()->warning(__METHOD__.": 離職使用者(".$id.")失敗！");
        }
        return false;
    }

    public function getUserByName($name) {
        if($stmt = $this->db->prepare("SELECT * FROM user WHERE name = :name")) {
            $stmt->bindParam(':name', $name);
            return $this->prepareArray($stmt);
        } else {
            Logger::getInstance()->error(__METHOD__.": 取得使用者($name)資料失敗！");
        }
        return false;
        
    }

    public function getUserByIP($ip, $on_board = false) {
        // To check if the $ip is from localhost
        if (in_array($ip, ['127.0.0.1', '::1'])) {
            // Logger::getInstance()->info(__METHOD__.': 偵測到來自 localhost IP，判定為系統管理者。');
            $site_code = System::getInstance()->getSiteCode();
            return array(IPResolver::packUserData(array(
                'ip' => $ip,
                'added_type' => 'STATIC',
                'entry_type' => 'SYSTEM',
                'entry_desc' => '系統管理者',
                'entry_id' => $site_code.'ADMIN',
                'timestamp' => time(),
                'note' => $site_code.'.CENWEB.MOI.LAND inf',
                'authority' => AUTHORITY::ADMIN
            )));
        } else {
            // To find IPResolver table record by ip
            // Logger::getInstance()->info(__METHOD__.': 利用 IPResolver.db IPResolver 表格資料查詢使用者資料。');
            $ipr = new IPResolver();
            $result = $ipr->getIPEntry($ip);
            if (empty($result)) {
                // Logger::getInstance()->warning(__METHOD__.": IPResolver.db IPResolver 表格查不到 $ip 資料。");
            } else {
                // add authority value into packed data
                $result[0]['authority'] = $this->getAuthority($result[0]['entry_id']);
                return array(IPResolver::packUserData($result[0]));
            }
            // authority stored at dimension.db user table
            if ($on_board) {
                if($stmt = $this->db->prepare("SELECT * FROM user WHERE ip = :ip AND (authority & :disabled_bit) <> :disabled_bit")) {
                    $stmt->bindParam(':ip', $ip);
                    $stmt->bindValue(':disabled_bit', AUTHORITY::DISABLED, SQLITE3_INTEGER);
                    $result = $this->prepareArray($stmt);
                    if(!empty($result)) {
                        return $result;
                    }
                    // Logger::getInstance()->warning(__METHOD__.": 從 dimension.db user 表格取得使用者($ip)資料失敗！");
                }
            } else {
                if($stmt = $this->db->prepare("SELECT * FROM user WHERE ip = :ip")) {
                    $stmt->bindParam(':ip', $ip);
                    $result = $this->prepareArray($stmt);
                    if(!empty($result)) {
                        return $result;
                    }
                    // Logger::getInstance()->warning(__METHOD__.": 從 dimension.db user 表格取得使用者($ip)資料失敗！");
                }
            }
        }

        return false;
        
    }

    public function updateExt($id, $ext) {
        if ($stm = $this->db->prepare("UPDATE user SET ext = :ext WHERE id = :id")) {
            $stm->bindParam(':ext', $ext);
            $stm->bindParam(':id', $id);
            return $stm->execute() === FALSE ? false : true;
        } else {
            
            Logger::getInstance()->error(__METHOD__.": 更新分機(${id}, ${ext})資料失敗！");
            return false;
        }
    }

    public function updateDept($id, $unit) {
        if ($stm = $this->db->prepare("UPDATE user SET unit = :unit WHERE id = :id")) {
            $stm->bindParam(':unit', $unit);
            $stm->bindParam(':id', $id);
            return $stm->execute() === FALSE ? false : true;
        } else {
            Logger::getInstance()->error(__METHOD__.": 更新部門(${id}, ${unit})資料失敗！");
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
            
            Logger::getInstance()->error(__METHOD__.": 取得人員授權資料失敗！");
        }
        return false;
    }
}
