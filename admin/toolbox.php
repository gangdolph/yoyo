<?php
if (!defined('APP_BOOTSTRAPPED')) {
    define('APP_BOOTSTRAPPED', true);
    require_once __DIR__ . '/../includes/bootstrap.php';
}

require_once __DIR__ . '/../includes/require-auth.php';
require '../includes/csrf.php';

ensure_admin('../dashboard.php');

$toolsPath = __DIR__ . '/../assets/tools.json';
$tools = [];
if (file_exists($toolsPath)) {
  $json = file_get_contents($toolsPath);
  $decoded = json_decode($json, true);
  if (is_array($decoded)) {
    $tools = $decoded;
  }
}

$errors = [];
$messages = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!validate_token($_POST['csrf_token'] ?? '')) {
    $errors[] = 'Invalid CSRF token.';
  } else {
    if (isset($_POST['add'])) {
      $tool = [
        'category' => trim($_POST['category'] ?? ''),
        'name' => trim($_POST['name'] ?? ''),
        'description' => trim($_POST['description'] ?? ''),
        'url' => trim($_POST['url'] ?? ''),
      ];
      $tools[] = $tool;
      if (file_put_contents($toolsPath, json_encode($tools, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) !== false) {
        $messages[] = 'Tool added.';
      } else {
        $errors[] = 'Failed to save tool list.';
      }
    } elseif (isset($_POST['update'])) {
      $idx = (int)($_POST['index'] ?? -1);
      if (isset($tools[$idx])) {
        $tools[$idx]['category'] = trim($_POST['category'] ?? '');
        $tools[$idx]['name'] = trim($_POST['name'] ?? '');
        $tools[$idx]['description'] = trim($_POST['description'] ?? '');
        $tools[$idx]['url'] = trim($_POST['url'] ?? '');
        if (file_put_contents($toolsPath, json_encode($tools, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) !== false) {
          $messages[] = 'Tool updated.';
        } else {
          $errors[] = 'Failed to save tool list.';
        }
      }
    } elseif (isset($_POST['delete'])) {
      $idx = (int)($_POST['index'] ?? -1);
      if (isset($tools[$idx])) {
        array_splice($tools, $idx, 1);
        if (file_put_contents($toolsPath, json_encode($tools, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) !== false) {
          $messages[] = 'Tool removed.';
        } else {
          $errors[] = 'Failed to save tool list.';
        }
      }
    }
  }
}
?>
<?php require '../includes/layout.php'; ?>
  <meta charset="UTF-8">
  <title>Manage Toolbox</title>
  <link rel="stylesheet" href="../assets/style.css">
</head>
<body>
  <?php include '../includes/header.php'; ?>
  <h2>Manage Toolbox</h2>
  <?php foreach ($errors as $e): ?>
    <p style="color:red;"><?= htmlspecialchars($e) ?></p>
  <?php endforeach; ?>
  <?php foreach ($messages as $m): ?>
    <p style="color:green;"><?= htmlspecialchars($m) ?></p>
  <?php endforeach; ?>
  <table>
    <thead>
      <tr>
        <th>Category</th>
        <th>Name</th>
        <th>Description</th>
        <th>URL</th>
        <th>Actions</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($tools as $i => $tool): ?>
      <tr>
        <form method="post">
          <td><input type="text" name="category" value="<?= htmlspecialchars($tool['category']) ?>"></td>
          <td><input type="text" name="name" value="<?= htmlspecialchars($tool['name']) ?>"></td>
          <td><input type="text" name="description" value="<?= htmlspecialchars($tool['description']) ?>"></td>
          <td><input type="url" name="url" value="<?= htmlspecialchars($tool['url']) ?>"></td>
          <td>
            <input type="hidden" name="csrf_token" value="<?= generate_token(); ?>">
            <input type="hidden" name="index" value="<?= $i ?>">
            <button type="submit" name="update">Update</button>
            <button type="submit" name="delete" onclick="return confirm('Delete this tool?');">Delete</button>
          </td>
        </form>
      </tr>
      <?php endforeach; ?>
      <tr>
        <form method="post">
          <td><input type="text" name="category"></td>
          <td><input type="text" name="name"></td>
          <td><input type="text" name="description"></td>
          <td><input type="url" name="url"></td>
          <td>
            <input type="hidden" name="csrf_token" value="<?= generate_token(); ?>">
            <button type="submit" name="add">Add</button>
          </td>
        </form>
      </tr>
    </tbody>
  </table>
  <p><a class="btn" href="../toolbox.php">View Toolbox</a></p>
  <?php include '../includes/footer.php'; ?>
</body>
</html>
