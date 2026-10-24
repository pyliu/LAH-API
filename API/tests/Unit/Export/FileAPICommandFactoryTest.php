<?php
declare(strict_types=1);

namespace Tests\Unit\Export;

use PHPUnit\Framework\TestCase;
use FileAPICommandFactory;
use FileAPIExcelExportCommand;
use FileAPISQLCsvCommand;
use FileAPIDataCleanCommand;
use FileAPINotSupportCommand;

class FileAPICommandFactoryTest extends TestCase {
    public function testCreateExcelExportCommand(): void {
        $cmd = FileAPICommandFactory::getCommand('file_xlsx');
        $this->assertInstanceOf(FileAPIExcelExportCommand::class, $cmd);
    }

    public function testCreateCsvCommand(): void {
        $_POST['sql'] = 'SELECT 1';
        $cmd = FileAPICommandFactory::getCommand('file_sql_csv');
        $this->assertInstanceOf(FileAPISQLCsvCommand::class, $cmd);
    }

    public function testCreateDataCleanCommand(): void {
        $cmd = FileAPICommandFactory::getCommand('file_data_clean');
        $this->assertInstanceOf(FileAPIDataCleanCommand::class, $cmd);
    }

    public function testCreateUnsupportedCommandFallback(): void {
        $cmd = FileAPICommandFactory::getCommand('completely_unsupported_type_xyz');
        $this->assertInstanceOf(FileAPINotSupportCommand::class, $cmd);
    }
}
