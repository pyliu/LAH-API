<?php
declare(strict_types=1);

namespace Tests\Unit\Database;

use PHPUnit\Framework\TestCase;
use SQLiteValRealpriceMemoStore;

class SQLiteValRealpriceMemoStoreTest extends TestCase {
    public function testInstantiation(): void {
        $dao = new SQLiteValRealpriceMemoStore();
        $this->assertInstanceOf(SQLiteValRealpriceMemoStore::class, $dao);
        $rec = $dao->getValRealpriceMemoRecord("NON_EXIST_CASE");
        $this->assertEmpty($rec);
    }
}