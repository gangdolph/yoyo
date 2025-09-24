<?php
require_once __DIR__ . '/includes/require-auth.php';
require 'includes/csrf.php';
require 'includes/db.php';

$serviceTaxonomy = require __DIR__ . '/includes/service_taxonomy.php';
$brandOptions = [];
if ($result = $conn->query('SELECT id, name FROM service_brands ORDER BY name')) {
    while ($row = $result->fetch_assoc()) {
        $brandOptions[] = $row;
    }
    $result->close();
}

$modelOptions = [];
if ($result = $conn->query('SELECT id, brand_id, name FROM service_models ORDER BY name')) {
    while ($row = $result->fetch_assoc()) {
        $modelOptions[] = $row;
    }
    $result->close();
}
$modelsEndpoint = 'api/models.php';

$requestedPath = $_GET['path'] ?? '';
if (!is_string($requestedPath)) {
    $requestedPath = '';
} else {
    $requestedPath = trim($requestedPath);
}
if ($requestedPath !== '' && !isset($serviceTaxonomy[$requestedPath])) {
    $requestedPath = '';
}
$serviceWizardDefaultService = $requestedPath;
?>
<?php require 'includes/layout.php'; ?>
  <title>Service Wizard</title>
  <link rel="stylesheet" href="assets/style.css">
</head>
<body>
  <?php include 'includes/sidebar.php'; ?>
  <?php include 'includes/header.php'; ?>

  <p class="service-cta-secondary"><a href="services.php">&larr; Back to service overview</a></p>
  <h2>Request a Service</h2>
  <p class="wizard-intro">Pick the flow that best matches what you need and we will guide you through the right questions.</p>

  <?php include __DIR__ . '/includes/partials/service-wizard.php'; ?>

  <script src="assets/service-wizard.js" defer></script>
  <?php include 'includes/footer.php'; ?>
</body>
</html>
