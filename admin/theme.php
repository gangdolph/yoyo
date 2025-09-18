<?php
require_once __DIR__ . '/../includes/auth.php';

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

function normalize_css_value(?string $value): string {
  $value = strtolower(trim((string)$value));
  $value = str_replace(["'", '"'], '', $value);
  return preg_replace('/\s+/', '', $value);
}

function detect_select_choice(?string $value, array $options, string $default = '__custom__'): string {
  if ($value === null) {
    return $default;
  }
  $normalized = normalize_css_value($value);
  foreach ($options as $key => $option) {
    if (!isset($option['value'])) {
      continue;
    }
    if (normalize_css_value($option['value']) === $normalized) {
      return (string)$key;
    }
  }
  return $default;
}

function resolve_select_value(array $options, ?string $selected, ?string $custom, string $fallback): string {
  $selected = trim((string)$selected);
  if ($selected !== '' && $selected !== '__custom__' && isset($options[$selected]['value'])) {
    return trim((string)$options[$selected]['value']);
  }
  $custom = trim((string)$custom);
  if ($custom !== '') {
    return $custom;
  }
  return trim($fallback);
}

function normalize_poly_values(array $values): string {
  if (empty($values)) {
    return '';
  }
  $parts = [];
  foreach ($values as $value) {
    if (!is_numeric($value)) {
      continue;
    }
    $float = (float)$value;
    $parts[] = rtrim(rtrim(sprintf('%.6F', $float), '0'), '.');
  }
  return implode(',', $parts);
}

function detect_poly_preset(?array $values, array $presets, ?string $stored = null): string {
  if ($stored && isset($presets[$stored])) {
    return $stored;
  }
  if (!is_array($values)) {
    return '__custom__';
  }
  if (empty($values)) {
    return isset($presets['flat']) ? 'flat' : '__custom__';
  }
  $normalized = normalize_poly_values($values);
  foreach ($presets as $key => $preset) {
    if (!isset($preset['values']) || !is_array($preset['values'])) {
      continue;
    }
    if ($normalized === normalize_poly_values($preset['values'])) {
      return $key;
    }
  }
  return '__custom__';
}

function resolve_poly_values(string $selected, string $input, array $presets): array {
  if ($selected !== '__custom__' && isset($presets[$selected]['values']) && is_array($presets[$selected]['values'])) {
    return array_map('floatval', $presets[$selected]['values']);
  }
  $polyCoefficients = array_values(array_filter(array_map('trim', explode(',', $input)), 'strlen'));
  return array_map('floatval', $polyCoefficients);
}

$themesFile = __DIR__ . '/../assets/themes.json';
$themes = [];
if (file_exists($themesFile)) {
  $json = json_decode(file_get_contents($themesFile), true);
  if (is_array($json)) {
    $themes = $json;
  }
}

$fontOptions = [
  'share-tech' => [
    'label' => 'Share Tech Mono (Monospace)',
    'value' => "'Share Tech Mono', monospace",
  ],
  'poppins' => [
    'label' => 'Poppins (Sans-serif)',
    'value' => "'Poppins', 'Helvetica Neue', sans-serif",
  ],
  'press-start' => [
    'label' => 'Press Start 2P (Pixel)',
    'value' => "'Press Start 2P', cursive",
  ],
  'roboto' => [
    'label' => 'Roboto (Sans-serif)',
    'value' => "'Roboto', 'Helvetica Neue', sans-serif",
  ],
  'space-grotesk' => [
    'label' => 'Space Grotesk (Grotesque)',
    'value' => "'Space Grotesk', sans-serif",
  ],
  'bebas' => [
    'label' => 'Bebas Neue (Display)',
    'value' => "'Bebas Neue', sans-serif",
  ],
];

