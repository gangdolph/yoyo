<?php
require_once __DIR__ . '/includes/require-auth.php';
require 'includes/csrf.php';

// Service requests now have a dedicated step handler. Buy, sell and trade
// flows were split into individual controllers so this file exclusively
// processes repair/service details.
// Only a category is expected – if it is missing send the user back to
// the service selection page.
$category = $_GET['category'] ?? '';
$allowed = ['repair', 'clean', 'build', 'other'];
if (!in_array($category, $allowed)) {
  header('Location: services.php');
  exit;
}

function label($text, $name, $type = 'text', $required = true) {
  echo "<label>$text</label>";
  echo "<input name=\"$name\" type=\"$type\" " . ($required ? "required" : "") . ">";
}
?>
<?php require 'includes/layout.php'; ?>
  <title>Service Details</title>
  <link rel="stylesheet" href="assets/style.css">
</head>
<body>
  <?php include 'includes/sidebar.php'; ?>
  <?php include 'includes/header.php'; ?>
  <h2>Service - <?= htmlspecialchars(ucfirst($category)) ?> Details</h2>

    <form method="post" action="submit-request.php" enctype="multipart/form-data">
      <input type="hidden" name="csrf_token" value="<?= generate_token(); ?>">
      <input type="hidden" name="type" value="service">
      <input type="hidden" name="category" value="<?= htmlspecialchars($category) ?>">

    <?php if (in_array($category, ['repair', 'clean', 'build'])): ?>
      <?php label("Make", "make"); ?>
      <?php label("Model", "model"); ?>
      <?php label("IMEI / Serial Number", "serial", 'text', false); ?>
      <?php label("Describe the problem", "issue"); ?>
    <?php endif; ?>

    <?php if ($category === 'build'): ?>
      <label>Is this a custom build request?</label>
      <select name="build">
        <option value="no">No</option>
        <option value="yes">Yes, I want a PC built</option>
      </select>
    <?php endif; ?>

    <?php if ($category === 'other'): ?>
      <?php label("Device Type", "device_type"); ?>
      <?php label("Problem Description", "issue"); ?>
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
