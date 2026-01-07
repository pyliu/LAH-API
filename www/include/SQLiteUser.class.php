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
     * * @param SQLite3Stmt $stm 準備好的語句
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
        } else if (empty($row['AP_OFF_DATE']) && $row['AP_OFF_JOB'] == 'Y') {
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
     * @param string $dept 部門名稱
     * @param string|bool $valid 篩選狀態 (在職、離職、全部)
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
        } else {
            Logger::getInstance()->error(__METHOD__.": 取得部門使用者資料失敗！");
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
        } else {
            Logger::getInstance()->error(__METHOD__.": 取得管理者資料失敗！");
        }
        return false;
    }
    
    /**
     * 取得所有科室主管列表
     */
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

    /**
     * 取得特定部門的主管
     */
    public function getChief($unit) {
        if($stmt = $this->db->prepare("SELECT * FROM user WHERE (authority & :chief_bit) = :chief_bit AND (authority & :disabled_bit) <> :disabled_bit AND unit = :unit ORDER BY id")) {
            $stmt->bindValue(':chief_bit', AUTHORITY::CHIEF, SQLITE3_INTEGER);
            $stmt->bindValue(':disabled_bit', AUTHORITY::DISABLED, SQLITE3_INTEGER);
            $stmt->bindParam(':unit', $unit, SQLITE3_TEXT);
            $res = $this->prepareArray($stmt);
            return isset($res[0]) ? $res[0] : null;
        } else {
            Logger::getInstance()->error(__METHOD__.": 取得 ${unit} 主管資料失敗！");
        }
        return false;
    }

    /**
     * 取得特定部門的所有在職人員
     */
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

    /**
     * 手動新增使用者
     */
    public function addUser($data) {
        if (empty($data['id'])) {
            Logger::getInstance()->warning(__METHOD__.': id is a required param, it\'s empty.');
            return false;
        }
        if (($data['sex'] ?? 0) != 1) {
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
            Logger::getInstance()->warning(__METHOD__.": 新增使用者(".$data['id'].", ".($data['name'] ?? '').")資料失敗！");
        }
        return false;
    }

    /**
     * 取得單一使用者資料
     */
    public function getUser($id) {
        if($stmt = $this->db->prepare("SELECT * FROM user WHERE id = :id")) {
            $stmt->bindParam(':id', $id);
            return $this->prepareArray($stmt);
        } else {
            Logger::getInstance()->error(__METHOD__.": 取得使用者($id)資料失敗！");
        }
        return false;
    }

    /**
     * 根據姓名搜尋使用者
     */
    public function getUserByName($name) {
        if($stmt = $this->db->prepare("SELECT * FROM user WHERE name = :name")) {
            $stmt->bindParam(':name', $name);
            return $this->prepareArray($stmt);
        } else {
            Logger::getInstance()->error(__METHOD__.": 取得使用者($name)資料失敗！");
        }
        return false;
    }

    /**
     * 根據 IP 取得使用者資訊
     * 整合多層次判定：
     * 1. Localhost (127.0.0.1) 自動判定為系統管理者。
     * 2. 查詢 IPResolver.db，尋找最新的動態分配記錄。
     * 3. 查詢 dimension.db 中的使用者固定 IP 欄位。
     */
    public function getUserByIP($ip, $on_board = false) {
        // 1. 檢查是否為 localhost (判定為系統管理者)
        if (in_array($ip, ['127.0.0.1', '::1'])) {
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
            // 2. 嘗試從 IPResolver 動態記錄中尋找使用者 (此處資料通常較即時)
            $ipr = new IPResolver();
            $result = $ipr->getIPEntry($ip);
            if (!empty($result)) {
                $result[0]['authority'] = $this->getAuthority($result[0]['entry_id']);
                return array(IPResolver::packUserData($result[0]));
            }

            // 3. 從本地 user 表格中根據 IP 欄位比對尋找
            if ($on_board) {
                // 僅限在職
                if($stmt = $this->db->prepare("SELECT * FROM user WHERE ip = :ip AND (authority & :disabled_bit) <> :disabled_bit AND offboard_date = ''")) {
                    $stmt->bindParam(':ip', $ip);
                    $stmt->bindValue(':disabled_bit', AUTHORITY::DISABLED, SQLITE3_INTEGER);
                    $result = $this->prepareArray($stmt);
                    if(!empty($result)) return $result;
                }
            } else {
                // 不限狀態
                if($stmt = $this->db->prepare("SELECT * FROM user WHERE ip = :ip")) {
                    $stmt->bindParam(':ip', $ip);
                    $result = $this->prepareArray($stmt);
                    if(!empty($result)) return $result;
                }
            }
        }
        return false;
    }

    /**
     * 設定使用者為在職狀態
     */
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

    /**
     * 設定使用者為離職狀態
     */
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
     * 更新使用者 IP (嚴格限制僅能使用內部私有網段 IP)
     */
    public function updateIp($id, $ip) {
        if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            Logger::getInstance()->warning(__METHOD__.": 更新失敗 - 無效的 IP 格式 ($ip)");
            return false;
        }
        // 使用 FILTER_FLAG_NO_PRIV_RANGE 確保 IP 位於私有網段 (Private Range)
        $is_public = filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
        if ($is_public !== false) {
            Logger::getInstance()->warning(__METHOD__.": 更新失敗 - 拒絕非內部 IP ($ip)");
            return false;
        }
        if ($stm = $this->db->prepare("UPDATE user SET ip = :ip WHERE id = :id")) {
            $stm->bindParam(':ip', $ip);
            $stm->bindParam(':id', $id);
            return $stm->execute() === FALSE ? false : true;
        }
        return false;
    }

    /**
     * 輔助方法：判斷是否為「優先權重」之站台內部 IP (Priority IP)
     * 基於各站台定義的 192.168.X0 ~ 192.168.X6 核心辦公網段。
     * @param string $ip
     * @return bool
     */
    private function isPriorityIp($ip) {
        if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) return false;
        $is_private = filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false;
        if (!$is_private) return false;

        $site = System::getInstance()->getSiteCode();
        $parts = explode('.', $ip);
        if (count($parts) !== 4) return false;
        
        $third_octet = (int)$parts[2];
        $is_priority = false;

        // 若屬於 192.168.X.X 則進行更嚴格的站台網段比對
        if ($parts[0] === '192' && $parts[1] === '168') {
            $site_ranges = [
                'HA' => [10, 16], 'HB' => [20, 26], 'HC' => [30, 36], 'HD' => [40, 46],
                'HE' => [50, 56], 'HF' => [60, 66], 'HG' => [70, 76], 'HH' => [80, 86]
            ];

            if (isset($site_ranges[$site])) {
                $range = $site_ranges[$site];
                $is_priority = ($third_octet >= $range[0] && $third_octet <= $range[1]);
                if ($is_priority) {
                    Logger::getInstance()->debug(__METHOD__ . ": [優先判定] IP $ip 屬於站台 $site 預設範圍 ({$range[0]}~{$range[1]})。");
                }
            } else {
                // 若不在定義站台中，則視所有私有 IP 為優先
                $is_priority = true;
            }
        } else {
            // 其餘私有網段 (如 10.x)，若非特定站台，預設視為 Priority
            $is_priority = !in_array($site, ["HA", "HB", "HC", "HD", "HE", "HF", "HG", "HH"]);
        }
        return $is_priority;
    }

    /**
     * [核心功能] 同步使用者動態 IP 資料
     * 分析 IPResolver 的動態紀錄並與 user 表比對：
     * 1. 只有一個新 IP 且為該站台優先網段時 -> 自動更新。
     * 2. 出現多個新 IP 時 -> 加入衝突清單回傳。
     * * @param int $interval 分析的時間區間 (秒)
     * @return array [auto_updated, conflicts]
     */
    public function syncUserDynamicIP($interval = 604800) {
        Logger::getInstance()->info(__METHOD__ . ": 開始執行使用者動態 IP 同步分析 (區間: " . ($interval / 86400) . " 天)");
        $ipr = new IPResolver();
        $rows = $ipr->getDynamicIPEntries($interval);
        
        if (empty($rows)) {
            return ['auto_updated' => [], 'conflicts' => []];
        }

        // 分組紀錄
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
            $diffIps = array_filter($ips, function($ip) use ($user) {
                return $ip !== $user['ip'];
            });
            $diffIps = array_values($diffIps);

            if (empty($diffIps)) continue;

            // 優先權過濾
            $prioritizedIps = array_filter($diffIps, [$this, 'isPriorityIp']);
            $prioritizedIps = array_values($prioritizedIps);

            $logic_match = false;
            $selected_ip = null;

            // 自動更新邏輯判定
            if (count($prioritizedIps) === 1) {
                $selected_ip = $prioritizedIps[0];
                $logic_match = true;
            } else if (count($prioritizedIps) === 0 && count($diffIps) === 1) {
                $selected_ip = $diffIps[0];
                $logic_match = true;
            }

            if ($logic_match) {
                if ($this->updateIp($uid, $selected_ip)) {
                    $autoUpdateList[] = ['id' => $uid, 'name' => $user['name'], 'ip' => $selected_ip];
                }
            } else {
                // 衝突處理：回傳候選 IP 及最新紀錄時間
                $pool = count($prioritizedIps) > 0 ? $prioritizedIps : $diffIps;
                $candidates = [];
                foreach ($pool as $ip) {
                    $latest_ts = 0;
                    foreach ($records as $r) {
                        if ($r['ip'] === $ip && $r['timestamp'] > $latest_ts) $latest_ts = $r['timestamp'];
                    }
                    $candidates[] = ['ip' => $ip, 'timestamp' => date('Y-m-d H:i:s', $latest_ts)];
                }
                $manualConflictList[] = [
                    'id' => $uid, 'name' => $user['name'], 'currentIp' => $user['ip'], 'candidates' => $candidates
                ];
            }
        }
        return ['auto_updated' => $autoUpdateList, 'conflicts' => $manualConflictList];
    }

    /**
     * 取得人員授權清單 (包含 Role 名稱與 User 資料的關聯)
     */
    public function getAuthorityList() {
        if($stmt = $this->db->prepare("
            SELECT a.role_id, a.ip AS role_ip, r.name AS role_name, u.*
            FROM authority a 
            LEFT JOIN role r ON a.role_id = r.id
            LEFT JOIN user u ON a.ip = u.ip AND (u.offboard_date = '')
            WHERE 1=1 ORDER BY r.name, a.ip
        ")) {
            return $this->prepareArray($stmt);
        }
        return false;
    }

    /**
     * 與 AD 使用者清單同步
     * 核心邏輯：
     * 1. 建立 AD 到 SQLite 的新增與姓名更新。
     * 2. 若使用者不在 AD 且在本地為「在職」，則設為「離職」。
     */
    public function syncAdUsers(array $ad_users = []) {
        if (empty($ad_users)) {
            $ad = new AdService();
            $ad_users = $ad->getValidUsers();
            if (empty($ad_users)) return false;
        }

        $site_code = System::getInstance()->getSiteCode();
        $stats = ['added' => 0, 'updated' => 0, 'skipped' => 0, 'failed' => 0, 'offboarded' => 0];
        $ad_user_ids = [];

        foreach ($ad_users as $user) {
            // 過濾非本所人員
            if (!isset($user['id']) || !startsWith($user['id'], $site_code)) continue;
            $id = $user['id'];
            $name = $user['name'];
            $ad_user_ids[$id] = true;
            
            $local_rows = $this->getUser($id);
            if (empty($local_rows)) {
                // [新增人員] 根據 AD 部門陣列選取合適的本地科室
                $unit = '人事室';
                if (!empty($user['department']) && is_array($user['department'])) {
                    foreach ($user['department'] as $dept) {
                        if (mb_substr($dept, -1, 1, 'UTF-8') === '課' || mb_substr($dept, -1, 1, 'UTF-8') === '室') {
                            $unit = $dept;
                            break;
                        }
                    }
                }
                $new_data = [
                    'id' => $id, 'name' => $name, 'sex' => 1, 'addr' => '', 'tel' => '', 'ext' => '411',
                    'cell' => '', 'unit' => $unit, 'title' => '其他', 'work' => '', 'exam' => '',
                    'education' => '', 'onboard_date' => date('Y/m/d'), 'ip' => '', 'authority' => 0, 'birthday' => ''
                ];
                if ($this->addUser($new_data)) $stats['added']++; else $stats['failed']++;
            } else {
                // [更新人員] 檢查姓名變更或復職
                $local_user = $local_rows[0];
                if (!empty($local_user['offboard_date'])) $this->onboardUser($id);
                if ($local_user['name'] !== $name) {
                    if ($this->updateName($id, $name)) $stats['updated']++; else $stats['failed']++;
                } else {
                    $stats['skipped']++;
                }
            }
        }

        // [離職處理] 檢查本地在職人員是否還在 AD 有效名單中
        $onboard_users = $this->getOnboardUsers();
        if ($onboard_users) {
            foreach ($onboard_users as $local_user) {
                if (!isset($ad_user_ids[$local_user['id']])) {
                    if ($this->offboardUser($local_user['id'])) $stats['offboarded']++; else $stats['failed']++;
                }
            }
        }
        return $stats;
    }
}
