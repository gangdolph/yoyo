<?php
require 'includes/auth.php';
require 'includes/db.php';
require 'includes/csrf.php';

$user_id = $_SESSION['user_id'];
$error = '';
$editing = false;
$listing = null;
$edit_id = isset($_GET['edit']) ? intval($_GET['edit']) : 0;

if ($edit_id) {
    if ($stmt = $conn->prepare('SELECT id, have_item, want_item, status FROM trade_listings WHERE id = ? AND owner_id = ?')) {
        $stmt->bind_param('ii', $edit_id, $user_id);
        $stmt->execute();
        $listing = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if ($listing) {
            $editing = true;
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_token($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid CSRF token.';
    } else {
        $have_item = trim($_POST['have_item'] ?? '');
        $want_item = trim($_POST['want_item'] ?? '');
        $status = $_POST['status'] ?? 'open';
        if ($have_item === '' || $want_item === '') {
            $error = 'Both fields are required.';
        }
        if (!$error) {
            if ($editing) {
                if ($stmt = $conn->prepare('UPDATE trade_listings SET have_item=?, want_item=?, status=? WHERE id=? AND owner_id=?')) {
                    $stmt->bind_param('sssii', $have_item, $want_item, $status, $edit_id, $user_id);
                    $stmt->execute();
                    $stmt->close();
                    header('Location: trade-listings.php');
                    exit;
                } else {
                    $error = 'Database error.';
                }
            } else {
                if ($stmt = $conn->prepare('INSERT INTO trade_listings (owner_id, have_item, want_item, status) VALUES (?,?,?,?)')) {
                    $stmt->bind_param('isss', $user_id, $have_item, $want_item, $status);
                    $stmt->execute();
                    $stmt->close();
                    header('Location: trade-listings.php');
                    exit;
                } else {
                    $error = 'Database error.';
                }
            }
        }
    }
}
?>
<?php require 'includes/layout.php'; ?>
  <meta charset="UTF-8">
  <title><?= $editing ? 'Edit Trade Listing' : 'New Trade Listing' ?></title>
  <link rel="stylesheet" href="assets/style.css">
</head>
<body>
  <?php include 'includes/sidebar.php'; ?>
  <?php include 'includes/header.php'; ?>
  <h2><?= $editing ? 'Edit Listing' : 'Create Trade Listing' ?></h2>
  <?php if ($error): ?><p class="error"><?= htmlspecialchars($error) ?></p><?php endif; ?>
  <form method="post">
    <label>Item You Have:<br><input type="text" name="have_item" value="<?= htmlspecialchars($listing['have_item'] ?? '') ?>" required></label><br>
    <label>Item You Want:<br><input type="text" name="want_item" value="<?= htmlspecialchars($listing['want_item'] ?? '') ?>" required></label><br>
    <label>Status:<br>
      <select name="status">
        <option value="open" <?= (($listing['status'] ?? '') === 'open') ? 'selected' : '' ?>>Open</option>
        <option value="accepted" <?= (($listing['status'] ?? '') === 'accepted') ? 'selected' : '' ?>>Accepted</option>
        <option value="closed" <?= (($listing['status'] ?? '') === 'closed') ? 'selected' : '' ?>>Closed</option>
      </select>
    </label><br>
    <input type="hidden" name="csrf_token" value="<?= generate_token(); ?>">
    <?php if ($editing): ?><input type="hidden" name="id" value="<?= $edit_id ?>"><?php endif; ?>
    <button type="submit">Save Listing</button>
  </form>
  <?php include 'includes/footer.php'; ?>
</body>
</html>
