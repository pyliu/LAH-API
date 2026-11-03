<?php
require_once(__DIR__ . DIRECTORY_SEPARATOR . "Env.class.php");
if (!defined('ORACLE_ENCODING')) {
    if (defined('LAH_LIGHTWEIGHT_BRIDGE_PROBE') && LAH_LIGHTWEIGHT_BRIDGE_PROBE) {
        define('ORACLE_ENCODING', 'CP950');
        if (!defined('ROOT_DIR')) {
            define('ROOT_DIR', dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'www');
        }
    } else {
        require_once(__DIR__ . DIRECTORY_SEPARATOR . "GlobalConstants.inc.php");
    }
}
if (!class_exists('System', false) && (!defined('LAH_LIGHTWEIGHT_BRIDGE_PROBE') || !LAH_LIGHTWEIGHT_BRIDGE_PROBE)) {
    require_once(__DIR__ . DIRECTORY_SEPARATOR . "System.class.php");
}
// 地所DB的內碼為「American_America.US7ASCII」
putenv('NLS_LANG=American_America.US7ASCII');

/**
 * 220.1.33.2 L1HWEB
 * 220.1.33.3 L2HWEB
 * 220.1.33.5 L3HWEB
 * 220.1.33.5 L1HWEB
*/
abstract class CONNECTION_TYPE {
    const MAIN = 0;
    const TWEB = 1;
    const L1HWEB = 2;
    const L2HWEB = 3;
    const L3HWEB = 4;
    const L1HWEB_Alt = 5;
    const BK = 6;
}

class OraDB {
    private $L1HWEB_DB;
    private $L1HWEB_Alt_DB;
    private $L2HWEB_DB;
    private $L3HWEB_DB;
    private $TWEB_DB;
    private $MAIN_DB;
    private $BK_DB;
    private $user;
    private $pass;
    private $nls = "US7ASCII";
    private $conn;
    private $stid;
    private $numrows = 0;
    private $CONN_TYPE;
    private $connected = false;
    private $db_encoding = ORACLE_ENCODING;
    private $web_encoding = 'UTF-8';
    private $use_cli_bridge = false;
    private $cli_sql = '';
    private $cli_binds = array();
    private $cli_rows = array();
    private $cli_cursor = 0;
    private $cli_error = null;

    /** @var bool Whether HTTP Loopback failed during the current request lifecycle */
    private static $http_loopback_disabled_in_request = false;

    private function initSetting() {
        $system = System::getInstance();
        $this->L1HWEB_DB = $system->getOraL1hwebDBConnStr();
        $this->L1HWEB_Alt_DB = $system->getOraL3hwebL1DBConnStr();
        $this->L2HWEB_DB = $system->getOraL2hwebDBConnStr();
        $this->L3HWEB_DB = $system->getOraL3hwebDBConnStr();
        $this->TWEB_DB = $system->getOraTestDBConnStr();
        $this->MAIN_DB = $system->getOraMainDBConnStr();
        $this->BK_DB = $system->getOraBackupDBConnStr();
        $this->user = $system->get("ORA_DB_USER");
        $this->pass = $system->get("ORA_DB_PASS");
    }

    private function getConnString() {
        switch($this->CONN_TYPE) {
            case CONNECTION_TYPE::L1HWEB:
                return $this->L1HWEB_DB;
            case CONNECTION_TYPE::L1HWEB_Alt:
                return $this->L1HWEB_Alt_DB;
            case CONNECTION_TYPE::L2HWEB:
                return $this->L2HWEB_DB;
            case CONNECTION_TYPE::L3HWEB:
                return $this->L3HWEB_DB;
            case CONNECTION_TYPE::TWEB:
                return $this->TWEB_DB;
            case CONNECTION_TYPE::BK:
                return $this->BK_DB;
            default:
                return $this->MAIN_DB;
        }
    }

