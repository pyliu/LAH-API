<?php
require_once('init.php');
require_once('System.class.php');
require_once('IPResolver.class.php');
require_once('DynamicSQLite.class.php');
require_once('Cache.class.php');

/**
 * Class SQLiteUser
 * 負責處理本地 SQLite (dimension.db) 中的使用者資料維護與查詢。
 * 包含與 L3HWEB、AD 系統的資料同步邏輯，以及使用者 IP 軌跡的追蹤比對。
 */
class SQLiteUser {
    /** @var SQLite3 資料庫連線實例 */
    private $db;

    /**
     * 檢查使用者 ID 是否已存在於資料庫中
     * @param string $id 使用者代碼
     * @return bool
     */
    private function exists($id) {
        $ret = $this->db->querySingle("SELECT id from user WHERE id = '".trim($id)."'");
        return !empty($ret);
    }

    /**
     * 綁定使用者資料參數至 SQL 語句
     * 此處包含多種格式轉換邏輯：
     * 1. 移除姓名中的地政事務所前綴（如「桃園所」）。
     * 2. 將報到日期轉換為 YYYY/MM/DD。
     * 3. 根據離職旗標自動填補離職日期。
     * 4. 預設分機與 IP 處理。
     * @param SQLite3Stmt $stm 準備好的語句
     * @param array $row 原始資料陣列
     */
    private function bindUserParams(&$stm, &$row) {
        if ($stm === false) {
            Logger::getInstance()->error(__METHOD__.": bindUserParams because of \$stm is false.");
            return;
        }
        
        // [業務邏輯] 移除舊資料中可能存在的單位前綴，保持姓名乾淨
        $row['AP_USER_NAME'] = preg_replace("/(桃園所|中壢所|大溪所|楊梅所|蘆竹所|八德所|平鎮所|龜山所|桃園|中壢|大溪|楊梅|蘆竹|八德|平鎮|龜山)/i", '', $row['AP_USER_NAME'] ?? '');
        
        $stm->bindParam(':id', $row['DocUserID']);
        $stm->bindParam(':name', $row['AP_USER_NAME']);
        $stm->bindValue(':sex', ($row['AP_SEX'] ?? '') == '男' ? 1 : 0);
        $stm->bindParam(':addr', $row['AP_ADR']);
        $stm->bindParam(':tel', $row['AP_TEL'], SQLITE3_TEXT);
        // 若無分機則設為總機 411
        $stm->bindValue(':ext', empty($row['AP_EXT']) ? '411' : $row['AP_EXT'], SQLITE3_TEXT);
        $stm->bindParam(':cell', $row['AP_SEL'], SQLITE3_TEXT);
        $stm->bindParam(':unit', $row['AP_UNIT_NAME']);
        $stm->bindParam(':title', $row['AP_JOB']);
        $stm->bindValue(':work', empty($row['unitname2']) ? ($row['AP_WORK'] ?? '') : $row['unitname2']);
        $stm->bindParam(':exam', $row['AP_TEST']);
        $stm->bindParam(':education', $row['AP_HI_SCHOOL']);
        $stm->bindParam(':birthday', $row['AP_BIRTH']);

        // [格式轉換] 將報到日期 (M D Y) 轉換為標準 YYYY/MM/DD
        $tokens = preg_split("/\s+/", $row['AP_ON_DATE'] ?? '');
        if (count($tokens) == 3) {
            $rewrite = $tokens[2]."/".str_pad($tokens[0], 2, '0', STR_PAD_LEFT)."/".str_pad($tokens[1], 2, '0', STR_PAD_LEFT);
            $stm->bindParam(':onboard_date', $rewrite);
        } else {
            $stm->bindParam(':onboard_date', $row['AP_ON_DATE']);
        }
        
        // [業務邏輯] 處理離職日期。若離職標記為 Y 但日期為空，則補上今日
        if (($row['AP_OFF_JOB'] ?? '') !== 'Y') {
            $row['AP_OFF_DATE'] = '';
        } else if (empty($row['AP_OFF_DATE']) && ($row['AP_OFF_JOB'] ?? '') == 'Y') {
            $tw_date = new Datetime("now");
            $tw_date->modify("-1911 year");
            $today = ltrim($tw_date->format("Y/m/d"), "0");
            $row['AP_OFF_DATE'] = $today;
        }
        $stm->bindParam(':offboard_date', $row['AP_OFF_DATE']);

        // 預設 IP 處理
        if (empty($row['AP_PCIP'])) {
            $stm->bindValue(':ip', '192.168.xx.xx');
        } else {
            $stm->bindParam(':ip', $row['AP_PCIP']);
        }

        // 權限繼承自目前資料庫設定，若無則預設
        $stm->bindValue(':authority', $this->getAuthority($row['DocUserID']));
    }

