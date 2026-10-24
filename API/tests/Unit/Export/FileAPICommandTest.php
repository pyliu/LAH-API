<?php
declare(strict_types=1);

namespace Tests\Unit\Export;

use PHPUnit\Framework\TestCase;
use FileAPICommand;

class ConcreteFileAPICommandStub extends FileAPICommand {
    public function execute(): void {}
    public function testCleanData(&$str): void {
        $this->cleanData($str);
    }
}

class FileAPICommandTest extends TestCase {
    public function testCleanDataBoolean(): void {
        $cmd = new ConcreteFileAPICommandStub();
        $valT = "t";
        $cmd->testCleanData($valT);
        $this->assertEquals("TRUE", $valT);

        $valF = "f";
        $cmd->testCleanData($valF);
        $this->assertEquals("FALSE", $valF);
    }
}