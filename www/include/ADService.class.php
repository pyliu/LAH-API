<?php
require_once("init.php");
use RuntimeException;

/**
 * Class AdService
 * 負責處理與 Active Directory (LDAP) 的連線與查詢
 * * 修改紀錄:
 * - [Fix] 移除強制 LDAPS 邏輯，改回預設 Port 389 以修復 "Can't contact LDAP server"
 * - [Feature] connectAndBind 支援 $requireSecure 參數
 * - 若為一般查詢，使用標準 LDAP
 * - 若涉及密碼修改，嘗試使用 STARTTLS 或 LDAPS
 * - [Log] 詳細日誌記錄
 */
class AdService
{
    private $logger;
    
    private string $host;
    private int $port;
    private string $baseDn;
    private string $bindUser;
    private string $bindPass;

    // 快取有效期 (秒) - 1天
    private const CACHE_TTL = 86400;

    public function __construct(array $config = [])
    {
        $this->logger = Logger::getInstance();

        if (!extension_loaded('ldap')) {
            $this->logger->error("[AdService] 環境錯誤: PHP LDAP 擴充套件未啟用");
            throw new RuntimeException("環境錯誤: PHP LDAP 擴充套件未啟用。請檢查 php.ini 設定 (extension=ldap)。");
        }
        
        if (empty($config)) {
            $this->logger->info("[AdService] 未傳入設定，嘗試載入預設設定檔...");
            $config = $this->loadDefaultConfig();
        }
        
        $this->validateAndLoadConfig($config);
    }

    private function loadDefaultConfig(): array
    {
        $candidates = [
            __DIR__ . '/config/AD.php',
            __DIR__ . '/../../config/AD.php'
        ];

        foreach ($candidates as $path) {
            if (file_exists($path)) {
                $this->logger->info("[AdService] 載入設定檔: $path");
                $config = require $path;
                if (!is_array($config)) {
                    throw new RuntimeException("AdService 設定錯誤: 設定檔 '{$path}' 必須回傳陣列。");
                }
                return $config;
            }
        }
        throw new RuntimeException("AdService 初始化失敗: 找不到設定檔。");
    }

    private function validateAndLoadConfig(array $config): void
    {
        $requiredKeys = ['AD_HOST', 'BASE_DN', 'QUERY_USER', 'QUERY_PASSWORD'];
        foreach ($requiredKeys as $key) {
            if (empty($config[$key])) {
                throw new RuntimeException("AdService 設定錯誤: 缺少 '{$key}'。");
            }
        }

        $this->host = $config['AD_HOST'];
        // 預設改回 389，因為您的環境不支援 636
        $this->port = (int)($config['AD_PORT'] ?? 389); 
        $this->baseDn = $config['BASE_DN'];
        $this->bindUser = $config['QUERY_USER'];
        $this->bindPass = $config['QUERY_PASSWORD'];
        
        $this->logger->info("[AdService] 設定載入完成: Host={$this->host}:{$this->port}, BaseDN={$this->baseDn}");
    }

