<?php
declare(strict_types=1);

namespace Tests\Unit\Database;

use PHPUnit\Framework\TestCase;
use SQLiteSMSLog;

class SQLiteSMSLogTest extends TestCase {
    public function testInstantiation(): void {
        $dao = new SQLiteSMSLog();
        $this->assertInstanceOf(SQLiteSMSLog::class, $dao);
    }
}