<?php
declare(strict_types=1);

namespace Tests\Unit\Export;

use PHPUnit\Framework\TestCase;
use FileAPIExcelExportCommand;

class FileAPIExcelExportCommandTest extends TestCase {
    public function testInstantiation(): void {
        $cmd = new FileAPIExcelExportCommand();
        $this->assertInstanceOf(FileAPIExcelExportCommand::class, $cmd);
    }
}