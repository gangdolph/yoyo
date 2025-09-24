<?php
/*
 * Discovery note: Existing payment flow hand-crafted cURL calls without shared error handling or config validation.
 * Change: Added a reusable Square HTTP client that validates configuration and centralizes retries/logging.
 */

declare(strict_types=1);

require_once __DIR__ . '/square-log.php';

final class SquareConfigException extends RuntimeException
{
}

final class SquareHttpClient
{
    private const SQUARE_VERSION = '2024-08-15';

    private string $environment;
    private string $baseUrl;
    private string $accessToken;
    private string $locationId;
    private string $applicationId;

    /**
     * @param array<string, mixed> $config
     */
    public function __construct(array $config)
    {
        $this->environment = strtolower(trim((string)($config['square_environment'] ?? 'sandbox')));
        if ($this->environment === '') {
            $this->environment = 'sandbox';
        }

        if (!in_array($this->environment, ['sandbox', 'production'], true)) {
            square_log('square.config_invalid_environment', ['environment' => $this->environment]);
            http_response_code(500);
            throw new SquareConfigException('Unsupported Square environment configuration.');
        }

        $this->accessToken = $this->requireConfig($config, 'square_access_token');
        $this->locationId = $this->requireConfig($config, 'square_location_id');
        $this->applicationId = $this->requireConfig($config, 'square_application_id');

        $this->baseUrl = $this->environment === 'production'
            ? 'https://connect.squareup.com'
            : 'https://connect.squareupsandbox.com';
    }

    public function getEnvironment(): string
    {
        return $this->environment;
    }

    public function getLocationId(): string
    {
        return $this->locationId;
    }

    public function getApplicationId(): string
    {
        return $this->applicationId;
    }

    public function getBaseUrl(): string
    {
        return $this->baseUrl;
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{statusCode: int, body: mixed, raw: string}
     */
    public function request(
        string $method,
        string $path,
        ?array $payload = null,
        ?string $idempotencyKey = null
    ): array {
        $method = strtoupper($method);
        $attempts = 0;
        $maxAttempts = 3;
        $backoffSeconds = 1.0;
        $raw = '';
        $decoded = null;
        $statusCode = 0;
        $errorMessage = null;

        $urlPath = strpos($path, 'http') === 0 ? $path : $this->baseUrl . $path;

        while ($attempts < $maxAttempts) {
            $attempts++;

            $headers = [
                'Content-Type: application/json',
                'Accept: application/json',
                'Square-Version: ' . self::SQUARE_VERSION,
                'Authorization: Bearer ' . $this->accessToken,
            ];
            if ($idempotencyKey !== null && $idempotencyKey !== '') {
                $headers[] = 'Idempotency-Key: ' . $idempotencyKey;
            }

            $options = [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_HTTPHEADER => $headers,
                CURLOPT_FAILONERROR => false,
            ];

            $targetUrl = $urlPath;

            if ($method === 'GET' && !empty($payload)) {
                $query = http_build_query($payload);
                $targetUrl .= (str_contains($targetUrl, '?') ? '&' : '?') . $query;
            } elseif ($payload !== null) {
                $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                if ($json === false) {
                    throw new RuntimeException('Failed to encode Square request payload.');
                }
                $options[CURLOPT_POSTFIELDS] = $json;
            }

            if ($method === 'POST') {
                $options[CURLOPT_POST] = true;
            } else {
                $options[CURLOPT_CUSTOMREQUEST] = $method;
            }

            $options[CURLOPT_URL] = $targetUrl;

            $handle = curl_init();
            if ($handle === false) {
                throw new RuntimeException('Unable to initialize Square cURL handle.');
            }

            curl_setopt_array($handle, $options);

            $raw = (string)curl_exec($handle);
            $curlErrNo = curl_errno($handle);
            $curlErr = $curlErrNo !== 0 ? curl_error($handle) : null;
            $statusCode = (int)curl_getinfo($handle, CURLINFO_HTTP_CODE);
            curl_close($handle);

            if ($curlErrNo !== 0) {
                $errorMessage = 'Transport error: ' . $curlErr;
                square_log('square.http_transport_error', [
                    'path' => $path,
                    'attempt' => $attempts,
                    'error' => $curlErr,
                ]);
            } else {
                $decoded = json_decode($raw, true);
                if ($decoded === null && json_last_error() !== JSON_ERROR_NONE) {
                    $errorMessage = 'Invalid JSON response.';
                    square_log('square.http_invalid_json', [
                        'path' => $path,
                        'status' => $statusCode,
                        'body' => $raw,
                    ]);
                } elseif ($statusCode === 429 || ($statusCode >= 500 && $statusCode < 600)) {
                    $errorMessage = 'Square responded with retryable status.';
                    square_log('square.http_retry', [
                        'path' => $path,
                        'status' => $statusCode,
                        'attempt' => $attempts,
                    ]);
                } else {
                    $errorMessage = null;
                }
            }

            if ($errorMessage === null) {
                break;
            }

            if ($attempts < $maxAttempts) {
                usleep((int)($backoffSeconds * 1_000_000));
                $backoffSeconds *= 2;
            }
        }

        if ($errorMessage !== null) {
            square_log('square.http_failure', [
                'path' => $path,
                'status' => $statusCode,
                'error' => $errorMessage,
                'body' => $raw,
            ]);
            throw new RuntimeException('Square request failed: ' . $errorMessage);
        }

        return [
            'statusCode' => $statusCode,
            'body' => $decoded,
            'raw' => $raw,
        ];
    }

    /**
     * @param array<string, mixed> $config
     */
    private function requireConfig(array $config, string $key): string
    {
        $value = trim((string)($config[$key] ?? ''));
        if ($value === '') {
            square_log('square.config_missing', ['key' => $key]);
            http_response_code(500);
            throw new SquareConfigException('Missing Square configuration: ' . $key);
        }
        return $value;
    }
}