    function __construct($type = CONNECTION_TYPE::MAIN) {
        $this->use_cli_bridge = !extension_loaded('oci8');
        if ($type === CONNECTION_TYPE::MAIN) {
            $type = self::getPointDBTarget();
        }
        $this->initSetting();
        $this->setConnType($type);
    }

    function __destruct() {
        $this->close();
    }

    /**
     * Reset static bridge failover state (primarily for unit tests).
     */
    public static function resetBridgeState() {
        self::$http_loopback_disabled_in_request = false;
    }

    /**
     * Resolve installation root directory (e.g. C:\AppServ or custom root).
     *
     * @return string
     */
    public static function resolveRootDir() {
        $env_root = trim((string)Env::get('LAH_ROOT', getenv('LAH_ROOT') ?: ''));
        if ($env_root !== '' && @is_dir($env_root)) {
            return rtrim($env_root, "\\/");
        }
        $candidates = array(
            dirname(__DIR__, 2),
            dirname(__DIR__, 3),
            'C:\\AppServ'
        );
        foreach ($candidates as $c) {
            if (@is_dir($c . DIRECTORY_SEPARATOR . 'php7') || @is_dir($c . DIRECTORY_SEPARATOR . 'php85')) {
                return $c;
            }
        }
        return 'C:\\AppServ';
    }

    /**
     * Resolve PHP 7 CLI executable path.
     *
     * @return string Empty string if not found
     */
    public static function resolvePhp7CliPath() {
        $explicit = trim((string)Env::get('PHP7_CLI_PATH', getenv('PHP7_CLI_PATH') ?: ''));
        if ($explicit !== '') {
            return @is_file($explicit) ? $explicit : '';
        }
        $root = self::resolveRootDir();
        $candidates = array(
            $root . DIRECTORY_SEPARATOR . 'php7' . DIRECTORY_SEPARATOR . 'php.exe',
            dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'php7' . DIRECTORY_SEPARATOR . 'php.exe',
            dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'php7' . DIRECTORY_SEPARATOR . 'php.exe',
            'C:\\AppServ\\php7\\php.exe'
        );
        foreach ($candidates as $c) {
            if (@is_file($c)) {
                return $c;
            }
        }
        return '';
    }

    /**
     * Resolve PHP 7 php.ini path so CLI worker reliably loads extension=oci8.
     *
     * @param string $php7_cli
     * @return string Empty string if not found
     */
    public static function resolvePhp7IniPath($php7_cli = '') {
        $explicit = trim((string)Env::get('PHP7_INI_PATH', getenv('PHP7_INI_PATH') ?: ''));
        if ($explicit !== '' && @is_file($explicit)) {
            return $explicit;
        }
        if ($php7_cli === '') {
            $php7_cli = self::resolvePhp7CliPath();
        }
        $candidates = array();
        if ($php7_cli !== '') {
            $candidates[] = dirname($php7_cli) . DIRECTORY_SEPARATOR . 'php.ini';
        }
        $candidates[] = 'C:\\Windows\\php.ini';

        $existing = array();
        foreach ($candidates as $ini) {
            if (@is_file($ini)) {
                $existing[] = $ini;
                $content = @file_get_contents($ini);
                if (is_string($content) && preg_match('/^\s*extension\s*=\s*["\']?(?:php_)?oci8/im', $content)) {
                    return $ini;
                }
            }
        }
        return !empty($existing) ? $existing[0] : '';
    }

    /**
     * Resolve Oracle Instant Client 11.2 directory for oci.dll loading.
     *
     * @return string Empty string if not found
     */
    public static function resolveInstantClientDir() {
        $explicit = trim((string)Env::get('OCI_DIR', getenv('OCI_DIR') ?: ''));
        if ($explicit !== '' && @is_dir($explicit)) {
            return rtrim($explicit, "\\/");
        }
        $root = self::resolveRootDir();
        $candidates = array(
            $root . DIRECTORY_SEPARATOR . 'instantclient_11_2',
            dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'instantclient_11_2',
            'C:\\Tools\\instantclient_11_2',
            'C:\\AppServ\\instantclient_11_2'
        );
        foreach ($candidates as $c) {
            if (@is_dir($c)) {
                return $c;
            }
        }
        return '';
    }

