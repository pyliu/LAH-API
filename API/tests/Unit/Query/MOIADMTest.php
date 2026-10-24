<?php
declare(strict_types=1);

namespace Tests\Unit\Query;

use PHPUnit\Framework\TestCase;
use MOIADM;

class MOIADMTest extends TestCase {
    public function testInstantiation(): void {
        $adm = new MOIADM();
        $this->assertInstanceOf(MOIADM::class, $adm);
    }
}