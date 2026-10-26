<?php
declare(strict_types=1);

namespace Tests\Integration;

use PHPUnit\Framework\TestCase;
use STATUS_CODE;

abstract class BaseIntegrationTestCase extends TestCase {
    protected string $baseUrl;
    protected int $timeout = 10;
    protected int $lastHttpCode = 0;

    public function getLastHttpCode(): int {
        return $this->lastHttpCode;
    }

    protected function setUp(): void {
        parent::setUp();
        $envBase = getenv('LAH_API_BASE_URL');
        $this->baseUrl = $envBase ? rtrim($envBase, '/') . '/' : 'http://127.0.0.1/LAH-API/v1/';
    }

    /**
     * 發送 HTTP POST 請求並回傳解析後之 JSON 陣列
     */
    protected function post(string $endpoint, array $data = [], array $files = [], array $headers = []): array {
        $url = $this->baseUrl . ltrim($endpoint, '/');
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);

        if (!empty($files)) {
            $postFields = $data;
            foreach ($files as $field => $filePath) {
                if (!file_exists($filePath)) {
                    $this->fail("Upload test file does not exist: {$filePath}");
                }
                $mime = mime_content_type($filePath) ?: 'application/octet-stream';
                $postFields[$field] = new \CURLFile($filePath, $mime, basename($filePath));
            }
            curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);
        } else {
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
            $headers[] = 'Content-Type: application/x-www-form-urlencoded';
        }

        if (!empty($headers)) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        }

        $rawResponse = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $this->lastHttpCode = $httpCode;
        $error = curl_error($ch);
        unset($ch);

        $this->assertEmpty($error, "cURL error when calling {$endpoint}: {$error}");
        $this->assertNotEquals(500, $httpCode, "Endpoint {$endpoint} returned HTTP 500 Internal Server Error");
        $this->assertIsString($rawResponse, "Empty response from {$endpoint}");
        $this->assertStringNotContainsString('Fatal error', $rawResponse, "Endpoint {$endpoint} output contains Fatal error");
        $this->assertStringNotContainsString('Parse error', $rawResponse, "Endpoint {$endpoint} output contains Parse error");

        $json = json_decode($rawResponse, true);
        $this->assertIsArray($json, "Response from {$endpoint} is not valid JSON. Raw output: {$rawResponse}");
        $this->assertArrayHasKey('status', $json, "Response missing 'status' field: {$rawResponse}");

        return $json;
    }

    /**
     * 發送 HTTP GET 請求並回傳解析後之 JSON 陣列
     */
    protected function get(string $endpoint, array $queryParams = [], array $headers = []): array {
        $url = $this->baseUrl . ltrim($endpoint, '/');
        if (!empty($queryParams)) {
            $url .= '?' . http_build_query($queryParams);
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);

        if (!empty($headers)) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        }

        $rawResponse = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $this->lastHttpCode = $httpCode;
        $error = curl_error($ch);
        unset($ch);

        $this->assertEmpty($error, "cURL error when calling {$endpoint}: {$error}");
        $this->assertNotEquals(500, $httpCode, "Endpoint {$endpoint} returned HTTP 500 Internal Server Error");

        $json = json_decode((string)$rawResponse, true);
        if (is_array($json)) {
            return $json;
        }

        return ['raw' => $rawResponse, 'http_code' => $httpCode];
    }

    protected function assertResponseSuccess(array $json, ?string $messageSubstring = null): void {
        $this->assertSame(STATUS_CODE::SUCCESS_NORMAL, $json['status'], "Expected status SUCCESS_NORMAL(1), got: " . json_encode($json, JSON_UNESCAPED_UNICODE));
        if ($messageSubstring !== null) {
            $this->assertStringContainsString($messageSubstring, $json['message'] ?? '');
        }
    }

    protected function assertResponseFail(array $json, ?int $expectedStatus = null, ?string $messageSubstring = null): void {
        if ($expectedStatus !== null) {
            $this->assertSame($expectedStatus, $json['status'], "Expected status {$expectedStatus}, got: " . json_encode($json, JSON_UNESCAPED_UNICODE));
        } else {
            $this->assertNotSame(STATUS_CODE::SUCCESS_NORMAL, $json['status'], "Expected failure status, got SUCCESS_NORMAL");
        }
        if ($messageSubstring !== null) {
            $this->assertStringContainsString($messageSubstring, $json['message'] ?? '');
        }
    }

    protected function assertResponseUnsupport(array $json): void {
        $this->assertSame(STATUS_CODE::UNSUPPORT_FAIL, $json['status'], "Expected status UNSUPPORT_FAIL(-1), got: " . json_encode($json, JSON_UNESCAPED_UNICODE));
    }
}
