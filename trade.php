<?php
require 'includes/auth.php';
require 'includes/db.php';
require 'includes/csrf.php';

$user_id = $_SESSION['user_id'];
$is_vip = false;
if ($stmtVip = $conn->prepare('SELECT vip_status, vip_expires_at FROM users WHERE id=?')) {
  $stmtVip->bind_param('i', $user_id);
  $stmtVip->execute();
  $stmtVip->bind_result($vipStatus, $vipExpires);
  if ($stmtVip->fetch()) {
    $is_vip = $vipStatus && (!$vipExpires || strtotime($vipExpires) > time());
  }
  $stmtVip->close();
}
$edit_id = isset($_GET['edit']) ? (int)$_GET['edit'] : 0;
$editing = false;
$request = null;
$db_error = null;
if ($edit_id) {
  $stmt = $conn->prepare("SELECT * FROM service_requests WHERE id = ? AND user_id = ? AND type = 'trade'");
  if ($stmt) {
    $stmt->bind_param("ii", $edit_id, $user_id);
    if (!$stmt->execute()) {
      error_log('Trade lookup failed: ' . $stmt->error);
      $db_error = 'Unable to load trade request. Please try again later.';
    } else {
      $request = $stmt->get_result()->fetch_assoc();
      if ($request && in_array($request['status'], ['New', 'Pending'])) {
        $editing = true;
      } else {
        $request = null;
      }
    }
    $stmt->close();
  } else {
    error_log('Prepare failed: ' . $conn->error);
  }
}

$requests = [];
$stmt = $conn->prepare("SELECT id, make, model, device_type, status, created_at FROM service_requests WHERE user_id = ? AND type='trade' ORDER BY created_at DESC");
if ($stmt) {
  $stmt->bind_param("i", $user_id);
  if (!$stmt->execute()) {
    error_log('Trade list query failed: ' . $stmt->error);
    $db_error = $db_error ?? 'Unable to load your trade requests. Please try again later.';
  } else {
    $requests = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
  }
  $stmt->close();
} else {
  error_log('Prepare failed: ' . $conn->error);
}
?>
<?php require 'includes/layout.php'; ?>
  <meta charset="UTF-8">
  <title><?= $editing ? 'Update Trade Request' : 'Trade with SkuzE' ?></title>
  <link rel="stylesheet" href="assets/style.css">
</head>
<body>
  <?php include 'includes/sidebar.php'; ?>
  <?php include 'includes/header.php'; ?>

  <?php if ($is_vip): ?>
    <p class="notice">VIP members skip admin approval for trade requests.</p>
  <?php endif; ?>

  <?php if ($db_error): ?>
    <p class="error"><?= htmlspecialchars($db_error) ?></p>
  <?php endif; ?>

  <h2><?= $editing ? 'Update Trade Request' : 'Trade a Device' ?></h2>
  <form method="post" action="<?= $editing ? 'update-request.php' : 'submit-request.php' ?>" enctype="multipart/form-data">
    <input type="hidden" name="csrf_token" value="<?= generate_token(); ?>">
    <input type="hidden" name="type" value="trade">
    <input type="hidden" name="category" value="trade">
    <?php if ($editing): ?>
      <input type="hidden" name="id" value="<?= $request['id'] ?>">
    <?php endif; ?>

    <label>Current Device Make</label>
    <input name="make" type="text" value="<?= htmlspecialchars($request['make'] ?? '') ?>" required>

    <label>Current Device Model</label>
    <input name="model" type="text" value="<?= htmlspecialchars($request['model'] ?? '') ?>" required>

    <label>Desired Device</label>
    <input name="device_type" type="text" value="<?= htmlspecialchars($request['device_type'] ?? '') ?>" required>

    <label>Condition / Details</label>
    <textarea name="issue" required><?= htmlspecialchars($request['issue'] ?? '') ?></textarea>

    <div class="drop-area" id="drop-area">
      <p>Drag &amp; drop a photo or use the button</p>
      <input type="file" name="photo" id="photo" accept="image/jpeg,image/png">
      <button type="button" class="fallback" onclick="document.getElementById('photo').click();">Choose Photo</button>
    </div>

    <button type="submit"><?= $editing ? 'Update Request' : 'Submit Request' ?></button>
  </form>

  <?php if (!empty($requests)): ?>
    <h3>My Trade Requests</h3>
    <table>
      <thead>
        <tr>
          <th>#</th>
          <th>Current Device</th>
          <th>Desired Device</th>
          <th>Status</th>
          <th>Submitted</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($requests as $r): ?>
        <tr>
          <td><?= $r['id'] ?></td>
          <td><?= htmlspecialchars($r['make']) . ' ' . htmlspecialchars($r['model']) ?></td>
          <td><?= htmlspecialchars($r['device_type']) ?></td>
          <td><?= htmlspecialchars($r['status'] ?? 'New') ?></td>
          <td><?= $r['created_at'] ?></td>
          <td>
            <?php if (in_array($r['status'], ['New', 'Pending'])): ?>
              <a href="trade.php?edit=<?= $r['id'] ?>">Edit</a>
              <form action="trade-cancel.php" method="post" style="display:inline">
                <input type="hidden" name="csrf_token" value="<?= generate_token(); ?>">
                <input type="hidden" name="id" value="<?= $r['id'] ?>">
                <button type="submit">Cancel</button>
              </form>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>

  <script>
    const dropArea = document.getElementById('drop-area');
    const fileInput = document.getElementById('photo');
    ['dragenter', 'dragover'].forEach(evt => {
      dropArea.addEventListener(evt, e => {
        e.preventDefault();
        dropArea.classList.add('dragover');
      });
    });
    ['dragleave', 'drop'].forEach(evt => {
      dropArea.addEventListener(evt, e => {
        e.preventDefault();
        dropArea.classList.remove('dragover');
      });
    });
    dropArea.addEventListener('drop', e => {
      fileInput.files = e.dataTransfer.files;
    });
  </script>

  <?php include 'includes/footer.php'; ?>
</body>
</html>
