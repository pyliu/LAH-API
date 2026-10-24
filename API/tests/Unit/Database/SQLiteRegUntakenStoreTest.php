<?php
declare(strict_types=1);

namespace Tests\Unit\Database;

use PHPUnit\Framework\TestCase;
use SQLiteRegUntakenStore;

class SQLiteRegUntakenStoreTest extends TestCase {
    public function testInstantiation(): void {
        $dao = new SQLiteRegUntakenStore();
        $this->assertInstanceOf(SQLiteRegUntakenStore::class, $dao);
        $rec = $dao->getRegUntakenRecord("NON_EXIST_CASE");
        $this->assertEmpty($rec);
    }
}