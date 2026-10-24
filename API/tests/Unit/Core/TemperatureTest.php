<?php
declare(strict_types=1);

namespace Tests\Unit\Core;

use PHPUnit\Framework\TestCase;
use Temperature;

class TemperatureTest extends TestCase {
    public function testInstantiation(): void {
        $temp = new Temperature();
        $this->assertInstanceOf(Temperature::class, $temp);
    }

    public function testQueryTemperatureReturnsArray(): void {
        $temp = new Temperature();
        $records = $temp->get("");
        $this->assertIsArray($records);
    }
}