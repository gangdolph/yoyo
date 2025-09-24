<?php
declare(strict_types=1);

/**
 * Append security events to logs/security.log for analyst review.
 */
function log_security_event(string $message): void
{
    $logDir = __DIR__ . '/../logs';
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0755, true);
    }

    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $agent = $_SERVER['HTTP_USER_AGENT'] ?? '-';
    $line = sprintf(
        "[%s] %s | ip=%s | ua=%s%s",
        gmdate('c'),
        $message,
        $ip,
        $agent,
        PHP_EOL
    );

    @file_put_contents($logDir . '/security.log', $line, FILE_APPEND | LOCK_EX);
}

/**
 * Ensure security headers are delivered even when .htaccess is ignored.
 */
function send_security_headers(): void
{
    static $sent = false;

    if ($sent || headers_sent()) {
        return;
    }

    $existing = array_change_key_case(array_reduce(
        headers_list(),
        static function (array $carry, string $header): array {
            [$name] = explode(':', $header, 2);
            $carry[trim(strtolower($name))] = true;

            return $carry;
        },
        []
    ));

    $headers = [
        'X-Content-Type-Options' => 'nosniff',
        'X-Frame-Options' => 'SAMEORIGIN',
        'Referrer-Policy' => 'strict-origin-when-cross-origin',
        'Permissions-Policy' => 'camera=(), microphone=(), geolocation=()',
        'Content-Security-Policy' => "default-src 'self'; img-src 'self' data: https:; script-src 'self' 'unsafe-inline' https:; style-src 'self' 'unsafe-inline' https:; font-src 'self' data: https:; connect-src 'self' https:; frame-ancestors 'self'; base-uri 'self'; form-action 'self'",
    ];

    foreach ($headers as $name => $value) {
        $key = strtolower($name);
        if (!isset($existing[$key])) {
            header($name . ': ' . $value);
        }
    }

    $sent = true;
}

/**
 * Send cache-prevention headers for sensitive views (e.g., login.php).
 */
function send_no_store_headers(): void
{
    if (headers_sent()) {
        return;
    }

    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Expires: 0');
}

/**
 * PHP fallback for sensitive path blocking when rewrite rules are unavailable.
 */
function enforce_sensitive_path_blocklist(): void
{
    static $checked = false;

    if ($checked) {
        return;
    }

    $checked = true;
    $path = (string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);

    $patterns = [
        '#^/(\\.git|\\.svn|\\.hg)(/|$)#i',
        '#^/(\.|_)?env$#i',
        '#^/composer\\.(json|lock)$#i',
    ];

    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $path)) {
            log_security_event('Blocked sensitive path request: ' . $path);
            http_response_code(404);
            exit;
        }
    }

    $basename = basename($path);
    if ($basename !== '' && $basename[0] === '.' && strpos($path, '/.well-known') !== 0) {
        log_security_event('Blocked hidden file request: ' . $path);
        http_response_code(404);
        exit;
    }
}

if (!defined('SECURITY_HELPERS_BOOTSTRAPPED')) {
    define('SECURITY_HELPERS_BOOTSTRAPPED', true);
    enforce_sensitive_path_blocklist();
    send_security_headers();
}
