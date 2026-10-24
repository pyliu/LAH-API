<?php
declare(strict_types=1);

namespace Tests\Unit\Core;

use PHPUnit\Framework\TestCase;
use TdocUserInfo;

class TdocUserInfoTest extends TestCase {
    public function testInstantiation(): void {
        $tdui = new TdocUserInfo();
        $this->assertInstanceOf(TdocUserInfo::class, $tdui);
    }
}