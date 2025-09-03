<?php
require 'includes/db.php';

$configPath = __DIR__ . '/config.php';
$config = file_exists($configPath) ? require $configPath : [];
$adsenseClient = $config['adsense_client'] ?? '';
$adsenseSlot = $config['adsense_slot'] ?? '';

$shops = [];
if ($stmt = $conn->prepare('SELECT id, username, company_name, company_website, company_logo FROM users WHERE account_type = "business" AND promoted = 1 AND (promoted_expires IS NULL OR promoted_expires > NOW()) ORDER BY promoted_expires DESC')) {
    $stmt->execute();
    $shops = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}
?>
<?php require 'includes/layout.php'; ?>
  <meta charset="UTF-8">
  <title>Promoted Shops</title>
<?php if ($adsenseClient): ?>
  <script async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=<?= htmlspecialchars($adsenseClient, ENT_QUOTES, 'UTF-8'); ?>" crossorigin="anonymous"></script>
<?php endif; ?>
</head>
<body>
  <?php include 'includes/sidebar.php'; ?>
  <?php include 'includes/header.php'; ?>
  <div class="page-container">
    <h2>Promoted Shops</h2>
    <?php if ($shops): ?>
      <ul>
        <?php foreach ($shops as $s): ?>
          <?php $logo = $s['company_logo'] ? '/assets/logos/' . $s['company_logo'] : ''; ?>
          <li>
            <?php if ($logo): ?><img src="<?= htmlspecialchars($logo, ENT_QUOTES, 'UTF-8'); ?>" alt="Logo" width="50"> <?php endif; ?>
            <?php if (!empty($s['company_website'])): ?>
              <a href="<?= htmlspecialchars($s['company_website'], ENT_QUOTES, 'UTF-8'); ?>" target="_blank">
                <?= htmlspecialchars($s['company_name'] ?: $s['username'], ENT_QUOTES, 'UTF-8'); ?>
              </a>
            <?php else: ?>
              <a href="view-profile.php?id=<?= $s['id']; ?>">
                <?= htmlspecialchars($s['company_name'] ?: $s['username'], ENT_QUOTES, 'UTF-8'); ?>
              </a>
            <?php endif; ?>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php else: ?>
      <p>No promoted shops at this time.</p>
    <?php endif; ?>

    <div class="ad-unit">
    <?php if ($adsenseClient && $adsenseSlot): ?>
      <ins class="adsbygoogle"
           style="display:block"
           data-ad-client="<?= htmlspecialchars($adsenseClient, ENT_QUOTES, 'UTF-8'); ?>"
           data-ad-slot="<?= htmlspecialchars($adsenseSlot, ENT_QUOTES, 'UTF-8'); ?>"
           data-ad-format="auto"
           data-full-width-responsive="true"></ins>
      <script>(adsbygoogle = window.adsbygoogle || []).push({});</script>
    <?php else: ?>
      <p>Ad space</p>
    <?php endif; ?>
    </div>
  </div>
  <?php include 'includes/footer.php'; ?>
</body>
</html>