    /**
     * [內部方法] 建立 LDAP 連線並綁定 (Bind)
     * @param bool $requireSecure 是否需要加密連線 (例如重設密碼時)
     * @return resource LDAP Link Identifier
     */
    private function connectAndBind(bool $requireSecure = false)
    {
        $host = $this->host;
        $port = $this->port;
        $protocol = "ldap://";

        // 判斷是否需要加密連線
        if ($requireSecure) {
            $this->logger->info("[AdService] 操作需要安全連線...");
            
            // 策略 1: 如果 Config 已經是 636，就用 LDAPS
            if ($port == 636) {
                $protocol = "ldaps://";
            }
            // 策略 2: 如果是 389，稍後嘗試 STARTTLS (下面實作)
        } else {
            // 如果 Config 指定 636，還是得用 LDAPS
            if ($port == 636) {
                $protocol = "ldaps://";
            }
        }

        // 處理 Host 字串，避免重複協定頭
        if (stripos($host, 'ldap://') !== false || stripos($host, 'ldaps://') !== false) {
            $connStr = $host; // 使用者設定檔已包含協定
        } else {
            $connStr = $protocol . $host;
        }

        $this->logger->info("[AdService] 開始連線至: {$connStr}:{$port}");

        // 設定忽略憑證檢查 (避免自簽憑證問題)
        putenv('LDAPTLS_REQCERT=never'); 

        $conn = ldap_connect($connStr, $port);
        if (!$conn) throw new RuntimeException("無法連線至 AD 伺服器: " . $connStr);

        ldap_set_option($conn, LDAP_OPT_PROTOCOL_VERSION, 3);
        ldap_set_option($conn, LDAP_OPT_REFERRALS, 0);
        ldap_set_option($conn, LDAP_OPT_TIMELIMIT, 15);
        @ldap_set_option(null, LDAP_OPT_X_TLS_REQUIRE_CERT, 0); 

        // [關鍵修改] 如果需要安全連線，且目前是 Port 389，嘗試升級為 TLS
        if ($requireSecure && $port == 389) {
            $this->logger->info("[AdService] 嘗試啟動 STARTTLS 加密...");
            if (!@ldap_start_tls($conn)) {
                $err = ldap_error($conn);
                $this->logger->warning("[AdService] STARTTLS 失敗: $err 。若此操作涉及修改密碼可能會被 AD 拒絕。");
                // 這裡不拋出例外，嘗試繼續 Bind，因為有些寬鬆的 AD 環境可能允許
            } else {
                $this->logger->info("[AdService] STARTTLS 啟動成功");
            }
        }

        if (!@ldap_bind($conn, $this->bindUser, $this->bindPass)) {
            $this->logger->error("[AdService] AD Bind 失敗 (User: {$this->bindUser})");
            throw new RuntimeException("AD 認證失敗: " . ldap_error($conn));
        }
        $this->logger->info("[AdService] AD Bind 成功");
        
        return $conn;
    }

    private function convertWindowsTimeToStr(string $adTime): string
    {
        if ($adTime == '0' || $adTime == '') {
            return '';
        }
        $winSecs = $adTime / 10000000;
        $unixTimestamp = $winSecs - 11644473600;
        if ($unixTimestamp > 0) {
            return date('Y-m-d H:i:s', (int)$unixTimestamp);
        }
        return '';
    }

    /**
     * [內部方法] 抓取系統中所有群組的 CN 與 Description 對照表
     */
    private function fetchGroupDescriptions($conn): array
    {
        $this->logger->info("[AdService] 開始抓取群組 (Group) 描述對照表...");
        $groupMap = [];
        $filter = '(&(objectClass=group)(description=*))';
        $attributes = ['cn', 'description'];

        $result = @ldap_search($conn, $this->baseDn, $filter, $attributes);
        if ($result) {
            $entries = ldap_get_entries($conn, $result);
            for ($i = 0; $i < $entries['count']; $i++) {
                $cn = $entries[$i]['cn'][0] ?? '';
                $desc = $entries[$i]['description'][0] ?? '';
                if ($cn && $desc) {
                    $groupMap[$cn] = $desc;
                }
            }
        }
        $this->logger->info("[AdService] 群組描述對照表抓取完成，共 " . count($groupMap) . " 筆");
        return $groupMap;
    }

    /**
     * [內部方法] 嘗試從快取檔案讀取資料
     */
    private function loadFromCache(string $filename): ?array
    {
        $filepath = __DIR__ . '/config/' . $filename;
        $this->logger->info("[AdService] 檢查快取檔案: {$filepath}");
        
        if (file_exists($filepath)) {
            $cache = require $filepath;
            
            if (is_array($cache) && isset($cache['timestamp'], $cache['data'])) {
                $age = time() - $cache['timestamp'];
                
                if ($age < self::CACHE_TTL) {
                    $this->logger->info("[AdService] 快取有效 (Age: {$age}s < " . self::CACHE_TTL . "s)，直接使用快取資料。");
                    return $cache['data'];
                } else {
                    $this->logger->info("[AdService] 快取已過期 (Age: {$age}s > " . self::CACHE_TTL . "s)，準備重新抓取。");
                }
            } else {
                $this->logger->warning("[AdService] 快取檔案格式錯誤或損毀。");
            }
        } else {
            $this->logger->info("[AdService] 快取檔案不存在。");
        }
        return null;
    }

