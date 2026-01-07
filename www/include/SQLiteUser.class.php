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

    public function getUser($id) {
        if($stmt = $this->db->prepare("SELECT * FROM user WHERE id = :id")) {
            $stmt->bindParam(':id', $id);
            return $this->prepareArray($stmt);
        } else {
            Logger::getInstance()->error(__METHOD__.": 取得使用者($id)資料失敗！");
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

    public function updateName($id, $name) {
        if ($stm = $this->db->prepare("UPDATE user SET name = :name WHERE id = :id")) {
            $stm->bindParam(':name', $name);
            $stm->bindParam(':id', $id);
            return $stm->execute() === FALSE ? false : true;
        }
        return false;
    }

    /**
     * 更新使用者 IP (嚴格限制僅能使用內部 IP)
     */
    public function updateIp($id, $ip) {
        if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            Logger::getInstance()->warning(__METHOD__.": 更新失敗 - 無效的 IP 格式 ($ip)");
            return false;
        }
        // 拒絕公網 IP
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
     * 輔助方法：判斷是否為優先權重之內部 IP (Priority IP)
     * 優化實作：針對各站台定義特定的 192.168.X0 ~ 192.168.X6 網段
     * @param string $ip IPv4 位址
     * @return bool
     */
    private function isPriorityIp($ip) {
        // 1. 基本 IPv4 驗證
        if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return false;
        }

        // 2. 判斷是否為私有網段 (filter_var 帶 flag 回傳 false 代表是私有網段)
        $is_private = filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false;
        if (!$is_private) {
            return false;
        }

        // 3. 獲取當前站台代碼
        $site = System::getInstance()->getSiteCode();
        
        // 4. 解析 IP 段以進行精確範圍比對 (例如 HA 需比對 192.168.10.x ~ 192.168.16.x)
        $parts = explode('.', $ip);
        if (count($parts) !== 4) return false;
        
        $third_octet = (int)$parts[2];
        $is_priority = false;

        // 檢查是否符合 192.168.X.X 結構並比對站台定義範圍
        if ($parts[0] === '192' && $parts[1] === '168') {
            // 定義各站台對應的起始與結束範圍 (例如 HA 為 10~16)
            $site_ranges = [
                'HA' => [10, 16],
                'HB' => [20, 26],
                'HC' => [30, 36],
                'HD' => [40, 46],
                'HE' => [50, 56],
                'HF' => [60, 66],
                'HG' => [70, 76],
                'HH' => [80, 86]
            ];

            if (isset($site_ranges[$site])) {
                $range = $site_ranges[$site];
                $is_priority = ($third_octet >= $range[0] && $third_octet <= $range[1]);
                
                // 記錄判定日誌 (僅在符合站台定義時)
                if ($is_priority) {
                    Logger::getInstance()->debug(__METHOD__ . ": [優先判定] IP $ip 屬於站台 $site 預設範圍 ({$range[0]}~{$range[1]})。");
                }
            } else {
                // 非定義站台，則只要是 192.168 私有網段即視為優先
                $is_priority = true;
            }
        } else {
            // 若為其餘私有網段 (如 10.x 或 172.x)，但在特定站台環境下不列為 Priority (因為可能來自跨機房或 VPN)
            // 若非定義站台，則預設列為 Priority
            $is_priority = !in_array($site, ["HA", "HB", "HC", "HD", "HE", "HF", "HG", "HH"]);
        }

        return $is_priority;
    }

    /**
     * [Feature] 同步使用者動態 IP 資料
     * 參考 JS CODE 邏輯：分析動態記錄，執行自動更新或回傳衝突清單供手動選擇
     * @param int $interval 追蹤的時間區間，預設 7 天
     * @return array 包含 auto_updated 和 conflicts 資訊
     */
    public function syncUserDynamicIP($interval = 604800) {
        Logger::getInstance()->info(__METHOD__ . ": 開始執行使用者動態 IP 同步分析 (區間: " . ($interval / 86400) . " 天)");
        
        $ipr = new IPResolver();
        $rows = $ipr->getDynamicIPEntries($interval);
        
        if (empty($rows)) {
            Logger::getInstance()->info(__METHOD__ . ": 指定區間內無任何動態 IP 紀錄。");
            return ['auto_updated' => [], 'conflicts' => []];
        }

        Logger::getInstance()->debug(__METHOD__ . ": 成功取得 " . count($rows) . " 筆動態 IP 紀錄。");

        // 1. 將 entries 以 entry_id 分組
        $userMap = [];
        foreach ($rows as $row) {
            $uid = $row['entry_id'];
            if (!isset($userMap[$uid])) {
                $userMap[$uid] = [];
            }
            $userMap[$uid][] = $row;
        }
        Logger::getInstance()->debug(__METHOD__ . ": 紀錄中共解析出 " . count($userMap) . " 位不重複使用者。");

        $autoUpdateList = [];
        $manualConflictList = [];
        
        // 2. 遍歷在職使用者進行分析
        $users = $this->getOnboardUsers();
        foreach ($users as $user) {
            $uid = $user['id'];
            if (!isset($userMap[$uid])) continue;

            $records = $userMap[$uid];
            
            // 找出所有不同於目前記錄的 IP 並去重
            $ips = array_unique(array_column($records, 'ip'));
            $diffIps = array_filter($ips, function($ip) use ($user) {
                return $ip !== $user['ip'];
            });
            $diffIps = array_values($diffIps); // 重置 index

            if (empty($diffIps)) continue;

            Logger::getInstance()->debug(__METHOD__ . ": [偵測變動] 使用者 $uid ({$user['name']}) 當前 IP 為 {$user['ip']}，發現候選 IP: " . implode(', ', $diffIps));

            // 篩選具優先權(內部網段)的 IP
            $prioritizedIps = array_filter($diffIps, [$this, 'isPriorityIp']);
            $prioritizedIps = array_values($prioritizedIps);

            $logic_match = false;
            $selected_ip = null;

            if (count($prioritizedIps) === 1) {
                // 邏輯 1：剛好只有一個優先 IP -> 自動更新
                $selected_ip = $prioritizedIps[0];
                $logic_match = true;
                Logger::getInstance()->info(__METHOD__ . ": 使用者 $uid 符合「單一內部優先 IP」邏輯，選定更新為: $selected_ip");
            } else if (count($prioritizedIps) === 0 && count($diffIps) === 1) {
                // 邏輯 2：沒有優先 IP 但只有一個不同 IP -> 自動更新
                $selected_ip = $diffIps[0];
                $logic_match = true;
                Logger::getInstance()->info(__METHOD__ . ": 使用者 $uid 符合「單一候選 IP」邏輯，選定更新為: $selected_ip");
            }

            if ($logic_match) {
                // 執行自動更新並記錄
                if ($this->updateIp($uid, $selected_ip)) {
                    $autoUpdateList[] = ['id' => $uid, 'name' => $user['name'], 'ip' => $selected_ip];
                    Logger::getInstance()->info(__METHOD__ . ": [成功] 使用者 $uid 資料庫 IP 已更新為 $selected_ip");
                } else {
                    Logger::getInstance()->error(__METHOD__ . ": [失敗] 使用者 $uid 更新至 $selected_ip 時發生錯誤。");
                }
            } else {
                // 邏輯 3：存在多個候選 IP -> 加入衝突清單
                $pool = count($prioritizedIps) > 0 ? $prioritizedIps : $diffIps;
                $candidates = [];
                foreach ($pool as $ip) {
                    // 找出該 IP 最新的時間戳
                    $latest_ts = 0;
                    foreach ($records as $r) {
                        if ($r['ip'] === $ip && $r['timestamp'] > $latest_ts) {
                            $latest_ts = $r['timestamp'];
                        }
                    }
                    $candidates[] = [
                        'ip' => $ip, 
                        'timestamp' => date('Y-m-d H:i:s', $latest_ts)
                    ];
                }
                
                $manualConflictList[] = [
                    'id' => $uid,
                    'name' => $user['name'],
                    'currentIp' => $user['ip'],
                    'candidates' => $candidates
                ];
                Logger::getInstance()->warning(__METHOD__ . ": 使用者 $uid 存在多個可能 IP (共 " . count($candidates) . " 個)，已加入手動選擇衝突清單。");
            }
        }

        Logger::getInstance()->info(__METHOD__ . ": 同步作業完成。自動更新: " . count($autoUpdateList) . " 位，手動衝突: " . count($manualConflictList) . " 位。");
        return [
            'auto_updated' => $autoUpdateList,
            'conflicts' => $manualConflictList
        ];
    }

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

    public function syncAdUsers(array $ad_users = []) {
        if (empty($ad_users)) {
            if (class_exists('AdService')) {
                $ad = new AdService();
                $ad_users = $ad->getValidUsers();
            }
            if (empty($ad_users)) return false;
        }

        $site_code = System::getInstance()->getSiteCode();
        $stats = ['added' => 0, 'updated' => 0, 'skipped' => 0, 'failed' => 0, 'offboarded' => 0];
        $ad_user_ids = [];

        foreach ($ad_users as $user) {
            if (!isset($user['id']) || !startsWith($user['id'], $site_code)) continue;
            $id = $user['id'];
            $name = $user['name'];
            $ad_user_ids[$id] = true;
            
            $local_rows = $this->getUser($id);
            if (empty($local_rows)) {
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
                $local_user = $local_rows[0];
                if (!empty($local_user['offboard_date'])) $this->onboardUser($id);
                if ($local_user['name'] !== $name) {
                    if ($this->updateName($id, $name)) $stats['updated']++; else $stats['failed']++;
                } else {
                    $stats['skipped']++;
                }
            }
        }

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
