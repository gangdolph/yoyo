<?php
require '../includes/auth.php';

if (!$_SESSION['is_admin']) {
  header("Location: ../dashboard.php");
  exit;
}

function sanitize_color_input(?string $value, string $fallback): string {
  $value = trim((string)$value);
  if ($value === '') {
    return $fallback;
  }
  if (preg_match('/^#([0-9a-f]{3}|[0-9a-f]{4}|[0-9a-f]{6}|[0-9a-f]{8})$/i', $value)) {
    return strtolower($value);
  }
  return $fallback;
}

function clamp_numeric($value, float $min, float $max, float $default = 0.0): float {
  if (!is_numeric($value)) {
    return $default;
  }
  $value = (float)$value;
  if ($value < $min) {
    return $min;
  }
  if ($value > $max) {
    return $max;
  }
  return $value;
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

$wavePresets = [
  'gentle' => [
    'label' => 'Gentle Waves',
    'frequency' => 2.5,
    'amplitude' => 6,
    'poly' => [0],
    'hue' => 0,
    'sat' => 85,
    'features' => [
      'poly' => false,
      'hue' => false,
      'sat' => true,
    ],
  ],
  'balanced' => [
    'label' => 'Balanced Flow',
    'frequency' => 4,
    'amplitude' => 10,
    'poly' => [0, 6, -4],
    'hue' => 320,
    'sat' => 110,
    'features' => [
      'poly' => true,
      'hue' => true,
      'sat' => true,
    ],
  ],
  'dramatic' => [
    'label' => 'Dramatic Peaks',
    'frequency' => 6,
    'amplitude' => 16,
    'poly' => [0, 12, -10],
    'hue' => 280,
    'sat' => 130,
    'features' => [
      'poly' => true,
      'hue' => true,
      'sat' => true,
    ],
  ],
];

$patternFeatureLabels = [
  'poly' => 'Curve warp',
  'hue' => 'Hue shift',
  'sat' => 'Saturation boost',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $name = strtolower(preg_replace('/[^a-z0-9_-]/i', '', $_POST['name'] ?? ''));
  if ($name) {
    $previous = $themes[$name] ?? null;
    $previousVars = isset($previous['vars']) && is_array($previous['vars']) ? $previous['vars'] : [];
    $btnFallback = $previousVars['--btn-text'] ?? '#111827';
    $themes[$name] = [
      'label' => $_POST['label'] ?: ucfirst($name),
      'vars' => [
        '--bg' => sanitize_color_input($_POST['bg'] ?? '', $previousVars['--bg'] ?? '#ffffff'),
        '--fg' => sanitize_color_input($_POST['fg'] ?? '', $previousVars['--fg'] ?? '#000000'),
        '--accent' => sanitize_color_input($_POST['accent'] ?? '', $previousVars['--accent'] ?? '#ff71ce'),
        '--btn-text' => sanitize_color_input($_POST['btn_text'] ?? '', $btnFallback),
        '--gradient' => trim($_POST['gradient'] ?? 'linear-gradient(135deg, #ff71ce 0%, #01cdfe 100%)'),
        '--vap1' => sanitize_color_input($_POST['vap1'] ?? '', $previousVars['--vap1'] ?? '#ff71ce'),
        '--vap2' => sanitize_color_input($_POST['vap2'] ?? '', $previousVars['--vap2'] ?? '#01cdfe'),
        '--vap3' => sanitize_color_input($_POST['vap3'] ?? '', $previousVars['--vap3'] ?? '#05ffa1'),
        '--font-header' => trim($_POST['font_header'] ?? "'Share Tech Mono', monospace"),
        '--font-body' => trim($_POST['font_body'] ?? "'Share Tech Mono', monospace"),
        '--font-paragraph' => trim($_POST['font_paragraph'] ?? "'Share Tech Mono', monospace"),
        '--cta-gradient' => trim($_POST['cta_gradient'] ?? 'linear-gradient(45deg, var(--accent), var(--vap2), var(--vap3))'),
        '--cta-depth' => clamp_numeric($_POST['cta_depth'] ?? 20, 0, 60, 20) . 'px',
      ],
    ];
    if (!empty($_POST['pattern_enabled'])) {
      $selectedPreset = $_POST['pattern_preset'] ?? 'custom';
      if (!isset($wavePresets[$selectedPreset])) {
        $selectedPreset = 'custom';
      }
      $featureSelections = [];
      $rawFeatures = $_POST['pattern_features'] ?? [];
      if (!is_array($rawFeatures)) {
        $rawFeatures = [];
      }
      foreach ($patternFeatureLabels as $featureKey => $_label) {
        $featureSelections[$featureKey] = in_array($featureKey, $rawFeatures, true);
      }
      $polyInput = $_POST['pattern_poly'] ?? '';
      $polyCoefficients = array_values(array_filter(array_map('trim', explode(',', $polyInput)), 'strlen'));
      $polyValues = array_map('floatval', $polyCoefficients);
      if (empty($featureSelections['poly'])) {
        $polyValues = [];
      }
      $themes[$name]['pattern'] = [
        'preset' => $selectedPreset,
        'frequency' => clamp_numeric($_POST['pattern_freq'] ?? 0, 0, 10, 0),
        'amplitude' => clamp_numeric($_POST['pattern_amp'] ?? 0, 0, 20, 0),
        'poly' => $polyValues,
        'hue' => $featureSelections['hue'] ? (int)clamp_numeric($_POST['pattern_hue'] ?? 0, 0, 360, 0) : 0,
        'sat' => $featureSelections['sat'] ? (int)clamp_numeric($_POST['pattern_sat'] ?? 100, 0, 200, 100) : 100,
        'features' => $featureSelections,
      ];
    } else {
      unset($themes[$name]['pattern']);
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
    <label>Button Text Color
      <input type="color" name="btn_text" value="<?= htmlspecialchars($current['vars']['--btn-text'] ?? '#111827', ENT_QUOTES, 'UTF-8'); ?>">
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
    <?php
      $currentPattern = isset($current['pattern']) && is_array($current['pattern']) ? $current['pattern'] : null;
      $currentPreset = 'custom';
      if ($currentPattern && !empty($currentPattern['preset']) && isset($wavePresets[$currentPattern['preset']])) {
        $currentPreset = $currentPattern['preset'];
      }
      $currentFeatures = [];
      foreach ($patternFeatureLabels as $featureKey => $_label) {
        if ($currentPattern && isset($currentPattern['features'][$featureKey])) {
          $currentFeatures[$featureKey] = (bool)$currentPattern['features'][$featureKey];
        } elseif ($currentPattern) {
          if ($featureKey === 'poly') {
            $currentFeatures[$featureKey] = !empty($currentPattern['poly']);
          } elseif ($featureKey === 'hue') {
            $currentFeatures[$featureKey] = !empty($currentPattern['hue']);
          } elseif ($featureKey === 'sat') {
            $currentFeatures[$featureKey] = isset($currentPattern['sat']) ? (int)$currentPattern['sat'] !== 100 : false;
          } else {
            $currentFeatures[$featureKey] = false;
          }
        } else {
          $currentFeatures[$featureKey] = false;
        }
      }
      $patternPresetsJson = htmlspecialchars(json_encode($wavePresets, JSON_UNESCAPED_SLASHES), ENT_QUOTES, 'UTF-8');
    ?>
    <label><input type="checkbox" id="pattern_toggle" name="pattern_enabled" <?= $currentPattern ? 'checked' : ''; ?>> Enable Pattern</label>
    <div id="pattern_settings" data-presets="<?= $patternPresetsJson; ?>" data-current-preset="<?= htmlspecialchars($currentPreset, ENT_QUOTES, 'UTF-8'); ?>" style="display: <?= $currentPattern ? 'block' : 'none'; ?>;">
      <p class="help-text">The generated pattern is applied to the header and footer backgrounds.</p>
      <fieldset class="pattern-presets">
        <legend>Wave Preset</legend>
        <?php foreach ($wavePresets as $presetKey => $preset): ?>
          <label class="pattern-preset">
            <input type="radio" name="pattern_preset" value="<?= htmlspecialchars($presetKey, ENT_QUOTES, 'UTF-8'); ?>" data-settings='<?= htmlspecialchars(json_encode($preset, JSON_UNESCAPED_SLASHES), ENT_QUOTES, 'UTF-8'); ?>' <?= $currentPreset === $presetKey ? 'checked' : ''; ?>>
            <?= htmlspecialchars($preset['label'], ENT_QUOTES, 'UTF-8'); ?>
          </label>
        <?php endforeach; ?>
        <label class="pattern-preset">
          <input type="radio" name="pattern_preset" value="custom" <?= $currentPreset === 'custom' ? 'checked' : ''; ?>> Custom
        </label>
      </fieldset>
      <div class="pattern-features">
        <?php foreach ($patternFeatureLabels as $featureKey => $featureLabel): ?>
          <label class="pattern-feature">
            <input type="checkbox" name="pattern_features[]" value="<?= htmlspecialchars($featureKey, ENT_QUOTES, 'UTF-8'); ?>" <?= !empty($currentFeatures[$featureKey]) ? 'checked' : ''; ?>>
            <?= htmlspecialchars($featureLabel, ENT_QUOTES, 'UTF-8'); ?>
          </label>
        <?php endforeach; ?>
      </div>
      <p class="help-text"><strong>How it works:</strong> frequency sets how often the wave repeats, amplitude adjusts the wave height, the polynomial coefficients warp the curve when enabled, hue rotates the color, and saturation tweaks the color intensity.</p>
      <label>Pattern Frequency
        <input type="range" min="0" max="10" step="0.1" id="pattern_freq" name="pattern_freq" value="<?= htmlspecialchars($current['pattern']['frequency'] ?? '0', ENT_QUOTES, 'UTF-8'); ?>" title="Controls how often the pattern repeats">
        <span id="pattern_freq_val"></span>
        <small>Controls how often the pattern repeats.</small>
      </label>
      <label>Pattern Amplitude
        <input type="range" min="0" max="20" step="0.5" id="pattern_amp" name="pattern_amp" value="<?= htmlspecialchars($current['pattern']['amplitude'] ?? '0', ENT_QUOTES, 'UTF-8'); ?>" title="Adjusts the height of the wave">
        <span id="pattern_amp_val"></span>
        <small>Adjusts the height of the wave.</small>
      </label>
      <label data-feature="poly">Pattern Polynomial (comma-separated)
        <input type="text" id="pattern_poly" name="pattern_poly" value="<?= htmlspecialchars(isset($current['pattern']['poly']) ? implode(',', $current['pattern']['poly']) : '', ENT_QUOTES, 'UTF-8'); ?>" title="Comma-separated coefficients that bend the wave shape" <?= empty($currentFeatures['poly']) ? 'disabled' : ''; ?>>
        <small>Comma-separated coefficients that bend the wave shape.</small>
      </label>
      <label data-feature="hue">Pattern Hue
        <input type="range" min="0" max="360" id="pattern_hue" name="pattern_hue" value="<?= htmlspecialchars($current['pattern']['hue'] ?? '0', ENT_QUOTES, 'UTF-8'); ?>" title="Rotates the pattern's base color" <?= empty($currentFeatures['hue']) ? 'disabled' : ''; ?>>
        <span id="pattern_hue_val"></span>
        <small>Rotates the pattern's base color.</small>
      </label>
      <label data-feature="sat">Pattern Saturation
        <input type="range" min="0" max="200" id="pattern_sat" name="pattern_sat" value="<?= htmlspecialchars($current['pattern']['sat'] ?? '100', ENT_QUOTES, 'UTF-8'); ?>" title="Changes the intensity of the pattern's color" <?= empty($currentFeatures['sat']) ? 'disabled' : ''; ?>>
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
      <?php if (!is_array($t) || empty($t['label'])) { continue; } ?>
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
    <label>Button Text Color
      <input type="color" name="btn_text" value="#111827">
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
