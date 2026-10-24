<?php
declare(strict_types=1);

namespace Tests\Unit\Database;

use PHPUnit\Framework\TestCase;
use SQLiteRegForeignerRestriction;

class SQLiteRegForeignerRestrictionTest extends TestCase {
    public function testInstantiation(): void {
        $dao = new SQLiteRegForeignerRestriction();
        $this->assertInstanceOf(SQLiteRegForeignerRestriction::class, $dao);
        $one = $dao->getOne("NON_EXIST_PKEY");
        $this->assertEmpty($one);
    }
}