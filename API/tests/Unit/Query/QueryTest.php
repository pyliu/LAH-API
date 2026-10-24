<?php
declare(strict_types=1);

namespace Tests\Unit\Query;

use PHPUnit\Framework\TestCase;
use Query;

class QueryTest extends TestCase {
    public function testInstantiation(): void {
        $q = new Query();
        $this->assertInstanceOf(Query::class, $q);
    }

    public function testGetCodeDataOfflineFallback(): void {
        $q = new Query();
        $data = $q->getCodeData("113");
        $this->assertIsArray($data);
        $this->assertEmpty($data);
    }
}