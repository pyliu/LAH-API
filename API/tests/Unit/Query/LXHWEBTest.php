<?php
declare(strict_types=1);

namespace Tests\Unit\Query;

use PHPUnit\Framework\TestCase;
use LXHWEB;

class LXHWEBTest extends TestCase {
    public function testInstantiation(): void {
        $lx = new LXHWEB();
        $this->assertInstanceOf(LXHWEB::class, $lx);
    }
}