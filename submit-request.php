<?php
require_once __DIR__ . '/includes/require-auth.php';
require 'includes/db.php';
require 'includes/csrf.php';
require_once 'mail.php';

$serviceTaxonomy = require __DIR__ . '/includes/service_taxonomy.php';

function service_wizard_active_payload(array $payload, string $category): array
{
    if ($category === '' || !isset($payload[$category]) || !is_array($payload[$category])) {
        return [];
    }

    return $payload[$category];
}

function service_wizard_value(array $activePayload, string $field, $default = null)
{
    if (array_key_exists($field, $_POST)) {
        return $_POST[$field];
    }
    if (array_key_exists($field, $activePayload)) {
        return $activePayload[$field];
    }

    return $default;
}

function service_wizard_file(array $files, string $category, string $field): ?array
{
    if (!isset($files['name'][$category][$field])) {
        return null;
    }

    $name = $files['name'][$category][$field];
    $error = $files['error'][$category][$field] ?? UPLOAD_ERR_NO_FILE;

    if ($error === UPLOAD_ERR_NO_FILE || $name === '') {
        return null;
    }

    return [
        'name' => $name,
        'type' => $files['type'][$category][$field] ?? '',
        'tmp_name' => $files['tmp_name'][$category][$field] ?? '',
        'error' => $error,
        'size' => $files['size'][$category][$field] ?? 0,
    ];
}

$success = false;
$error = '';
$filename = null;
$type = htmlspecialchars(trim($_POST['type'] ?? 'service'), ENT_QUOTES, 'UTF-8');

