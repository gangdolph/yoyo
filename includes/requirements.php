<?php
$requiredExtensions = ['curl', 'json', 'mbstring', 'openssl', 'mysqli'];
$missing = [];
foreach ($requiredExtensions as $ext) {
  if (!extension_loaded($ext)) {
    $missing[] = $ext;
  }
}
if ($missing) {
  $message = 'Missing PHP extensions: ' . implode(', ', $missing) .
    ". Enable them in cPanel's \"Select PHP Version\" interface.";
  error_log($message);
  die($message);
}

