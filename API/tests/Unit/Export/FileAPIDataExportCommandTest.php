<?php
declare(strict_types=1);

namespace Tests\Unit\Export;

use PHPUnit\Framework\TestCase;
use FileAPIDataExportCommand;

class FileAPIDataExportCommandTest extends TestCase {
    public function testInstantiation(): void {
        $cmd = new FileAPIDataExportCommand("AI00301", "ALL");
        $this->assertInstanceOf(FileAPIDataExportCommand::class, $cmd);
    }
}