<?php
declare(strict_types=1);

namespace Tests\Unit\Core;

use PHPUnit\Framework\TestCase;
use MonitorMail;

class MonitorMailTest extends TestCase {
    public function testInstantiation(): void {
        $mm = new MonitorMail();
        $this->assertInstanceOf(MonitorMail::class, $mm);
    }
}