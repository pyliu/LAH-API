<?php
declare(strict_types=1);

namespace Tests\Unit\Core;

use PHPUnit\Framework\TestCase;
use Scheduler;

class SchedulerTest extends TestCase {
    public function testInstantiation(): void {
        $sched = new Scheduler();
        $this->assertInstanceOf(Scheduler::class, $sched);
    }
}