    /**
     * Resolve internal HTTP Loopback URL for www/api/ora_bridge.php.
     *
     * @return string
     */
    public static function resolveBridgeUrl() {
        $explicit = trim((string)Env::get('ORA_BRIDGE_URL', getenv('ORA_BRIDGE_URL') ?: ''));
        if ($explicit !== '') {
            return $explicit;
        }
        $port = trim((string)Env::get('APACHE_HTTP_PORT', getenv('APACHE_HTTP_PORT') ?: '80'));
        if ($port === '' || $port === '80') {
            return 'http://127.0.0.1/api/ora_bridge.php';
        }
        return 'http://127.0.0.1:' . $port . '/api/ora_bridge.php';
    }

    /**
     * Derive shared HMAC secret from www/.env configuration.
     *
     * @return string
     */
    public static function getBridgeSecret() {
        $explicit = trim((string)Env::get('ORA_BRIDGE_SECRET', ''));
        if ($explicit !== '') {
            return hash('sha256', 'lah-ora-bridge-v1|' . $explicit);
        }
        $seed = implode('|', array(
            (string)Env::get('API_KEY', ''),
            (string)Env::get('ORA_DB_USER', ''),
            (string)Env::get('ORA_DB_PASS', ''),
            (string)Env::get('ORA_DB_HXWEB_IP', ''),
            (string)Env::get('SITE', 'HA')
        ));
        return hash('sha256', 'lah-ora-bridge-v1|' . $seed);
    }

    /**
     * Compute HMAC-SHA256 signature for a raw JSON request body.
     *
     * @param string $raw_body
     * @return string
     */
    public static function signBridgePayload($raw_body) {
        return hash_hmac('sha256', (string)$raw_body, self::getBridgeSecret());
    }

    /**
     * Gatekeeper: check whether native oci8 or PHP 7 Bridge (HTTP Loopback / CLI Worker) is available.
     *
     * @return bool
     */
    public static function isDriverOrBridgeAvailable() {
        if (extension_loaded('oci8') && function_exists('oci_connect')) {
            return true;
        }
        $disabled = strtolower(trim((string)Env::get('ORA_BRIDGE_DISABLED', getenv('ORA_BRIDGE_DISABLED') ?: '')));
        if ($disabled === 'true' || $disabled === '1') {
            return false;
        }
        $explicit_url = trim((string)Env::get('ORA_BRIDGE_URL', getenv('ORA_BRIDGE_URL') ?: ''));
        if ($explicit_url !== '') {
            return true;
        }
        $worker_script = self::resolveCliWorkerScript();
        $php7_cli = self::resolvePhp7CliPath();
        if ($php7_cli !== '' && @is_file($worker_script)) {
            return true;
        }
        return false;
    }

    /**
     * Resolve CLI worker script path (supports ORA_CLI_WORKER_PATH override for testing).
     *
     * @return string
     */
    public static function resolveCliWorkerScript() {
        $custom = trim((string)Env::get('ORA_CLI_WORKER_PATH', getenv('ORA_CLI_WORKER_PATH') ?: ''));
        if ($custom !== '' && @is_file($custom)) {
            return $custom;
        }
        return __DIR__ . DIRECTORY_SEPARATOR . 'ora_cli_worker.php';
    }

