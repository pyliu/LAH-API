<?php
declare(strict_types=1);

namespace Tests\Unit\Query;

use PHPUnit\Framework\TestCase;
use RegQuery;

class RegQueryTest extends TestCase {
    public function testInstantiation(): void {
        $rq = new RegQuery();
        $this->assertInstanceOf(RegQuery::class, $rq);
    }
}