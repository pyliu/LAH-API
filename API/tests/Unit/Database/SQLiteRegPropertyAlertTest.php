<?php
declare(strict_types=1);

namespace Tests\Unit\Database;

use PHPUnit\Framework\TestCase;
use SQLiteRegPropertyAlert;

class SQLiteRegPropertyAlertTest extends TestCase {
    public function testInstantiation(): void {
        $dao = new SQLiteRegPropertyAlert();
        $this->assertInstanceOf(SQLiteRegPropertyAlert::class, $dao);
        $one = $dao->getOne(999999);
        $this->assertEmpty($one);
    }
}