<?php
declare(strict_types=1);

namespace Tests\Unit\Query;

use PHPUnit\Framework\TestCase;
use OraDB;
use OraDBWrapper;
use Env;

class OraDBWrapperTest extends TestCase {
    protected function tearDown(): void {
        putenv('PHP7_CLI_PATH');
        putenv('ORA_CLI_WORKER_PATH');
        putenv('ORA_BRIDGE_URL');
        putenv('ORA_BRIDGE_DISABLED');
        unset(
            $_ENV['PHP7_CLI_PATH'],
            $_ENV['ORA_CLI_WORKER_PATH'],
            $_ENV['ORA_BRIDGE_URL'],
            $_ENV['ORA_BRIDGE_DISABLED']
        );
        Env::reset();
        OraDB::resetBridgeState();
        parent::tearDown();
    }

    public function testInstantiationAndSiteWhenBridgeUnavailable(): void {
        putenv('ORA_BRIDGE_DISABLED=true');
        Env::reset();

        $wrapper = new OraDBWrapper();
        $this->assertInstanceOf(OraDBWrapper::class, $wrapper);
        $site = $wrapper->getSite();
        $this->assertNotEmpty($site);
        $this->assertFalse($wrapper->reachable(), "When OCI8 and bridge are disabled, reachable must be false");
        $this->assertNull($wrapper->getDB());
        $this->assertFalse($wrapper->getError(), "getError() must safely return false instead of throwing on null DB");
    }

    public function testReachableTrueWhenBridgeAvailable(): void {
        putenv('ORA_BRIDGE_DISABLED=false');
        putenv('PHP7_CLI_PATH=' . PHP_BINARY);
        Env::reset();

        $this->assertTrue(OraDB::isDriverOrBridgeAvailable());
        $wrapper = new OraDBWrapper();
        $this->assertTrue($wrapper->reachable(), "When PHP 7 bridge is available and DB ping succeeds, reachable must be true");
        $this->assertInstanceOf(OraDB::class, $wrapper->getDB());
    }
}
