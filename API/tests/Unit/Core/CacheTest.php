<?php
declare(strict_types=1);

namespace Tests\Unit\Core;

use PHPUnit\Framework\TestCase;
use Cache;

class CacheTest extends TestCase {
    private Cache $cache;

    protected function setUp(): void {
        parent::setUp();
        $this->cache = Cache::getInstance();
    }

    public function testCacheSingleton(): void {
        $inst1 = Cache::getInstance();
        $inst2 = Cache::getInstance();
        $this->assertSame($inst1, $inst2, 'Cache must follow singleton pattern');
    }

    public function testGetExpireTimestampInMockMode(): void {
        // In mock mode, getExpireTimestamp should always return a future timestamp
        $expire = $this->cache->getExpireTimestamp('test_key');
        $this->assertGreaterThan(time(), $expire, 'Mock cache expire timestamp should be in the future');
    }

    public function testIsExpiredInMockMode(): void {
        // In mock mode, cache is never considered expired
        $this->assertFalse($this->cache->isExpired('test_key'), 'Mock cache is never expired');
    }
}
