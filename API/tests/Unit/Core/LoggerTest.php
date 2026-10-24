<?php
declare(strict_types=1);

namespace Tests\Unit\Core;

use PHPUnit\Framework\TestCase;
use Logger;

class LoggerTest extends TestCase {
    public function testLoggerSingletonInstance(): void {
        $l1 = Logger::getInstance();
        $l2 = Logger::getInstance();
        $this->assertSame($l1, $l2);
    }

    public function testLoggerMethodsDoNotThrow(): void {
        $logger = Logger::getInstance();
        $logger->info("Unit test info log");
        $logger->warning("Unit test warning log");
        $logger->error("Unit test error log");
        $this->assertTrue(true);
    }
}