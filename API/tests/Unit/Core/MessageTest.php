<?php
declare(strict_types=1);

namespace Tests\Unit\Core;

use PHPUnit\Framework\TestCase;
use Message;

class MessageTest extends TestCase {
    public function testInstantiation(): void {
        $msg = new Message();
        $this->assertInstanceOf(Message::class, $msg);
    }
}