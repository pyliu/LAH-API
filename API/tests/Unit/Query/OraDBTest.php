<?php
declare(strict_types=1);

namespace Tests\Unit\Query;

use PHPUnit\Framework\TestCase;
use OraDB;
use Env;

class OraDBTest extends TestCase {
    private string $mockWorkerPath = '';

    protected function setUp(): void {
        parent::setUp();
        OraDB::resetBridgeState();
        Env::reset();

        $this->mockWorkerPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'lah_mock_ora_cli_worker_' . getmypid() . '.php';
        $workerCode = <<<'PHP'
<?php
$stdin = file_get_contents('php://stdin');
$req = json_decode($stdin, true);
if (!is_array($req)) {
    echo json_encode(['ok' => false, 'error' => ['message' => 'Bad JSON']]);
    exit(1);
}
$action = $req['action'] ?? 'query';
if ($action === 'ping') {
    echo json_encode([
        'ok' => true,
        'channel' => 'cli_proc_open',
        'php_version' => '7.4.33-mock',
        'oci8' => true
    ]);
    exit(0);
}
$sql = isset($req['sql_b64']) ? base64_decode((string)$req['sql_b64'], true) : ($req['sql'] ?? '');
if (strpos((string)$sql, 'UPDATE ') === 0) {
    echo json_encode([
        'ok' => true,
        'channel' => 'cli_proc_open',
        'stmt_type' => 'UPDATE',
        'affected_rows' => 3,
        'rows_b64' => []
    ]);
    exit(0);
}
// Echo back CP950 column alias and CP950 bound value + SQL check
$cp950Key = mb_convert_encoding('地所名稱', 'CP950', 'UTF-8');
$bindVal = '';
if (!empty($req['binds'][':bv_name']['val'])) {
    $bindVal = !empty($req['binds'][':bv_name']['b64'])
        ? base64_decode((string)$req['binds'][':bv_name']['val'])
        : (string)$req['binds'][':bv_name']['val'];
}
echo json_encode([
    'ok' => true,
    'channel' => 'cli_proc_open',
    'stmt_type' => 'SELECT',
    'affected_rows' => 1,
    'rows_b64' => [
        [
            [
                'k_b64' => base64_encode($cp950Key),
                'is_str' => true,
                'v' => base64_encode($bindVal)
            ],
            [
                'k_b64' => base64_encode('SQL_LEN'),
                'is_str' => false,
                'v' => strlen((string)$sql)
            ]
        ]
    ]
]);
PHP;
        file_put_contents($this->mockWorkerPath, $workerCode);
    }

    protected function tearDown(): void {
        putenv('PHP7_CLI_PATH');
        putenv('ORA_CLI_WORKER_PATH');
        putenv('ORA_BRIDGE_URL');
        putenv('ORA_BRIDGE_DISABLED');
        putenv('ORA_BRIDGE_SECRET');
        unset(
            $_ENV['PHP7_CLI_PATH'],
            $_ENV['ORA_CLI_WORKER_PATH'],
            $_ENV['ORA_BRIDGE_URL'],
            $_ENV['ORA_BRIDGE_DISABLED'],
            $_ENV['ORA_BRIDGE_SECRET']
        );
        Env::reset();
        OraDB::resetBridgeState();
        if ($this->mockWorkerPath !== '' && file_exists($this->mockWorkerPath)) {
            @unlink($this->mockWorkerPath);
        }
        parent::tearDown();
    }

    public function testInstantiation(): void {
        $db = new OraDB();
        $this->assertInstanceOf(OraDB::class, $db);
    }

    public function testBridgeSecretAndHmacSignatureDeterminism(): void {
        putenv('ORA_BRIDGE_SECRET=unit-test-secret-key');
        Env::reset();

        $secret1 = OraDB::getBridgeSecret();
        $secret2 = OraDB::getBridgeSecret();
        $this->assertSame(64, strlen($secret1));
        $this->assertSame($secret1, $secret2);

        $payload = '{"action":"ping"}';
        $sig = OraDB::signBridgePayload($payload);
        $this->assertSame(hash_hmac('sha256', $payload, $secret1), $sig);
    }

    public function testCliBridgeBinaryEnvelopeWithCp950LiteralsAndAliases(): void {
        putenv('PHP7_CLI_PATH=' . PHP_BINARY);
        putenv('ORA_CLI_WORKER_PATH=' . $this->mockWorkerPath);
        Env::reset();

        $db = new OraDB();
        $cp950Literal = mb_convert_encoding('桃園地政', 'CP950', 'UTF-8');
        $cp950Sql = "SELECT * FROM MOICAS.CRSMS WHERE RM09 = '" . $cp950Literal . "' AND RM10 = :bv_name";

        $db->parse($cp950Sql);
        $db->bind(':bv_name', $cp950Literal);
        $db->execute();

        $rows = $db->fetchAll(false);
        $this->assertCount(1, $rows);
        $this->assertArrayHasKey('地所名稱', $rows[0]);
        $this->assertSame('桃園地政', $rows[0]['地所名稱']);
        $this->assertSame(strlen($cp950Sql), $rows[0]['SQL_LEN']);
        $this->assertSame(1, $db->getLatestQueryCount());
    }

