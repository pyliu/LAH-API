<?php
declare(strict_types=1);

namespace Tests\Unit\Query;

use PHPUnit\Framework\TestCase;
use OraDB;

class OraDBTest extends TestCase {
    public function testInstantiation(): void {
        $db = new OraDB();
        $this->assertInstanceOf(OraDB::class, $db);
    }
}