    /**
     * Build environment variables array for the PHP 7 CLI worker process,
     * ensuring instantclient_11_2 and php7 precede php85 in PATH.
     *
     * @param string $php7_cli
     * @return array
     */
    private static function buildCliWorkerEnv($php7_cli) {
        $env = getenv();
        if (!is_array($env)) {
            $env = array();
        }
        $path_key = 'PATH';
        foreach (array_keys($env) as $k) {
            if (strcasecmp($k, 'PATH') === 0) {
                $path_key = $k;
                break;
            }
        }
        $current_path = isset($env[$path_key]) ? (string)$env[$path_key] : (string)(getenv('PATH') ?: '');
        $prepend = array();
        $oci_dir = self::resolveInstantClientDir();
        if ($oci_dir !== '') {
            $prepend[] = $oci_dir;
        }
        if ($php7_cli !== '') {
            $prepend[] = dirname($php7_cli);
        }
        if (!empty($prepend)) {
            $env[$path_key] = implode(PATH_SEPARATOR, $prepend) . ($current_path !== '' ? PATH_SEPARATOR . $current_path : '');
        }
        $env['NLS_LANG'] = 'American_America.US7ASCII';
        return $env;
    }

    /**
     * Send signed payload to Apache mod_php7 HTTP Loopback endpoint.
     * Returns decoded JSON array if HTTP 200 and mod_php7 oci8 is present; returns null to trigger CLI fallback.
     *
     * @param string $payload
     * @param int $connect_timeout_ms
     * @param int $timeout_sec
     * @return array|null
     */
    private static function sendHttpLoopbackRequest($payload, $connect_timeout_ms = 800, $timeout_sec = 60) {
        $url = self::resolveBridgeUrl();
        $sig = self::signBridgePayload($payload);

        if (extension_loaded('curl') && function_exists('curl_init')) {
            $ch = @curl_init($url);
            if ($ch === false) {
                return null;
            }
            @curl_setopt_array($ch, array(
                CURLOPT_POST                  => true,
                CURLOPT_POSTFIELDS            => $payload,
                CURLOPT_RETURNTRANSFER        => true,
                CURLOPT_CONNECTTIMEOUT_MS     => $connect_timeout_ms,
                CURLOPT_TIMEOUT               => $timeout_sec,
                CURLOPT_PROXY                 => '',
                CURLOPT_HTTPHEADER            => array(
                    'Content-Type: application/json; charset=utf-8',
                    'X-LAH-Bridge-Signature: ' . $sig,
                    'Expect:'
                )
            ));
            $resp_body = @curl_exec($ch);
            $http_code = (int)@curl_getinfo($ch, CURLINFO_HTTP_CODE);
            @curl_close($ch);

            if (!is_string($resp_body) || $http_code !== 200) {
                return null;
            }
            $decoded = json_decode($resp_body, true);
            if (!is_array($decoded) || !empty($decoded['oci8_missing'])) {
                return null;
            }
            return $decoded;
        }

        $ctx = stream_context_create(array(
            'http' => array(
                'method'        => 'POST',
                'header'        => "Content-Type: application/json; charset=utf-8\r\n" .
                                   "X-LAH-Bridge-Signature: " . $sig . "\r\n",
                'content'       => $payload,
                'timeout'       => max(1, (int)ceil($connect_timeout_ms / 1000)),
                'ignore_errors' => true
            )
        ));
        $resp_body = @file_get_contents($url, false, $ctx);
        if (!is_string($resp_body) || $resp_body === '') {
            return null;
        }
        $decoded = json_decode($resp_body, true);
        if (!is_array($decoded) || !empty($decoded['oci8_missing'])) {
            return null;
        }
        // Check if HTTP 403/404/503 returned
        if (isset($decoded['error']['code']) && in_array((int)$decoded['error']['code'], array(403, 404, 503), true)) {
            return null;
        }
        return $decoded;
    }