    /**
     * 使用 REPLACE INTO 寫入或更新使用者資料
     */
    private function replace(&$row) {
        $stm = $this->db->prepare("
            REPLACE INTO user ('id', 'name', 'sex', 'addr', 'tel', 'ext', 'cell', 'unit', 'title', 'work', 'exam', 'education', 'onboard_date', 'offboard_date', 'ip', 'pw_hash', 'authority', 'birthday')
            VALUES (:id, :name, :sex, :addr, :tel, :ext, :cell, :unit, :title, :work, :exam, :education, :onboard_date, :offboard_date, :ip, '827ddd09eba5fdaee4639f30c5b8715d', :authority, :birthday)
        ");
        $this->bindUserParams($stm, $row);
        return $stm->execute() === FALSE ? false : true;
    }
    
    /**
     * 將執行結果轉換為關聯陣列
     */
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

    /**
     * 取得並初始化 Dimension 資料庫檔案路徑，若資料表不存在則自動建立
     */
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

    /**
     * 建構子：連線資料庫並設定效能參數 (WAL 模式)
     */
    function __construct() {
        $db_path = $this->getDimensionDB();
        $this->db = new SQLite3($db_path);
        // 設定 WAL 模式與快取，優化高併發讀寫效能
        $this->db->exec("PRAGMA journal_mode = WAL");
        $this->db->exec("PRAGMA cache_size = 100000");
        $this->db->exec("PRAGMA temp_store = MEMORY");
        $this->db->exec("BEGIN TRANSACTION");
    }

    /**
     * 解構子：提交事務並關閉連線
     */
    function __destruct() {
        $this->db->exec("END TRANSACTION");
        $this->db->close();
    }

    /**
     * 從外部來源匯入使用者資料
     * @param array $row 使用者資料
     */
    public function import(&$row) {
        if (empty($row['DocUserID'])) {
            Logger::getInstance()->warning(__METHOD__.": DocUserID is empty. Import user procedure can not be proceeded.");
            return false;
        }
        return $this->replace($row);
    }

    /**
     * 從 Excel 匯入列資料
     */
    public function importXlsxUser(&$xlsx_row) {
        if (empty($xlsx_row[0])) {
            Logger::getInstance()->warning(__METHOD__.': id is a required param, it\'s empty.');
            return false;
        }
        $sex = ($xlsx_row[2] === '女' || $xlsx_row[2] === '0') ? 0 : 1;
        
        if($stmt = $this->db->prepare("
          REPLACE INTO user ('id', 'name', 'sex', 'addr', 'tel', 'ext', 'cell', 'unit', 'title', 'work', 'exam', 'education', 'onboard_date', 'offboard_date', 'ip', 'pw_hash', 'authority', 'birthday')
          VALUES (:id, :name, :sex, :addr, :tel, :ext, :cell, :unit, :title, :work, :exam, :education, :onboard_date, :offboard_date, :ip, '827ddd09eba5fdaee4639f30c5b8715d', :authority, :birthday)
        ")) {
            $stmt->bindParam(':id', $xlsx_row[0]);
            $stmt->bindParam(':name', $xlsx_row[1]);
            $stmt->bindParam(':sex', $sex);
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
            $stmt->bindValue(':offboard_date', $xlsx_row[13] ?? '');
            $stmt->bindParam(':ip', $xlsx_row[14]);
            $stmt->bindValue(':authority', 0);
            $stmt->bindParam(':birthday', $xlsx_row[15]);
            return $stmt->execute() === FALSE ? false : true;
        }
        return false;
    }

    /**
     * 取得使用者的權限數值
     */
    public function getAuthority($id) {
        return  $this->db->querySingle("SELECT authority from user WHERE id = '".trim($id)."'") ?? AUTHORITY::NORMAL;
    }

    /**
     * 取得資料庫中所有使用者
     */
    public function getAllUsers() {
        if($stmt = $this->db->prepare("SELECT * FROM user WHERE 1 = 1 ORDER BY id")) {
            return $this->prepareArray($stmt);
        } else {
            Logger::getInstance()->error(__METHOD__.": 取得所有使用者資料失敗！");
        }
        return false;
    }

    /**
     * 根據部門取得使用者資料
     */
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
        
        if($stmt = $this->db->prepare($sql)) {
            if (!$no_valid) {
                $stmt->bindValue(':disabled_bit', AUTHORITY::DISABLED, SQLITE3_INTEGER);
            }
            if (!$all) {
                $stmt->bindParam(':bv_unit', $dept);
            }
            return $this->prepareArray($stmt);
        }
        return false;
    }

    /**
     * 取得目前所有在職人員
     */
    public function getOnboardUsers() {
        if($stmt = $this->db->prepare("SELECT * FROM user WHERE (authority & :disabled_bit) <> :disabled_bit AND offboard_date = '' ORDER BY id")) {
            $stmt->bindValue(':disabled_bit', AUTHORITY::DISABLED, SQLITE3_INTEGER);
            return $this->prepareArray($stmt);
        } else {
            Logger::getInstance()->error(__METHOD__.": 取得在職使用者資料失敗！");
        }
        return false;
    }

    /**
     * 取得目前所有停用或離職人員
     */
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

    /**
     * 取得系統管理者列表
     */
    public function getAdmins() {
        if($stmt = $this->db->prepare("SELECT * FROM user WHERE (authority & :admin_bit) = :admin_bit AND (authority & :disabled_bit) <> :disabled_bit ORDER BY id")) {
            $stmt->bindValue(':admin_bit', AUTHORITY::ADMIN, SQLITE3_INTEGER);
            $stmt->bindValue(':disabled_bit', AUTHORITY::DISABLED, SQLITE3_INTEGER);
            return $this->prepareArray($stmt);
        }
        return false;
    }
    
    /**
     * 取得主管列表
     */
    public function getChiefs() {
        if($stmt = $this->db->prepare("SELECT * FROM user WHERE (authority & :chief_bit) = :chief_bit AND (authority & :disabled_bit) <> :disabled_bit ORDER BY id")) {
            $stmt->bindValue(':chief_bit', AUTHORITY::CHIEF, SQLITE3_INTEGER);
            $stmt->bindValue(':disabled_bit', AUTHORITY::DISABLED, SQLITE3_INTEGER);
            return $this->prepareArray($stmt);
        }
        return false;
    }

    public function getChief($unit) {
        if($stmt = $this->db->prepare("SELECT * FROM user WHERE (authority & :chief_bit) = :chief_bit AND (authority & :disabled_bit) <> :disabled_bit AND unit = :unit ORDER BY id")) {
            $stmt->bindValue(':chief_bit', AUTHORITY::CHIEF, SQLITE3_INTEGER);
            $stmt->bindValue(':disabled_bit', AUTHORITY::DISABLED, SQLITE3_INTEGER);
            $stmt->bindParam(':unit', $unit, SQLITE3_TEXT);
            $res = $this->prepareArray($stmt);
            return isset($res[0]) ? $res[0] : null;
        }
        return false;
    }

    public function getStaffs($unit) {
        if($stmt = $this->db->prepare("SELECT * FROM user WHERE (authority & :disabled_bit) <> :disabled_bit AND unit = :unit ORDER BY id")) {
            $stmt->bindParam(':unit', $unit, SQLITE3_TEXT);
            $stmt->bindValue(':disabled_bit', AUTHORITY::DISABLED, SQLITE3_INTEGER);
            return $this->prepareArray($stmt);
        }
        return false;
    }

    /**
     * 產生組織架構圖用的樹狀結構
     */
    public function getTreeData($unit) {
        $chief = $this->getChief($unit);
        if (!$chief) return array();
        
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

    /**
     * 手動新增使用者
     */
    public function addUser($data) {
        if (empty($data['id'])) return false;
        $sex = ($data['sex'] ?? 0) != 1 ? 0 : 1;
        
        if($stmt = $this->db->prepare("
          INSERT INTO user ('id', 'name', 'sex', 'addr', 'tel', 'ext', 'cell', 'unit', 'title', 'work', 'exam', 'education', 'onboard_date', 'offboard_date', 'ip', 'pw_hash', 'authority', 'birthday')
          VALUES (:id, :name, :sex, :addr, :tel, :ext, :cell, :unit, :title, :work, :exam, :education, :onboard_date, :offboard_date, :ip, '827ddd09eba5fdaee4639f30c5b8715d', :authority, :birthday)
        ")) {
            $stmt->bindParam(':id', $data['id']);
            $stmt->bindParam(':name', $data['name']);
            $stmt->bindParam(':sex', $sex);
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
        }
        return false;
    }

    /**
     * 儲存/更新使用者詳細資料
     */
    public function saveUser($data) {
        if (empty($data['id'])) {
            Logger::getInstance()->warning(__METHOD__.': id is a required param, it\'s empty.');
            return false;
        }
        $sex = (($data['sex'] ?? 0) != 1) ? 0 : 1;
        
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
            $stmt->bindParam(':sex', $sex);
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
            $stmt->bindParam(':birthday', $data['birthday']);
            return $stmt->execute() === FALSE ? false : true;
        }
        return false;
    }

    /**
     * 根據 IPResolver 資料自動匯入或更新
     */
    public function autoImportUser($data) {
        if (empty($data['entry_id'])) return false;
        
        $unit = IPResolver::parseUnit($data['note']);
        if ($this->exists($data['entry_id'])) {
            $operators = Cache::getInstance()->getUserNames();
            $mapped_name = $operators[$data['entry_id']] ?? $data['entry_desc'];
            if($stmt = $this->db->prepare("UPDATE user SET name = :name, offboard_date = '' WHERE id = :id")) {
                $stmt->bindParam(':id', $data['entry_id']);
                $stmt->bindParam(':name', $mapped_name);
                return $stmt->execute() === FALSE ? false : true;
            }
        } else {
            return $this->addUser(IPResolver::packUserData($data));
        }
        return false;
    }

    public function getUser($id) {
        if($stmt = $this->db->prepare("SELECT * FROM user WHERE id = :id")) {
            $stmt->bindParam(':id', $id);
            return $this->prepareArray($stmt);
        }
        return false;
    }

    public function getUserByName($name) {
        if($stmt = $this->db->prepare("SELECT * FROM user WHERE name = :name")) {
            $stmt->bindParam(':name', $name);
            return $this->prepareArray($stmt);
        }
        return false;
    }

    public function getUserByIP($ip, $on_board = false) {
        if (in_array($ip, ['127.0.0.1', '::1'])) {
            $site_code = System::getInstance()->getSiteCode();
            return array(IPResolver::packUserData(array(
                'ip' => $ip, 'added_type' => 'STATIC', 'entry_type' => 'SYSTEM',
                'entry_desc' => '系統管理者', 'entry_id' => $site_code.'ADMIN',
                'timestamp' => time(), 'note' => $site_code.'.CENWEB.MOI.LAND inf',
                'authority' => AUTHORITY::ADMIN
            )));
        } else {
            $ipr = new IPResolver();
            $result = $ipr->getIPEntry($ip);
            if (!empty($result)) {
                $result[0]['authority'] = $this->getAuthority($result[0]['entry_id']);
                return array(IPResolver::packUserData($result[0]));
            }

            $sql = "SELECT * FROM user WHERE ip = :ip";
            if ($on_board) $sql .= " AND (authority & :disabled_bit) <> :disabled_bit AND offboard_date = ''";
            
            if($stmt = $this->db->prepare($sql)) {
                $stmt->bindParam(':ip', $ip);
                if ($on_board) $stmt->bindValue(':disabled_bit', AUTHORITY::DISABLED, SQLITE3_INTEGER);
                $res = $this->prepareArray($stmt);
                if(!empty($res)) return $res;
            }
        }
        return false;
    }

    public function onboardUser($id) {
        if (empty($id)) return false;
        $today = date("Y/m/d");
        if($stmt = $this->db->prepare("UPDATE user SET offboard_date = '', onboard_date = :onboard_date WHERE id = :id")) {
            $stmt->bindParam(':id', $id);
            $stmt->bindParam(':onboard_date', $today);
            return $stmt->execute() === FALSE ? false : true;
        }
        return false;
    }

    public function offboardUser($id) {
        if (empty($id)) return false;
        $today = date("Y/m/d");
        if($stmt = $this->db->prepare("UPDATE user SET offboard_date = :offboard_date WHERE id = :id")) {
            $stmt->bindParam(':id', $id);
            $stmt->bindParam(':offboard_date', $today);
            return $stmt->execute() === FALSE ? false : true;
        }
        return false;
    }

    /**
     * 更新使用者姓名
     */
    public function updateName($id, $name) {
        if ($stm = $this->db->prepare("UPDATE user SET name = :name WHERE id = :id")) {
            $stm->bindParam(':name', $name);
            $stm->bindParam(':id', $id);
            return $stm->execute() === FALSE ? false : true;
        }
        return false;
    }

    /**
     * 更新使用者分機
     */
    public function updateExt($id, $ext) {
        if ($stm = $this->db->prepare("UPDATE user SET ext = :ext WHERE id = :id")) {
            $stm->bindParam(':ext', $ext);
            $stm->bindParam(':id', $id);
            return $stm->execute() === FALSE ? false : true;
        }
        return false;
    }

    /**
     * 更新使用者單位
     */
    public function updateDept($id, $unit) {
        if ($stm = $this->db->prepare("UPDATE user SET unit = :unit WHERE id = :id")) {
            $stm->bindParam(':unit', $unit);
            $stm->bindParam(':id', $id);
            return $stm->execute() === FALSE ? false : true;
        }
        return false;
    }

    /**
     * 更新使用者 IP (嚴格限制僅能使用內部私有網段 IP)
     */
    public function updateIp($id, $ip) {
        if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) return false;
        $is_public = filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
        if ($is_public !== false) return false;
        
        if ($stm = $this->db->prepare("UPDATE user SET ip = :ip WHERE id = :id")) {
            $stm->bindParam(':ip', $ip);
            $stm->bindParam(':id', $id);
            return $stm->execute() === FALSE ? false : true;
        }
        return false;
    }

    /**
     * 輔助方法：優先權重網段判定
     */
    private function isPriorityIp($ip) {
        if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) return false;
        $is_private = filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false;
        if (!$is_private) return false;

        $site = System::getInstance()->getSiteCode();
        $parts = explode('.', $ip);
        if (count($parts) !== 4) return false;
        
        $third_octet = (int)$parts[2];
        if ($parts[0] === '192' && $parts[1] === '168') {
            $site_ranges = [
                'HA' => [10, 16], 'HB' => [20, 26], 'HC' => [30, 36], 'HD' => [40, 46],
                'HE' => [50, 56], 'HF' => [60, 66], 'HG' => [70, 76], 'HH' => [80, 86]
            ];
            if (isset($site_ranges[$site])) {
                $range = $site_ranges[$site];
                return ($third_octet >= $range[0] && $third_octet <= $range[1]);
            }
            return true;
        }
        return !in_array($site, ["HA", "HB", "HC", "HD", "HE", "HF", "HG", "HH"]);
    }

