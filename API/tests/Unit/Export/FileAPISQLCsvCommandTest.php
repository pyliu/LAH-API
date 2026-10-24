<?php
declare(strict_types=1);

namespace Tests\Unit\Export;

use PHPUnit\Framework\TestCase;
use FileAPISQLCsvCommand;

class FileAPISQLCsvCommandTest extends TestCase {
    public function testInstantiation(): void {
        $cmd = new FileAPISQLCsvCommand("SELECT 1");
        $this->assertInstanceOf(FileAPISQLCsvCommand::class, $cmd);
    }
}