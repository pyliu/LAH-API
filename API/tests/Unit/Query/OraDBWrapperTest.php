<?php
declare(strict_types=1);

namespace Tests\Unit\Query;

use PHPUnit\Framework\TestCase;
use OraDBWrapper;

class OraDBWrapperTest extends TestCase {
    public function testInstantiationAndSite(): void {
        $wrapper = new OraDBWrapper();
        $this->assertInstanceOf(OraDBWrapper::class, $wrapper);
        $site = $wrapper->getSite();
        $this->assertNotEmpty($site);
        $this->assertFalse($wrapper->reachable(), "In test environment without OCI8, reachable must be false");
    }
}