    /**
     * Send payload to PHP 7 CLI worker via proc_open.
     *
     * @param string $payload
     * @param string|null $error_msg_out
     * @return array|null
     */
    private static function sendCliWorkerRequest($payload, &$error_msg_out = null) {
        $php7_cli = self::resolvePhp7CliPath();
        if ($php7_cli === '') {
            $php7_cli = trim((string)Env::get('PHP7_CLI_PATH', getenv('PHP7_CLI_PATH') ?: 'C:\\AppServ\\php7\\php.exe'));
        }
        $worker_script = self::resolveCliWorkerScript();
        if (!@is_file($php7_cli)) {
            $error_msg_out = "Oracle CLI worker PHP 7 binary not found: {$php7_cli}";
            return null;
        }
        if (!@is_file($worker_script)) {
            $error_msg_out = "Oracle CLI worker script not found: {$worker_script}";
            return null;
        }

        $php7_ini = self::resolvePhp7IniPath($php7_cli);
        $cmd = ($php7_ini !== '' && @is_file($php7_ini))
            ? array($php7_cli, '-c', $php7_ini, $worker_script)
            : array($php7_cli, $worker_script);

        $descriptors = array(
            0 => array('pipe', 'r'),
            1 => array('pipe', 'w'),
            2 => array('pipe', 'w')
        );
        $env = self::buildCliWorkerEnv($php7_cli);
        $options = (DIRECTORY_SEPARATOR === '\\') ? array('bypass_shell' => true) : array();

        $proc = @proc_open($cmd, $descriptors, $pipes, __DIR__, $env, $options);
        if (!is_resource($proc)) {
            $error_msg_out = "Failed to launch Oracle CLI worker: {$php7_cli}";
            return null;
        }

        fwrite($pipes[0], $payload);
        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[2]);
        proc_close($proc);

