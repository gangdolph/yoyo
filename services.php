<?php
require_once __DIR__ . '/includes/auth.php';
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
$modelsEndpoint = 'api/models.php';
?>
<?php require 'includes/layout.php'; ?>
  <title>Request a Service</title>
  <link rel="stylesheet" href="assets/style.css">
</head>
<body>
  <?php include 'includes/sidebar.php'; ?>
  <?php include 'includes/header.php'; ?>

  <h2>Request a Service</h2>
  <p class="wizard-intro">Pick the flow that best matches what you need and we will guide you through the right questions.</p>

  <?php include __DIR__ . '/includes/partials/service-wizard.php'; ?>

  <script src="assets/service-wizard.js" defer></script>
  <?php include 'includes/footer.php'; ?>
</body>
</html>
