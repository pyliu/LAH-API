<?php
declare(strict_types=1);

namespace Tests\Unit\Core;

use PHPUnit\Framework\TestCase;
use Env;

class EnvTest extends TestCase {
    public function testLoadReturnsArray(): void {
        $env = Env::load();
        $this->assertIsArray($env);
    }

    public function testGetExistingAndFallback(): void {
        $val = Env::get("NON_EXISTENT_KEY_" . uniqid(), "DEFAULT_FALLBACK");
        $this->assertEquals("DEFAULT_FALLBACK", $val);

        $mock = Env::get("MOCK", "false");
        $this->assertEquals("true", (string)$mock);
    }
}