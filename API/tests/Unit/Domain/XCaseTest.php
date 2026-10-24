<?php
declare(strict_types=1);

namespace Tests\Unit\Domain;

use PHPUnit\Framework\TestCase;
use XCase;

class XCaseTest extends TestCase {
    public function testInstantiation(): void {
        $xcase = new XCase();
        $this->assertInstanceOf(XCase::class, $xcase);
    }

    public function testOfflineFallbackReturnsFalse(): void {
        $xcase = new XCase();
        $res = $xcase->getLocalDBMaxNumByWord("HA81", "113");
        $this->assertFalse($res, "Without Oracle connection, getLocalDBMaxNumByWord must return false");
    }
}