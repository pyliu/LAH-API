<?php
declare(strict_types=1);

namespace Tests\Unit\Database;

use PHPUnit\Framework\TestCase;
use SQLiteDBFactory;

class SQLiteDBFactoryTest extends TestCase {
    public function testGetDbFolder(): void {
        $folder = SQLiteDBFactory::getDbFolder();
        $this->assertNotEmpty($folder);
        $this->assertDirectoryExists($folder);
    }

    public function testGetPrefetchDB(): void {
        $path = SQLiteDBFactory::getPrefetchDB();
        $this->assertStringEndsWith("prefetch.db", $path);
        $this->assertFileExists($path);
    }
}