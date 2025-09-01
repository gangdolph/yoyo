<?php
require 'includes/auth.php';
require 'includes/csrf.php';

$category = $_GET['category'] ?? '';

function label($text, $name, $type = 'text', $required = true) {
  echo "<label>$text</label>";
  echo "<input name=\"$name\" type=\"$type\" " . ($required ? "required" : "") . ">";
}
?>
<?php require 'includes/layout.php'; ?>
  <title>Buy Details</title>
  <link rel="stylesheet" href="assets/style.css">
</head>
<body>
  <?php include 'includes/sidebar.php'; ?>
  <?php include 'includes/header.php'; ?>
  <?php if ($category === ''): ?>
    <h2>Request a Device</h2>
    <form method="get">
      <select name="category" required>
        <option value="">Select One</option>
        <option value="phone">Phone</option>
        <option value="console">Game Console</option>
        <option value="pc">PC</option>
        <option value="other">Other Device</option>
      </select>
      <button type="submit">Next</button>
    </form>
  <?php else: ?>
    <h2>Buy - <?= htmlspecialchars(ucfirst($category)) ?> Details</h2>

    <form method="post" action="submit-request.php">
      <input type="hidden" name="csrf_token" value="<?= generate_token(); ?>">
      <input type="hidden" name="type" value="buy">
      <input type="hidden" name="category" value="<?= htmlspecialchars($category) ?>">

      <?php if ($category === 'phone' || $category === 'console' || $category === 'pc'): ?>
        <?php label('Preferred Make', 'make'); ?>
        <?php label('Preferred Model', 'model'); ?>
        <?php label('Budget', 'issue', 'number'); ?>
      <?php endif; ?>

      <?php if ($category === 'pc'): ?>
        <label>Is this a custom build?</label>
        <select name="build">
          <option value="no">No</option>
          <option value="yes">Yes, build a PC</option>
        </select>
      <?php endif; ?>

      <?php if ($category === 'other'): ?>
        <?php label('Device Type', 'device_type'); ?>
        <?php label('Budget / Details', 'issue'); ?>
      <?php endif; ?>

      <button type="submit">Review and Submit</button>
    </form>
  <?php endif; ?>
  <?php include 'includes/footer.php'; ?>
</body>
</html>
