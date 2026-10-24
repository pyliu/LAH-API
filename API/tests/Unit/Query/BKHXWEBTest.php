<?php
declare(strict_types=1);

namespace Tests\Unit\Query;

use PHPUnit\Framework\TestCase;
use BKHXWEB;

class BKHXWEBTest extends TestCase {
    public function testInstantiation(): void {
        $bk = new BKHXWEB();
        $this->assertInstanceOf(BKHXWEB::class, $bk);
    }
}