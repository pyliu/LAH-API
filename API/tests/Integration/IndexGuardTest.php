<?php
declare(strict_types=1);

namespace Tests\Integration;

class IndexGuardTest extends BaseIntegrationTestCase {
    private string $endpoint = 'index.php';

    /**
     * 路徑 1：GET index.php 觸發 403 禁止存取目錄防護
     */
    public function testGetIndexReturnsForbidden(): void {
        $raw = $this->getRaw($this->endpoint);

        $this->assertSame(403, $raw['http_code'], '直接存取 index.php 必須回傳 HTTP 403');
        $this->assertStringContainsString('application/json', $raw['content_type']);

        $json = json_decode($raw['body'], true);
        $this->assertIsArray($json);
        $this->assertSame(403, $json['status']);
        $this->assertStringContainsString('Forbidden: Direct directory access to /v1 is prohibited.', $json['message']);
    }

    /**
     * 路徑 2：POST index.php 觸發 403 禁止存取目錄防護
     */
    public function testPostIndexReturnsForbidden(): void {
        $raw = $this->postRaw($this->endpoint, ['any' => 'data']);

        $this->assertSame(403, $raw['http_code'], 'POST 存取 index.php 必須回傳 HTTP 403');
        $this->assertStringContainsString('application/json', $raw['content_type']);

        $json = json_decode($raw['body'], true);
        $this->assertIsArray($json);
        $this->assertSame(403, $json['status']);
        $this->assertStringContainsString('Forbidden', $json['message']);
    }

    /**
     * 路徑 3：直接存取 /v1/ 根路徑（預設導引至 index.php 目錄守護）
     */
    public function testDirectoryRootAccessReturnsForbidden(): void {
        $raw = $this->getRaw('');

        $this->assertSame(403, $raw['http_code'], '存取 /v1/ 根目錄必須回傳 HTTP 403');
        $json = json_decode($raw['body'], true);
        $this->assertIsArray($json);
        $this->assertSame(403, $json['status']);
        $this->assertStringContainsString('Forbidden: Direct directory access to /v1 is prohibited.', $json['message']);
    }
}
