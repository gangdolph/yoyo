<?php
if (!defined('APP_BOOTSTRAPPED')) {
    define('APP_BOOTSTRAPPED', true);
    require_once __DIR__ . '/../includes/bootstrap.php';
}

require_once __DIR__ . '/../includes/require-auth.php';
require_once __DIR__ . '/../includes/theme_store.php';

ensure_admin('../dashboard.php');

$themePath = yoyo_theme_store_path();
$dataDirectory = dirname($themePath);
$directoryExists = is_dir($dataDirectory);
$directoryWritable = $directoryExists ? is_writable($dataDirectory) : is_writable(dirname($dataDirectory));
$relativePath = '/data/themes.json';

$collectionResult = yoyo_theme_store_load();
$themeCollection = $collectionResult['collection'];
$themeIssues = $collectionResult['errors'];

$collectionJson = json_encode($themeCollection, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
if ($collectionJson === false) {
    $collectionJson = '{}';
}
?>
<?php require '../includes/layout.php'; ?>
  <title>Theme Studio</title>
  <link rel="stylesheet" href="../assets/style.css">
  <style>
    .theme-admin {
      padding: var(--space-xl, 2rem);
      display: grid;
      gap: var(--space-xl, 2rem);
      max-width: 1200px;
      margin: 0 auto;
    }

    .theme-admin__grid {
      display: grid;
      gap: var(--space-xl, 2rem);
      grid-template-columns: minmax(0, 300px) minmax(0, 1fr);
      align-items: start;
    }

    @media (max-width: 1080px) {
      .theme-admin__grid {
        grid-template-columns: 1fr;
      }
    }

    .theme-admin__workspace {
      display: grid;
      gap: var(--space-xl, 2rem);
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

    .theme-admin__panel[aria-busy="true"] {
      opacity: 0.75;
      pointer-events: none;
    }

    .theme-admin__sidebar {
      position: sticky;
      top: var(--space-lg, 1.5rem);
      max-height: calc(100vh - var(--space-2xl, 3rem));
      overflow: auto;
    }

    @media (max-width: 1080px) {
      .theme-admin__sidebar {
        position: static;
        max-height: none;
      }
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

    .theme-admin__issues {
      display: grid;
      gap: 0.75rem;
    }

    .theme-admin__list {
      list-style: none;
      padding: 0;
      margin: 0;
      display: grid;
      gap: 0.5rem;
    }

    .theme-admin__list-item {
      display: grid;
      gap: 0.5rem;
      border-radius: var(--radius-md, 12px);
      border: 1px solid color-mix(in srgb, var(--color-border) 60%, transparent);
      background: color-mix(in srgb, var(--color-surface) 65%, transparent);
      padding: 0.75rem;
    }

    .theme-admin__list-item[data-active="true"] {
      border-color: color-mix(in srgb, var(--color-highlight) 65%, transparent);
      box-shadow: inset 0 0 0 1px color-mix(in srgb, var(--color-highlight) 35%, transparent);
    }

    .theme-admin__list-header {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 0.75rem;
    }

    .theme-admin__list-button {
      all: unset;
      cursor: pointer;
      font-weight: 600;
      display: flex;
      align-items: center;
      gap: 0.5rem;
      color: inherit;
    }

    .theme-admin__list-button:focus-visible {
      outline: 2px solid var(--color-highlight, #05ffa1);
      outline-offset: 2px;
    }

    .theme-admin__badge {
      display: inline-flex;
      align-items: center;
      gap: 0.35rem;
      padding: 0.15rem 0.5rem;
      border-radius: var(--radius-pill, 999px);
      font-size: 0.7rem;
      text-transform: uppercase;
      letter-spacing: 0.08em;
      background: color-mix(in srgb, var(--color-accent-soft) 60%, transparent);
    }

    .theme-admin__list-controls {
      display: flex;
      align-items: center;
      gap: 0.5rem;
      justify-content: space-between;
      flex-wrap: wrap;
    }

    .theme-admin__list-order {
      display: inline-flex;
      align-items: center;
      gap: 0.35rem;
    }

    .theme-admin__list-order button {
      border: 1px solid color-mix(in srgb, var(--color-border) 60%, transparent);
      background: color-mix(in srgb, var(--color-surface-alt) 70%, transparent);
      color: inherit;
      border-radius: var(--radius-sm, 8px);
      padding: 0.25rem 0.5rem;
      cursor: pointer;
      font-size: 0.85rem;
      line-height: 1;
    }

    .theme-admin__list-order button:hover {
      background: color-mix(in srgb, var(--color-accent-soft) 45%, transparent);
    }

    .theme-admin__list-default {
      display: inline-flex;
      align-items: center;
      gap: 0.35rem;
      font-size: 0.85rem;
    }

    .theme-admin__list-default input {
      accent-color: var(--color-accent, #ff71ce);
    }

    .theme-admin__list-actions {
      display: flex;
      justify-content: flex-end;
      gap: 0.75rem;
    }

    .theme-admin__editor-header {
      display: flex;
      justify-content: space-between;
      align-items: flex-start;
      gap: 1rem;
    }

    .theme-admin__editor-header h2 {
      margin: 0;
      font-size: 1.35rem;
    }

    .theme-admin__flags {
      display: inline-flex;
      align-items: center;
      gap: 0.5rem;
      flex-wrap: wrap;
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

    .theme-admin__textarea {
      min-height: 90px;
      resize: vertical;
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

    .theme-preview__toggle {
      display: inline-flex;
      align-items: center;
      gap: 0.5rem;
      border-radius: var(--radius-pill, 999px);
      padding: 0.25rem;
      background: color-mix(in srgb, var(--color-surface) 65%, transparent);
      border: 1px solid color-mix(in srgb, var(--color-border) 55%, transparent);
      margin-bottom: 1rem;
    }

    .theme-preview__toggle[hidden] {
      display: none;
    }

    .theme-preview__toggle button {
      border: 0;
      border-radius: var(--radius-pill, 999px);
      padding: 0.35rem 0.9rem;
      background: transparent;
      color: inherit;
      font-weight: 600;
      letter-spacing: 0.06em;
      text-transform: uppercase;
      cursor: pointer;
    }

    .theme-preview__toggle button:hover,
    .theme-preview__toggle button:focus-visible {
      background: color-mix(in srgb, var(--color-accent-soft) 40%, transparent);
      outline: none;
    }

    .theme-preview__toggle button.is-active {
      background: color-mix(in srgb, var(--color-highlight) 28%, transparent);
      color: var(--color-background, #020215);
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
  </style>
  <script type="application/json" id="theme-collection-data"><?= htmlspecialchars($collectionJson, ENT_NOQUOTES, 'UTF-8'); ?></script>
  <script type="module" src="../assets/js/theme-admin.js" defer></script>
</head>
<body>
  <?php include '../includes/header.php'; ?>
  <main class="theme-admin" data-theme-admin>
    <header>
      <h1>Theme Studio</h1>
      <p class="theme-admin__note">Manage every available skin, reorder the menu, and pick the default experience. Changes are saved to <code><?= htmlspecialchars($relativePath, ENT_QUOTES, 'UTF-8'); ?></code>, so ensure the <code>data/</code> directory is writable. If permissions block a save, your latest settings stay in this browser until you can retry.</p>
      <?php if ($directoryExists && !$directoryWritable): ?>
        <p class="theme-admin__alert">Heads up: the <code><?= htmlspecialchars($dataDirectory, ENT_QUOTES, 'UTF-8'); ?></code> directory is not currently writable. Grant write access so the theme JSON can be stored.</p>
      <?php endif; ?>
      <?php if (!empty($themeIssues)): ?>
        <div class="theme-admin__issues">
          <?php foreach ($themeIssues as $issue): ?>
            <p class="theme-admin__alert"><?= htmlspecialchars($issue, ENT_QUOTES, 'UTF-8'); ?></p>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </header>
    <div class="theme-admin__grid">
      <aside class="theme-admin__panel theme-admin__sidebar" data-theme-sidebar>
        <section class="theme-admin__section">
          <h2>Theme catalogue</h2>
          <p class="theme-admin__note">Select a theme to edit its tokens. Use the arrows to adjust ordering and pick which entry loads by default.</p>
        </section>
        <ul class="theme-admin__list" data-theme-list aria-label="Available themes"></ul>
        <div class="theme-admin__list-actions">
          <button class="btn-secondary" type="button" data-action="add-theme">Add theme</button>
        </div>
      </aside>
      <div class="theme-admin__workspace">
        <form class="theme-admin__panel" data-theme-form>
          <header class="theme-admin__editor-header">
            <div>
              <p class="theme-admin__note">Currently editing</p>
              <h2 data-active-theme-label>Theme</h2>
            </div>
            <div class="theme-admin__flags" data-active-theme-flags></div>
          </header>

          <section class="theme-admin__section">
            <h2>Theme metadata</h2>
            <label>
              <span>Display name</span>
              <input class="theme-admin__input" type="text" name="label" maxlength="80" placeholder="e.g. Neon Glass" data-meta="label">
            </label>
            <label>
              <span>Identifier</span>
              <input class="theme-admin__input" type="text" name="id" readonly data-meta="id">
            </label>
            <label>
              <span>Notes</span>
              <textarea class="theme-admin__textarea" name="description" placeholder="Optional description" data-meta="description"></textarea>
            </label>
          </section>

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
            <button class="btn" type="submit" data-action="save">Save collection</button>
            <button class="btn" type="button" data-action="generate-negative" aria-label="Generate negative theme variant">Generate negative theme</button>
            <button class="btn" type="button" data-action="revert">Revert to saved</button>
          </div>
          <output class="theme-admin__status" data-theme-status role="status" aria-live="polite"></output>
        </form>

        <section class="theme-preview" aria-live="polite">
          <div class="theme-preview__toggle" data-preview-toggle hidden role="group" aria-label="Preview theme pairing">
            <button type="button" data-preview-variant="base" aria-pressed="false">Base</button>
            <button type="button" data-preview-variant="negative" aria-pressed="false">Negative</button>
          </div>
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
    </div>
  </main>
  <?php include '../includes/footer.php'; ?>
</body>
</html>
