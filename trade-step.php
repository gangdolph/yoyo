<?php
require_once __DIR__ . '/includes/require-auth.php';
require 'includes/csrf.php';

$category = $_GET['category'] ?? '';
if (!$category) {
  header('Location: trade.php');
  exit;
}

function label($text, $name, $type = 'text', $required = true) {
  echo "<label>$text</label>";
  echo "<input name=\"$name\" type=\"$type\" " . ($required ? "required" : "") . ">";
}
?>
<?php require 'includes/layout.php'; ?>
  <title>Trade Details</title>
  <link rel="stylesheet" href="assets/style.css">
</head>
<body>
  <?php include 'includes/sidebar.php'; ?>
  <?php include 'includes/header.php'; ?>
  <h2>Trade - <?= htmlspecialchars(ucfirst($category)) ?> Details</h2>

  <form method="post" action="submit-request.php" enctype="multipart/form-data">
    <input type="hidden" name="csrf_token" value="<?= generate_token(); ?>">
    <input type="hidden" name="type" value="trade">
    <input type="hidden" name="category" value="<?= htmlspecialchars($category) ?>">

    <?php
      $labels = [
        'make' => 'Current Device Make',
        'model' => 'Current Device Model',
        'device_type' => 'Desired Device',
        'issue' => 'Condition / Details',
        'serial' => 'IMEI / Serial Number',
      ];

      if ($category === 'other') {
        $labels['make'] = 'Device You Have';
        $labels['model'] = 'Desired Device';
        $labels['device_type'] = 'Preferred Replacement Details';
      }
    ?>

    <?php label($labels['make'], 'make'); ?>
    <?php label($labels['model'], 'model'); ?>
    <?php label($labels['serial'], 'serial', 'text', false); ?>
    <?php label($labels['device_type'], 'device_type'); ?>
    <?php label($labels['issue'], 'issue'); ?>

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

