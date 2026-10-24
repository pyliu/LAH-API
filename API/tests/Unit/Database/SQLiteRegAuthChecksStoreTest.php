<?php
declare(strict_types=1);

namespace Tests\Unit\Database;

use PHPUnit\Framework\TestCase;
use SQLiteRegAuthChecksStore;

class SQLiteRegAuthChecksStoreTest extends TestCase {
    public function testInstantiation(): void {
        $dao = new SQLiteRegAuthChecksStore();
        $this->assertInstanceOf(SQLiteRegAuthChecksStore::class, $dao);
        $rec = $dao->getRegAuthChecksRecord("NON_EXIST_CASE");
        $this->assertEmpty($rec);
    }
}