    public function testCliBridgeDmlAffectedRows(): void {
        putenv('PHP7_CLI_PATH=' . PHP_BINARY);
        putenv('ORA_CLI_WORKER_PATH=' . $this->mockWorkerPath);
        Env::reset();

        $db = new OraDB();
        $db->parse("UPDATE MOICAS.CRSMS SET RM09 = 'A' WHERE RM01 = '114'");
        $db->execute();

        $this->assertSame(3, $db->getLatestQueryCount());
    }

    public function testHttpLoopbackFallbackToCliWorkerWhenLoopbackUnreachable(): void {
        // Point HTTP Loopback to an closed local port; should seamlessly fall back to CLI worker
        putenv('ORA_BRIDGE_URL=http://127.0.0.1:59998/api/ora_bridge.php');
        putenv('PHP7_CLI_PATH=' . PHP_BINARY);
        putenv('ORA_CLI_WORKER_PATH=' . $this->mockWorkerPath);
        Env::reset();

        $db = new OraDB();
        $cp950Val = mb_convert_encoding('中壢地政', 'CP950', 'UTF-8');
        $db->parse("SELECT 1 FROM DUAL");
        $db->bind(':bv_name', $cp950Val);
        $db->execute();

        $row = $db->fetch(false);
        $this->assertIsArray($row);
        $this->assertSame('中壢地政', $row['地所名稱'] ?? '');
    }

    public function testProbeBridgeStatusSanitizedOutput(): void {
        putenv('PHP7_CLI_PATH=' . PHP_BINARY);
        putenv('ORA_CLI_WORKER_PATH=' . $this->mockWorkerPath);
        Env::reset();

        $status = OraDB::probeBridgeStatus();
        $this->assertSame(['enabled', 'ready', 'mode', 'worker_oci8'], array_keys($status));
        $this->assertTrue($status['enabled']);
        $this->assertTrue($status['ready']);
        $this->assertSame('cli_proc_open', $status['mode']);
        $this->assertTrue($status['worker_oci8']);
    }

    /**
     * Helper to invoke www/api/ora_bridge.php in an isolated CLI subprocess with custom $_SERVER and STDIN body.
     */
    private function invokeOraBridgeSubprocess(array $serverVars, string $rawBody): array {
        $bridgePath = dirname(__DIR__, 4) . DIRECTORY_SEPARATOR . 'www' . DIRECTORY_SEPARATOR . 'api' . DIRECTORY_SEPARATOR . 'ora_bridge.php';
        $runnerCode = sprintf(
            '$_SERVER = array_merge($_SERVER, %s); require %s;',
            var_export($serverVars, true),
            var_export($bridgePath, true)
        );

        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w']
        ];
        $proc = proc_open([PHP_BINARY, '-r', $runnerCode], $descriptors, $pipes, dirname($bridgePath));
        fwrite($pipes[0], $rawBody);
        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        proc_close($proc);

        $decoded = json_decode((string)$stdout, true);
        return is_array($decoded) ? $decoded : ['raw' => $stdout];
    }

    public function testOraBridgeEndpointDualLoopbackGuard(): void {
        $body = json_encode(['action' => 'ping']);
        $validSig = OraDB::signBridgePayload($body);

        // 1. Reject external IP
        $resExternal = $this->invokeOraBridgeSubprocess([
            'REMOTE_ADDR' => '192.168.1.100',
            'HTTP_X_LAH_BRIDGE_SIGNATURE' => $validSig
        ], $body);
        $this->assertFalse($resExternal['ok'] ?? true);
        $this->assertStringContainsString('loopback access only', $resExternal['error']['message'] ?? '');

        // 2. Reject spoofed X-Forwarded-For even if REMOTE_ADDR is 127.0.0.1
        $resForwarded = $this->invokeOraBridgeSubprocess([
            'REMOTE_ADDR' => '127.0.0.1',
            'HTTP_X_FORWARDED_FOR' => '10.0.0.5',
            'HTTP_X_LAH_BRIDGE_SIGNATURE' => $validSig
        ], $body);
        $this->assertFalse($resForwarded['ok'] ?? true);
        $this->assertStringContainsString('loopback access only', $resForwarded['error']['message'] ?? '');

        // 3. Reject invalid HMAC signature
        $resBadSig = $this->invokeOraBridgeSubprocess([
            'REMOTE_ADDR' => '127.0.0.1',
            'HTTP_X_LAH_BRIDGE_SIGNATURE' => 'deadbeef'
        ], $body);
        $this->assertFalse($resBadSig['ok'] ?? true);
        $this->assertStringContainsString('invalid bridge signature', $resBadSig['error']['message'] ?? '');

        // 4. Accept valid loopback + valid HMAC signature
        $resValid = $this->invokeOraBridgeSubprocess([
            'REMOTE_ADDR' => '127.0.0.1',
            'HTTP_X_LAH_BRIDGE_SIGNATURE' => $validSig
        ], $body);
        $this->assertSame('http_loopback', $resValid['channel'] ?? '');
        $this->assertArrayHasKey('oci8', $resValid);
    }
}
