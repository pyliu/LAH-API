<?php
declare(strict_types=1);

namespace Tests\Unit\Export;

use PHPUnit\Framework\TestCase;
use FileAPISQLTxtCommand;

class FileAPISQLTxtCommandTest extends TestCase {
    public function testInstantiation(): void {
        $cmd = new FileAPISQLTxtCommand("SELECT 1");
        $this->assertInstanceOf(FileAPISQLTxtCommand::class, $cmd);
    }
}