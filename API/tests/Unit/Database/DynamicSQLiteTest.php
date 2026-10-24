<?php
declare(strict_types=1);

namespace Tests\Unit\Database;

use PHPUnit\Framework\TestCase;
use DynamicSQLite;

class DynamicSQLiteTest extends TestCase {
    public function testInitAndCreateTable(): void {
        $dbPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . "dynamic_test_" . uniqid() . ".db";
        $dsl = new DynamicSQLite($dbPath);
        $dsl->initDB();
        $dsl->createTableBySQL("CREATE TABLE IF NOT EXISTS test_tbl (id INTEGER PRIMARY KEY, name TEXT)");
        $this->assertFileExists($dbPath);
        if (file_exists($dbPath)) @unlink($dbPath);
    }
}