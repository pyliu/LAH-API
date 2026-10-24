<?php
declare(strict_types=1);

namespace Tests\Unit\Database;

use PHPUnit\Framework\TestCase;
use SQLiteSurDestructionTracking;

class SQLiteSurDestructionTrackingTest extends TestCase {
    public function testInstantiation(): void {
        $dao = new SQLiteSurDestructionTracking();
        $this->assertInstanceOf(SQLiteSurDestructionTracking::class, $dao);
    }
}