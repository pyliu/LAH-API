<?php
declare(strict_types=1);

namespace Tests\Unit\Database;

use PHPUnit\Framework\TestCase;
use SQLiteAPConnectionHistory;

class SQLiteAPConnectionHistoryTest extends TestCase {
    public function testInstantiation(): void {
        $dao = new SQLiteAPConnectionHistory("192.168.1.1");
        $this->assertInstanceOf(SQLiteAPConnectionHistory::class, $dao);
    }
}