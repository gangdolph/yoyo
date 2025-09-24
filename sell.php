<?php
require_once __DIR__ . '/includes/require-auth.php';
require_once __DIR__ . '/includes/authz.php';
require 'includes/db.php';
require 'includes/csrf.php';
require 'includes/tags.php';
require_once __DIR__ . '/includes/repositories/ChangeRequestsService.php';
require_once __DIR__ . '/includes/repositories/ListingsRepo.php';

ensure_seller();

$user_id = $_SESSION['user_id'];
$is_vip = false;
if ($stmt = $conn->prepare('SELECT vip_status, vip_expires_at FROM users WHERE id=?')) {
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $stmt->bind_result($vip, $vip_expires);
    if ($stmt->fetch()) {
        $is_vip = $vip && (!$vip_expires || strtotime($vip_expires) > time());
    }
    $stmt->close();
}

$error = '';
$tags_input = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_token($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid CSRF token.';
    } else {
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $condition = trim($_POST['condition'] ?? '');
        $allowedConditions = ['new', 'used', 'refurbished'];
        $price = trim($_POST['price'] ?? '');
        $category = trim($_POST['category'] ?? '');
        $tags_input = trim($_POST['tags'] ?? '');
        $pickup_only = isset($_POST['pickup_only']) ? 1 : 0;
        $imageName = null;

        $tags = tags_from_input($tags_input);
        $tags_input = tags_to_input_value($tags);
        $tags_storage = tags_to_storage($tags);

        if ($title === '' || $description === '' || $condition === '' || !in_array($condition, $allowedConditions, true) || $price === '' || !is_numeric($price)) {
            $error = 'Title, description, valid condition, and a valid price are required.';
        }

        // Require at least one image upload
        if (!$error && empty($_FILES['image']['name'])) {
            $error = 'An image is required.';
        }

        if (!$error && !empty($_FILES['image']['name'])) {
            $upload_path = __DIR__ . '/uploads/';
            if (!is_dir($upload_path)) {
                mkdir($upload_path, 0755, true);
            }
            $maxSize = 5 * 1024 * 1024; // 5MB
            $allowed = ['image/jpeg', 'image/png'];
            if ($_FILES['image']['size'] > $maxSize) {
                $error = 'Image exceeds 5MB limit.';
            } else {
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $mime = finfo_file($finfo, $_FILES['image']['tmp_name']);
                finfo_close($finfo);
                if (!in_array($mime, $allowed)) {
                    $error = 'Only JPEG and PNG images allowed.';
                } else {
                    $ext = $mime === 'image/png' ? '.png' : '.jpg';
                    $imageName = uniqid('listing_', true) . $ext;
                    $target = $upload_path . $imageName;
                    if (!move_uploaded_file($_FILES['image']['tmp_name'], $target)) {
                        $error = 'Failed to upload image.';
                    }
                }
            }
        }

        if (!$error) {
            $changeRequests = new ChangeRequestsService($conn);
            $repo = new ListingsRepo($conn, $changeRequests);
            $data = [
                'title' => $title,
                'description' => $description,
                'condition' => $condition,
                'price' => $price,
                'category' => $category !== '' ? $category : null,
                'tags' => $tags_storage,
                'image' => $imageName,
                'pickup_only' => $pickup_only,
                'quantity' => 1,
            ];

            $result = $repo->create($user_id, $data, $is_vip);
            if (!$result['success']) {
                $error = $result['error'] ?? 'Unable to create listing.';
            } else {
                header('Location: /shop-manager/index.php?tab=listings');
                exit;
            }
        }
    }
}
?>
<?php require 'includes/layout.php'; ?>
  <meta charset="UTF-8">
  <title>Create Listing</title>
  <link rel="stylesheet" href="assets/style.css">
  <script src="assets/tags.js" defer></script>
</head>
<body>
  <?php include 'includes/sidebar.php'; ?>
  <?php include 'includes/header.php'; ?>
  <h2>Create a Listing</h2>
  <?php if ($is_vip): ?>
    <p class="notice">Your listing will be auto-approved as a VIP member.</p>
  <?php endif; ?>
  <?php if ($error): ?>
    <p class="error"><?= htmlspecialchars($error) ?></p>
  <?php endif; ?>
  <form method="post" enctype="multipart/form-data">
    <label>Title:<br><input type="text" name="title" required></label><br>
    <label>Description:<br><textarea name="description" required></textarea></label><br>
    <label>Condition:<br>
      <select name="condition" required>
        <option value="">Select condition</option>
        <option value="new">New</option>
        <option value="used">Used</option>
        <option value="refurbished">Refurbished</option>
      </select>
    </label><br>
    <label>Price:<br><input type="number" step="0.01" name="price" required></label><br>
    <label>Category:<br>
      <select name="category">
        <option value="">None</option>
        <option value="phone">Phone</option>
        <option value="console">Game Console</option>
        <option value="pc">PC</option>
        <option value="other">Other</option>
      </select>
    </label><br>
    <label>Tags:<br>
      <div class="tag-input" data-tag-editor>
        <div class="tag-list" data-tag-list></div>
        <input type="text" data-tag-source placeholder="Add tag and press Enter">
        <input type="hidden" name="tags" value="<?= htmlspecialchars($tags_input); ?>" data-tag-store>
      </div>
      <small class="field-hint">Use short keywords like "official" or "bundle".</small>
    </label><br>
    <label><input type="checkbox" name="pickup_only" value="1"> Pickup only (no shipping)</label><br>
    <label>Image:<br><input type="file" name="image" accept="image/png,image/jpeg" required></label><br>
    <input type="hidden" name="csrf_token" value="<?= generate_token(); ?>">
    <button type="submit">Save Listing</button>
  </form>
  <?php include 'includes/footer.php'; ?>
</body>
</html>

