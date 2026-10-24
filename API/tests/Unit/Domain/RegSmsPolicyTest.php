<?php
declare(strict_types=1);

namespace Tests\Unit\Domain;

use PHPUnit\Framework\TestCase;
use RegSmsPolicy;

class RegSmsPolicyTest extends TestCase {
    public function testGetCrossCode(): void {
        $this->assertEquals("H1", RegSmsPolicy::getCrossCode("HA"));
        $this->assertEquals("H2", RegSmsPolicy::getCrossCode("HB"));
        $this->assertEquals("H8", RegSmsPolicy::getCrossCode("HH"));
        $this->assertEquals("", RegSmsPolicy::getCrossCode("INVALID"));
    }

    public function testExtractCaseWord(): void {
        $this->assertEquals("HA81", RegSmsPolicy::extractCaseWord("113-HA81-000001"));
        $this->assertEquals("HB01", RegSmsPolicy::extractCaseWord("113HB01000002"));
        $this->assertEquals("", RegSmsPolicy::extractCaseWord("short"));
    }

    public function testIsLocalCaseWord(): void {
        $this->assertTrue(RegSmsPolicy::isLocalCaseWord("HA81", "HA"));
        $this->assertFalse(RegSmsPolicy::isLocalCaseWord("HB81", "HA"));
        $this->assertNull(RegSmsPolicy::isLocalCaseWord("XYZ", "HA"));
    }
}