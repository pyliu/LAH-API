<?php
declare(strict_types=1);

namespace Tests\Unit\Query;

use PHPUnit\Framework\TestCase;
use MSDB;

class MSDBTest extends TestCase {
    public function testInstantiation(): void {
        $msdb = new MSDB();
        $this->assertInstanceOf(MSDB::class, $msdb);
    }
}