<?php
declare(strict_types=1);

namespace Tests\Unit\Database;

use PHPUnit\Framework\TestCase;
use SQLiteRegFixCaseStore;

class SQLiteRegFixCaseStoreTest extends TestCase {
    public function testInstantiation(): void {
        $dao = new SQLiteRegFixCaseStore();
        $this->assertInstanceOf(SQLiteRegFixCaseStore::class, $dao);
        $rec = $dao->getRegFixCaseRecord("NON_EXIST_CASE");
        $this->assertEmpty($rec);
    }
}