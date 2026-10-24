<?php
declare(strict_types=1);

namespace Tests\Unit\Export;

use PHPUnit\Framework\TestCase;
use FileAPIDataCleanCommand;

class FileAPIDataCleanCommandTest extends TestCase {
    public function testInstantiation(): void {
        $cmd = new FileAPIDataCleanCommand();
        $this->assertInstanceOf(FileAPIDataCleanCommand::class, $cmd);
    }
}