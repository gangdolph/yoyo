<?php
declare(strict_types=1);

// Absolute Composer autoloader (from /public_html/includes/)
$autoloadPath = __DIR__ . '/../vendor/autoload.php';
if (!file_exists($autoloadPath)) {
  throw new RuntimeException("Missing Composer autoload at $autoloadPath");
}

/** @var Composer\Autoload\ClassLoader $loader */
$loader = require $autoloadPath;

/*
 * If Composer was dumped in classmap-authoritative mode in web PHP,
 * PSR-4 lookups are ignored. Re-enable them at runtime.
 */
$ref = new \ReflectionClass($loader);
$prop = $ref->getProperty('classMapAuthoritative');
$prop->setAccessible(true);
if ($prop->getValue($loader) === true) {
  $prop->setValue($loader, false);
}

/*
 * Force-register Square’s PSR-4 namespace. This works even if the
 * web runtime is holding onto stale autoload files.
 */
$squareSrc = __DIR__ . '/../vendor/square/square/src';
if (is_dir($squareSrc)) {
  $loader->addPsr4('Square\\', $squareSrc, true);
}

if (!class_exists('\Square\SquareClient')) {
  throw new RuntimeException("Square SDK not autoloadable after PSR-4 registration");
}

require __DIR__ . '/../config.square.php';
$token = defined('SQUARE_ACCESS_TOKEN') ? (string) SQUARE_ACCESS_TOKEN : '';
$env = defined('SQUARE_ENV') ? strtolower((string) SQUARE_ENV) : 'sandbox';
$baseUrl = $env === 'production'
    ? 'https://connect.squareup.com'
    : 'https://connect.squareupsandbox.com';

$client = new \Square\SquareClient(
  $token,
  options: ['baseUrl' => $baseUrl],
);

return $client;
