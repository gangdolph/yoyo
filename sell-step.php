<?php
require_once __DIR__ . '/includes/auth.php';
require 'includes/csrf.php';

$category = $_GET['category'] ?? '';
if (!$category) {
  header('Location: sell.php');
  exit;
}

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

