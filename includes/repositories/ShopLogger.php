<?php
/*
 * Discovery note: Shared shop logger lacked the discovery/change header and context.
 * Change: Documented the helper so future updates know it centralises structured auditing.
 */
declare(strict_types=1);

/**
 * Write a structured event to the shop log for auditing.
 */
function shop_log(string $event, array $context = []): void
{
    $logDir = dirname(__DIR__, 1) . '/../logs';
    $file = $logDir . '/shop.log';

    if (!is_dir($logDir)) {
        mkdir($logDir, 0775, true);
    }

    $timestamp = date('Y-m-d H:i:s');
    $payload = $context ? json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : '';
    if ($payload === false) {
        $payload = '';
    }

    $line = sprintf('[%s] %s%s', $timestamp, $event, $payload !== '' ? ' ' . $payload : '');

    file_put_contents($file, $line . PHP_EOL, FILE_APPEND | LOCK_EX);
}
