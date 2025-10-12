<?php
if (!defined('APP_BOOTSTRAPPED')) {
    define('APP_BOOTSTRAPPED', true);
    require_once __DIR__ . '/../includes/bootstrap.php';
}

require_once __DIR__ . '/../includes/require-auth.php';
require '../includes/csrf.php';

ensure_admin('../dashboard.php');

$errors = [];
$success = isset($_GET['saved']) ? 'Changes saved.' : '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_token($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid CSRF token. Please try again.';
    } else {
        $action = $_POST['action'] ?? '';
        switch ($action) {
            case 'add_brand':
                $name = trim($_POST['name'] ?? '');
                if ($name === '') {
                    $errors[] = 'Brand name is required.';
                    break;
                }

                if ($check = $conn->prepare('SELECT id FROM service_brands WHERE name = ? LIMIT 1')) {
                    $check->bind_param('s', $name);
                    $check->execute();
                    $check->store_result();
                    if ($check->num_rows > 0) {
                        $errors[] = 'That brand already exists.';
                    }
                    $check->close();
                }

                if (empty($errors) && $stmt = $conn->prepare('INSERT INTO service_brands (name) VALUES (?)')) {
                    $stmt->bind_param('s', $name);
                    $stmt->execute();
                    $stmt->close();
                    header('Location: service-taxonomy.php?saved=1');
                    exit;
                }
                break;

            case 'update_brand':
                $brandId = (int)($_POST['brand_id'] ?? 0);
                $name = trim($_POST['name'] ?? '');
                if ($brandId <= 0) {
                    $errors[] = 'Unknown brand selected.';
                    break;
                }
                if ($name === '') {
                    $errors[] = 'Brand name is required.';
                    break;
                }

                if ($check = $conn->prepare('SELECT id FROM service_brands WHERE name = ? AND id <> ? LIMIT 1')) {
                    $check->bind_param('si', $name, $brandId);
                    $check->execute();
                    $check->store_result();
                    if ($check->num_rows > 0) {
                        $errors[] = 'Another brand already uses that name.';
                    }
                    $check->close();
                }

                if (empty($errors) && $stmt = $conn->prepare('UPDATE service_brands SET name = ? WHERE id = ?')) {
                    $stmt->bind_param('si', $name, $brandId);
                    $stmt->execute();
                    $stmt->close();
                    header('Location: service-taxonomy.php?saved=1');
                    exit;
                }
                break;

            case 'add_model':
                $brandId = (int)($_POST['brand_id'] ?? 0);
                $name = trim($_POST['name'] ?? '');
                if ($brandId <= 0) {
                    $errors[] = 'Select a brand for the model.';
                    break;
                }
                if ($name === '') {
                    $errors[] = 'Model name is required.';
                    break;
                }

                $brandExists = false;
                if ($checkBrand = $conn->prepare('SELECT id FROM service_brands WHERE id = ? LIMIT 1')) {
                    $checkBrand->bind_param('i', $brandId);
                    $checkBrand->execute();
                    $checkBrand->store_result();
                    $brandExists = $checkBrand->num_rows > 0;
                    $checkBrand->close();
                }
                if (!$brandExists) {
                    $errors[] = 'Selected brand no longer exists.';
                    break;
                }

                if ($check = $conn->prepare('SELECT id FROM service_models WHERE brand_id = ? AND name = ? LIMIT 1')) {
                    $check->bind_param('is', $brandId, $name);
                    $check->execute();
                    $check->store_result();
                    if ($check->num_rows > 0) {
                        $errors[] = 'That model already exists for the brand.';
                    }
                    $check->close();
                }

                if (empty($errors) && $stmt = $conn->prepare('INSERT INTO service_models (brand_id, name) VALUES (?, ?)')) {
                    $stmt->bind_param('is', $brandId, $name);
                    $stmt->execute();
                    $stmt->close();
                    header('Location: service-taxonomy.php?saved=1');
                    exit;
                }
                break;

            case 'update_model':
                $modelId = (int)($_POST['model_id'] ?? 0);
                $brandId = (int)($_POST['brand_id'] ?? 0);
                $name = trim($_POST['name'] ?? '');
                if ($modelId <= 0) {
                    $errors[] = 'Unknown model selected.';
                    break;
                }
                if ($brandId <= 0) {
                    $errors[] = 'Select a brand for the model.';
                    break;
                }
                if ($name === '') {
                    $errors[] = 'Model name is required.';
                    break;
                }

                $brandExists = false;
                if ($checkBrand = $conn->prepare('SELECT id FROM service_brands WHERE id = ? LIMIT 1')) {
                    $checkBrand->bind_param('i', $brandId);
                    $checkBrand->execute();
                    $checkBrand->store_result();
                    $brandExists = $checkBrand->num_rows > 0;
                    $checkBrand->close();
                }
                if (!$brandExists) {
                    $errors[] = 'Selected brand no longer exists.';
                    break;
                }

                if ($check = $conn->prepare('SELECT id FROM service_models WHERE brand_id = ? AND name = ? AND id <> ? LIMIT 1')) {
                    $check->bind_param('isi', $brandId, $name, $modelId);
                    $check->execute();
                    $check->store_result();
                    if ($check->num_rows > 0) {
                        $errors[] = 'Another model with that name already exists for the brand.';
                    }
                    $check->close();
                }

                if (empty($errors) && $stmt = $conn->prepare('UPDATE service_models SET brand_id = ?, name = ? WHERE id = ?')) {
                    $stmt->bind_param('isi', $brandId, $name, $modelId);
                    $stmt->execute();
                    $stmt->close();
                    header('Location: service-taxonomy.php?saved=1');
                    exit;
                }
                break;

            default:
                $errors[] = 'Unknown action requested.';
        }
    }
}

