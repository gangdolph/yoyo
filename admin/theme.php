<?php
require '../includes/auth.php';

if (!$_SESSION['is_admin']) {
  header("Location: ../dashboard.php");
  exit;
}

$themesFile = __DIR__ . '/../assets/themes.json';
$themes = [];
if (file_exists($themesFile)) {
  $json = json_decode(file_get_contents($themesFile), true);
  if (is_array($json)) {
    $themes = $json;
  }
}

$gradientPresets = [
  'vibrant' => 'linear-gradient(135deg, #ff71ce 0%, #01cdfe 100%)',
  'dark'    => 'linear-gradient(135deg, #2d1e59 0%, #09002e 100%)',
  'pastel'  => 'linear-gradient(135deg, #ff9a9e 0%, #fad0c4 100%)',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $name = strtolower(preg_replace('/[^a-z0-9_-]/i', '', $_POST['name'] ?? ''));
  if ($name) {
    $themes[$name] = [
      'label' => $_POST['label'] ?: ucfirst($name),
      'vars' => [
        '--bg' => $_POST['bg'] ?? '#ffffff',
        '--fg' => $_POST['fg'] ?? '#000000',
        '--accent' => $_POST['accent'] ?? '#ff71ce',
        '--gradient' => $_POST['gradient'] ?? 'linear-gradient(135deg, #ff71ce 0%, #01cdfe 100%)',
        '--vap1' => $_POST['vap1'] ?? null,
        '--vap2' => $_POST['vap2'] ?? null,
        '--vap3' => $_POST['vap3'] ?? null,
        '--font-header' => $_POST['font_header'] ?? "'Share Tech Mono', monospace",
        '--font-body' => $_POST['font_body'] ?? "'Share Tech Mono', monospace",
        '--font-paragraph' => $_POST['font_paragraph'] ?? "'Share Tech Mono', monospace",
        '--cta-gradient' => $_POST['cta_gradient'] ?? 'linear-gradient(45deg, var(--accent), var(--vap2), var(--vap3))',
        '--cta-depth' => isset($_POST['cta_depth']) && $_POST['cta_depth'] !== '' ? $_POST['cta_depth'] . 'px' : '20px',
      ],
    ];
    if (!empty($_POST['pattern_enabled'])) {
      $freq = $_POST['pattern_freq'] ?? '';
      $amp = $_POST['pattern_amp'] ?? '';
      $poly = $_POST['pattern_poly'] ?? '';
      $hue = $_POST['pattern_hue'] ?? '';
      $sat = $_POST['pattern_sat'] ?? '';
      $themes[$name]['pattern'] = [
        'frequency' => (float)$freq,
        'amplitude' => (float)$amp,
        'poly' => array_map('floatval', array_filter(array_map('trim', explode(',', $poly)), 'strlen')),
        'hue' => (int)$hue,
        'sat' => (int)$sat,
      ];
    }
    $saved = file_put_contents($themesFile, json_encode($themes, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    if ($saved !== false) {
      // ensure the JSON file remains web accessible
      @chmod($themesFile, 0644);
      clearstatcache(true, $themesFile);
      if (!is_readable($themesFile)) {
        error_log('themes.json is not readable by the web server');
      }
    }
  }
  header('Location: theme.php');
  exit;
}

$edit = $_GET['edit'] ?? '';
$current = $themes[$edit] ?? null;
?>
<?php require '../includes/layout.php'; ?>
  <title>Theme Settings</title>
  <link rel="stylesheet" href="../assets/style.css">
</head>
<body>
  <?php include '../includes/header.php'; ?>
  <h2>Theme Settings</h2>
<?php if ($current): ?>
  <form method="post">
    <input type="hidden" name="name" value="<?= htmlspecialchars($edit, ENT_QUOTES, 'UTF-8'); ?>">
    <label>Name
      <input type="text" name="label" value="<?= htmlspecialchars($current['label'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
    </label>
    <label>Background Color
      <input type="color" name="bg" value="<?= htmlspecialchars($current['vars']['--bg'] ?? '#ffffff', ENT_QUOTES, 'UTF-8'); ?>">
    </label>
    <label>Foreground Color
      <input type="color" name="fg" value="<?= htmlspecialchars($current['vars']['--fg'] ?? '#000000', ENT_QUOTES, 'UTF-8'); ?>">
    </label>
    <label>Accent Color
      <input type="color" name="accent" value="<?= htmlspecialchars($current['vars']['--accent'] ?? '#ff71ce', ENT_QUOTES, 'UTF-8'); ?>">
    </label>
    <label>Header Font
      <input type="text" name="font_header" value="<?= htmlspecialchars($current['vars']['--font-header'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
    </label>
    <label>Body Font
      <input type="text" name="font_body" value="<?= htmlspecialchars($current['vars']['--font-body'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
    </label>
    <label>Paragraph Font
      <input type="text" name="font_paragraph" value="<?= htmlspecialchars($current['vars']['--font-paragraph'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
    </label>
    <div>
      <span>Gradient</span>
      <div class="gradient-presets">
        <?php foreach ($gradientPresets as $grad): ?>
          <button type="button" class="gradient-preset" data-gradient="<?= htmlspecialchars($grad, ENT_QUOTES, 'UTF-8'); ?>" style="background: <?= htmlspecialchars($grad, ENT_QUOTES, 'UTF-8'); ?>;"></button>
        <?php endforeach; ?>
      </div>
      <label>Custom Gradient (optional)
        <input type="text" id="gradient" name="gradient" value="<?= htmlspecialchars($current['vars']['--gradient'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
      </label>
    </div>
    <label>CTA Gradient
      <input type="text" name="cta_gradient" value="<?= htmlspecialchars($current['vars']['--cta-gradient'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
    </label>
    <label>CTA Depth (px)
      <input type="number" name="cta_depth" value="<?= htmlspecialchars(preg_replace('/[^0-9.]/', '', $current['vars']['--cta-depth'] ?? '20'), ENT_QUOTES, 'UTF-8'); ?>">
    </label>
    <label>Vap Color 1
      <input type="color" name="vap1" value="<?= htmlspecialchars($current['vars']['--vap1'] ?? '#ff71ce', ENT_QUOTES, 'UTF-8'); ?>">
    </label>
    <label>Vap Color 2
      <input type="color" name="vap2" value="<?= htmlspecialchars($current['vars']['--vap2'] ?? '#01cdfe', ENT_QUOTES, 'UTF-8'); ?>">
    </label>
    <label>Vap Color 3
      <input type="color" name="vap3" value="<?= htmlspecialchars($current['vars']['--vap3'] ?? '#05ffa1', ENT_QUOTES, 'UTF-8'); ?>">
    </label>
    <label><input type="checkbox" id="pattern_toggle" name="pattern_enabled" <?= isset($current['pattern']) ? 'checked' : ''; ?>> Enable Pattern</label>
    <div id="pattern_settings" style="display: <?= isset($current['pattern']) ? 'block' : 'none'; ?>;">
      <p class="help-text">The generated pattern is applied to the header and footer backgrounds.</p>
      <p class="help-text"><strong>How it works:</strong> frequency sets how often the wave repeats, amplitude adjusts the wave height, the polynomial coefficients warp the curve, hue rotates the color, and saturation tweaks the color intensity.</p>
      <label>Pattern Frequency
        <input type="range" min="0" max="10" step="0.1" id="pattern_freq" name="pattern_freq" value="<?= htmlspecialchars($current['pattern']['frequency'] ?? '0', ENT_QUOTES, 'UTF-8'); ?>" title="Controls how often the pattern repeats">
        <span id="pattern_freq_val"></span>
        <small>Controls how often the pattern repeats.</small>
      </label>
      <label>Pattern Amplitude
        <input type="range" min="0" max="10" step="0.1" id="pattern_amp" name="pattern_amp" value="<?= htmlspecialchars($current['pattern']['amplitude'] ?? '0', ENT_QUOTES, 'UTF-8'); ?>" title="Adjusts the height of the wave">
        <span id="pattern_amp_val"></span>
        <small>Adjusts the height of the wave.</small>
      </label>
      <label>Pattern Polynomial (comma-separated)
        <input type="text" id="pattern_poly" name="pattern_poly" value="<?= htmlspecialchars(isset($current['pattern']['poly']) ? implode(',', $current['pattern']['poly']) : '', ENT_QUOTES, 'UTF-8'); ?>" title="Comma-separated coefficients that bend the wave shape">
        <small>Comma-separated coefficients that bend the wave shape.</small>
      </label>
      <label>Pattern Hue
        <input type="range" min="0" max="360" id="pattern_hue" name="pattern_hue" value="<?= htmlspecialchars($current['pattern']['hue'] ?? '0', ENT_QUOTES, 'UTF-8'); ?>" title="Rotates the pattern's base color">
        <span id="pattern_hue_val"></span>
        <small>Rotates the pattern's base color.</small>
      </label>
      <label>Pattern Saturation
        <input type="range" min="0" max="100" id="pattern_sat" name="pattern_sat" value="<?= htmlspecialchars($current['pattern']['sat'] ?? '100', ENT_QUOTES, 'UTF-8'); ?>" title="Changes the intensity of the pattern's color">
        <span id="pattern_sat_val"></span>
        <small>Changes the intensity of the pattern's color.</small>
      </label>
    </div>
    <button type="submit">Save Theme</button>
  </form>
  <p><a class="btn" href="theme.php">Back</a></p>
<?php else: ?>
  <ul>
    <?php foreach ($themes as $name => $t): ?>
      <li><?= htmlspecialchars($t['label'], ENT_QUOTES, 'UTF-8'); ?> - <a href="?edit=<?= urlencode($name); ?>">Edit</a></li>
    <?php endforeach; ?>
  </ul>
  <p>To create a new theme, enter a unique name below:</p>
  <form method="post">
    <label>Key
      <input type="text" name="name" required>
    </label>
    <label>Display Name
      <input type="text" name="label" required>
    </label>
    <label>Background Color
      <input type="color" name="bg" value="#ffffff">
    </label>
    <label>Foreground Color
      <input type="color" name="fg" value="#000000">
    </label>
    <label>Accent Color
      <input type="color" name="accent" value="#ff71ce">
    </label>
    <label>Header Font
      <input type="text" name="font_header" value="'Share Tech Mono', monospace">
    </label>
    <label>Body Font
      <input type="text" name="font_body" value="'Share Tech Mono', monospace">
    </label>
    <label>Paragraph Font
      <input type="text" name="font_paragraph" value="'Share Tech Mono', monospace">
    </label>
    <div>
      <span>Gradient</span>
      <div class="gradient-presets">
        <?php foreach ($gradientPresets as $grad): ?>
          <button type="button" class="gradient-preset" data-gradient="<?= htmlspecialchars($grad, ENT_QUOTES, 'UTF-8'); ?>" style="background: <?= htmlspecialchars($grad, ENT_QUOTES, 'UTF-8'); ?>;"></button>
        <?php endforeach; ?>
      </div>
      <label>Custom Gradient (optional)
        <input type="text" id="gradient" name="gradient" value="linear-gradient(135deg, #ff71ce 0%, #01cdfe 100%)">
      </label>
    </div>
    <label>CTA Gradient
      <input type="text" name="cta_gradient" value="linear-gradient(45deg, var(--accent), var(--vap2), var(--vap3))">
    </label>
    <label>CTA Depth (px)
      <input type="number" name="cta_depth" value="20">
    </label>
    <button type="submit">Create Theme</button>
  </form>
<?php endif; ?>
  <p><a class="btn" href="index.php">Back to Admin Panel</a></p>
  <script src="../assets/admin-pattern.js"></script>
  <script src="../assets/admin-theme.js"></script>
  <?php include '../includes/footer.php'; ?>
</body>
</html>