    /**
     * 同步使用者動態 IP 資料
     */
    public function syncUserDynamicIP($interval = 604800) {
        Logger::getInstance()->info(__METHOD__ . ": 開始執行使用者動態 IP 同步分析 (區間: " . ($interval / 86400) . " 天)");
        $ipr = new IPResolver();
        $rows = $ipr->getDynamicIPEntries($interval);
        
        if (empty($rows)) return ['auto_updated' => [], 'conflicts' => []];

        $userMap = [];
        foreach ($rows as $row) {
            $uid = $row['entry_id'];
            if (!isset($userMap[$uid])) $userMap[$uid] = [];
            $userMap[$uid][] = $row;
        }

        $autoUpdateList = [];
        $manualConflictList = [];
        $users = $this->getOnboardUsers();
        
        foreach ($users as $user) {
            $uid = $user['id'];
            if (!isset($userMap[$uid])) continue;

            $records = $userMap[$uid];
            $ips = array_unique(array_column($records, 'ip'));
            $diffIps = array_filter($ips, function($ip) use ($user) { return $ip !== $user['ip']; });
            $diffIps = array_values($diffIps);

            if (empty($diffIps)) continue;

            $prioritizedIps = array_filter($diffIps, [$this, 'isPriorityIp']);
            $prioritizedIps = array_values($prioritizedIps);

            $selected_ip = null;
            if (count($prioritizedIps) === 1) {
                $selected_ip = $prioritizedIps[0];
            } else if (count($prioritizedIps) === 0 && count($diffIps) === 1) {
                $selected_ip = $diffIps[0];
            }

            if ($selected_ip) {
                if ($this->updateIp($uid, $selected_ip)) {
                    $autoUpdateList[] = ['id' => $uid, 'name' => $user['name'], 'ip' => $selected_ip];
                }
            } else {
                $pool = count($prioritizedIps) > 0 ? $prioritizedIps : $diffIps;
                $candidates = [];
                foreach ($pool as $ip) {
                    $latest_ts = 0;
                    foreach ($records as $r) { if ($r['ip'] === $ip && $r['timestamp'] > $latest_ts) $latest_ts = $r['timestamp']; }
                    $candidates[] = ['ip' => $ip, 'timestamp' => date('Y-m-d H:i:s', $latest_ts)];
                }
                $manualConflictList[] = [ 'id' => $uid, 'name' => $user['name'], 'currentIp' => $user['ip'], 'candidates' => $candidates ];
            }
        }
        return ['auto_updated' => $autoUpdateList, 'conflicts' => $manualConflictList];
    }