$brands = [];
if ($result = $conn->query('SELECT id, name FROM service_brands ORDER BY name')) {
    while ($row = $result->fetch_assoc()) {
        $id = (int)$row['id'];
        $brands[$id] = [
            'id' => $id,
            'name' => $row['name'],
            'models' => [],
        ];
    }
}

if (!empty($brands)) {
    $brandIds = implode(',', array_keys($brands));
    if ($brandIds !== '' && $result = $conn->query('SELECT id, brand_id, name FROM service_models WHERE brand_id IN (' . $brandIds . ') ORDER BY name')) {
        while ($row = $result->fetch_assoc()) {
            $brandId = (int)$row['brand_id'];
            if (isset($brands[$brandId])) {
                $brands[$brandId]['models'][] = [
                    'id' => (int)$row['id'],
                    'brand_id' => $brandId,
                    'name' => $row['name'],
                ];
            }
        }
    }
}

$brandOptions = array_values($brands);
?>
<?php require '../includes/layout.php'; ?>
  <title>Service Taxonomy</title>
  <link rel="stylesheet" href="../assets/style.css">
</head>
<body>
  <?php include '../includes/header.php'; ?>
  <h2>Service Taxonomy</h2>
  <p><a class="btn" href="index.php">Back to Admin Panel</a></p>

  <?php if (!empty($success)): ?>
    <p style="color:green;"><?= htmlspecialchars($success) ?></p>
  <?php endif; ?>
  <?php if (!empty($errors)): ?>
    <div style="color:red;">
      <ul>
        <?php foreach ($errors as $error): ?>
          <li><?= htmlspecialchars($error) ?></li>
        <?php endforeach; ?>
      </ul>
    </div>
  <?php endif; ?>

  <section>
    <h3>Add Brand</h3>
    <form method="post">
      <input type="hidden" name="csrf_token" value="<?= generate_token(); ?>">
      <input type="hidden" name="action" value="add_brand">
      <label>Brand Name
        <input type="text" name="name" required>
      </label>
      <button type="submit">Add Brand</button>
    </form>
  </section>

  <section>
    <h3>Add Model</h3>
    <form method="post">
      <input type="hidden" name="csrf_token" value="<?= generate_token(); ?>">
      <input type="hidden" name="action" value="add_model">
      <label>Brand
        <select name="brand_id" required>
          <option value="">Select a brand</option>
          <?php foreach ($brandOptions as $brand): ?>
            <option value="<?= $brand['id']; ?>"><?= htmlspecialchars($brand['name']); ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label>Model Name
        <input type="text" name="name" required>
      </label>
      <button type="submit">Add Model</button>
    </form>
  </section>

  <section>
    <h3>Existing Brands &amp; Models</h3>
    <?php if (empty($brandOptions)): ?>
      <p>No brands found. Add one above to get started.</p>
    <?php else: ?>
      <?php foreach ($brandOptions as $brand): ?>
        <div class="card" style="margin-bottom: 1.5rem; padding: 1rem;">
          <form method="post" style="margin-bottom: 1rem;">
            <input type="hidden" name="csrf_token" value="<?= generate_token(); ?>">
            <input type="hidden" name="action" value="update_brand">
            <input type="hidden" name="brand_id" value="<?= $brand['id']; ?>">
            <label>Brand Name
              <input type="text" name="name" value="<?= htmlspecialchars($brand['name']); ?>" required>
            </label>
            <button type="submit">Save Brand</button>
          </form>

          <?php if (empty($brand['models'])): ?>
            <p>No models for this brand yet.</p>
          <?php else: ?>
            <table>
              <thead>
                <tr>
                  <th style="text-align:left;">Model</th>
                  <th style="text-align:left;">Brand</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($brand['models'] as $model): ?>
                  <tr>
                    <td>
                      <form method="post" style="display:flex; gap:0.5rem; align-items:center;">
                        <input type="hidden" name="csrf_token" value="<?= generate_token(); ?>">
                        <input type="hidden" name="action" value="update_model">
                        <input type="hidden" name="model_id" value="<?= $model['id']; ?>">
                        <input type="text" name="name" value="<?= htmlspecialchars($model['name']); ?>" required>
                        <select name="brand_id" required>
                          <?php foreach ($brandOptions as $option): ?>
                            <option value="<?= $option['id']; ?>" <?= $option['id'] === $model['brand_id'] ? 'selected' : ''; ?>><?= htmlspecialchars($option['name']); ?></option>
                          <?php endforeach; ?>
                        </select>
                        <button type="submit">Save</button>
                      </form>
                    </td>
                    <td><?= htmlspecialchars($brand['name']); ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </section>

  <?php include '../includes/footer.php'; ?>
</body>
</html>