$gradientPresets = [
  'vibrant' => [
    'label' => 'Vibrant Neon',
    'value' => 'linear-gradient(135deg, #ff71ce 0%, #01cdfe 100%)',
  ],
  'twilight' => [
    'label' => 'Twilight Horizon',
    'value' => 'linear-gradient(135deg, #4b1f8c 0%, #1e0059 100%)',
  ],
  'sunset' => [
    'label' => 'Sunset Drive',
    'value' => 'linear-gradient(135deg, #ff9a9e 0%, #fad0c4 100%)',
  ],
  'forest' => [
    'label' => 'Forest Canopy',
    'value' => 'linear-gradient(135deg, #2e3d2f 0%, #1b2c20 100%)',
  ],
  'midnight' => [
    'label' => 'Midnight Pulse',
    'value' => 'linear-gradient(135deg, #0f2027 0%, #2c5364 100%)',
  ],
];

$polyPresets = [
  'flat' => [
    'label' => 'Flat (no warp)',
    'values' => [],
  ],
  'gentle-arc' => [
    'label' => 'Gentle Arc (0, 6, -4)',
    'values' => [0, 6, -4],
  ],
  'peaks' => [
    'label' => 'Peaks (0, 12, -10)',
    'values' => [0, 12, -10],
  ],
  'cascade' => [
    'label' => 'Cascade (0, 8, -6, 2)',
    'values' => [0, 8, -6, 2],
  ],
];

$patternFunctionOptions = [
  'sine' => 'Sine wave',
  'cosine' => 'Cosine wave',
  'triangle' => 'Triangle wave',
  'sawtooth' => 'Sawtooth wave',
];

