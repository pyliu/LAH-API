<?php
declare(strict_types=1);

namespace Tests\Unit\Query;

use PHPUnit\Framework\TestCase;
use SQLSRV_DataBase;

class SQLSRVDataBaseTest extends TestCase {
    public function testInstantiationWithoutConnection(): void {
        $db = new SQLSRV_DataBase("test_user", "test_pass", "test_db", "127.0.0.1", "UTF-8", 1433, false);
        $this->assertInstanceOf(SQLSRV_DataBase::class, $db);
    }
}