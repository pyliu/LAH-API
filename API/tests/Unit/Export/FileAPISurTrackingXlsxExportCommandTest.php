<?php
declare(strict_types=1);

namespace Tests\Unit\Export;

use PHPUnit\Framework\TestCase;
use FileAPISurTrackingXlsxExportCommand;

class FileAPISurTrackingXlsxExportCommandTest extends TestCase {
    public function testInstantiation(): void {
        $cmd = new FileAPISurTrackingXlsxExportCommand();
        $this->assertInstanceOf(FileAPISurTrackingXlsxExportCommand::class, $cmd);
    }
}