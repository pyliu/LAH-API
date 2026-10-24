<?php
declare(strict_types=1);

namespace Tests\Unit\Database;

use PHPUnit\Framework\TestCase;
use SQLiteOFFICESSTATS;

class SQLiteOFFICESSTATSTest extends TestCase {
    public function testInstantiation(): void {
        $dao = new SQLiteOFFICESSTATS();
        $this->assertInstanceOf(SQLiteOFFICESSTATS::class, $dao);
    }
}