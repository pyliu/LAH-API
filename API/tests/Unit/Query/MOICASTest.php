<?php
declare(strict_types=1);

namespace Tests\Unit\Query;

use PHPUnit\Framework\TestCase;
use MOICAS;

class MOICASTest extends TestCase {
    public function testInstantiation(): void {
        $cas = new MOICAS();
        $this->assertInstanceOf(MOICAS::class, $cas);
    }

    public function testCheckCaseExistsOfflineFallback(): void {
        $cas = new MOICAS();
        $res = $cas->checkCaseExists("113-HA81-000001");
        $this->assertEquals(-1, $res, "Offline MOICAS checkCaseExists must return -1");
    }
}