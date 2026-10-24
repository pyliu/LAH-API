<?php
declare(strict_types=1);

namespace Tests\Unit\Query;

use PHPUnit\Framework\TestCase;
use MyQuery;

class MyQueryTest extends TestCase {
    public function testInstantiation(): void {
        $mq = new MyQuery("HB0001");
        $this->assertInstanceOf(MyQuery::class, $mq);
    }
}