    /**
     * [內部方法] 將陣列儲存為 PHP 設定檔
     */
    private function saveToConfigFile(string $filename, array $data): bool
    {
        try {
            $dir = __DIR__ . '/config';
            if (!is_dir($dir)) {
                if (!mkdir($dir, 0755, true)) {
                    throw new RuntimeException("無法建立目錄: $dir");
                }
            }

            $filepath = $dir . '/' . $filename;
            $this->logger->info("[AdService] 準備寫入快取檔案: $filepath");
            
            $payload = [
                'updated_at' => date('Y-m-d H:i:s'),
                'timestamp' => time(),
                'total_count' => count($data),
                'data' => $data
            ];
            
            $content = "<?php\n\n// Generated by AdService at " . date('Y-m-d H:i:s') . "\nreturn " . var_export($payload, true) . ";\n";

            if (file_put_contents($filepath, $content) === false) {
                throw new RuntimeException("寫入檔案失敗: $filepath");
            }

            $this->logger->info("[AdService] 成功儲存 AD 資料至: $filename (共 " . count($data) . " 筆)");
            return true;
        } catch (\Exception $e) {
            $this->logger->error("[AdService] 儲存 AD 設定檔失敗 [$filename]: " . $e->getMessage());
            return false;
        }
    }

    // ==========================================
    // Public User Fetching Methods
    // ==========================================

    /**
     * 取得所有有效使用者 (未停用)
     */
    public function getValidUsers(bool $force = false): array
    {
        $filename = 'AD.VALID.php';
        $this->logger->info("[AdService] 請求取得【有效使用者】(Force: " . ($force ? 'Yes' : 'No') . ")");

        if (!$force) {
            $cachedData = $this->loadFromCache($filename);
            if ($cachedData !== null) return $cachedData;
        }

        // Filter: 未停用
        $filter = '(&(objectClass=user)(objectCategory=person)(!(userAccountControl:1.2.840.113556.1.4.803:=2)))';
        $data = $this->fetchUsersByFilter($filter, "取得有效使用者");
        $this->saveToConfigFile($filename, $data);
        return $data;
    }

    public function saveValidUsers(): bool
    {
        $this->logger->info("[AdService] 手動觸發儲存【有效使用者】...");
        return !empty($this->getValidUsers(true));
    }

    /**
     * 取得所有停用使用者 (已停用)
     */
    public function getInvalidUsers(bool $force = false): array
    {
        $filename = 'AD.INVALID.php';
        $this->logger->info("[AdService] 請求取得【停用使用者】(Force: " . ($force ? 'Yes' : 'No') . ")");

        if (!$force) {
            $cachedData = $this->loadFromCache($filename);
            if ($cachedData !== null) return $cachedData;
        }

        // Filter: 已停用
        $filter = '(&(objectClass=user)(objectCategory=person)(userAccountControl:1.2.840.113556.1.4.803:=2))';
        $data = $this->fetchUsersByFilter($filter, "取得停用使用者");
        $this->saveToConfigFile($filename, $data);
        return $data;
    }

    public function saveInvalidUsers(): bool
    {
        $this->logger->info("[AdService] 手動觸發儲存【停用使用者】...");
        return !empty($this->getInvalidUsers(true));
    }

    /**
     * 取得所有使用者 (含有效與停用)
     */
    public function getAllUsers(bool $force = false): array
    {
        $filename = 'AD.ALL.php';
        $this->logger->info("[AdService] 請求取得【所有使用者】(Force: " . ($force ? 'Yes' : 'No') . ")");

        if (!$force) {
            $cachedData = $this->loadFromCache($filename);
            if ($cachedData !== null) return $cachedData;
        }

        $filter = '(&(objectClass=user)(objectCategory=person))';
        $data = $this->fetchUsersByFilter($filter, "取得所有使用者");
        $this->saveToConfigFile($filename, $data);
        return $data;
    }

