<?php
require_once __DIR__ . '/includes/auth.php';
require 'includes/csrf.php';
require 'includes/db.php';
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

  <ul class="service-cta-grid" role="list">
    <li class="service-cta-item" role="listitem">
      <a class="service-cta-card" href="service-wizard.php?path=build">
        <h3 class="service-cta-card__title">Start a Custom Build</h3>
        <p class="service-cta-card__description">Plan a bespoke PC or workstation build with component sourcing.</p>
        <span class="service-cta-card__action" aria-hidden="true">Design my build →</span>
      </a>
    </li>
    <li class="service-cta-item" role="listitem">
      <a class="service-cta-card" href="service-wizard.php?path=repair">
        <h3 class="service-cta-card__title">Request a Repair</h3>
        <p class="service-cta-card__description">Fix hardware, screen, and component issues with certified technicians.</p>
        <span class="service-cta-card__action" aria-hidden="true">Start repair request →</span>
      </a>
    </li>
    <li class="service-cta-item" role="listitem">
      <a class="service-cta-card" href="service-wizard.php?path=clean">
        <h3 class="service-cta-card__title">Schedule a Cleaning</h3>
        <p class="service-cta-card__description">Improve performance with deep cleanings, malware removal, and tune-ups.</p>
        <span class="service-cta-card__action" aria-hidden="true">Plan a cleaning visit →</span>
      </a>
    </li>
  </ul>

  <p class="service-cta-secondary">
    Looking for something else? <a href="service-wizard.php">Browse the full service wizard.</a>
  </p>

  <?php include 'includes/footer.php'; ?>
</body>
</html>
