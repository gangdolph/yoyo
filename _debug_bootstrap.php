<?php
declare(strict_types=1);

/**
 * Temporary diagnostics bootstrap for shared hosting.
 * - Forces PHP to log all errors to a writable file in docroot
 * - Catches fatal errors via shutdown function
 * - Optional browser output when ?debug_token=SHOW&verbose=1
 *
 * Remove this file (and its includes) after the issue is solved.
 */

if (!defined('APP_DEBUG_BOOTSTRAP')) {
  define('APP_DEBUG_BOOTSTRAP', true);

  // Target log file in docroot (writable on cPanel)
  $logFile = __DIR__ . '/php_error.log';

  // Turn on full error reporting to LOG (not to screen by default)
  error_reporting(E_ALL);
  ini_set('display_errors', '0');           // keep screen clean by default
  ini_set('log_errors', '1');
  ini_set('error_log', $logFile);
  ini_set('html_errors', '0');

  // Friendly fatal handler (writes last error to log and, if authorized, to screen)
  register_shutdown_function(function () use ($logFile) {
    $e = error_get_last();
    if ($e && in_array($e['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
      $line = sprintf(
        "[fatal] %s in %s:%d\nREQUEST_URI=%s\nPOST=%s\n",
        $e['message'],
        $e['file'],
        $e['line'],
        $_SERVER['REQUEST_URI'] ?? '',
        json_encode($_POST ?? [], JSON_UNESCAPED_SLASHES)
      );
      error_log($line);
      // Optional controlled screen output
      $tokenOk = (($_GET['debug_token'] ?? '') === 'SHOW');
      $verbose = (isset($_GET['verbose']) && $_GET['verbose'] === '1');
      if ($tokenOk && $verbose) {
        header('Content-Type: text/plain; charset=UTF-8', true, 500);
        echo "A fatal error occurred.\n\n", $line;
      } else {
        // Generic friendly message
        if (!headers_sent()) header('HTTP/1.1 500 Internal Server Error');
        echo "Something went wrong. Please try again.";
      }
      exit;
    }
  });

  // Helper to log handled exceptions/notices
  set_error_handler(function ($severity, $message, $file, $line) {
    // Respect @-operator
    if (!(error_reporting() & $severity)) return false;
    error_log("[php] $message in $file:$line");
    return false; // let PHP’s normal handler proceed too
  });

  set_exception_handler(function (Throwable $ex) {
    error_log("[exception] " . $ex->getMessage() . " in " . $ex->getFile() . ":" . $ex->getLine());
    // Optional controlled screen output
    $tokenOk = (($_GET['debug_token'] ?? '') === 'SHOW');
    $verbose = (isset($_GET['verbose']) && $_GET['verbose'] === '1');
    if ($tokenOk && $verbose) {
      header('Content-Type: text/plain; charset=UTF-8', true, 500);
      echo "Exception: ", $ex->getMessage(), "\n\n", $ex->getTraceAsString();
    } else {
      if (!headers_sent()) header('HTTP/1.1 500 Internal Server Error');
      echo "Unexpected error. Please try again.";
    }
    exit;
  });
}

