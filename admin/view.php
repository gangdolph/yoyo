<?php
if (!defined('APP_BOOTSTRAPPED')) {
    define('APP_BOOTSTRAPPED', true);
    require_once __DIR__ . '/../includes/bootstrap.php';
}

require_once __DIR__ . '/../includes/require-auth.php';
require '../includes/csrf.php';
require '../includes/notifications.php';

ensure_admin('../dashboard.php');

$error = '';
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$id) die("Invalid ID");


$stmt = $conn->prepare("SELECT r.*, u.id AS user_id, u.username, u.email, u.phone, u.last_active FROM service_requests r
                        JOIN users u ON r.user_id = u.id WHERE r.id = ?");
if ($stmt === false) {
  error_log('Prepare failed: ' . $conn->error);
  die("Database error");
}
$stmt->bind_param("i", $id);
if (!$stmt->execute()) {
  error_log('Execute failed: ' . $stmt->error);
  die("Database error");
}
$data = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$data) die("Request not found");

$last_active = strtotime($data['last_active']);
$is_online = (time() - $last_active < 300); // 5 minutes
$online_status = $is_online ? "<span style='color:green;'>Online</span>" : "<span style='color:gray;'>Offline</span>";

  if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_token($_POST['csrf_token'] ?? '')) {
      $error = 'Invalid CSRF token.';
    } else {
      $status = $_POST['status'];
      $note = $_POST['admin_note'];

      $update = $conn->prepare("UPDATE service_requests SET status = ?, admin_note = ? WHERE id = ?");
      if ($update === false) {
        error_log('Prepare failed: ' . $conn->error);
      } else {
        $update->bind_param("ssi", $status, $note, $id);
        if (!$update->execute()) {
          error_log('Execute failed: ' . $update->error);
        } else {
          $msg = notification_message('service_status', ['status' => $status]);
          create_notification($conn, $data['user_id'], 'service_status', $msg);
        }
        $update->close();
      }

      $redirect = ($data['type'] === 'trade') ? 'trade-requests.php' : 'index.php';
      header("Location: $redirect");
      exit;
    }
  }
?>
<?php require '../includes/layout.php'; ?>
  <title>View Request</title>
  <link rel="stylesheet" href="../assets/style.css">
</head>
<body>
    <?php include '../includes/header.php'; ?>
    <h2>Request #<?= $data['id'] ?></h2>
    <p><strong>User:</strong> <?= username_with_avatar($conn, $data['user_id'], $data['username']) ?> (<?= htmlspecialchars($data['email']) ?> | <?= htmlspecialchars($data['phone']) ?>) — <?= $online_status ?></p>
  <p><strong>Category:</strong> <?= htmlspecialchars($data['category']) ?></p>
  <p><strong>Make/Model:</strong> <?= htmlspecialchars($data['make']) ?> / <?= htmlspecialchars($data['model']) ?></p>
  <p><strong>Serial:</strong> <?= htmlspecialchars($data['serial']) ?></p>
  <p><strong>Build Request:</strong> <?= $data['build'] === 'yes' ? 'Yes' : 'No' ?></p>
  <p><strong>Device Type:</strong> <?= htmlspecialchars($data['device_type']) ?></p>
  <p><strong>Issue:</strong><br><?= nl2br(htmlspecialchars($data['issue'])) ?></p>
  <p><strong>Submitted:</strong> <?= $data['created_at'] ?></p>

    <?php if (!empty($error)) echo "<p style='color:red;'>" . htmlspecialchars($error) . "</p>"; ?>
    <form method="post">
      <input type="hidden" name="csrf_token" value="<?= generate_token(); ?>">
      <label>Status:</label>
      <select name="status">
      <?php
      $options = ['New', 'In Progress', 'Awaiting Customer', 'Completed'];
      foreach ($options as $opt) {
        $sel = ($data['status'] === $opt) ? 'selected' : '';
        echo "<option value=\"$opt\" $sel>$opt</option>";
      }
      ?>
    </select>

    <label>Internal Admin Notes:</label>
    <textarea name="admin_note"><?= htmlspecialchars($data['admin_note']) ?></textarea>

    <button type="submit">Save Changes</button>
  </form>
  <p><a class="btn" href="index.php">← Back to All Requests</a></p>
  <?php include '../includes/footer.php'; ?>
</body>
</html>
