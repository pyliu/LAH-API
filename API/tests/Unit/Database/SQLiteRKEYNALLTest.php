<?php
declare(strict_types=1);

namespace Tests\Unit\Database;

use PHPUnit\Framework\TestCase;
use SQLiteRKEYNALL;

class SQLiteRKEYNALLTest extends TestCase {
    public function testInstantiation(): void {
        $dao = new SQLiteRKEYNALL();
        $this->assertInstanceOf(SQLiteRKEYNALL::class, $dao);
    }
}