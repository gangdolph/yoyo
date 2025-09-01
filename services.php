<?php
require 'includes/auth.php';
require 'includes/csrf.php';
?>
<?php require 'includes/layout.php'; ?>
  <title>Request a Service</title>
  <link rel="stylesheet" href="assets/style.css">
</head>
<body>
  <?php include 'includes/sidebar.php'; ?>
  <?php include 'includes/header.php'; ?>

  <h2>Select a Service</h2>
  <div class="service-list">
    <button class="service-option" data-category="pc_build">PC Build</button>
    <button class="service-option" data-category="cleaning">Cleaning</button>
    <button class="service-option" data-category="console_mod">Console Modding</button>
    <button class="service-option" data-category="phone_repair">Phone Repair</button>
    <button class="service-option" data-category="other">Other</button>
  </div>

  <div id="serviceModal" class="modal">
    <div class="modal-content">
      <span id="closeModal" class="close">&times;</span>
      <h3 id="modalTitle">Service Request</h3>
      <form method="post" action="submit-request.php">
        <input type="hidden" name="csrf_token" value="<?= generate_token(); ?>">
        <input type="hidden" name="type" value="service">
        <input type="hidden" name="category" id="categoryField">

        <div class="field make-field">
          <label>Make</label>
          <input type="text" name="make" id="makeField">
        </div>

        <div class="field model-field">
          <label>Model</label>
          <input type="text" name="model" id="modelField">
        </div>

        <div class="field issue-field">
          <label>Issue / Details</label>
          <textarea name="issue" id="issueField" required></textarea>
        </div>

        <button type="submit">Submit Request</button>
      </form>
    </div>
  </div>

  <script>
    const templates = {
      pc_build: {
        title: 'PC Build',
        template: 'I would like a custom PC built.',
        showMake: false,
        showModel: false
      },
      cleaning: {
        title: 'Cleaning',
        template: 'Please clean my device.',
        showMake: true,
        showModel: true
      },
      console_mod: {
        title: 'Console Modding',
        template: 'I want my console modded.',
        showMake: true,
        showModel: true
      },
      phone_repair: {
        title: 'Phone Repair',
        template: 'My phone needs repair.',
        showMake: true,
        showModel: true
      },
      other: {
        title: 'Other Service',
        template: 'Describe your service request.',
        showMake: true,
        showModel: true
      }
    };

    const modal = document.getElementById('serviceModal');
    const closeModal = document.getElementById('closeModal');
    const issueField = document.getElementById('issueField');
    const categoryField = document.getElementById('categoryField');
    const makeField = document.querySelector('.make-field');
    const modelField = document.querySelector('.model-field');
    const modalTitle = document.getElementById('modalTitle');

    document.querySelectorAll('.service-option').forEach(btn => {
      btn.addEventListener('click', () => {
        const key = btn.dataset.category;
        const data = templates[key];
        categoryField.value = key;
        issueField.value = data.template;
        modalTitle.textContent = data.title + ' Request';
        makeField.style.display = data.showMake ? 'block' : 'none';
        modelField.style.display = data.showModel ? 'block' : 'none';
        modal.style.display = 'block';
      });
    });

    closeModal.addEventListener('click', () => {
      modal.style.display = 'none';
    });

    window.addEventListener('click', e => {
      if (e.target === modal) {
        modal.style.display = 'none';
      }
    });
  </script>

  <?php include 'includes/footer.php'; ?>
</body>
</html>

