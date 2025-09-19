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
      <section class="promoted-grid" aria-label="Promoted shops">
        <?php foreach ($shops as $s): ?>
          <?php
            $logo = $s['company_logo'] ? '/assets/logos/' . $s['company_logo'] : '';
            $shopName = $s['company_name'] ?: $s['username'];
            $hasWebsite = !empty($s['company_website']);
            $url = $hasWebsite ? $s['company_website'] : 'view-profile.php?id=' . $s['id'];
            $domain = '';
            if ($hasWebsite) {
              $parsedHost = parse_url($s['company_website'], PHP_URL_HOST) ?: '';
              $domain = preg_replace('/^www\./i', '', $parsedHost);
            }
          ?>
          <article class="card-neo promoted-card">
            <?php if ($logo): ?>
              <div class="promoted-card__media">
                <img
                  src="<?= htmlspecialchars($logo, ENT_QUOTES, 'UTF-8'); ?>"
                  alt="<?= htmlspecialchars($shopName . ' logo', ENT_QUOTES, 'UTF-8'); ?>"
                  class="promoted-card__logo"
                  loading="lazy"
                >
              </div>
            <?php endif; ?>
            <div class="promoted-card__body">
              <h3 class="promoted-card__title">
                <a
                  class="promoted-card__link"
                  href="<?= htmlspecialchars($url, ENT_QUOTES, 'UTF-8'); ?>"
                  <?php if ($hasWebsite): ?> target="_blank" rel="noopener noreferrer"<?php endif; ?>
                >
                  <?= htmlspecialchars($shopName, ENT_QUOTES, 'UTF-8'); ?>
                </a>
              </h3>
              <p class="promoted-card__meta">
                <span class="promoted-card__handle">@<?= htmlspecialchars($s['username'], ENT_QUOTES, 'UTF-8'); ?></span>
                <span class="promoted-card__divider" aria-hidden="true">•</span>
                <?php if ($domain): ?>
                  <span class="promoted-card__domain"><?= htmlspecialchars($domain, ENT_QUOTES, 'UTF-8'); ?></span>
                <?php else: ?>
                  <span class="promoted-card__domain">On Yoyo Market</span>
                <?php endif; ?>
              </p>
            </div>
          </article>
        <?php endforeach; ?>
      </section>
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
