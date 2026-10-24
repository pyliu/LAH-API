<?php
declare(strict_types=1);

namespace Tests\Unit\Core;

use PHPUnit\Framework\TestCase;
use System;

class SystemTest extends TestCase {
    private System $system;

    protected function setUp(): void {
        parent::setUp();
        $this->system = System::getInstance();
    }

    public function testSingletonInstance(): void {
        $instance1 = System::getInstance();
        $instance2 = System::getInstance();
        $this->assertSame($instance1, $instance2, 'System must follow singleton pattern');
    }

    public function testGetSiteCodeDefaultFallback(): void {
        $site = $this->system->getSiteCode();
        $this->assertNotEmpty($site);
        $this->assertIsString($site);
        $this->assertGreaterThanOrEqual(2, strlen($site));
    }

    public function testGetSiteAlphabetAndNumber(): void {
        $alphabet = $this->system->getSiteAlphabet();
        $this->assertNotEmpty($alphabet);
        $this->assertIsString($alphabet);

        $number = $this->system->getSiteNumber();
        $this->assertIsInt($number);
        $this->assertGreaterThanOrEqual(0, $number);
    }

    public function testMockModeAlwaysTrueInTestEnvironment(): void {
        $this->assertTrue($this->system->isMockMode(), 'MOCK mode must be active in test environment');
    }

    public function testCalcEffectiveAuthorityBitmask(): void {
        // Admin IP (127.0.0.1) should yield isAdmin and isSuper true in returned auth array
        $authAdmin = $this->system->calcEffectiveAuthority(0, '127.0.0.1');
        $this->assertIsArray($authAdmin);
        $this->assertTrue($authAdmin['isAdmin'] ?? false, 'Localhost IP must have isAdmin=true');
        $this->assertTrue($authAdmin['isSuper'] ?? false, 'Localhost IP must have isSuper=true');

        // Regular remote IP should not be admin by default
        $authRegular = $this->system->calcEffectiveAuthority(0, '192.168.200.200');
        $this->assertIsArray($authRegular);
        $this->assertFalse($authAdmin['isAdmin'] === false && $authRegular['isAdmin']);
    }
}
