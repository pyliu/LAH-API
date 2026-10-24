<?php
declare(strict_types=1);

namespace Tests\Unit\Database;

use PHPUnit\Framework\TestCase;
use SQLiteImage;

class SQLiteImageTest extends TestCase {
    public function testInstantiation(): void {
        $dao = new SQLiteImage();
        $this->assertInstanceOf(SQLiteImage::class, $dao);
    }
}