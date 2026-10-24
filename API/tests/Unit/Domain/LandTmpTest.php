<?php
declare(strict_types=1);

namespace Tests\Unit\Domain;

use PHPUnit\Framework\TestCase;
use LandTmp;

class LandTmpTest extends TestCase {
    public function testInstantiation(): void {
        $lt = new LandTmp();
        $this->assertInstanceOf(LandTmp::class, $lt);
    }
}