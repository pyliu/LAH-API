<?php
declare(strict_types=1);

namespace Tests\Unit\Core;

use PHPUnit\Framework\TestCase;
use WatchDog;

class WatchDogTest extends TestCase {
    public function testInstantiation(): void {
        $wd = new WatchDog();
        $this->assertInstanceOf(WatchDog::class, $wd);
    }
}