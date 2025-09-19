<?php
require_once __DIR__ . '/../includes/auth.php';

if (empty($_SESSION['is_admin'])) {
    header('Location: ../dashboard.php');
    exit;
}

$themePath = dirname(__DIR__) . '/data/theme.json';
$dataDirectory = dirname($themePath);
$directoryExists = is_dir($dataDirectory);
$directoryWritable = $directoryExists ? is_writable($dataDirectory) : is_writable(dirname($dataDirectory));
$relativePath = '/data/theme.json';
?>
<?php require '../includes/layout.php'; ?>
  <title>Theme Studio</title>
  <link rel="stylesheet" href="../assets/style.css">
  <style>
    .theme-admin {
      padding: var(--space-xl, 2rem);
      display: grid;
      gap: var(--space-xl, 2rem);
      max-width: 1100px;
      margin: 0 auto;
    }

    .theme-admin__grid {
      display: grid;
      gap: var(--space-xl, 2rem);
      grid-template-columns: minmax(0, 360px) minmax(0, 1fr);
      align-items: flex-start;
    }

    @media (max-width: 960px) {
      .theme-admin__grid {
        grid-template-columns: 1fr;
      }
    }

    .theme-admin__panel {
      display: grid;
      gap: 1.5rem;
      background: color-mix(in srgb, var(--color-card-bg) 75%, transparent);
      border: 1px solid color-mix(in srgb, var(--color-border) 60%, transparent);
      border-radius: var(--radius-lg, 18px);
      box-shadow: var(--shadow-soft, 0 12px 32px -20px rgba(0, 0, 0, 0.85));
      padding: var(--space-lg, 1.5rem);
    }

    .theme-admin__section {
      display: grid;
      gap: 0.75rem;
    }

    .theme-admin__section > h2 {
      margin: 0;
      font-size: 1.05rem;
      letter-spacing: 0.06em;
      text-transform: uppercase;
    }

    .theme-admin__note {
      margin: 0;
      font-size: 0.9rem;
      color: color-mix(in srgb, var(--color-text) 70%, transparent);
    }

    .theme-admin__alert {
      border-radius: var(--radius-md, 12px);
      padding: 0.75rem 1rem;
      font-size: 0.9rem;
      border: 1px solid color-mix(in srgb, var(--color-highlight) 40%, transparent);
      background: color-mix(in srgb, var(--color-highlight) 12%, transparent);
      color: var(--color-text);
    }

    .theme-admin__radio-group,
    .theme-admin__checkbox-group {
      display: grid;
      gap: 0.5rem;
    }

    .theme-admin__radio,
    .theme-admin__checkbox {
      display: flex;
      align-items: center;
      gap: 0.5rem;
      padding: 0.5rem 0.75rem;
      border-radius: var(--radius-md, 12px);
      border: 1px solid color-mix(in srgb, var(--color-border) 60%, transparent);
      background: color-mix(in srgb, var(--color-surface) 65%, transparent);
    }

    .theme-admin__radio input,
    .theme-admin__checkbox input {
      accent-color: var(--color-accent, #ff71ce);
    }

    .theme-admin__input,
    .theme-admin__select,
    .theme-admin__textarea {
      width: 100%;
      padding: 0.6rem 0.75rem;
      border-radius: var(--radius-sm, 8px);
      border: 1px solid color-mix(in srgb, var(--color-border) 60%, transparent);
      background: color-mix(in srgb, var(--color-surface-alt) 75%, transparent);
      color: var(--color-text);
      font-size: 1rem;
    }

    .theme-admin__slider {
      display: grid;
      gap: 0.35rem;
    }

    .theme-admin__slider-label {
      display: flex;
      justify-content: space-between;
      align-items: center;
      font-size: 0.9rem;
      text-transform: uppercase;
      letter-spacing: 0.08em;
    }

    .theme-admin__slider input[type="range"] {
      width: 100%;
    }

    .theme-admin__value {
      font-variant-numeric: tabular-nums;
      margin-left: 0.35rem;
    }

    .theme-admin__actions {
      display: flex;
      flex-wrap: wrap;
      gap: 0.75rem;
      align-items: center;
    }

    .theme-admin__actions .btn,
    .theme-admin__actions .button,
    .theme-admin__actions button {
      flex: 0 0 auto;
    }

    .theme-admin__status {
      min-height: 1.2rem;
      font-size: 0.9rem;
      color: color-mix(in srgb, var(--color-text) 70%, transparent);
    }

    .theme-admin__status[data-state="success"] {
      color: var(--color-highlight, #05ffa1);
    }

    .theme-admin__status[data-state="warning"] {
      color: #ffcf5c;
    }

    .theme-admin__status[data-state="error"] {
      color: #ff7b7b;
    }

    .theme-admin__status[data-state="pending"] {
      color: var(--color-accent, #ff71ce);
    }

    .theme-preview {
      display: grid;
      gap: var(--space-lg, 1.5rem);
    }

    .theme-preview__band {
      display: block;
      text-align: center;
    }

    .theme-preview__card {
      display: grid;
      gap: var(--space-sm, 0.75rem);
    }

    .theme-preview__meta {
      font-size: 0.85rem;
      color: color-mix(in srgb, var(--color-text) 80%, transparent);
      margin: 0;
    }

    .theme-preview__chips {
      display: flex;
      flex-wrap: wrap;
      gap: 0.35rem;
    }

    .theme-preview__chip {
      padding: 0.25rem 0.5rem;
      border-radius: var(--radius-pill, 999px);
      background: color-mix(in srgb, var(--color-accent-soft) 45%, transparent);
      font-size: 0.75rem;
      text-transform: uppercase;
      letter-spacing: 0.08em;
    }

    .theme-preview__cta {
      justify-self: flex-start;
    }

    .theme-admin__panel[aria-busy="true"] {
      opacity: 0.75;
      pointer-events: none;
    }
  </style>
  <script type="module" src="../assets/js/theme-admin.js" defer></script>
</head>
<body>
  <?php include '../includes/header.php'; ?>
  <main class="theme-admin">
    <header>
      <h1>Theme Studio</h1>
      <p class="theme-admin__note">Adjust the live "alien glass" theme tokens and preview the results instantly. Changes are saved to <code><?= htmlspecialchars($relativePath, ENT_QUOTES, 'UTF-8'); ?></code> so be sure the <code>data/</code> directory is writable. If permissions block a save, your latest settings stay in this browser until you can retry.</p>
      <?php if ($directoryExists && !$directoryWritable): ?>
        <p class="theme-admin__alert">Heads up: the <code><?= htmlspecialchars($dataDirectory, ENT_QUOTES, 'UTF-8'); ?></code> directory is not currently writable. Grant write access so the theme JSON can be stored.</p>
      <?php endif; ?>
    </header>
    <div class="theme-admin__grid">
      <form class="theme-admin__panel" data-theme-form>
        <section class="theme-admin__section">
          <h2>Base style</h2>
          <div class="theme-admin__radio-group" role="radiogroup" aria-label="Theme style">
            <label class="theme-admin__radio" for="style-legacy">
              <input type="radio" id="style-legacy" name="style" value="legacy-vaporwave" data-field="style">
              <span>Legacy Vaporwave</span>
            </label>
            <label class="theme-admin__radio" for="style-neo">
              <input type="radio" id="style-neo" name="style" value="neo-noir" data-field="style">
              <span>Neo Noir</span>
            </label>
            <label class="theme-admin__radio" for="style-sunrise">
              <input type="radio" id="style-sunrise" name="style" value="synth-sunrise" data-field="style">
              <span>Synth Sunrise</span>
            </label>
          </div>
        </section>

        <section class="theme-admin__section">
          <h2>Brand voice</h2>
          <label>
            <span>Hero tagline</span>
            <input class="theme-admin__input" type="text" name="tagline" maxlength="120" placeholder="ex: Trade under neon skies" data-field="tagline">
          </label>
          <label>
            <span>Interface density</span>
            <select class="theme-admin__select" name="density" data-field="density">
              <option value="comfortable">Comfortable</option>
              <option value="cozy">Cozy</option>
              <option value="compact">Compact</option>
            </select>
          </label>
          <fieldset class="theme-admin__section">
            <legend>Pattern overlays</legend>
            <div class="theme-admin__checkbox-group">
              <label class="theme-admin__checkbox" for="pattern-scanlines">
                <input type="checkbox" id="pattern-scanlines" value="scanlines" data-field="patterns">
                <span>Scanlines</span>
              </label>
              <label class="theme-admin__checkbox" for="pattern-grid">
                <input type="checkbox" id="pattern-grid" value="grid" data-field="patterns">
                <span>Grids</span>
              </label>
              <label class="theme-admin__checkbox" for="pattern-stars">
                <input type="checkbox" id="pattern-stars" value="stars" data-field="patterns">
                <span>Starlight</span>
              </label>
            </div>
          </fieldset>
        </section>

        <section class="theme-admin__section">
          <h2>Color orbit</h2>
          <div class="theme-admin__slider">
            <div class="theme-admin__slider-label">
              <label for="control-hue">Band hue</label>
              <span class="theme-admin__value" data-output="hue"></span>
            </div>
            <input type="range" id="control-hue" min="0" max="360" step="1" data-field="hue">
          </div>
          <div class="theme-admin__slider">
            <div class="theme-admin__slider-label">
              <label for="control-saturation">Saturation</label>
              <span class="theme-admin__value" data-output="saturation"></span>
            </div>
            <input type="range" id="control-saturation" min="0" max="100" step="1" data-field="saturation">
          </div>
          <div class="theme-admin__slider">
            <div class="theme-admin__slider-label">
              <label for="control-lightness">Lightness</label>
              <span class="theme-admin__value" data-output="lightness"></span>
            </div>
            <input type="range" id="control-lightness" min="0" max="100" step="1" data-field="lightness">
          </div>
          <div class="theme-admin__slider">
            <div class="theme-admin__slider-label">
              <label for="control-accent-a">Accent hue A</label>
              <span class="theme-admin__value" data-output="accentHueA"></span>
            </div>
            <input type="range" id="control-accent-a" min="0" max="360" step="1" data-field="accentHueA">
          </div>
          <div class="theme-admin__slider">
            <div class="theme-admin__slider-label">
              <label for="control-accent-b">Accent hue B</label>
              <span class="theme-admin__value" data-output="accentHueB"></span>
            </div>
            <input type="range" id="control-accent-b" min="0" max="360" step="1" data-field="accentHueB">
          </div>
        </section>

        <section class="theme-admin__section">
          <h2>Glass &amp; glow</h2>
          <div class="theme-admin__slider">
            <div class="theme-admin__slider-label">
              <label for="control-glass-opacity">Glass opacity</label>
              <span class="theme-admin__value" data-output="glassOpacity"></span>
            </div>
            <input type="range" id="control-glass-opacity" min="0.2" max="0.95" step="0.01" data-field="glassOpacity">
          </div>
          <div class="theme-admin__slider">
            <div class="theme-admin__slider-label">
              <label for="control-glass-blur">Glass blur</label>
              <span class="theme-admin__value" data-output="glassBlur"></span>
            </div>
            <input type="range" id="control-glass-blur" min="0" max="48" step="1" data-field="glassBlur">
          </div>
          <div class="theme-admin__slider">
            <div class="theme-admin__slider-label">
              <label for="control-glow">Glow bloom</label>
              <span class="theme-admin__value" data-output="glow"></span>
            </div>
            <input type="range" id="control-glow" min="0" max="72" step="1" data-field="glow">
          </div>
          <div class="theme-admin__slider">
            <div class="theme-admin__slider-label">
              <label for="control-hover-lift">Hover lift</label>
              <span class="theme-admin__value" data-output="hoverLift"></span>
            </div>
            <input type="range" id="control-hover-lift" min="0" max="12" step="0.5" data-field="hoverLift">
          </div>
        </section>

        <section class="theme-admin__section">
          <h2>Detail refinements</h2>
          <div class="theme-admin__slider">
            <div class="theme-admin__slider-label">
              <label for="control-corner">Corner radius</label>
              <span class="theme-admin__value" data-output="cornerRadius"></span>
            </div>
            <input type="range" id="control-corner" min="8" max="48" step="1" data-field="cornerRadius">
          </div>
          <div class="theme-admin__slider">
            <div class="theme-admin__slider-label">
              <label for="control-border-alpha">Border alpha</label>
              <span class="theme-admin__value" data-output="borderAlpha"></span>
            </div>
            <input type="range" id="control-border-alpha" min="0" max="1" step="0.01" data-field="borderAlpha">
          </div>
          <div class="theme-admin__slider">
            <div class="theme-admin__slider-label">
              <label for="control-noise">Noise grain</label>
              <span class="theme-admin__value" data-output="noise"></span>
            </div>
            <input type="range" id="control-noise" min="0" max="0.5" step="0.01" data-field="noise">
          </div>
        </section>

        <div class="theme-admin__actions">
          <button class="btn" type="submit" data-action="save">Save theme</button>
          <button class="btn" type="button" data-action="revert">Revert to saved</button>
        </div>
        <output class="theme-admin__status" data-theme-status role="status" aria-live="polite"></output>
      </form>

      <section class="theme-preview" aria-live="polite">
        <div class="theme-preview__band band-strip theme-preview__band" data-preview-band>
          <span>Style:
            <strong data-preview-style>Legacy Vaporwave</strong>
          </span>
          <div data-preview-pattern-summary></div>
        </div>
        <article class="theme-preview__card card-neo">
          <p class="theme-preview__meta">Density: <span data-preview-density>Comfortable</span> · Glass: <span data-preview-glass></span></p>
          <h3 data-preview-tagline>Trade under neon skies</h3>
          <div class="theme-preview__chips" data-preview-patterns></div>
          <button type="button" class="cta-glass theme-preview__cta">Call to action</button>
        </article>
      </section>
    </div>
  </main>
  <?php include '../includes/footer.php'; ?>
</body>
</html>
