<?php
declare(strict_types=1);

namespace Tests\Unit\Database;

use PHPUnit\Framework\TestCase;
use SQLiteRegForeignerPDF;

class SQLiteRegForeignerPDFTest extends TestCase {
    public function testInstantiation(): void {
        $dao = new SQLiteRegForeignerPDF();
        $this->assertInstanceOf(SQLiteRegForeignerPDF::class, $dao);
        $one = $dao->getOne(999999);
        $this->assertEmpty($one);
    }
}