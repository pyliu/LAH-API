<?php
declare(strict_types=1);

namespace Tests\Unit\Query;

use PHPUnit\Framework\TestCase;
use MOICAD;

class MOICADTest extends TestCase {
    public function testInstantiation(): void {
        $cad = new MOICAD();
        $this->assertInstanceOf(MOICAD::class, $cad);
    }
}