    public function getAuthorityList() {
        if($stmt = $this->db->prepare("
            SELECT a.role_id, a.ip AS role_ip, r.name AS role_name, u.*
            FROM authority a 
            LEFT JOIN role r ON a.role_id = r.id
            LEFT JOIN user u ON a.ip = u.ip AND (u.offboard_date = '')
            WHERE 1=1 ORDER BY r.name, a.ip
        ")) { return $this->prepareArray($stmt); }
        return false;
    }

    /**
     * 與 AD 使用者清單同步
     * 核心邏輯：
     * 1. 建立 AD 到 SQLite 的新增與姓名更新。
     * 2. 若使用者不在 AD 且在本地為「在職」，則設為「離職」。
     * * @param array $ad_users (選用) 外部傳入的 AD 使用者陣列，若無則自動呼叫 AdService 抓取
     * @return array|bool 同步統計結果，失敗回傳 false
     */
    public function syncAdUsers(array $ad_users = []) {
        // [步驟 1] 若未傳入資料，嘗試透過 AdService 取得 AD 中所有有效使用者
        if (empty($ad_users)) {
            if (class_exists('AdService')) {
                $ad = new AdService();
                $ad_users = $ad->getValidUsers();
            }
            if (empty($ad_users)) return false;
        }

        $site_code = System::getInstance()->getSiteCode();
        $stats = ['added' => 0, 'updated' => 0, 'skipped' => 0, 'failed' => 0, 'offboarded' => 0];
        $ad_user_ids = []; // 用於記錄 AD 中出現的所有 ID，最後比對離職人員

        // [步驟 2] 遍歷 AD 使用者名單，執行「新增」或「更新」邏輯
        foreach ($ad_users as $user) {
            // 過濾非本所人員 (根據 ID 前綴判定，如 HA 開頭)
            if (!isset($user['id']) || !startsWith($user['id'], $site_code)) continue;
            
            $id = $user['id'];
            $name = $user['name'];
            $ad_user_ids[$id] = true; // 標記此 ID 在 AD 為活耀狀態
            
            // 檢查本地資料庫是否已有此人
            $local_rows = $this->getUser($id);
            
            if (empty($local_rows)) {
                // 情境 A: 本地無資料 -> 執行「新增」
                
                // 根據 AD 回傳的部門陣列，尋找第一個以「課」或「室」結尾的部門作為主單位
                $unit = '人事室'; // 預設值
                if (!empty($user['department']) && is_array($user['department'])) {
                    foreach ($user['department'] as $dept) {
                        if (mb_substr($dept, -1, 1, 'UTF-8') === '課' || mb_substr($dept, -1, 1, 'UTF-8') === '室') {
                            $unit = $dept;
                            break;
                        }
                    }
                }
                
                // 初始化新使用者資料結構
                $new_data = [
                    'id' => $id, 
                    'name' => $name, 
                    'sex' => 1, 
                    'addr' => '', 
                    'tel' => '', 
                    'ext' => '411',
                    'cell' => '', 
                    'unit' => $unit, 
                    'title' => '其他', 
                    'work' => '', 
                    'exam' => '',
                    'education' => '', 
                    'onboard_date' => date('Y/m/d'), 
                    'ip' => '', 
                    'authority' => 0, 
                    'birthday' => ''
                ];
                
                if ($this->addUser($new_data)) {
                    Logger::getInstance()->info(__METHOD__.": [Sync] 成功新增 AD 使用者 {$id} ({$name})");
                    $stats['added']++; 
                } else {
                    $stats['failed']++;
                }
            } else {
                // 情境 B: 本地已有資料 -> 執行「狀態檢查」與「資料更新」
                $local_user = $local_rows[0];
                
                // B-1. 若本地標記為離職但 AD 仍有效，執行「復職/重新報到」
                if (!empty($local_user['offboard_date'])) {
                    $this->onboardUser($id);
                    Logger::getInstance()->info(__METHOD__.": [Sync] 使用者 {$id} ({$name}) 偵測到復職，已更新狀態為在職");
                }
                
                // B-2. 檢查姓名是否有變動
                if ($local_user['name'] !== $name) {
                    if ($this->updateName($id, $name)) {
                        Logger::getInstance()->info(__METHOD__.": [Sync] 使用者 {$id} 姓名變更: {$local_user['name']} -> {$name}");
                        $stats['updated']++; 
                    } else {
                        $stats['failed']++;
                    }
                } else {
                    // 姓名與狀態均無變動，跳過
                    $stats['skipped']++;
                }
            }
        }

        // [步驟 3] 執行「離職處理」: 檢查本地在職人員是否已從 AD 消失
        $onboard_users = $this->getOnboardUsers();
        if ($onboard_users) {
            foreach ($onboard_users as $local_user) {
                $uid = $local_user['id'];
                
                // 如果本地 ID 不在剛才統計的 AD 活耀 ID 清單中
                if (!isset($ad_user_ids[$uid])) {
                    // 執行離職設定
                    if ($this->offboardUser($uid)) {
                        Logger::getInstance()->info(__METHOD__.": [Sync] 使用者 {$uid} ({$local_user['name']}) 已不在 AD 有效名單，設為離職");
                        $stats['offboarded']++; 
                    } else {
                        $stats['failed']++;
                    }
                }
            }
        }
        
        return $stats;
    }
}
