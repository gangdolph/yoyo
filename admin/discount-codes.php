<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/authz.php';
require '../includes/db.php';
require '../includes/csrf.php';

ensure_admin('../dashboard.php');

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!validate_token($_POST['csrf_token'] ?? '')) {
    $error = 'Invalid CSRF token.';
  } elseif (isset($_POST['delete'])) {
    $code = $_POST['code'] ?? '';
    $stmt = $conn->prepare('DELETE FROM discount_codes WHERE code = ?');
    if ($stmt) {
      $stmt->bind_param('s', $code);
      $stmt->execute();
      $stmt->close();
    }
    header('Location: discount-codes.php');
    exit;
  } else {
    $code = trim($_POST['code'] ?? '');
    $percent = intval($_POST['percent'] ?? 0);
    $expiry = $_POST['expiry'] ?? '';
    $limit = intval($_POST['usage_limit'] ?? 0);

    if ($code !== '' && $percent > 0 && $percent <= 100 && $expiry !== '' && $limit > 0) {
      $stmt = $conn->prepare('INSERT INTO discount_codes (code, percent_off, expiry, usage_limit) VALUES (?, ?, ?, ?)');
      if ($stmt) {
        $stmt->bind_param('sisi', $code, $percent, $expiry, $limit);
        $stmt->execute();
        $stmt->close();
      }
    }
  }
}

$result = $conn->query('SELECT code, percent_off, expiry, usage_limit FROM discount_codes ORDER BY expiry');
$codes = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
?>
<?php require '../includes/layout.php'; ?>
  <title>Discount Codes</title>
  <link rel="stylesheet" href="../assets/style.css">
</head>
<body>
  <?php include '../includes/header.php'; ?>
  <h2>Discount Codes</h2>
  <?php if (!empty($error)) echo "<p style='color:red;'>" . htmlspecialchars($error) . "</p>"; ?>
  <p><a class="btn" href="index.php">Back to Admin Panel</a></p>
  <form method="post">
    <input type="hidden" name="csrf_token" value="<?= generate_token(); ?>">
    <h3>Create Code</h3>
    <label>Code <input name="code" required></label>
    <label>Percent Off <input type="number" name="percent" min="1" max="100" required></label>
    <label>Expiry <input type="date" name="expiry" required></label>
    <label>Usage Limit <input type="number" name="usage_limit" min="1" required></label>
    <button type="submit">Add Code</button>
  </form>

  <h3>Existing Codes</h3>
  <table>
    <tr><th>Code</th><th>Percent</th><th>Expiry</th><th>Remaining Uses</th><th>Actions</th></tr>
    <?php foreach ($codes as $c): ?>
      <tr>
        <td><?= htmlspecialchars($c['code']) ?></td>
        <td><?= $c['percent_off'] ?>%</td>
        <td><?= htmlspecialchars($c['expiry']) ?></td>
        <td><?= $c['usage_limit'] ?></td>
        <td>
          <form method="post" style="display:inline;">
            <input type="hidden" name="csrf_token" value="<?= generate_token(); ?>">
            <input type="hidden" name="code" value="<?= htmlspecialchars($c['code']) ?>">
            <button type="submit" name="delete">Delete</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
  </table>
  <?php include '../includes/footer.php'; ?>
</body>
</html>
