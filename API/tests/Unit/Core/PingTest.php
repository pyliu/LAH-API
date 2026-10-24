<?php
declare(strict_types=1);

namespace Tests\Unit\Core;

use PHPUnit\Framework\TestCase;
use Ping;

class PingTest extends TestCase {
    public function testInstantiationAndHost(): void {
        $ping = new Ping("127.0.0.1", 1, 64);
        $this->assertInstanceOf(Ping::class, $ping);
        $this->assertEquals("127.0.0.1", $ping->getHost());
        $this->assertEquals(64, $ping->getTtl());
        $this->assertEquals(1, $ping->getTimeout());
    }

    public function testSetPort(): void {
        $ping = new Ping("127.0.0.1");
        $ping->setPort(8080);
        $this->assertEquals(8080, $ping->getPort());
    }
}