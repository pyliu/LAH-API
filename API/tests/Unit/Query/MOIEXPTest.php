<?php
declare(strict_types=1);

namespace Tests\Unit\Query;

use PHPUnit\Framework\TestCase;
use MOIEXP;

class MOIEXPTest extends TestCase {
    public function testInstantiation(): void {
        $exp = new MOIEXP();
        $this->assertInstanceOf(MOIEXP::class, $exp);
    }
}