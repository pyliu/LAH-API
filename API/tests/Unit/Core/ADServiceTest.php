<?php
declare(strict_types=1);

namespace Tests\Unit\Core;

use PHPUnit\Framework\TestCase;
use AdService;

class ADServiceTest extends TestCase {
    public function testInstantiationOrExtensionException(): void {
        if (!function_exists("ldap_connect")) {
            $this->expectException(\RuntimeException::class);
            new AdService();
        } else {
            $ad = new AdService();
            $this->assertInstanceOf(AdService::class, $ad);
        }
    }
}