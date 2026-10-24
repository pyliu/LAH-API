<?php
declare(strict_types=1);

namespace Tests\Unit\Export;

use PHPUnit\Framework\TestCase;
use FileAPILogExportCommand;

class FileAPILogExportCommandTest extends TestCase {
    public function testInstantiation(): void {
        $cmd = new FileAPILogExportCommand("2026-10-24");
        $this->assertInstanceOf(FileAPILogExportCommand::class, $cmd);
    }
}