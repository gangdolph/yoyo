<?php
require_once __DIR__ . '/includes/require-auth.php';
require 'includes/db.php';
require 'includes/csrf.php';
require 'includes/listing-query.php';

$user_id = $_SESSION['user_id'];
$error = '';
$editing = false;
$listing = null;
$edit_id = isset($_GET['edit']) ? intval($_GET['edit']) : 0;

if ($edit_id) {
    if ($stmt = $conn->prepare('SELECT id, have_item, want_item, trade_type, description, image, status, brand_id, model_id FROM trade_listings WHERE id = ? AND owner_id = ?')) {
        $stmt->bind_param('ii', $edit_id, $user_id);
        $stmt->execute();
        $listing = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if ($listing) {
            $editing = true;
        }
    }
}

$brandOptions = listing_brand_options($conn);
$modelIndex = listing_model_index($conn);
$selectedBrandId = $editing && isset($listing['brand_id']) ? (int)$listing['brand_id'] : 0;
$selectedModelId = $editing && isset($listing['model_id']) ? (int)$listing['model_id'] : 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_token($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid CSRF token.';
    } else {
        $have_item = trim($_POST['have_item'] ?? '');
        $want_item = trim($_POST['want_item'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $trade_type = $_POST['trade_type'] ?? 'item';
        $status = $_POST['status'] ?? 'open';
        $brandInput = trim((string)($_POST['brand_id'] ?? ''));
        $modelInput = trim((string)($_POST['model_id'] ?? ''));
        $imageName = $listing['image'] ?? null;

        if ($have_item === '' || $want_item === '' || $description === '') {
            $error = 'Have, want, and description fields are required.';
        }

        $brand_id = null;
        if (!$error && $brandInput !== '') {
            $brandCandidate = (int)$brandInput;
            if ($brandCandidate > 0 && isset($brandOptions[$brandCandidate])) {
                $brand_id = $brandCandidate;
            } else {
                $error = 'Please choose a valid brand.';
            }
        }

        $model_id = null;
        if (!$error && $modelInput !== '') {
            $modelCandidate = (int)$modelInput;
            $modelRow = $modelIndex[$modelCandidate] ?? null;
            if ($modelCandidate > 0 && $modelRow) {
                $model_id = $modelCandidate;
                $modelBrand = (int)$modelRow['brand_id'];
                if ($brand_id !== null && $brand_id !== $modelBrand) {
                    $error = 'Selected model does not match the chosen brand.';
                } else {
                    $brand_id = $modelBrand;
                }
            } else {
                $error = 'Please choose a valid model.';
            }
        }

        $selectedBrandId = $brand_id !== null ? $brand_id : 0;
        $selectedModelId = $model_id !== null ? $model_id : 0;

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
                if (!in_array($mime, $allowed, true)) {
                    $error = 'Only JPEG and PNG images allowed.';
                } else {
                    $ext = $mime === 'image/png' ? '.png' : '.jpg';
                    $imageName = uniqid('trade_', true) . $ext;
                    $target = $upload_path . $imageName;
                    if (!move_uploaded_file($_FILES['image']['tmp_name'], $target)) {
                        $error = 'Failed to upload image.';
                    }
                }
            }
        }

        if (!$error) {
            if ($editing) {
          if ($stmt = $conn->prepare('UPDATE trade_listings SET have_item=?, want_item=?, brand_id=?, model_id=?, trade_type=?, description=?, status=?, image=? WHERE id=? AND owner_id=?')) {
                      $stmt->bind_param('ssiissssii', $have_item, $want_item, $brand_id, $model_id, $trade_type, $description, $status, $imageName, $edit_id, $user_id);
                    $stmt->execute();
                    $stmt->close();
                    header('Location: trade-listings.php');
                    exit;
                } else {
                    $error = 'Database error.';
                }
            } else {
          if ($stmt = $conn->prepare('INSERT INTO trade_listings (owner_id, have_item, want_item, brand_id, model_id, trade_type, description, image, status) VALUES (?,?,?,?,?,?,?,?,?)')) {
                      $stmt->bind_param('issiissss', $user_id, $have_item, $want_item, $brand_id, $model_id, $trade_type, $description, $imageName, $status);
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
  <form method="post" enctype="multipart/form-data">
    <label>Item You Have:<br><input type="text" name="have_item" value="<?= htmlspecialchars($listing['have_item'] ?? '') ?>" required></label><br>
      <label>Item You Want:<br><input type="text" name="want_item" value="<?= htmlspecialchars($listing['want_item'] ?? '') ?>" required></label><br>
      <label>Brand:<br>
        <select name="brand_id">
          <option value="">Select brand</option>
          <?php foreach ($brandOptions as $id => $name): ?>
            <option value="<?= $id; ?>" <?= $selectedBrandId === (int)$id ? 'selected' : ''; ?>><?= htmlspecialchars($name); ?></option>
          <?php endforeach; ?>
        </select>
      </label><br>
      <label>Model:<br>
        <select name="model_id" <?= empty($modelIndex) ? 'disabled' : ''; ?>>
          <option value="">Select model</option>
          <?php foreach ($modelIndex as $model): ?>
            <?php
              $brandLabel = $brandOptions[$model['brand_id']] ?? ('Brand ' . $model['brand_id']);
              $modelLabel = $brandLabel . ' – ' . $model['name'];
            ?>
            <option value="<?= $model['id']; ?>" data-brand-id="<?= $model['brand_id']; ?>" <?= $selectedModelId === (int)$model['id'] ? 'selected' : ''; ?>><?= htmlspecialchars($modelLabel); ?></option>
          <?php endforeach; ?>
        </select>
      </label><br>
      <label>Trade Type:<br>
        <select name="trade_type">
          <option value="item" <?= (($listing['trade_type'] ?? 'item') === 'item') ? 'selected' : '' ?>>Item</option>
          <option value="cash_card" <?= (($listing['trade_type'] ?? '') === 'cash_card') ? 'selected' : '' ?>>Cash Card</option>
        </select>
      </label><br>
      <label>Description:<br><textarea name="description" required><?= htmlspecialchars($listing['description'] ?? '') ?></textarea></label><br>
    <?php if (!empty($listing['image'])): ?><img src="uploads/<?= htmlspecialchars($listing['image']) ?>" alt="Listing image" style="max-width:150px"><br><?php endif; ?>
    <label>Image:<br><input type="file" name="image" accept="image/png,image/jpeg"></label><br>
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
