<?php
declare(strict_types=1);

namespace Tests\Unit\Query;

use PHPUnit\Framework\TestCase;
use MOISMS;

class MOISMSTest extends TestCase {
    public function testInstantiation(): void {
        $sms = new MOISMS();
        $this->assertInstanceOf(MOISMS::class, $sms);
    }
}