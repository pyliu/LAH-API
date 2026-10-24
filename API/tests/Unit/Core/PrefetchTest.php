<?php
declare(strict_types=1);

namespace Tests\Unit\Core;

use PHPUnit\Framework\TestCase;
use Prefetch;

class PrefetchTest extends TestCase {
    public function testInstantiation(): void {
        $pf = new Prefetch();
        $this->assertInstanceOf(Prefetch::class, $pf);
    }
}