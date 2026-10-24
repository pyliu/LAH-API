<?php
declare(strict_types=1);

namespace Tests\Unit\Database;

use PHPUnit\Framework\TestCase;
use SQLiteAdminActionLog;

class SQLiteAdminActionLogTest extends TestCase {
    public function testSingleton(): void {
        $inst1 = SQLiteAdminActionLog::getInstance();
        $inst2 = SQLiteAdminActionLog::getInstance();
        $this->assertSame($inst1, $inst2);
    }
}