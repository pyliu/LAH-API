<?php
declare(strict_types=1);

namespace Tests\Unit\Database;

use PHPUnit\Framework\TestCase;
use SQLiteMonitorMail;

class SQLiteMonitorMailTest extends TestCase {
    public function testInstantiation(): void {
        $dao = new SQLiteMonitorMail();
        $this->assertInstanceOf(SQLiteMonitorMail::class, $dao);
    }
}