        $res = json_decode((string)$stdout, true);
        if (!is_array($res)) {
            $error_msg_out = trim((string)$stderr) !== '' ? trim((string)$stderr) : 'Oracle CLI bridge returned invalid JSON';
            return null;
        }
        return $res;
    }

    /**
     * Probe OCI8 bridge readiness without exposing sensitive paths or credentials (used by ver.php).
     *
     * @return array{enabled: bool, ready: bool, mode: string, worker_oci8: bool}
     */
    public static function probeBridgeStatus() {
        if (extension_loaded('oci8') && function_exists('oci_connect')) {
            return array(
                'enabled'     => false,
                'ready'       => true,
                'mode'        => 'native',
                'worker_oci8' => true
            );
        }

        $disabled = strtolower(trim((string)Env::get('ORA_BRIDGE_DISABLED', getenv('ORA_BRIDGE_DISABLED') ?: '')));
        if ($disabled === 'true' || $disabled === '1') {
            return array(
                'enabled'     => false,
                'ready'       => false,
                'mode'        => 'unavailable',
                'worker_oci8' => false
            );
        }

        $ping_payload = json_encode(array('action' => 'ping'));

        // 1. Probe HTTP Loopback if bridge file exists or explicit URL configured
        $bridge_file = defined('ROOT_DIR') ? (ROOT_DIR . DIRECTORY_SEPARATOR . 'api' . DIRECTORY_SEPARATOR . 'ora_bridge.php') : '';
        $explicit_url = trim((string)Env::get('ORA_BRIDGE_URL', getenv('ORA_BRIDGE_URL') ?: ''));
        if ($explicit_url !== '' || ($bridge_file !== '' && @is_file($bridge_file))) {
            $http_res = self::sendHttpLoopbackRequest($ping_payload, 400, 2);
            if (is_array($http_res) && !empty($http_res['oci8'])) {
                return array(
                    'enabled'     => true,
                    'ready'       => true,
                    'mode'        => 'http_loopback',
                    'worker_oci8' => true
                );
            }
        }

        // 2. Probe CLI Worker fallback
        $cli_err = null;
        $cli_res = self::sendCliWorkerRequest($ping_payload, $cli_err);
        if (is_array($cli_res) && !empty($cli_res['oci8'])) {
            return array(
                'enabled'     => true,
                'ready'       => true,
                'mode'        => 'cli_proc_open',
                'worker_oci8' => true
            );
        }

        return array(
            'enabled'     => true,
            'ready'       => false,
            'mode'        => 'unavailable',
            'worker_oci8' => false
        );
    }

    public static function getPointDBTarget() {
        $type = CONNECTION_TYPE::MAIN;
        // get config from system
        $system = System::getInstance();
        $target_str = $system->getOraConnectTarget();
        if ($target_str === 'TEST') {
            $type = CONNECTION_TYPE::TWEB;
        } else if ($target_str === 'BACKUP') {
            $type = CONNECTION_TYPE::BK;
        }
        return $type;
    }

    public function connect() {
        if ($this->use_cli_bridge) {
            $this->connected = true;
            return $this->connected;
        }
        if (!$this->connected) {
            // clean previous connection first
            $this->close();
            $this->conn = oci_connect($this->user, $this->pass, $this->getConnString(), $this->nls);
            if ($this->conn) {
                $this->connected = true;
            } else {
                $this->connected = false;
                $e = oci_error();
                Logger::getInstance()->error(__METHOD__.": ".$e['message']);
                throw new \RuntimeException(htmlentities($e['message'], ENT_QUOTES));
            }
        }
        return $this->connected;
    }

    public function close() {
        if (!$this->use_cli_bridge) {
            if ($this->stid) {
                oci_free_statement($this->stid);
            }
            if ($this->conn) {
                oci_close($this->conn);
            }
        }
        $this->stid = null;
        $this->conn = null;
        $this->connected = false;
        $this->cli_sql = '';
        $this->cli_binds = array();
        $this->cli_rows = array();
        $this->cli_cursor = 0;
    }

    public function parse($str) {
        // lazy connect here
        $this->connect();
        if ($this->use_cli_bridge) {
            $this->cli_sql = $str;
            $this->cli_binds = array();
            $this->cli_rows = array();
            $this->cli_cursor = 0;
            $this->stid = true;
            return;
        }
        // release previous resource
        if ($this->stid) {
            oci_free_statement($this->stid);
        }
        // Prepare the statement
        $this->stid = oci_parse($this->conn, $str);
        if (!$this->stid) {
            $e = oci_error($this->conn);
            
            Logger::getInstance()->error(__METHOD__.": ".$e['message']);
            throw new \RuntimeException(htmlentities($e['message'], ENT_QUOTES));
        }
    }

    /**
     * Decode Binary-Transparent Envelope (or legacy rows) from bridge worker response.
     *
     * @param array $res
     */
    private function applyBridgeResponse(array $res) {
        $this->cli_rows = array();
        if (isset($res['rows_b64']) && is_array($res['rows_b64'])) {
            foreach ($res['rows_b64'] as $enc_row) {
                if (!is_array($enc_row)) {
                    continue;
                }
                $dec_row = array();
                foreach ($enc_row as $col) {
                    if (!is_array($col) || !isset($col['k_b64'])) {
                        continue;
                    }
                    $decoded_key = base64_decode((string)$col['k_b64'], true);
                    $key = ($decoded_key !== false) ? $decoded_key : (string)$col['k_b64'];
                    $val = array_key_exists('v', $col) ? $col['v'] : null;
                    if (!empty($col['is_str']) && is_string($val)) {
                        $decoded_val = base64_decode($val, true);
                        $val = ($decoded_val !== false) ? $decoded_val : $val;
                    }
                    $dec_row[$key] = $val;
                }
                $this->cli_rows[] = $dec_row;
            }
        } elseif (!empty($res['rows']) && is_array($res['rows'])) {
            foreach ($res['rows'] as $enc_row) {
                $dec_row = array();
                foreach ($enc_row as $k => $v) {
                    $dec_row[$k] = is_string($v) ? base64_decode($v) : $v;
                }
                $this->cli_rows[] = $dec_row;
            }
        }

        if (isset($res['affected_rows'])) {
            $this->numrows = (int)$res['affected_rows'];
        } else {
            $this->numrows = count($this->cli_rows);
        }
        $this->cli_cursor = 0;
    }

    public function execute() {
        if ($this->use_cli_bridge) {
            $payload = json_encode(array(
                'action'   => 'query',
                'user'     => (string)$this->user,
                'pass'     => (string)$this->pass,
                'conn_str' => (string)$this->getConnString(),
                'nls'      => (string)$this->nls,
                'sql_b64'  => base64_encode((string)$this->cli_sql),
                'binds'    => $this->cli_binds
            ));
            if ($payload === false) {
                $msg = 'Failed to JSON-encode Oracle bridge request payload: ' . json_last_error_msg();
                $this->cli_error = array('message' => $msg);
                Logger::getInstance()->error(__METHOD__.": ".$msg);
                throw new \RuntimeException(htmlentities($msg, ENT_QUOTES));
            }

            $res = null;
            $cli_err = null;

            // Channel 1: Try Apache mod_php7 HTTP Loopback first (unless disabled or unavailable in this request)
            $explicit_url = trim((string)Env::get('ORA_BRIDGE_URL', getenv('ORA_BRIDGE_URL') ?: ''));
            $custom_cli_worker = trim((string)Env::get('ORA_CLI_WORKER_PATH', getenv('ORA_CLI_WORKER_PATH') ?: ''));
            $bridge_file = defined('ROOT_DIR') ? (ROOT_DIR . DIRECTORY_SEPARATOR . 'api' . DIRECTORY_SEPARATOR . 'ora_bridge.php') : '';
            $can_try_http = !self::$http_loopback_disabled_in_request && (
                $explicit_url !== '' ||
                ($custom_cli_worker === '' && $bridge_file !== '' && @is_file($bridge_file))
            );

            if ($can_try_http) {
                $res = self::sendHttpLoopbackRequest($payload);
                if ($res === null) {
                    self::$http_loopback_disabled_in_request = true;
                }
            }

            // Channel 2: Automatic fallback to PHP 7 CLI Worker via proc_open
            if ($res === null) {
                $res = self::sendCliWorkerRequest($payload, $cli_err);
            }

            if (!is_array($res) || empty($res['ok'])) {
                $err_obj = (is_array($res) && isset($res['error']) && is_array($res['error']))
                    ? $res['error']
                    : array('message' => $cli_err ?: 'Oracle bridge execution failed');
                if (!empty($err_obj['message_b64']) && empty($err_obj['message'])) {
                    $decoded_err = base64_decode((string)$err_obj['message_b64'], true);
                    if ($decoded_err !== false) {
                        $err_obj['message'] = $decoded_err;
                    }
                }
                $msg = isset($err_obj['message']) && $err_obj['message'] !== ''
                    ? (string)$err_obj['message']
                    : ($cli_err ?: 'Oracle bridge execution failed');
                $this->cli_error = $err_obj;
                Logger::getInstance()->error(__METHOD__.": ".$msg);
                throw new \RuntimeException(htmlentities($msg, ENT_QUOTES));
            }

            $this->cli_error = null;
            $this->applyBridgeResponse($res);
            return $this->stid;
        }
        // Perform the logic of the query
        $r = oci_execute($this->stid);
        if (!$r) {
            $e = oci_error($this->stid);
            
            Logger::getInstance()->error(__METHOD__.": ".$e['message']);
            throw new \RuntimeException(htmlentities($e['message'], ENT_QUOTES));
        }
        $affected = @oci_num_rows($this->stid);
        if ($affected !== false) {
            $this->numrows = (int)$affected;
        }
        return $this->stid;
    }

    private function transcodeKeyIfNeeded($key) {
        if (!is_string($key) || $key === '') {
            return $key;
        }
        if (function_exists('mb_check_encoding') && !@mb_check_encoding($key, $this->web_encoding)) {
            $converted = @UConverter::transcode($key, $this->web_encoding, $this->db_encoding);
            if (is_string($converted) && $converted !== '') {
                return $converted;
            }
        }
        return $key;
    }

    public function fetch($raw = false) {
        if ($this->use_cli_bridge) {
            $result = ($this->cli_cursor < count($this->cli_rows))
                ? $this->cli_rows[$this->cli_cursor++]
                : false;
        } else {
            $result = oci_fetch_assoc($this->stid); // oci_fetch_assoc is faster than oci_fetch_array
        }
        if ($raw) {
            return $result;
        }
        $convert = array();
        if (!empty($result)) {
            foreach ($result as $key=>$value) {
                $out_key = $this->transcodeKeyIfNeeded($key);
                $convert[$out_key] = (!is_string($value) || empty($value)) ? $value : UConverter::transcode($value, $this->web_encoding, $this->db_encoding);
            }
        }
        return $convert;
    }
    
    public function fetchAll($raw = false) {
        $this->numrows = 0;
        $results = array();
        if ($this->use_cli_bridge) {
            while ($this->cli_cursor < count($this->cli_rows)) {
                $row = $this->cli_rows[$this->cli_cursor++];
                $out_row = array();
                foreach ($row as $key=>$value) {
                    if ($raw) {
                        $out_row[$key] = $value;
                    } else {
                        $out_key = $this->transcodeKeyIfNeeded($key);
                        $out_row[$out_key] = (!is_string($value) || empty($value)) ? $value : UConverter::transcode($value, $this->web_encoding, $this->db_encoding);
                    }
                }
                $results[] = $out_row;
                $this->numrows++;
            }
            return $results;
        }
        while ($row = oci_fetch_assoc($this->stid)) {
            $out_row = array();
            foreach ($row as $key=>$value) {
                if ($raw) {
                    $out_row[$key] = $value;
                } else {
                    $out_key = $this->transcodeKeyIfNeeded($key);
                    $out_row[$out_key] = (!is_string($value) || empty($value)) ? $value : UConverter::transcode($value, $this->web_encoding, $this->db_encoding);
                }
            }
            $results[] = $out_row;
            $this->numrows++;
        }
        return $results;
    }

    public function getLatestQueryCount() {
        return $this->numrows;
    }

    public function bind($bind_var, $real_var) {
        if ($this->use_cli_bridge) {
            $this->cli_binds[$bind_var] = array(
                'b64' => is_string($real_var),
                'val' => is_string($real_var) ? base64_encode($real_var) : $real_var
            );
            return;
        }
        oci_bind_by_name($this->stid, $bind_var, $real_var);
    }

    public function setConnType($type) {
        switch($type) {
            case CONNECTION_TYPE::L1HWEB:
                $this->CONN_TYPE = CONNECTION_TYPE::L1HWEB;
                break;
            case CONNECTION_TYPE::L1HWEB_Alt:
                $this->CONN_TYPE = CONNECTION_TYPE::L1HWEB_Alt;
                break;
            case CONNECTION_TYPE::L2HWEB:
                $this->CONN_TYPE = CONNECTION_TYPE::L2HWEB;
                break;
            case CONNECTION_TYPE::L3HWEB:
                $this->CONN_TYPE = CONNECTION_TYPE::L3HWEB;
                break;
            case CONNECTION_TYPE::TWEB:
                $this->CONN_TYPE = CONNECTION_TYPE::TWEB;
                break;
            case CONNECTION_TYPE::BK:
                $this->CONN_TYPE = CONNECTION_TYPE::BK;
                break;
            default:
                $this->CONN_TYPE = CONNECTION_TYPE::MAIN;
        }
        // clear DB connection
        $this->close();
    }

    public function getError() {
        if ($this->use_cli_bridge) {
            return $this->cli_error ?? false;
        }
        if ($this->stid) {
            return oci_error($this->stid);
        }
        if ($this->conn) {
            return oci_error($this->conn);
        }
        return oci_error();
    }
}
