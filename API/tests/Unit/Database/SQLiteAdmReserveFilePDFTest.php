<?php
declare(strict_types=1);

namespace Tests\Unit\Database;

use PHPUnit\Framework\TestCase;
use SQLiteAdmReserveFilePDF;

class SQLiteAdmReserveFilePDFTest extends TestCase {
    public function testInstantiation(): void {
        $dao = new SQLiteAdmReserveFilePDF();
        $this->assertInstanceOf(SQLiteAdmReserveFilePDF::class, $dao);
        $res = $dao->getOne(999999);
        $this->assertEmpty($res);
    }
}