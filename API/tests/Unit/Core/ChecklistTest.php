<?php
declare(strict_types=1);

namespace Tests\Unit\Core;

use PHPUnit\Framework\TestCase;
use Checklist;

class ChecklistTest extends TestCase {
    public function testInstantiationAndDebug(): void {
        $chk = new Checklist();
        $this->assertInstanceOf(Checklist::class, $chk);
        $chk->debug();
        $this->assertTrue(true);
    }
}