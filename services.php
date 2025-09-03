<?php
require 'includes/auth.php';
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

  <div id="wizard">
    <form id="serviceForm" method="post" action="submit-request.php">
      <input type="hidden" name="csrf_token" value="<?= generate_token(); ?>">
      <input type="hidden" name="type" value="service">
      <input type="hidden" name="category" id="categoryField">

      <div class="step" data-step="1">
        <h3>Select Service Type</h3>
        <button type="button" class="step1" data-category="repair">Repair</button>
        <button type="button" class="step1" data-category="clean">Cleaning</button>
        <button type="button" class="step1" data-category="build">Build</button>
        <button type="button" class="step1" data-category="other">Other</button>
      </div>

      <div class="step" data-step="2" style="display:none;">
        <h3>Choose Brand</h3>
        <select id="brandSelect" name="brand_id" required>
          <option value="">Select brand</option>
          <?php
          $brands = $conn->query('SELECT id, name FROM service_brands ORDER BY name');
          if ($brands) {
            while ($b = $brands->fetch_assoc()) {
              echo '<option value="' . (int)$b['id'] . '">' . htmlspecialchars($b['name']) . '</option>';
            }
          }
          ?>
        </select>
        <button type="button" id="toStep3">Next</button>
      </div>

      <div class="step" data-step="3" style="display:none;">
        <h3>Choose Model</h3>
        <select id="modelSelect" name="model_id" required>
          <option value="">Select model</option>
        </select>
        <button type="button" id="toStep4">Next</button>
      </div>

      <div class="step" data-step="4" style="display:none;">
        <h3>Issue Details</h3>
        <label for="issueField">Issue / Details</label>
        <textarea id="issueField" name="issue" required></textarea>
        <div id="serialWrapper">
          <label for="serialField">Serial (optional)</label>
          <input id="serialField" type="text" name="serial">
        </div>
        <div id="deviceTypeWrapper" style="display:none;">
          <label for="deviceTypeField">Device Type</label>
          <input id="deviceTypeField" type="text" name="device_type" required>
        </div>
        <button type="submit">Submit Request</button>
      </div>
    </form>
  </div>

  <script>
  const steps = document.querySelectorAll('#wizard .step');
  const categoryField = document.getElementById('categoryField');
  const brandSelect = document.getElementById('brandSelect');
  const modelSelect = document.getElementById('modelSelect');
  const serialWrapper = document.getElementById('serialWrapper');
  const deviceTypeWrapper = document.getElementById('deviceTypeWrapper');
  const deviceTypeField = document.getElementById('deviceTypeField');

  const templates = {
    repair:  { brand: true, model: true, serial: true, device: false },
    clean:   { brand: true, model: true, serial: true, device: false },
    build:   { brand: true, model: true, serial: true, device: false },
    other:   { brand: false, model: false, serial: false, device: true }
  };

  function showStep(n){
    steps.forEach(step => {
      step.style.display = step.dataset.step == n ? 'block' : 'none';
    });
  }

  document.querySelectorAll('.step1').forEach(btn => {
    btn.addEventListener('click', () => {
      const key = btn.dataset.category;
      const conf = templates[key];
      categoryField.value = key;
      brandSelect.required = conf.brand;
      modelSelect.required = conf.model;
      serialWrapper.style.display = conf.serial ? 'block' : 'none';
      deviceTypeWrapper.style.display = conf.device ? 'block' : 'none';
      deviceTypeField.required = conf.device;
      if(conf.brand){
        showStep(2);
      } else {
        showStep(4);
      }
    });
  });

  document.getElementById('toStep3').addEventListener('click', () => {
    if(!brandSelect.value){
      alert('Please select a brand');
      return;
    }
    fetch('api/models.php?brand_id=' + encodeURIComponent(brandSelect.value))
      .then(r => r.json())
      .then(models => {
        modelSelect.innerHTML = '<option value="">Select model</option>';
        models.forEach(m => {
          const opt = document.createElement('option');
          opt.value = m.id;
          opt.textContent = m.name;
          modelSelect.appendChild(opt);
        });
      });
    showStep(3);
  });

  document.getElementById('toStep4').addEventListener('click', () => {
    if(!modelSelect.value){
      alert('Please select a model');
      return;
    }
    showStep(4);
  });
  </script>

  <?php include 'includes/footer.php'; ?>
</body>
</html>
