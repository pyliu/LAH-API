<?php
declare(strict_types=1);

namespace Tests\Unit\Domain;

use PHPUnit\Framework\TestCase;
use PrcAllCasesData;

class PrcAllCasesDataTest extends TestCase {
    public function testInstantiationWithArray(): void {
        $record = [
            "SS03" => "113",
            "SS04_1" => "HA",
            "SS04_2" => "012345"
        ];
        $data = new PrcAllCasesData($record);
        $this->assertInstanceOf(PrcAllCasesData::class, $data);
    }
}