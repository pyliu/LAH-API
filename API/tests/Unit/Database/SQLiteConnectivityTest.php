<?php
declare(strict_types=1);

namespace Tests\Unit\Database;

use PHPUnit\Framework\TestCase;
use SQLiteConnectivity;

class SQLiteConnectivityTest extends TestCase {
    public function testInstantiation(): void {
        $dao = new SQLiteConnectivity();
        $this->assertInstanceOf(SQLiteConnectivity::class, $dao);
        $targets = $dao->getTargets();
        $this->assertIsArray($targets);
    }
}