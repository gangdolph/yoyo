<?php
require 'includes/auth.php';
require 'includes/db.php';
require 'includes/csrf.php';

$user_id = $_SESSION['user_id'];
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_token($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid CSRF token.';
    } else {
        $action = $_POST['action'] ?? '';
        if ($action === 'add') {
            $item = trim($_POST['item'] ?? '');
            if ($item === '') {
                $error = 'Item name required.';
            } else {
                if ($stmt = $conn->prepare('INSERT INTO user_inventory (user_id, item_name) VALUES (?, ?)')) {
                    $stmt->bind_param('is', $user_id, $item);
                    $stmt->execute();
                    $stmt->close();
                }
            }
        } elseif ($action === 'delete') {
            $item_id = intval($_POST['item_id'] ?? 0);
            if ($stmt = $conn->prepare('DELETE FROM user_inventory WHERE id = ? AND user_id = ?')) {
                $stmt->bind_param('ii', $item_id, $user_id);
                $stmt->execute();
                $stmt->close();
            }
        }
    }
}

$items = [];
if ($stmt = $conn->prepare('SELECT id, item_name FROM user_inventory WHERE user_id = ? ORDER BY item_name')) {
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}
?>
<?php require 'includes/layout.php'; ?>
  <meta charset="UTF-8">
  <title>Your Inventory</title>
  <link rel="stylesheet" href="assets/style.css">
</head>
<body>
  <?php include 'includes/sidebar.php'; ?>
  <?php include 'includes/header.php'; ?>
  <h2>Your Inventory</h2>
  <?php if ($error): ?><p class="error"><?= htmlspecialchars($error) ?></p><?php endif; ?>
  <ul>
    <?php foreach ($items as $i): ?>
      <li>
        <?= htmlspecialchars($i['item_name']) ?>
        <form method="post" style="display:inline">
          <input type="hidden" name="csrf_token" value="<?= generate_token(); ?>">
          <input type="hidden" name="action" value="delete">
          <input type="hidden" name="item_id" value="<?= $i['id'] ?>">
          <button type="submit">Delete</button>
        </form>
      </li>
    <?php endforeach; ?>
  </ul>
  <form method="post">
    <input type="hidden" name="csrf_token" value="<?= generate_token(); ?>">
    <input type="hidden" name="action" value="add">
    <label>New Item:<br><input type="text" name="item"></label>
    <button type="submit">Add</button>
  </form>
  <?php include 'includes/footer.php'; ?>
</body>
</html>
