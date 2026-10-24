<?php
declare(strict_types=1);

namespace Tests\Unit\Export;

use PHPUnit\Framework\TestCase;
use FileAPIInheritanceRestrictionXlsxExportCommand;

class FileAPIInheritanceRestrictionXlsxExportCommandTest extends TestCase {
    public function testInstantiation(): void {
        $cmd = new FileAPIInheritanceRestrictionXlsxExportCommand();
        $this->assertInstanceOf(FileAPIInheritanceRestrictionXlsxExportCommand::class, $cmd);
    }
}