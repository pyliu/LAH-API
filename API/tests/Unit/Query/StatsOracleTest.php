<?php
declare(strict_types=1);

namespace Tests\Unit\Query;

use PHPUnit\Framework\TestCase;
use StatsOracle;

class StatsOracleTest extends TestCase {
    public function testInstantiation(): void {
        $so = new StatsOracle();
        $this->assertInstanceOf(StatsOracle::class, $so);
    }
}