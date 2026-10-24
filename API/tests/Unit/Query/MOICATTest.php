<?php
declare(strict_types=1);

namespace Tests\Unit\Query;

use PHPUnit\Framework\TestCase;
use MOICAT;

class MOICATTest extends TestCase {
    public function testInstantiation(): void {
        $cat = new MOICAT();
        $this->assertInstanceOf(MOICAT::class, $cat);
    }
}