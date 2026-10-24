<?php
declare(strict_types=1);

namespace Tests\Unit\Core;

use PHPUnit\Framework\TestCase;
use IPResolver;
use System;

class IPResolverTest extends TestCase {
    public function testParseUnitNormal(): void {
        $this->assertEquals('資訊課', IPResolver::parseUnit('HA inf'));
        $this->assertEquals('登記課', IPResolver::parseUnit('HB reg'));
        $this->assertEquals('行政課', IPResolver::parseUnit('HC adm'));
        $this->assertEquals('測量課', IPResolver::parseUnit('HD sur'));
        $this->assertEquals('地價課', IPResolver::parseUnit('HE val'));
    }

    public function testParseUnitEmptyOrUnknown(): void {
        $this->assertEquals('未分配', IPResolver::parseUnit(''));
        $this->assertEquals('未分配', IPResolver::parseUnit('unknown'));
    }

    public function testPackUserDataAdmin(): void {
        $site = System::getInstance()->getSiteCode();
        $raw = [
            'entry_id' => $site . 'ADMIN',
            'entry_desc' => '系統管理者',
            'note' => $site . ' inf',
            'ip' => '127.0.0.1',
            'authority' => 1
        ];

        $packed = IPResolver::packUserData($raw);
        $this->assertIsArray($packed);
        $this->assertEquals($site . 'ADMIN', $packed['id']);
        $this->assertEquals('系統管理者', $packed['name']);
        $this->assertEquals('資訊課', $packed['unit']);
        $this->assertEquals('系管人員', $packed['title']);
    }

    public function testPackUserDataRegular(): void {
        $raw = [
            'entry_id' => 'HB9999',
            'entry_desc' => '王大明',
            'note' => 'HB reg',
            'ip' => '192.168.1.100',
            'authority' => 2
        ];

        $packed = IPResolver::packUserData($raw);
        $this->assertIsArray($packed);
        $this->assertEquals('HB9999', $packed['id']);
        $this->assertEquals('王大明', $packed['name']);
        $this->assertEquals('登記課', $packed['unit']);
    }
}
