<?php
declare(strict_types=1);

namespace Tests\Unit\Database;

use PHPUnit\Framework\TestCase;
use SQLiteRKEYN;

class SQLiteRKEYNTest extends TestCase {
    public function testInstantiation(): void {
        $dao = new SQLiteRKEYN();
        $this->assertInstanceOf(SQLiteRKEYN::class, $dao);
    }
}