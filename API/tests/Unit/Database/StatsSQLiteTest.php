<?php
declare(strict_types=1);

namespace Tests\Unit\Database;

use PHPUnit\Framework\TestCase;
use StatsSQLite;

class StatsSQLiteTest extends TestCase {
    public function testInstantiation(): void {
        $dao = new StatsSQLite();
        $this->assertInstanceOf(StatsSQLite::class, $dao);
    }
}