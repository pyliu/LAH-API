<?php
declare(strict_types=1);

namespace Tests\Unit\Database;

use PHPUnit\Framework\TestCase;
use SQLiteSYSAUTH1;

class SQLiteSYSAUTH1Test extends TestCase {
    public function testInstantiation(): void {
        $dao = new SQLiteSYSAUTH1();
        $this->assertInstanceOf(SQLiteSYSAUTH1::class, $dao);
        $users = $dao->getAllUsers();
        $this->assertIsArray($users);
    }
}