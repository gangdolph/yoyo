<?php
require_once __DIR__ . '/includes/require-auth.php';
require 'includes/db.php';
require 'includes/csrf.php';
require 'includes/listing-query.php';

$category = $_GET['category'] ?? '';
if (!$category) {
  header('Location: sell.php');
  exit;
}

$brandOptions = listing_brand_options($conn);
$modelIndex = listing_model_index($conn);
$selectedBrandId = isset($_POST['brand_id']) ? (int)$_POST['brand_id'] : 0;
$selectedModelId = isset($_POST['model_id']) ? (int)$_POST['model_id'] : 0;

function label($text, $name, $type = 'text', $required = true) {
  echo "<label>$text</label>";
  echo "<input name=\"$name\" type=\"$type\" " . ($required ? "required" : "") . ">";
}
?>
<?php require 'includes/layout.php'; ?>
  <title>Sell Details</title>
  <link rel="stylesheet" href="assets/style.css">
</head>
<body>
  <?php include 'includes/sidebar.php'; ?>
  <?php include 'includes/header.php'; ?>
  <h2>Sell - <?= htmlspecialchars(ucfirst($category)) ?> Details</h2>

  <form method="post" action="submit-request.php" enctype="multipart/form-data">
    <input type="hidden" name="csrf_token" value="<?= generate_token(); ?>">
    <input type="hidden" name="type" value="sell">
    <input type="hidden" name="category" value="<?= htmlspecialchars($category) ?>">

    <label>Brand</label>
    <select name="brand_id">
      <option value="">Select brand</option>
      <?php foreach ($brandOptions as $id => $name): ?>
        <option value="<?= $id; ?>" <?= $selectedBrandId === (int)$id ? 'selected' : ''; ?>><?= htmlspecialchars($name); ?></option>
      <?php endforeach; ?>
    </select>

    <label>Model</label>
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

    <?php if ($category === 'phone' || $category === 'console' || $category === 'pc'): ?>
      <?php label('Make', 'make'); ?>
      <?php label('Model', 'model'); ?>
      <?php label('IMEI / Serial Number', 'serial', 'text', false); ?>
      <?php label('Condition / Issues', 'issue'); ?>
    <?php endif; ?>

    <?php if ($category === 'other'): ?>
      <?php label('Device Type', 'device_type'); ?>
      <?php label('Condition / Issues', 'issue'); ?>
    <?php endif; ?>

    <div class="drop-area" id="drop-area">
      <p>Drag &amp; drop a photo or use the button</p>
      <input type="file" name="photo" id="photo" accept="image/jpeg,image/png">
      <button type="button" class="fallback" onclick="document.getElementById('photo').click();">Choose Photo</button>
    </div>

    <button type="submit">Review and Submit</button>
  </form>
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

