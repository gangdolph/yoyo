<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Composer autoload (expect vendor directory in project root)
$autoloadPath = __DIR__ . '/vendor/autoload.php';
if (file_exists($autoloadPath)) {
  require_once $autoloadPath;
} else {
  error_log('Composer autoload not found at ' . $autoloadPath . '. Run `composer install` to install dependencies.');
}

// Load SMTP settings from config file if present or environment variables
$configPath = __DIR__ . '/config.php';
if (file_exists($configPath)) {
  $config = require $configPath;
} else {
  $config = [
    'smtp_host' => getenv('SMTP_HOST'),
    'smtp_user' => getenv('SMTP_USER'),
    'smtp_pass' => getenv('SMTP_PASS'),
    'smtp_port' => getenv('SMTP_PORT') ?: 465,
  ];
}

function send_email($to, $subject, $body) {
  global $config;

  $mail = new PHPMailer(true);
  try {
    $mail->isSMTP();
    $mail->Host = $config['smtp_host'];
    $mail->SMTPAuth = true;
    $mail->Username = $config['smtp_user'];
    $mail->Password = $config['smtp_pass'];
    $mail->SMTPSecure = 'ssl';
    $mail->Port = $config['smtp_port'];

    $mail->setFrom($config['smtp_user'], 'SkuzE');
    $mail->addAddress($to);

    $mail->Subject = $subject;
    $mail->Body    = $body;

    $mail->send();
    return true;
  } catch (Exception $e) {
    error_log("Mailer Error: " . $mail->ErrorInfo);
    throw $e;
  }
}