    public function saveAllUsers(): bool
    {
        $this->logger->info("[AdService] 手動觸發儲存【所有使用者】...");
        return !empty($this->getAllUsers(true));
    }

    /**
     * [Feature 1] 取得被鎖定 (Locked Out) 但未停用的使用者
     */
    public function getLockedUsers(bool $force = false): array
    {
        $filename = 'AD.LOCKED.php';
        $this->logger->info("[AdService] 請求取得【鎖定使用者】(Force: " . ($force ? 'Yes' : 'No') . ")");

        if (!$force) {
            $cachedData = $this->loadFromCache($filename);
            if ($cachedData !== null) return $cachedData;
        }

        // Filter: 未停用 AND (lockoutTime>=1 AND lockoutTime!=0)
        $filter = '(&(objectClass=user)(objectCategory=person)(!(userAccountControl:1.2.840.113556.1.4.803:=2))(&(lockoutTime>=1)(!(lockoutTime=0))))';
        
        $data = $this->fetchUsersByFilter($filter, "取得鎖定使用者");
        $this->saveToConfigFile($filename, $data);
        return $data;
    }

    public function saveLockedUsers(): bool
    {
        $this->logger->info("[AdService] 手動觸發儲存【鎖定使用者】...");
        return !empty($this->getLockedUsers(true));
    }

    // ==========================================
    // Management Methods (Unlock / Reset PW)
    // ==========================================

    /**
     * [Feature 2] 解鎖使用者並可選重設密碼
     * @param string $account 使用者帳號 (sAMAccountName)
     * @param string|null $newPassword (Optional) 若提供，則同時重設密碼
     * @return bool
     */
    public function unlockUser(string $account, ?string $newPassword = null): bool
    {
        $conn = null;
        try {
            $this->logger->info("[AdService] 嘗試解鎖使用者: {$account}" . ($newPassword ? " (含重設密碼)" : ""));
            
            // 只有在需要重設密碼時，才要求加密連線 (STARTTLS or LDAPS)
            $requireSecure = ($newPassword !== null);
            
            $conn = $this->connectAndBind($requireSecure);

            // 1. 尋找使用者的 DN
            $filter = "(&(objectClass=user)(objectCategory=person)(sAMAccountName={$account}))";
            $result = ldap_search($conn, $this->baseDn, $filter, ['dn']);
            $entries = ldap_get_entries($conn, $result);

            if ($entries['count'] == 0) {
                throw new RuntimeException("找不到使用者: {$account}");
            }

            $userDn = $entries[0]['dn'];
            $modifications = [];

            // 2. 準備解鎖動作: 將 lockoutTime 設為 0
            $modifications['lockoutTime'] = 0;

            // 3. 準備密碼重設動作 (如果有的話)
            if ($newPassword !== null) {
                // AD 密碼必須用引號包起來，並轉為 UTF-16LE
                $quotedPassword = "\"" . $newPassword . "\"";
                $encodedPwd = iconv("UTF-8", "UTF-16LE", $quotedPassword);
                $modifications['unicodePwd'] = $encodedPwd;
            }

            // 4. 執行修改
            if (@ldap_modify($conn, $userDn, $modifications)) {
                $this->logger->info("[AdService] 使用者 {$account} 解鎖成功" . ($newPassword ? "/密碼已重設" : ""));
                return true;
            } else {
                $error = ldap_error($conn);
                throw new RuntimeException("修改失敗: {$error}。");
            }

        } catch (\Exception $e) {
            $this->logger->error("[AdService] 解鎖使用者失敗: " . $e->getMessage());
            throw $e;
        } finally {
            if ($conn) @ldap_unbind($conn);
        }
    }

    // ==========================================
    // Core Fetching Logic
    // ==========================================

