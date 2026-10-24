<?php
declare(strict_types=1);

namespace Tests\Unit\Core;

use PHPUnit\Framework\TestCase;
use DocUserInfo;

class DocUserInfoTest extends TestCase {
    public function testInstantiation(): void {
        $dui = new DocUserInfo();
        $this->assertInstanceOf(DocUserInfo::class, $dui);
    }
}