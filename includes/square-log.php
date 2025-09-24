<?php
/*
 * Discovery note: Existing logging lived in repositories/ShopLogger with plain text output.
 * Change: Added Square-specific helper writing to logs/square.log with sensitive keys scrubbed.
 */

declare(strict_types=1);

/**
 * Write a sanitized entry to the Square integration log.
 */
function square_log(string $event, array $context = []): void
{
    $logDir = dirname(__DIR__) . '/logs';
    $file = $logDir . '/square.log';

    if (!is_dir($logDir)) {
        mkdir($logDir, 0775, true);
    }

    $scrubbed = [];
    foreach ($context as $key => $value) {
        $normalized = is_string($key) ? strtolower($key) : '';
        if ($normalized !== '' && preg_match('/token|secret|key|password/', $normalized)) {
            $scrubbed[$key] = '[redacted]';
            continue;
        }
        if (is_scalar($value) || $value === null) {
            $scrubbed[$key] = $value;
        } else {
            $encoded = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $scrubbed[$key] = $encoded !== false ? $encoded : '[unserializable]';
        }
    }

    $payload = $scrubbed ? json_encode($scrubbed, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : '';
    if ($payload === false) {
        $payload = '';
    }

    $line = sprintf('[%s] %s%s', date('Y-m-d H:i:s'), $event, $payload !== '' ? ' ' . $payload : '');

    file_put_contents($file, $line . PHP_EOL, FILE_APPEND | LOCK_EX);
}