$wavePresets = [
  'gentle' => [
    'label' => 'Gentle Waves',
    'function' => 'sine',
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
    'function' => 'cosine',
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
    'function' => 'sine',
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
    $btnIdleFallback = $previousVars['--btn-text-idle'] ?? ($previousVars['--btn-text'] ?? '#111827');
    $gradientFallback = $previousVars['--gradient'] ?? 'linear-gradient(135deg, #ff71ce 0%, #01cdfe 100%)';
    $fontHeaderFallback = $previousVars['--font-header'] ?? "'Share Tech Mono', monospace";
    $fontBodyFallback = $previousVars['--font-body'] ?? "'Share Tech Mono', monospace";
    $fontParagraphFallback = $previousVars['--font-paragraph'] ?? "'Share Tech Mono', monospace";
    $themes[$name] = [
      'label' => $_POST['label'] ?: ucfirst($name),
      'vars' => [
        '--bg' => sanitize_color_input($_POST['bg'] ?? '', $previousVars['--bg'] ?? '#ffffff'),
        '--fg' => sanitize_color_input($_POST['fg'] ?? '', $previousVars['--fg'] ?? '#000000'),
        '--accent' => sanitize_color_input($_POST['accent'] ?? '', $previousVars['--accent'] ?? '#ff71ce'),
        '--btn-text-idle' => sanitize_color_input($_POST['btn_text_idle'] ?? '', $btnIdleFallback),
        '--btn-text' => sanitize_color_input($_POST['btn_text'] ?? '', $btnFallback),
        '--gradient' => resolve_select_value($gradientPresets, $_POST['gradient_select'] ?? '', $_POST['gradient'] ?? '', $gradientFallback),
        '--vap1' => sanitize_color_input($_POST['vap1'] ?? '', $previousVars['--vap1'] ?? '#ff71ce'),
        '--vap2' => sanitize_color_input($_POST['vap2'] ?? '', $previousVars['--vap2'] ?? '#01cdfe'),
        '--vap3' => sanitize_color_input($_POST['vap3'] ?? '', $previousVars['--vap3'] ?? '#05ffa1'),
        '--font-header' => resolve_select_value($fontOptions, $_POST['font_header_select'] ?? '', $_POST['font_header'] ?? '', $fontHeaderFallback),
        '--font-body' => resolve_select_value($fontOptions, $_POST['font_body_select'] ?? '', $_POST['font_body'] ?? '', $fontBodyFallback),
        '--font-paragraph' => resolve_select_value($fontOptions, $_POST['font_paragraph_select'] ?? '', $_POST['font_paragraph'] ?? '', $fontParagraphFallback),
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
      $polyPresetKey = $_POST['pattern_poly_select'] ?? '__custom__';
      if ($polyPresetKey !== '__custom__' && !isset($polyPresets[$polyPresetKey])) {
        $polyPresetKey = '__custom__';
      }
      $polyValues = resolve_poly_values($polyPresetKey, $polyInput, $polyPresets);
      if (empty($featureSelections['poly'])) {
        $polyValues = [];
        $polyPresetKey = '__custom__';
      }
      $patternFunction = $_POST['pattern_function'] ?? 'sine';
      if (!isset($patternFunctionOptions[$patternFunction])) {
        $patternFunction = 'sine';
      }
      $themes[$name]['pattern'] = [
        'preset' => $selectedPreset,
        'function' => $patternFunction,
        'frequency' => clamp_numeric($_POST['pattern_freq'] ?? 0, 0, 10, 0),
        'amplitude' => clamp_numeric($_POST['pattern_amp'] ?? 0, 0, 20, 0),
        'poly' => $polyValues,
        'hue' => $featureSelections['hue'] ? (int)clamp_numeric($_POST['pattern_hue'] ?? 0, 0, 360, 0) : 0,
        'sat' => $featureSelections['sat'] ? (int)clamp_numeric($_POST['pattern_sat'] ?? 100, 0, 200, 100) : 100,
        'features' => $featureSelections,
      ];
      if ($polyPresetKey !== '__custom__') {
        $themes[$name]['pattern']['polyPreset'] = $polyPresetKey;
      } else {
        unset($themes[$name]['pattern']['polyPreset']);
      }
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
$currentVars = isset($current['vars']) && is_array($current['vars']) ? $current['vars'] : [];
$currentGradientSelect = detect_select_choice($currentVars['--gradient'] ?? '', $gradientPresets);
$currentHeaderFontSelect = detect_select_choice($currentVars['--font-header'] ?? '', $fontOptions);
$currentBodyFontSelect = detect_select_choice($currentVars['--font-body'] ?? '', $fontOptions);
$currentParagraphFontSelect = detect_select_choice($currentVars['--font-paragraph'] ?? '', $fontOptions);
$currentBtnIdle = $currentVars['--btn-text-idle'] ?? ($currentVars['--btn-text'] ?? '#111827');
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
    <label>Button Text (Default State)
      <input type="color" name="btn_text_idle" value="<?= htmlspecialchars($currentBtnIdle, ENT_QUOTES, 'UTF-8'); ?>">
    </label>
    <label>Button Text Color
      <input type="color" name="btn_text" value="<?= htmlspecialchars($current['vars']['--btn-text'] ?? '#111827', ENT_QUOTES, 'UTF-8'); ?>">
    </label>
    <label>Header Font
      <div class="input-with-select">
        <select name="font_header_select" data-sync-target="font_header" data-sync-normalize="css">
          <?php foreach ($fontOptions as $key => $option): ?>
            <option value="<?= htmlspecialchars($key, ENT_QUOTES, 'UTF-8'); ?>" data-value="<?= htmlspecialchars($option['value'], ENT_QUOTES, 'UTF-8'); ?>" <?= $currentHeaderFontSelect === $key ? 'selected' : ''; ?>><?= htmlspecialchars($option['label'], ENT_QUOTES, 'UTF-8'); ?></option>
          <?php endforeach; ?>
          <option value="__custom__" <?= $currentHeaderFontSelect === '__custom__' ? 'selected' : ''; ?>>Custom…</option>
        </select>
        <input type="text" name="font_header" value="<?= htmlspecialchars($current['vars']['--font-header'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" placeholder="e.g. 'Share Tech Mono', monospace">
      </div>
    </label>
    <label>Body Font
      <div class="input-with-select">
        <select name="font_body_select" data-sync-target="font_body" data-sync-normalize="css">
          <?php foreach ($fontOptions as $key => $option): ?>
            <option value="<?= htmlspecialchars($key, ENT_QUOTES, 'UTF-8'); ?>" data-value="<?= htmlspecialchars($option['value'], ENT_QUOTES, 'UTF-8'); ?>" <?= $currentBodyFontSelect === $key ? 'selected' : ''; ?>><?= htmlspecialchars($option['label'], ENT_QUOTES, 'UTF-8'); ?></option>
          <?php endforeach; ?>
          <option value="__custom__" <?= $currentBodyFontSelect === '__custom__' ? 'selected' : ''; ?>>Custom…</option>
        </select>
        <input type="text" name="font_body" value="<?= htmlspecialchars($current['vars']['--font-body'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" placeholder="e.g. 'Roboto', sans-serif">
      </div>
    </label>
    <label>Paragraph Font
      <div class="input-with-select">
        <select name="font_paragraph_select" data-sync-target="font_paragraph" data-sync-normalize="css">
          <?php foreach ($fontOptions as $key => $option): ?>
            <option value="<?= htmlspecialchars($key, ENT_QUOTES, 'UTF-8'); ?>" data-value="<?= htmlspecialchars($option['value'], ENT_QUOTES, 'UTF-8'); ?>" <?= $currentParagraphFontSelect === $key ? 'selected' : ''; ?>><?= htmlspecialchars($option['label'], ENT_QUOTES, 'UTF-8'); ?></option>
          <?php endforeach; ?>
          <option value="__custom__" <?= $currentParagraphFontSelect === '__custom__' ? 'selected' : ''; ?>>Custom…</option>
        </select>
        <input type="text" name="font_paragraph" value="<?= htmlspecialchars($current['vars']['--font-paragraph'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" placeholder="e.g. 'Space Grotesk', sans-serif">
      </div>
    </label>
    <div>
      <span>Gradient</span>
      <label class="select-inline">Preset
        <select id="gradient_select" name="gradient_select" data-sync-target="gradient" data-sync-normalize="css">
          <?php foreach ($gradientPresets as $key => $gradient): ?>
            <option value="<?= htmlspecialchars($key, ENT_QUOTES, 'UTF-8'); ?>" data-value="<?= htmlspecialchars($gradient['value'], ENT_QUOTES, 'UTF-8'); ?>" <?= $currentGradientSelect === $key ? 'selected' : ''; ?>><?= htmlspecialchars($gradient['label'], ENT_QUOTES, 'UTF-8'); ?></option>
          <?php endforeach; ?>
          <option value="__custom__" <?= $currentGradientSelect === '__custom__' ? 'selected' : ''; ?>>Custom…</option>
        </select>
      </label>
      <div class="gradient-presets">
        <?php foreach ($gradientPresets as $gradient): ?>
          <button type="button" class="gradient-preset" data-gradient="<?= htmlspecialchars($gradient['value'], ENT_QUOTES, 'UTF-8'); ?>" style="background: <?= htmlspecialchars($gradient['value'], ENT_QUOTES, 'UTF-8'); ?>;" title="<?= htmlspecialchars($gradient['label'], ENT_QUOTES, 'UTF-8'); ?>"></button>
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
      $currentPatternFunction = $currentPattern && isset($currentPattern['function'], $patternFunctionOptions[$currentPattern['function']]) ? $currentPattern['function'] : 'sine';
      $currentPolyPreset = $currentPattern ? detect_poly_preset($currentPattern['poly'] ?? [], $polyPresets, $currentPattern['polyPreset'] ?? null) : 'flat';
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
      <label>Wave Function
        <select id="pattern_function" name="pattern_function">
          <?php foreach ($patternFunctionOptions as $key => $label): ?>
            <option value="<?= htmlspecialchars($key, ENT_QUOTES, 'UTF-8'); ?>" <?= $currentPatternFunction === $key ? 'selected' : ''; ?>><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?></option>
          <?php endforeach; ?>
        </select>
        <small>Choose the base waveform used before extra effects.</small>
      </label>
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
        <select id="pattern_poly_select" name="pattern_poly_select" data-sync-target="pattern_poly" data-sync-normalize="numbers" <?= empty($currentFeatures['poly']) ? 'disabled' : ''; ?>>
          <?php foreach ($polyPresets as $key => $polyPreset): ?>
            <option value="<?= htmlspecialchars($key, ENT_QUOTES, 'UTF-8'); ?>" data-value="<?= htmlspecialchars(implode(',', $polyPreset['values']), ENT_QUOTES, 'UTF-8'); ?>" <?= $currentPolyPreset === $key ? 'selected' : ''; ?>><?= htmlspecialchars($polyPreset['label'], ENT_QUOTES, 'UTF-8'); ?></option>
          <?php endforeach; ?>
          <option value="__custom__" <?= $currentPolyPreset === '__custom__' ? 'selected' : ''; ?>>Custom…</option>
        </select>
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
    <label>Button Text (Default State)
      <input type="color" name="btn_text_idle" value="#111827">
    </label>
    <label>Button Text Color
      <input type="color" name="btn_text" value="#111827">
    </label>
    <label>Header Font
      <div class="input-with-select">
        <select name="font_header_select" data-sync-target="font_header" data-sync-normalize="css">
          <?php foreach ($fontOptions as $key => $option): ?>
            <option value="<?= htmlspecialchars($key, ENT_QUOTES, 'UTF-8'); ?>" data-value="<?= htmlspecialchars($option['value'], ENT_QUOTES, 'UTF-8'); ?>" <?= $key === 'share-tech' ? 'selected' : ''; ?>><?= htmlspecialchars($option['label'], ENT_QUOTES, 'UTF-8'); ?></option>
          <?php endforeach; ?>
          <option value="__custom__">Custom…</option>
        </select>
        <input type="text" name="font_header" value="'Share Tech Mono', monospace" placeholder="e.g. 'Share Tech Mono', monospace">
      </div>
    </label>
    <label>Body Font
      <div class="input-with-select">
        <select name="font_body_select" data-sync-target="font_body" data-sync-normalize="css">
          <?php foreach ($fontOptions as $key => $option): ?>
            <option value="<?= htmlspecialchars($key, ENT_QUOTES, 'UTF-8'); ?>" data-value="<?= htmlspecialchars($option['value'], ENT_QUOTES, 'UTF-8'); ?>" <?= $key === 'share-tech' ? 'selected' : ''; ?>><?= htmlspecialchars($option['label'], ENT_QUOTES, 'UTF-8'); ?></option>
          <?php endforeach; ?>
          <option value="__custom__">Custom…</option>
        </select>
        <input type="text" name="font_body" value="'Share Tech Mono', monospace" placeholder="e.g. 'Roboto', sans-serif">
      </div>
    </label>
    <label>Paragraph Font
      <div class="input-with-select">
        <select name="font_paragraph_select" data-sync-target="font_paragraph" data-sync-normalize="css">
          <?php foreach ($fontOptions as $key => $option): ?>
            <option value="<?= htmlspecialchars($key, ENT_QUOTES, 'UTF-8'); ?>" data-value="<?= htmlspecialchars($option['value'], ENT_QUOTES, 'UTF-8'); ?>" <?= $key === 'share-tech' ? 'selected' : ''; ?>><?= htmlspecialchars($option['label'], ENT_QUOTES, 'UTF-8'); ?></option>
          <?php endforeach; ?>
          <option value="__custom__">Custom…</option>
        </select>
        <input type="text" name="font_paragraph" value="'Share Tech Mono', monospace" placeholder="e.g. 'Space Grotesk', sans-serif">
      </div>
    </label>
    <div>
      <span>Gradient</span>
      <label class="select-inline">Preset
        <select id="gradient_select" name="gradient_select" data-sync-target="gradient" data-sync-normalize="css">
          <?php foreach ($gradientPresets as $key => $gradient): ?>
            <option value="<?= htmlspecialchars($key, ENT_QUOTES, 'UTF-8'); ?>" data-value="<?= htmlspecialchars($gradient['value'], ENT_QUOTES, 'UTF-8'); ?>" <?= $key === 'vibrant' ? 'selected' : ''; ?>><?= htmlspecialchars($gradient['label'], ENT_QUOTES, 'UTF-8'); ?></option>
          <?php endforeach; ?>
          <option value="__custom__">Custom…</option>
        </select>
      </label>
      <div class="gradient-presets">
        <?php foreach ($gradientPresets as $gradient): ?>
          <button type="button" class="gradient-preset" data-gradient="<?= htmlspecialchars($gradient['value'], ENT_QUOTES, 'UTF-8'); ?>" style="background: <?= htmlspecialchars($gradient['value'], ENT_QUOTES, 'UTF-8'); ?>;" title="<?= htmlspecialchars($gradient['label'], ENT_QUOTES, 'UTF-8'); ?>"></button>
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
