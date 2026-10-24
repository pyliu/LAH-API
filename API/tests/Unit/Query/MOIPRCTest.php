<?php
declare(strict_types=1);

namespace Tests\Unit\Query;

use PHPUnit\Framework\TestCase;
use MOIPRC;

class MOIPRCTest extends TestCase {
    public function testInstantiation(): void {
        $prc = new MOIPRC();
        $this->assertInstanceOf(MOIPRC::class, $prc);
    }
}