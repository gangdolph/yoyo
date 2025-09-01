<?php
error_reporting(E_ALL); ini_set('display_errors', '1');

$root = __DIR__;
$autoload = $root . '/vendor/autoload.php';

echo "<pre>";
echo "PHP: " . PHP_VERSION . "\n";
echo "Docroot: $root\n";
echo "open_basedir: " . ini_get('open_basedir') . "\n";
echo "Autoload exists: " . (file_exists($autoload) ? 'YES' : 'NO') . "\n";
echo "vendor/square dir exists: " . (is_dir($root.'/vendor/square') ? 'YES' : 'NO') . "\n";
echo "vendor/square/square dir exists: " . (is_dir($root.'/vendor/square/square') ? 'YES' : 'NO') . "\n";

require $autoload;

echo "Composer loader present: " . (class_exists('Composer\\Autoload\\ClassLoader') ? 'YES' : 'NO') . "\n";
echo "Square\\SquareClient exists: " . (class_exists('\\Square\\SquareClient') ? 'YES' : 'NO') . "\n";
echo "Square\\Apis\\PaymentsApi exists: " . (class_exists('\\Square\\Apis\\PaymentsApi') ? 'YES' : 'NO') . "\n";

$psr4_file = $root . '/vendor/composer/autoload_psr4.php';
echo "autoload_psr4.php exists: " . (file_exists($psr4_file) ? 'YES' : 'NO') . "\n";
if (file_exists($psr4_file)) {
  $psr4 = require $psr4_file;
  echo "PSR-4 keys count: " . count($psr4) . "\n";
  $squareKeys = array_filter(array_keys($psr4), fn($k) => stripos($k, 'Square\\') === 0);
  echo "PSR-4 keys for Square: " . implode(', ', $squareKeys) . "\n";
}

$installed_file = $root . '/vendor/composer/installed.php';
echo "installed.php exists: " . (file_exists($installed_file) ? 'YES' : 'NO') . "\n";
if (file_exists($installed_file)) {
  $installed = require $installed_file;
  $versions = $installed['versions'] ?? $installed;
  $hasSquare = false;
  foreach ($versions as $name => $info) {
    $pkg = is_string($name) ? $name : ($info['name'] ?? '');
    if ($pkg === 'square/square') { $hasSquare = true; break; }
  }
  echo "Installed 'square/square': " . ($hasSquare ? 'YES' : 'NO') . "\n";
}

echo "</pre>";
