<?php
declare(strict_types=1);

namespace Tests\Unit\Database;

use PHPUnit\Framework\TestCase;
use SQLiteOFFICES;

class SQLiteOFFICESTest extends TestCase {
    public function testInstantiation(): void {
        $dao = new SQLiteOFFICES();
        $this->assertInstanceOf(SQLiteOFFICES::class, $dao);
        $res = $dao->get("HA");
        $this->assertIsArray($res);
    }
}