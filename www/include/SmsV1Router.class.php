<?php
require_once(__DIR__ . DIRECTORY_SEPARATOR . 'SmsV1Service.class.php');

/**
 * SmsV1Router - 純 HTTP 請求分派器（供 sms_v1_api.php 與單元測試共用）
 */
class SmsV1Router {
    /** @var SmsV1Service */
    private $service;

    public function __construct(?SmsV1Service $service = null) {
        $this->service = $service !== null ? $service : new SmsV1Service();
    }

    /**
     * 分派 HTTP 請求並回傳標準回應結構
     *
     * @param string $method HTTP Method（GET / POST ...）
     * @param string $path 請求路徑（支援完整前綴或相對於 /api/v1/sms 的子路徑）
     * @param array<string, mixed> $query Query string 參數（支援 route/path/endpoint/action fallback）
     * @param string $rawBody 原始請求 Body
     * @return array{status: int, body: array<string, mixed>}
     */
    public function dispatch(string $method, string $path, array $query = array(), string $rawBody = ''): array {
        $normalizedMethod = strtoupper(trim($method));
        if ($normalizedMethod === '') {
            $normalizedMethod = 'GET';
        }
        $normalizedPath = $this->normalizePath($path, $query);

        $routes = array(
            '/metadata' => array(
                'GET' => 'handleGetMetadata',
            ),
            '/cases/sync' => array(
                'POST' => 'handlePostCasesSync',
            ),
        );

        if (!isset($routes[$normalizedPath])) {
            return array(
                'status' => 404,
                'body' => array(
                    'error' => 'Not Found',
                    'message' => 'Route not found: ' . $normalizedMethod . ' ' . $normalizedPath,
                ),
            );
        }

        $methodMap = $routes[$normalizedPath];
        if (!isset($methodMap[$normalizedMethod])) {
            return array(
                'status' => 405,
                'body' => array(
                    'error' => 'Method Not Allowed',
                    'message' => 'Method ' . $normalizedMethod . ' is not allowed for ' . $normalizedPath,
                ),
            );
        }

        $handler = $methodMap[$normalizedMethod];

        try {
            return $this->$handler($query, $rawBody);
        } catch (\InvalidArgumentException $e) {
            return array(
                'status' => 400,
                'body' => array(
                    'error' => 'Bad Request',
                    'message' => $e->getMessage(),
                ),
            );
        } catch (\Throwable $e) {
            $this->logError('SmsV1Router error on ' . $normalizedMethod . ' ' . $normalizedPath . ': ' . $e->getMessage());
            return array(
                'status' => 500,
                'body' => array(
                    'error' => 'Internal Server Error',
                    'message' => 'Failed to process SMS API request.',
                ),
            );
        }
    }

    /**
     * @param array<string, mixed> $query
     * @param string $rawBody
     * @return array{status: int, body: array<string, mixed>}
     */
    private function handleGetMetadata(array $query, string $rawBody): array {
        return array(
            'status' => 200,
            'body' => $this->service->getMetadata(),
        );
    }

    /**
     * @param array<string, mixed> $query
     * @param string $rawBody
     * @return array{status: int, body: array<string, mixed>}
     */
    private function handlePostCasesSync(array $query, string $rawBody): array {
        $targetDate = null;
        $trimmed = trim($rawBody);
        if ($trimmed !== '') {
            $payload = json_decode($trimmed, true);
            if (json_last_error() !== JSON_ERROR_NONE || !is_array($payload)) {
                throw new \InvalidArgumentException('Invalid JSON request body.');
            }
            if (array_key_exists('target_date_yyy_mm_dd', $payload) && $payload['target_date_yyy_mm_dd'] !== null) {
                if (!is_string($payload['target_date_yyy_mm_dd'])) {
                    throw new \InvalidArgumentException('Invalid target_date_yyy_mm_dd: expected 7-digit ROC date YYYMMDD.');
                }
                $targetDate = $payload['target_date_yyy_mm_dd'];
            }
        }

        return array(
            'status' => 200,
            'body' => $this->service->syncCases($targetDate),
        );
    }

    /**
     * @param string $path
     * @param array<string, mixed> $query
     * @return string
     */
    private function normalizePath(string $path, array $query): string {
        $parsed = parse_url($path, PHP_URL_PATH);
        $clean = is_string($parsed) ? trim($parsed) : trim($path);

        if ($clean === '' || $clean === '/') {
            foreach (array('route', 'path', 'endpoint', 'action') as $key) {
                if (isset($query[$key]) && is_string($query[$key]) && trim($query[$key]) !== '') {
                    $clean = trim($query[$key]);
                    break;
                }
            }
        }

        if ($clean === '') {
            return '/';
        }

        if ($clean[0] !== '/') {
            $clean = '/' . $clean;
        }

        $prefixes = array(
            '#^/(?:LAH-API|lah-api)/(?:v1|api)/sms_v1_api\.php(?=/|$)#',
            '#^/(?:LAH-API|lah-api)/(?:v1|api)/sms(?=/|$)#',
            '#^/api/v1/sms(?=/|$)#',
            '#^/api/sms_v1_api\.php(?=/|$)#',
            '#^/sms_v1_api\.php(?=/|$)#',
        );
        foreach ($prefixes as $pattern) {
            $replaced = preg_replace($pattern, '', $clean, 1, $count);
            if ($count > 0 && is_string($replaced)) {
                $clean = $replaced;
                break;
            }
        }

        if ($clean === '' || $clean === '/') {
            return '/';
        }

        $clean = '/' . trim($clean, '/');
        return $clean;
    }

    private function logError(string $message): void {
        if (class_exists('Logger', false)) {
            try {
                Logger::getInstance()->error($message);
            } catch (\Throwable $ignored) {
                // Avoid masking API 500 JSON response if logger fails
            }
        }
    }
}
