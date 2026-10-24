<?php
declare(strict_types=1);

namespace Tests\Unit\Export;

use PHPUnit\Framework\TestCase;
use FileAPINotSupportCommand;

class FileAPINotSupportCommandTest extends TestCase {
    public function testInstantiation(): void {
        $cmd = new FileAPINotSupportCommand();
        $this->assertInstanceOf(FileAPINotSupportCommand::class, $cmd);
    }
}