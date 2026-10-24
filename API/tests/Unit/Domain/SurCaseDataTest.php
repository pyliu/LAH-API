<?php
declare(strict_types=1);

namespace Tests\Unit\Domain;

use PHPUnit\Framework\TestCase;
use SurCaseData;

class SurCaseDataTest extends TestCase {
    public function testInstantiation(): void {
        $mockRecord = [
            "MM01" => "113",
            "MM02" => "HA81",
            "MM03" => "000001",
            "MM04_1" => "1130101",
            "MM04_2" => "100000",
            "MM22" => "O"
        ];
        $sur = new SurCaseData($mockRecord);
        $this->assertInstanceOf(SurCaseData::class, $sur);
        $this->assertEquals("O：收件", $sur->getStatus());
    }
}