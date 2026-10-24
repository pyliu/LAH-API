<?php
declare(strict_types=1);

namespace Tests\Unit\Domain;

use PHPUnit\Framework\TestCase;
use RegCaseData;

class RegCaseDataTest extends TestCase {
    public function testInstantiationWithEmptyArray(): void {
        $rc = new RegCaseData([]);
        $this->assertInstanceOf(RegCaseData::class, $rc);
    }

    public function testInstantiationWithMockRecord(): void {
        $mockRecord = [
            "RM01" => "113",
            "RM02" => "HA81",
            "RM03" => "000001",
            "RM09" => "01",
            "RM24" => "A1"
        ];
        $rc = new RegCaseData($mockRecord);
        $this->assertInstanceOf(RegCaseData::class, $rc);
    }
}