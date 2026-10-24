<?php
declare(strict_types=1);

namespace Tests\Integration;

use PHPUnit\Framework\TestCase;

class ApiEndpointsTest extends TestCase {
    private string $baseUrl = 'http://127.0.0.1/LAH-API/v1/';
    private string $apiDir;

    protected function setUp(): void {
        parent::setUp();
        $this->apiDir = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'v1';
    }

    public function testAll37EndpointsRespondProperly(): void {
        $files = glob($this->apiDir . DIRECTORY_SEPARATOR . '*.php');
        $this->assertNotEmpty($files, 'API directory should not be empty');
        $this->assertCount(37, $files, 'There should be exactly 37 API endpoints in v1');

        foreach ($files as $file) {
            $apiName = basename($file);
            $url = $this->baseUrl . $apiName;

            $postData = http_build_query(['type' => 'test']);
            $opts = [
                'http' => [
                    'method' => 'POST',
                    'header' => "Content-type: application/x-www-form-urlencoded\r\n",
                    'content' => $postData,
                    'ignore_errors' => true,
                    'timeout' => 5
                ]
            ];
            $context = stream_context_create($opts);
            $response = @file_get_contents($url, false, $context);

            $this->assertNotFalse($response, "Failed to connect to API endpoint: {$apiName}");

            // Extract HTTP status code from $http_response_header
            $statusLine = $http_response_header[0] ?? '';
            preg_match('{HTTP\/\S*\s(\d{3})}', $statusLine, $match);
            $statusCode = isset($match[1]) ? (int)$match[1] : 500;

            // Must not be 500 Internal Server Error
            $this->assertNotEquals(500, $statusCode, "API {$apiName} returned HTTP 500 Internal Server Error");

            // Must not contain PHP Fatal error
            $this->assertStringNotContainsString('Fatal error', $response, "API {$apiName} contains PHP Fatal error");
            $this->assertStringNotContainsString('Parse error', $response, "API {$apiName} contains PHP Parse error");

            // If JSON response, verify structure
            $json = json_decode($response, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($json)) {
                $this->assertArrayHasKey('status', $json, "API {$apiName} JSON response missing 'status' field");
            }
        }
    }
}