    /**
     * [核心方法] 根據過濾條件搜尋並解析使用者
     */
    private function fetchUsersByFilter(string $filter, string $logAction): array
    {
        $conn = null;
        try {
            // 一般查詢，不需要加密連線 (False)
            $conn = $this->connectAndBind(false);

            // --- [Step 1] 預先抓取群組中文描述對照表 ---
            $groupDescMap = $this->fetchGroupDescriptions($conn);
            // ------------------------------------------

            // [Step 2] 搜尋使用者
            $attributes = [
                'samaccountname', 
                'description', 
                'memberof', 
                'lastlogon', 
                'pwdlastset',
                'whencreated',
                'whenchanged',
                'lastlogontimestamp',
                'badpwdcount',
                'badpasswordtime',
                'accountexpires',
                'lockouttime',
                'useraccountcontrol',
                'primarygroupid'
            ];

            $this->logger->info("[AdService] {$logAction}: 執行使用者搜尋 Filter=[{$filter}]");
            $result = ldap_search($conn, $this->baseDn, $filter, $attributes);
            if (!$result) throw new RuntimeException("AD 搜尋失敗: " . ldap_error($conn));

            $entries = ldap_get_entries($conn, $result);
            $rawCount = $entries['count'] ?? 0;
            $this->logger->info("[AdService] {$logAction}: 搜尋完成，共找到 {$rawCount} 筆原始資料，開始解析...");
            
            $users = [];
            
            for ($i = 0; $i < $entries['count']; $i++) {
                $entry = $entries[$i];
                
                $account = $entry['samaccountname'][0] ?? '';
                $name = $entry['description'][0] ?? ''; 
                
                $lastLoginTime = $this->convertWindowsTimeToStr($entry['lastlogon'][0] ?? '0');
                $pwdLastSetTime = $this->convertWindowsTimeToStr($entry['pwdlastset'][0] ?? '0');

                $raw = [
                    'whencreated'        => $entry['whencreated'][0] ?? '',
                    'whenchanged'        => $entry['whenchanged'][0] ?? '',
                    'pwdlastset'         => $entry['pwdlastset'][0] ?? '',
                    'lastlogon'          => $entry['lastlogon'][0] ?? '',
                    'lastlogontimestamp' => $entry['lastlogontimestamp'][0] ?? '',
                    'badpwdcount'        => $entry['badpwdcount'][0] ?? '',
                    'badpasswordtime'    => $entry['badpasswordtime'][0] ?? '',
                    'accountexpires'     => $entry['accountexpires'][0] ?? '',
                    'lockouttime'        => $entry['lockouttime'][0] ?? '',
                    'useraccountcontrol' => $entry['useraccountcontrol'][0] ?? '',
                    'primarygroupid'     => $entry['primarygroupid'][0] ?? '',
                ];

                $roles = []; 
                $units = []; 

                if (isset($entry['memberof'])) {
                    $memberOfList = $entry['memberof'];
                    if (isset($memberOfList['count'])) unset($memberOfList['count']);

                    foreach ($memberOfList as $dn) {
                        if (preg_match('/^CN=([^,]+),/', $dn, $matches)) {
                            $cnName = $matches[1];
                            
                            if (strpos($dn, 'OU=APPS') !== false) {
                                if (isset($groupDescMap[$cnName])) {
                                    $roles[$cnName] = $groupDescMap[$cnName];
                                } else {
                                    $roles[$cnName] = $cnName;
                                }
                            } 
                            elseif (strpos($dn, 'CN=Users') !== false) {
                                if (isset($groupDescMap[$cnName])) {
                                    $units[] = $groupDescMap[$cnName];
                                } else {
                                    $units[] = $cnName;
                                }
                            }
                        }
                    }
                }

                $users[] = [
                    'id'            => $account,
                    'name'          => $name,
                    'department'    => $units,
                    'roles'         => $roles,
                    'last_login'    => $lastLoginTime,
                    'pwd_last_set'  => $pwdLastSetTime,
                    'raw'           => $raw
                ];
            }

            $finalCount = count($users);
            $this->logger->info("[AdService] {$logAction}: 解析完成，共回傳 {$finalCount} 筆使用者資料");
            return $users;

        } catch (\Exception $e) {
            $this->logger->error("[AdService] {$logAction} 發生錯誤: " . $e->getMessage());
            throw $e;
        } finally {
            if ($conn) @ldap_unbind($conn);
        }
    }
}