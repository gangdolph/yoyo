<?php
require 'includes/db.php';

$toolsPath = __DIR__ . '/assets/tools.json';
$tools = [];
if (file_exists($toolsPath)) {
  $data = file_get_contents($toolsPath);
  $decoded = json_decode($data, true);
  if (is_array($decoded)) {
    $tools = $decoded;
  }
}

$grouped = [];
foreach ($tools as $t) {
  $cat = $t['category'] ?? 'misc';
  $grouped[$cat][] = $t;
}
?>
<?php require 'includes/layout.php'; ?>
  <meta charset="UTF-8">
  <title>Toolbox</title>
  <link rel="stylesheet" href="assets/style.css">
</head>
<body>
  <?php include 'includes/sidebar.php'; ?>
  <?php include 'includes/header.php'; ?>
  <h2>Toolbox</h2>
  <?php if ($grouped): ?>
    <?php foreach ($grouped as $category => $list): ?>
      <h3><?= htmlspecialchars(ucfirst($category), ENT_QUOTES, 'UTF-8'); ?></h3>
      <ul>
      <?php foreach ($list as $tool): ?>
        <li>
          <a href="<?= htmlspecialchars($tool['url'], ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener">
            <?= htmlspecialchars($tool['name'], ENT_QUOTES, 'UTF-8'); ?>
          </a>
          - <?= htmlspecialchars($tool['description'], ENT_QUOTES, 'UTF-8'); ?>
        </li>
      <?php endforeach; ?>
      </ul>
    <?php endforeach; ?>
  <?php else: ?>
    <p>No tools available.</p>
  <?php endif; ?>
  <?php include 'includes/footer.php'; ?>
</body>
</html>