$user_id = $_SESSION['user_id'];
$is_member = false;
if ($stmtVip = $conn->prepare('SELECT vip_status, vip_expires_at FROM users WHERE id=?')) {
    $stmtVip->bind_param('i', $user_id);
    $stmtVip->execute();
    $stmtVip->bind_result($vipStatus, $vipExpires);
    if ($stmtVip->fetch()) {
        $is_member = $vipStatus && (!$vipExpires || strtotime($vipExpires) > time());
    }
    $stmtVip->close();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_token($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid CSRF token.';
    } else {
        $category = htmlspecialchars(trim($_POST['category'] ?? ''), ENT_QUOTES, 'UTF-8');
        $servicePayload = isset($_POST['service']) && is_array($_POST['service']) ? $_POST['service'] : [];
        $activePayload = service_wizard_active_payload($servicePayload, $category);

        $brandRaw = service_wizard_value($activePayload, 'brand_id');
        $brand_id = ($brandRaw !== null && $brandRaw !== '') ? (int) $brandRaw : null;

        $modelRaw = service_wizard_value($activePayload, 'model_id');
        $model_id = ($modelRaw !== null && $modelRaw !== '') ? (int) $modelRaw : null;

        $makeValue = service_wizard_value($activePayload, 'make');
        $make = $makeValue !== null ? htmlspecialchars(trim((string) $makeValue), ENT_QUOTES, 'UTF-8') : null;

        $modelValue = service_wizard_value($activePayload, 'model');
        $model = $modelValue !== null ? htmlspecialchars(trim((string) $modelValue), ENT_QUOTES, 'UTF-8') : null;

        $serialValue = service_wizard_value($activePayload, 'serial');
        $serial = $serialValue !== null ? htmlspecialchars(trim((string) $serialValue), ENT_QUOTES, 'UTF-8') : null;

        $issueValue = service_wizard_value($activePayload, 'issue', '');
        $issue = htmlspecialchars(trim((string) $issueValue), ENT_QUOTES, 'UTF-8');

        $buildRaw = service_wizard_value($activePayload, 'build', null);
        $build = $buildRaw !== null ? htmlspecialchars(trim((string) $buildRaw), ENT_QUOTES, 'UTF-8') : 'no';

        $deviceTypeValue = service_wizard_value($activePayload, 'device_type');
        $device_type = $deviceTypeValue !== null ? htmlspecialchars(trim((string) $deviceTypeValue), ENT_QUOTES, 'UTF-8') : null;

        if ($category === '') {
          $error = 'Category is required.';
        }

        $serviceDefinition = $serviceTaxonomy[$category] ?? null;
        if (!$error && !$serviceDefinition) {
          $error = 'Invalid service category.';
        }

        $requirements = $serviceDefinition['requirements'] ?? [];
        $requiresBrand = !empty($requirements['brand_id']);
        $requiresModel = !empty($requirements['model_id']);
        $requiresIssue = !empty($requirements['issue']);
        $requiresDeviceType = !empty($requirements['device_type']);
        $requiresBuild = !empty($requirements['build']);

        if (!$error && $requiresIssue && $issue === '') {
          $error = 'Please describe the issue so our technicians can help.';
        }

        if (!$error && $requiresDeviceType && ($device_type === null || $device_type === '')) {
          $error = 'Device type is required for this request.';
        }

        if (!$error && $requiresBrand && !$brand_id) {
          $error = 'Please choose a brand.';
        }

        if (!$error && $requiresModel && !$model_id) {
          $error = 'Please choose a model.';
        }

        if (!$error && $requiresBuild) {
          if ($buildRaw === null || !in_array($build, ['yes', 'no'], true)) {
            $error = 'Please confirm if this is a full custom build.';
          }
        }

        if (!$error && $requiresBrand && $brand_id) {
          if ($stmtB = $conn->prepare('SELECT id FROM service_brands WHERE id=?')) {
            $stmtB->bind_param('i', $brand_id);
            $stmtB->execute();
            $stmtB->store_result();
            if ($stmtB->num_rows === 0) {
              $error = 'Invalid brand.';
            }
            $stmtB->close();
          }
        }

        if (!$error && $requiresModel && $model_id) {
          if ($stmtM = $conn->prepare('SELECT id FROM service_models WHERE id=? AND brand_id=?')) {
            $stmtM->bind_param('ii', $model_id, $brand_id);
            $stmtM->execute();
            $stmtM->store_result();
            if ($stmtM->num_rows === 0) {
              $error = 'Invalid model.';
            }
            $stmtM->close();
          }
        }

        if (!$error) {
          $extraNotes = [];
          $symptomsInput = service_wizard_value($activePayload, 'symptoms', []);
          if (!is_array($symptomsInput)) {
            $symptomsInput = [];
          }
          if ($symptomsInput) {
            $symptoms = array_map(static function ($value) {
              return preg_replace('/[^a-z0-9 \-]/i', '', (string) $value);
            }, $symptomsInput);
            $symptoms = array_filter($symptoms);
            if ($symptoms) {
              $extraNotes[] = 'Reported symptoms: ' . implode(', ', $symptoms);
            }
          }

          $contactPreferenceInput = service_wizard_value($activePayload, 'contact_preference', '');
          if ($contactPreferenceInput !== null) {
            $contactPreference = preg_replace('/[^a-z0-9 \-]/i', '', (string) $contactPreferenceInput);
            if ($contactPreference !== '') {
              $extraNotes[] = 'Preferred contact: ' . $contactPreference;
            }
          }

          if ($extraNotes) {
            $issue = trim($issue . "\n\n" . implode("\n", $extraNotes));
          }
        }

        $uploadedFile = null;
        if (isset($_FILES['photo']) && is_array($_FILES['photo'])) {
          $photoError = $_FILES['photo']['error'] ?? UPLOAD_ERR_NO_FILE;
          if ($photoError !== UPLOAD_ERR_NO_FILE && ($_FILES['photo']['name'] ?? '') !== '') {
            $uploadedFile = $_FILES['photo'];
          }
        }

        if (!$uploadedFile && isset($_FILES['service']) && is_array($_FILES['service'])) {
          $nestedFile = service_wizard_file($_FILES['service'], $category, 'photo');
          if ($nestedFile) {
            $uploadedFile = $nestedFile;
          }
        }

        // ✅ Handle optional file upload
        if (!$error && $uploadedFile) {
          $upload_path = __DIR__ . '/uploads/';
          if (!is_dir($upload_path)) {
            mkdir($upload_path, 0755, true);
          }
          $maxSize = 5 * 1024 * 1024; // 5MB
          $allowed = ['image/jpeg', 'image/png'];

          if (($uploadedFile['size'] ?? 0) > $maxSize) {
            $error = "Image exceeds 5MB limit.";
          } else {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = false;
            if ($finfo) {
              $mime = finfo_file($finfo, $uploadedFile['tmp_name']);
              finfo_close($finfo);
            }
            if (!in_array($mime, $allowed, true)) {
              $error = "Only JPEG and PNG images allowed.";
            } else {
              $ext = $mime === 'image/png' ? '.png' : '.jpg';
              $filename = uniqid('upload_', true) . $ext;
              $target = $upload_path . $filename;
              if (!move_uploaded_file($uploadedFile['tmp_name'], $target)) {
                $error = "Failed to upload image.";
              }
            }
          }
        }

        // Proceed if no file errors
        if (!$error) {
          $status = $is_member ? 'In Progress' : 'New';
          $stmt = $conn->prepare("INSERT INTO service_requests
            (user_id, type, category, brand_id, model_id, make, model, serial, issue, build, device_type, photo, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

          if ($stmt) {
            $stmt->bind_param("issiiisssssss", $user_id, $type, $category, $brand_id, $model_id, $make, $model, $serial, $issue, $build, $device_type, $filename, $status);
            if ($stmt->execute()) {
              $success = true;

              // ✅ Send admin notification
              $adminEmail = 'owner@skuze.tech';
              $subject = "New Service Request Submitted";
              $body = "User ID: $user_id\nType: $type\nCategory: $category\nMake/Model: $make $model\nSerial: $serial\nBuild Request: $build\nDevice Type: $device_type\nIssue: $issue";
              if ($filename) {
                $body .= "\nPhoto stored at: uploads/$filename";
              }
              try {
                send_email($adminEmail, $subject, $body);
              } catch (Exception $e) {
                error_log('Email dispatch failed: ' . $e->getMessage());
                $error = 'Request saved but email notification failed.';
              }
            } else {
              error_log('Execute failed: ' . $stmt->error);
              $error = "Error executing query.";
            }
            $stmt->close();
          } else {
            error_log('Prepare failed: ' . $conn->error);
            $error = "Database error.";
          }
        }
    }
}
?>
<?php require 'includes/layout.php'; ?>
  <title>Request Submitted</title>
  <link rel="stylesheet" href="assets/style.css">
</head>
<body>
  <?php include 'includes/sidebar.php'; ?>
  <?php include 'includes/header.php'; ?>
  <?php if ($success): ?>
    <h2>Request Submitted</h2>
    <p>Thank you! We'll review your request and get back to you shortly.</p>
    <p><a href="dashboard.php">Back to Dashboard</a></p>
  <?php else: ?>
    <h2>Error</h2>
    <p><?= htmlspecialchars($error) ?></p>
    <?php $map = ['buy' => 'buy.php', 'sell' => 'sell.php', 'trade' => 'trade.php', 'service' => 'services.php'];
          $origin = $map[$type] ?? 'services.php'; ?>
    <p><a href="<?= $origin ?>">Try Again</a></p>
  <?php endif; ?>
  <?php include 'includes/footer.php'; ?>
</body>
</html>
