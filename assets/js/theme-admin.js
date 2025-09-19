const STYLE_LABELS = {
  'legacy-vaporwave': 'Legacy Vaporwave',
  'neo-noir': 'Neo Noir',
  'synth-sunrise': 'Synth Sunrise'
};

const PATTERN_LABELS = {
  scanlines: 'Scanlines',
  grid: 'Grids',
  stars: 'Starlight'
};

const PATTERN_ORDER = Object.keys(PATTERN_LABELS);

const FIELD_RANGES = {
  hue: { min: 0, max: 360 },
  saturation: { min: 0, max: 100 },
  lightness: { min: 0, max: 100 },
  accentHueA: { min: 0, max: 360 },
  accentHueB: { min: 0, max: 360 },
  glassOpacity: { min: 0.2, max: 0.95, decimals: 2 },
  glassBlur: { min: 0, max: 48 },
  glow: { min: 0, max: 72 },
  hoverLift: { min: 0, max: 12, decimals: 2 },
  cornerRadius: { min: 8, max: 48 },
  borderAlpha: { min: 0, max: 1, decimals: 2 },
  noise: { min: 0, max: 0.5, decimals: 2 }
};

const OUTPUT_FORMATTERS = {
  hue: (value) => `${Math.round(value)}°`,
  saturation: (value) => `${Math.round(value)}%`,
  lightness: (value) => `${Math.round(value)}%`,
  accentHueA: (value) => `${Math.round(value)}°`,
  accentHueB: (value) => `${Math.round(value)}°`,
  glassOpacity: (value) => value.toFixed(2),
  glassBlur: (value) => `${Math.round(value)}px`,
  glow: (value) => `${Math.round(value)}px`,
  hoverLift: (value) => `${value.toFixed(1)}px`,
  cornerRadius: (value) => `${Math.round(value)}px`,
  borderAlpha: (value) => value.toFixed(2),
  noise: (value) => value.toFixed(2)
};

const DEFAULT_STATE = {
  style: 'legacy-vaporwave',
  tagline: 'Trade under neon skies',
  density: 'comfortable',
  patterns: [],
  hue: 310,
  saturation: 100,
  lightness: 60,
  accentHueA: 310,
  accentHueB: 205,
  glassOpacity: 0.45,
  glassBlur: 24,
  glow: 36,
  hoverLift: 3,
  cornerRadius: 26,
  borderAlpha: 0.18,
  noise: 0.08
};

function clamp(value, min, max) {
  return Math.min(Math.max(value, min), max);
}

function toNumber(value, fallback, range) {
  const next = Number(value);
  if (!Number.isFinite(next)) {
    return fallback;
  }
  return clamp(next, range.min, range.max);
}

function normalisePatterns(list) {
  if (!Array.isArray(list)) {
    return [];
  }
  const seen = new Set();
  const ordered = [];
  for (const key of PATTERN_ORDER) {
    for (const value of list) {
      if (value === key && !seen.has(value)) {
        seen.add(value);
        ordered.push(value);
      }
    }
  }
  return ordered;
}

function structuredCloneIfPossible(value) {
  if (typeof window.structuredClone === 'function') {
    try {
      return window.structuredClone(value);
    } catch (error) {
      console.warn('Unable to structuredClone payload, falling back to JSON clone.', error);
    }
  }
  try {
    return JSON.parse(JSON.stringify(value));
  } catch (error) {
    console.warn('Unable to deep clone payload.', error);
    return Array.isArray(value) ? value.slice() : { ...(value || {}) };
  }
}

function cloneTheme(theme) {
  if (!theme || typeof theme !== 'object') {
    return {};
  }
  return structuredCloneIfPossible(theme);
}

function isPlainObject(value) {
  return Object.prototype.toString.call(value) === '[object Object]';
}

function buildStateFromTheme(theme) {
  const state = { ...DEFAULT_STATE, patterns: [] };
  if (!isPlainObject(theme)) {
    state.patterns = [];
    return state;
  }

  const meta = isPlainObject(theme.meta) ? theme.meta : {};
  const controls = isPlainObject(meta.controls) ? meta.controls : {};

  if (typeof controls.style === 'string') {
    state.style = controls.style;
  } else if (typeof theme.name === 'string') {
    state.style = theme.name;
  }

  if (typeof meta.tagline === 'string') {
    state.tagline = meta.tagline;
  } else if (typeof controls.tagline === 'string') {
    state.tagline = controls.tagline;
  }

  if (typeof controls.density === 'string') {
    state.density = controls.density;
  } else if (typeof meta.density === 'string') {
    state.density = meta.density;
  }

  const patternSource = Array.isArray(meta.patterns)
    ? meta.patterns
    : Array.isArray(controls.patterns)
      ? controls.patterns
      : [];
  state.patterns = normalisePatterns(patternSource);

  for (const key of Object.keys(FIELD_RANGES)) {
    if (Object.prototype.hasOwnProperty.call(controls, key)) {
      state[key] = toNumber(controls[key], DEFAULT_STATE[key], FIELD_RANGES[key]);
    } else if (meta.controls && Object.prototype.hasOwnProperty.call(meta.controls, key)) {
      state[key] = toNumber(meta.controls[key], DEFAULT_STATE[key], FIELD_RANGES[key]);
    }
  }

  return state;
}

function overwriteState(target, source) {
  for (const key of Object.keys(DEFAULT_STATE)) {
    if (key === 'patterns') {
      target.patterns = Array.isArray(source.patterns) ? [...source.patterns] : [];
    } else if (Object.prototype.hasOwnProperty.call(source, key)) {
      target[key] = source[key];
    } else {
      target[key] = DEFAULT_STATE[key];
    }
  }
}

function hslString(h, s, l, alpha) {
  const hue = Math.round(clamp(h, 0, 360));
  const sat = Math.round(clamp(s, 0, 100));
  const light = Math.round(clamp(l, 0, 100));
  if (typeof alpha === 'number' && alpha >= 0 && alpha <= 1) {
    return `hsla(${hue}, ${sat}%, ${light}%, ${alpha.toFixed(2)})`;
  }
  return `hsl(${hue}, ${sat}%, ${light}%)`;
}

function formatDensity(value) {
  if (!value) {
    return '';
  }
  return value.charAt(0).toUpperCase() + value.slice(1);
}

function buildThemePayload(baseTheme, state) {
  const base = cloneTheme(baseTheme);
  base.name = state.style;

  const meta = isPlainObject(base.meta) ? { ...base.meta } : {};
  meta.tagline = state.tagline;
  meta.density = state.density;
  meta.patterns = [...state.patterns];
  meta.controls = {
    style: state.style,
    tagline: state.tagline,
    density: state.density,
    patterns: [...state.patterns],
    hue: state.hue,
    saturation: state.saturation,
    lightness: state.lightness,
    accentHueA: state.accentHueA,
    accentHueB: state.accentHueB,
    glassOpacity: state.glassOpacity,
    glassBlur: state.glassBlur,
    glow: state.glow,
    hoverLift: state.hoverLift,
    cornerRadius: state.cornerRadius,
    borderAlpha: state.borderAlpha,
    noise: state.noise
  };
  base.meta = meta;

  base.colors = { ...(base.colors || {}) };
  base.effects = { ...(base.effects || {}) };
  base.radii = { ...(base.radii || {}) };
  base.ui = { ...(base.ui || {}) };

  const bandHue = clamp(state.hue, FIELD_RANGES.hue.min, FIELD_RANGES.hue.max);
  const bandStart = hslString(bandHue, state.saturation, state.lightness);
  const bandEnd = hslString((bandHue + 42) % 360, clamp(state.saturation + 6, 0, 100), clamp(state.lightness + 12, 0, 100));
  const accentPrimary = hslString(state.accentHueA, state.saturation, clamp(state.lightness + 2, 0, 100));
  const accentSecondary = hslString(state.accentHueB, clamp(state.saturation, 0, 100), clamp(state.lightness + 8, 0, 100));
  const accentSoft = hslString(state.accentHueA, clamp(state.saturation, 0, 100), clamp(state.lightness + 20, 0, 100), 0.38);
  const highlight = hslString((state.accentHueB + 36) % 360, clamp(state.saturation + 10, 0, 100), clamp(state.lightness + 18, 0, 100));

  base.colors.accent = accentPrimary;
  base.colors.accentAlt = accentSecondary;
  base.colors.accentSoft = accentSoft;
  base.colors.highlight = highlight;
  base.colors.cta = accentPrimary;
  base.colors.gradient = `linear-gradient(135deg, ${accentPrimary} 0%, ${accentSecondary} 100%)`;
  base.colors.border = `rgba(255, 255, 255, ${state.borderAlpha.toFixed(2)})`;
  base.colors.borderStrong = `rgba(255, 255, 255, ${Math.min(1, state.borderAlpha + 0.18).toFixed(2)})`;
  base.colors.cardBorder = `rgba(255, 255, 255, ${Math.min(1, state.borderAlpha + 0.1).toFixed(2)})`;

  base.effects.bandGradient = `linear-gradient(135deg, ${bandStart} 0%, ${bandEnd} 100%)`;
  base.effects.bandOverlay = `hsla(${Math.round(bandHue)}, ${Math.round(state.saturation)}%, ${Math.max(0, Math.round(state.lightness - 24))}%, ${(state.glassOpacity + 0.15).toFixed(2)})`;
  base.effects.ctaGradient = `linear-gradient(45deg, ${accentPrimary}, ${highlight}, ${accentSecondary})`;
  base.effects.patternHue = `${Math.round(bandHue)}deg`;
  base.effects.patternSaturation = `${Math.round(state.saturation)}%`;
  base.effects.glassOpacity = state.glassOpacity.toFixed(2);
  base.effects.glassOverlayOpacity = state.glassOpacity.toFixed(2);
  base.effects.glassSheenOpacity = Math.min(0.9, state.glassOpacity + 0.2).toFixed(2);
  base.effects.glassBlur = `${Math.round(state.glassBlur)}px`;
  base.effects.glassBorder = `1px solid rgba(255, 255, 255, ${Math.min(1, state.borderAlpha + 0.05).toFixed(2)})`;
  base.effects.glowStrength = `${Math.round(state.glow)}px`;
  base.effects.noiseOpacity = state.noise.toFixed(2);
  base.effects.hoverLift = `${state.hoverLift.toFixed(2)}px`;

  base.radii.xl = `${Math.round(state.cornerRadius)}px`;
  base.radii.lg = `${Math.max(0, Math.round(state.cornerRadius) - 4)}px`;
  base.radii.md = `${Math.max(0, Math.round(state.cornerRadius) - 10)}px`;
  base.ui.density = state.density;
  base.ui.hoverLift = `${state.hoverLift.toFixed(2)}px`;

  return base;
}

function parseInitialCollection() {
  const script = document.getElementById('theme-collection-data');
  if (script && script.textContent) {
    try {
      const parsed = JSON.parse(script.textContent);
      if (isPlainObject(parsed)) {
        return parsed;
      }
    } catch (error) {
      console.warn('Unable to parse embedded theme collection payload.', error);
    }
  }

  if (isPlainObject(window.__THEME_COLLECTION__)) {
    return structuredCloneIfPossible(window.__THEME_COLLECTION__);
  }

  return {};
}

function normaliseCollection(raw) {
  const fallbackTheme = window.yoyoTheme?.getBaseTheme?.() || window.__THEME__ || {};
  const themes = [];
  const seenIds = new Set();
  const rawThemes = Array.isArray(raw?.themes) ? raw.themes : [];

  for (const entry of rawThemes) {
    if (!isPlainObject(entry)) {
      continue;
    }
    const id = typeof entry.id === 'string' && entry.id.trim().length > 0 ? entry.id.trim() : '';
    if (!id || seenIds.has(id)) {
      continue;
    }
    const label = typeof entry.label === 'string' && entry.label.trim().length > 0 ? entry.label.trim() : id;
    const description = typeof entry.description === 'string' ? entry.description.trim() : '';
    const theme = isPlainObject(entry.theme) ? cloneTheme(entry.theme) : cloneTheme(fallbackTheme);
    themes.push({ id, label, description, theme });
    seenIds.add(id);
  }

  if (themes.length === 0) {
    const baseTheme = cloneTheme(fallbackTheme);
    themes.push({
      id: 'legacy-vaporwave',
      label: 'Legacy Vaporwave',
      description: '',
      theme: baseTheme
    });
  }

  const candidateDefault = typeof raw?.defaultThemeId === 'string' ? raw.defaultThemeId.trim() : '';
  const defaultThemeId = candidateDefault && themes.some((theme) => theme.id === candidateDefault)
    ? candidateDefault
    : themes[0].id;

  const pairings = [];
  const rawPairings = Array.isArray(raw?.pairings) ? raw.pairings : [];
  const pairedBases = new Set();

  for (const entry of rawPairings) {
    if (!isPlainObject(entry)) {
      continue;
    }
    const type = entry.type === 'negative' ? 'negative' : null;
    const baseThemeId = typeof entry.baseThemeId === 'string' ? entry.baseThemeId.trim() : '';
    const variantThemeId = typeof entry.variantThemeId === 'string' ? entry.variantThemeId.trim() : '';
    if (!type || !baseThemeId || !variantThemeId) {
      continue;
    }
    if (!seenIds.has(baseThemeId) || !seenIds.has(variantThemeId)) {
      continue;
    }
    if (pairedBases.has(baseThemeId)) {
      continue;
    }
    pairings.push({
      type,
      baseThemeId,
      variantThemeId,
      label: typeof entry.label === 'string' ? entry.label.trim() : '',
      generated: Boolean(entry.generated)
    });
    pairedBases.add(baseThemeId);
  }

  return { defaultThemeId, themes, pairings };
}

function cloneCollection(collection) {
  return {
    defaultThemeId: collection.defaultThemeId,
    themes: collection.themes.map((entry) => ({
      id: entry.id,
      label: entry.label,
      description: entry.description,
      theme: cloneTheme(entry.theme)
    })),
    pairings: Array.isArray(collection.pairings)
      ? collection.pairings.map((pair) => ({ ...pair }))
      : []
  };
}

function slugify(value) {
  return value
    .toLowerCase()
    .replace(/[^a-z0-9]+/g, '-')
    .replace(/^-+|-+$/g, '')
    .replace(/-{2,}/g, '-');
}

(function initialise() {
  const yoyo = window.yoyoTheme;
  const form = document.querySelector('[data-theme-form]');
  const list = document.querySelector('[data-theme-list]');
  if (!form || !list || !yoyo) {
    return;
  }

  const metadataInputs = {
    label: form.querySelector('[data-meta="label"]'),
    id: form.querySelector('[data-meta="id"]'),
    description: form.querySelector('[data-meta="description"]')
  };
  const addButton = document.querySelector('[data-action="add-theme"]');
  const generateNegativeButton = form.querySelector('[data-action="generate-negative"]');
  const statusEl = form.querySelector('[data-theme-status]');
  const preview = {
    tagline: document.querySelector('[data-preview-tagline]'),
    style: document.querySelector('[data-preview-style]'),
    density: document.querySelector('[data-preview-density]'),
    glass: document.querySelector('[data-preview-glass]'),
    patternSummary: document.querySelector('[data-preview-pattern-summary]'),
    patternList: document.querySelector('[data-preview-patterns]')
  };
  const previewToggle = document.querySelector('[data-preview-toggle]');
  const previewToggleButtons = previewToggle ? Array.from(previewToggle.querySelectorAll('[data-preview-variant]')) : [];
  const activeLabelEl = form.querySelector('[data-active-theme-label]');
  const flagsEl = form.querySelector('[data-active-theme-flags]');
  const controls = Array.from(form.querySelectorAll('[data-field]'));
  const saveButton = form.querySelector('[data-action="save"]');
  const revertButton = form.querySelector('[data-action="revert"]');

  let collection = normaliseCollection(parseInitialCollection());
  let originalCollection = cloneCollection(collection);
  let activeThemeId = collection.defaultThemeId;
  let activeState = null;
  let hasUnsavedChanges = false;
  let isSaving = false;
  const stateCache = new Map();
  let previewMode = 'base';

  function getEntryById(themeId) {
    return collection.themes.find((theme) => theme.id === themeId) || null;
  }

  function getPairings() {
    return Array.isArray(collection.pairings) ? collection.pairings : [];
  }

  function ensurePairings() {
    if (!Array.isArray(collection.pairings)) {
      collection.pairings = [];
    }
    return collection.pairings;
  }

  function findPairForTheme(themeId) {
    if (!themeId) {
      return null;
    }
    return getPairings().find((pair) => pair && (pair.baseThemeId === themeId || pair.variantThemeId === themeId)) || null;
  }

  function removePairingsForTheme(themeId) {
    if (!Array.isArray(collection.pairings)) {
      return;
    }
    collection.pairings = collection.pairings.filter((pair) => pair && pair.baseThemeId !== themeId && pair.variantThemeId !== themeId);
  }

  function setStatus(message, tone) {
    if (!statusEl) {
      return;
    }
    statusEl.textContent = message || '';
    if (tone) {
      statusEl.dataset.state = tone;
    } else {
      statusEl.removeAttribute('data-state');
    }
  }

  function setBusy(active) {
    if (active) {
      form.setAttribute('aria-busy', 'true');
    } else {
      form.removeAttribute('aria-busy');
    }
    if (saveButton) {
      saveButton.disabled = active;
    }
    if (revertButton) {
      revertButton.disabled = active;
    }
  }

  function markDirty(message) {
    hasUnsavedChanges = true;
    setStatus(message || 'Draft updated — remember to save.', 'pending');
  }

  function clearDirty(message, tone) {
    hasUnsavedChanges = false;
    setStatus(message || '', tone);
  }

  function ensureState(themeId) {
    if (!stateCache.has(themeId)) {
      const entry = getEntryById(themeId);
      const state = buildStateFromTheme(entry?.theme || {});
      stateCache.set(themeId, state);
    }
    return stateCache.get(themeId);
  }

  function updateOutputs() {
    if (!activeState) {
      return;
    }
    for (const [field, formatter] of Object.entries(OUTPUT_FORMATTERS)) {
      const output = form.querySelector(`[data-output="${field}"]`);
      if (!output) {
        continue;
      }
      const value = activeState[field];
      if (typeof value === 'number') {
        output.textContent = formatter(value);
      }
    }
  }

  function applyStateToForm() {
    if (!activeState) {
      return;
    }
    for (const element of controls) {
      const field = element.dataset.field;
      if (!field) {
        continue;
      }
      if (field === 'patterns') {
        element.checked = activeState.patterns.includes(element.value);
      } else if (field === 'style' && element.type === 'radio') {
        element.checked = element.value === activeState.style;
      } else if (FIELD_RANGES[field]) {
        element.value = String(activeState[field]);
      } else if (field === 'tagline') {
        element.value = activeState.tagline || '';
      } else if (field === 'density') {
        element.value = activeState.density;
      }
    }
  }

  function updatePreviewToggle() {
    if (!previewToggle) {
      return;
    }
    const pair = findPairForTheme(activeThemeId);
    if (!pair) {
      previewMode = 'base';
      previewToggle.hidden = true;
      previewToggleButtons.forEach((button) => {
        button.classList.remove('is-active');
        button.setAttribute('aria-pressed', 'false');
      });
      return;
    }

    const baseEntry = getEntryById(pair.baseThemeId);
    const negativeEntry = getEntryById(pair.variantThemeId);
    if (!baseEntry || !negativeEntry) {
      previewMode = 'base';
      previewToggle.hidden = true;
      return;
    }

    if (previewMode !== 'negative' && previewMode !== 'base') {
      previewMode = pair.variantThemeId === activeThemeId ? 'negative' : 'base';
    }

    previewToggle.hidden = false;
    previewToggleButtons.forEach((button) => {
      const variant = button.dataset.previewVariant;
      if (variant === 'base') {
        const label = baseEntry.label || baseEntry.id;
        button.textContent = label;
        button.setAttribute('aria-label', `Preview ${label}`);
      } else if (variant === 'negative') {
        const label = pair.label || negativeEntry.label || negativeEntry.id;
        button.textContent = label;
        button.setAttribute('aria-label', `Preview ${label}`);
      }
      const isActive = previewMode === variant;
      button.setAttribute('aria-pressed', isActive ? 'true' : 'false');
      button.classList.toggle('is-active', isActive);
    });
  }

  function resolvePreviewContext() {
    const pair = findPairForTheme(activeThemeId);
    if (!pair) {
      const entry = getEntryById(activeThemeId);
      if (!entry) {
        return null;
      }
      return {
        entry,
        state: ensureState(entry.id),
        pair: null,
        mode: 'base'
      };
    }

    let mode = previewMode;
    if (mode !== 'negative' && mode !== 'base') {
      mode = pair.variantThemeId === activeThemeId ? 'negative' : 'base';
    }

    let targetId = mode === 'negative' ? pair.variantThemeId : pair.baseThemeId;
    let entry = getEntryById(targetId);
    if (!entry) {
      targetId = pair.baseThemeId;
      entry = getEntryById(targetId);
      mode = 'base';
    }
    if (!entry) {
      return null;
    }

    const state = ensureState(entry.id);
    return {
      entry,
      state,
      pair,
      mode
    };
  }

  function updatePreview() {
    const context = resolvePreviewContext();
    if (!context) {
      return;
    }

    const { entry, state, pair, mode } = context;
    entry.theme = buildThemePayload(entry.theme, state);

    if (preview.style) {
      const baseLabel = entry.label || STYLE_LABELS[state.style] || state.style;
      const label = pair && mode === 'negative'
        ? (pair.label || baseLabel)
        : baseLabel;
      preview.style.textContent = label;
    }
    if (preview.tagline) {
      preview.tagline.textContent = state.tagline && state.tagline.trim().length > 0
        ? state.tagline
        : DEFAULT_STATE.tagline;
    }
    if (preview.density) {
      preview.density.textContent = formatDensity(state.density);
    }
    if (preview.glass) {
      preview.glass.textContent = `${Math.round(state.glassBlur)}px blur · ${state.glassOpacity.toFixed(2)} opacity`;
    }
    if (preview.patternSummary) {
      preview.patternSummary.textContent = state.patterns.length
        ? `Overlays: ${state.patterns.map((value) => PATTERN_LABELS[value] || value).join(' • ')}`
        : 'Overlays: None';
    }
    if (preview.patternList) {
      preview.patternList.innerHTML = '';
      if (state.patterns.length === 0) {
        const empty = document.createElement('span');
        empty.className = 'theme-preview__chip';
        empty.textContent = 'Clean glass';
        preview.patternList.append(empty);
      } else {
        for (const pattern of state.patterns) {
          const chip = document.createElement('span');
          chip.className = 'theme-preview__chip';
          chip.textContent = PATTERN_LABELS[pattern] || pattern;
          preview.patternList.append(chip);
        }
      }
    }
    yoyo.preview(entry.theme);
  }

  function updateMetadataFields(entry) {
    if (!entry) {
      return;
    }
    if (metadataInputs.label) {
      metadataInputs.label.value = entry.label || '';
    }
    if (metadataInputs.id) {
      metadataInputs.id.value = entry.id;
    }
    if (metadataInputs.description) {
      metadataInputs.description.value = entry.description || '';
    }
    if (activeLabelEl) {
      activeLabelEl.textContent = entry.label || entry.id;
    }
    if (flagsEl) {
      flagsEl.innerHTML = '';
      if (collection.defaultThemeId === entry.id) {
        const badge = document.createElement('span');
        badge.className = 'theme-admin__badge';
        badge.textContent = 'Default theme';
        flagsEl.append(badge);
      }
      const pair = findPairForTheme(entry.id);
      if (pair) {
        const badge = document.createElement('span');
        badge.className = 'theme-admin__badge';
        badge.textContent = pair.baseThemeId === entry.id ? 'Has negative variant' : 'Negative variant';
        flagsEl.append(badge);
      }
    }
  }

  function renderThemeList() {
    const previouslyFocused = document.activeElement;
    const focusDescriptor = previouslyFocused && previouslyFocused.dataset && previouslyFocused.dataset.themeId
      ? { id: previouslyFocused.dataset.themeId, action: previouslyFocused.dataset.action || previouslyFocused.dataset.role }
      : null;

    list.innerHTML = '';

    collection.themes.forEach((entry, index) => {
      const item = document.createElement('li');
      item.className = 'theme-admin__list-item';
      item.dataset.themeId = entry.id;
      if (entry.id === activeThemeId) {
        item.dataset.active = 'true';
      }

      const header = document.createElement('div');
      header.className = 'theme-admin__list-header';

      const selectButton = document.createElement('button');
      selectButton.type = 'button';
      selectButton.className = 'theme-admin__list-button';
      selectButton.dataset.action = 'select-theme';
      selectButton.dataset.themeId = entry.id;
      selectButton.textContent = entry.label || entry.id;
      header.append(selectButton);

      if (collection.defaultThemeId === entry.id) {
        const badge = document.createElement('span');
        badge.className = 'theme-admin__badge';
        badge.textContent = 'Default';
        header.append(badge);
      }

      const pair = findPairForTheme(entry.id);
      if (pair) {
        const badge = document.createElement('span');
        badge.className = 'theme-admin__badge';
        badge.textContent = pair.baseThemeId === entry.id ? 'Has negative' : 'Negative';
        header.append(badge);
      }

      item.append(header);

      if (entry.description) {
        const description = document.createElement('p');
        description.className = 'theme-admin__note';
        description.textContent = entry.description;
        item.append(description);
      }

      const controlsRow = document.createElement('div');
      controlsRow.className = 'theme-admin__list-controls';

      const orderGroup = document.createElement('div');
      orderGroup.className = 'theme-admin__list-order';

      const upButton = document.createElement('button');
      upButton.type = 'button';
      upButton.dataset.action = 'move-up';
      upButton.dataset.themeId = entry.id;
      upButton.textContent = '↑';
      if (index === 0) {
        upButton.disabled = true;
      }
      orderGroup.append(upButton);

      const downButton = document.createElement('button');
      downButton.type = 'button';
      downButton.dataset.action = 'move-down';
      downButton.dataset.themeId = entry.id;
      downButton.textContent = '↓';
      if (index === collection.themes.length - 1) {
        downButton.disabled = true;
      }
      orderGroup.append(downButton);

      controlsRow.append(orderGroup);

      const defaultLabel = document.createElement('label');
      defaultLabel.className = 'theme-admin__list-default';
      const defaultRadio = document.createElement('input');
      defaultRadio.type = 'radio';
      defaultRadio.name = 'defaultTheme';
      defaultRadio.value = entry.id;
      defaultRadio.dataset.action = 'set-default';
      defaultRadio.dataset.themeId = entry.id;
      defaultRadio.checked = collection.defaultThemeId === entry.id;
      defaultLabel.append(defaultRadio);
      defaultLabel.append(document.createTextNode('Default'));
      controlsRow.append(defaultLabel);

      item.append(controlsRow);

      list.append(item);
    });

    if (focusDescriptor) {
      const selector = `[data-action="${focusDescriptor.action}"][data-theme-id="${focusDescriptor.id}"]`;
      const next = list.querySelector(selector);
      if (next) {
        next.focus();
      }
    }
  }

  function setActiveTheme(themeId, options = {}) {
    const entry = getEntryById(themeId);
    if (!entry) {
      return;
    }
    activeThemeId = entry.id;
    activeState = ensureState(entry.id);
    updateMetadataFields(entry);
    applyStateToForm();
    updateOutputs();
    entry.theme = buildThemePayload(entry.theme, activeState);
    const pair = findPairForTheme(entry.id);
    if (pair) {
      previewMode = pair.variantThemeId === entry.id ? 'negative' : 'base';
    } else {
      previewMode = 'base';
    }
    updatePreviewToggle();
    updatePreview();
    if (!options.silent) {
      renderThemeList();
    }
  }

  function handleFieldChange(target) {
    if (!activeState) {
      return;
    }
    const field = target.dataset.field;
    if (!field) {
      return;
    }

    if (field === 'patterns') {
      const value = target.value;
      const exists = activeState.patterns.includes(value);
      if (target.checked && !exists) {
        activeState.patterns = normalisePatterns([...activeState.patterns, value]);
      } else if (!target.checked && exists) {
        activeState.patterns = activeState.patterns.filter((entry) => entry !== value);
      }
    } else if (field === 'style' && target.type === 'radio') {
      activeState.style = target.value;
    } else if (field === 'tagline') {
      activeState.tagline = target.value.slice(0, 120);
    } else if (field === 'density') {
      activeState.density = target.value;
    } else if (FIELD_RANGES[field]) {
      activeState[field] = toNumber(target.value, activeState[field], FIELD_RANGES[field]);
    }

    const entry = getEntryById(activeThemeId);
    if (entry) {
      entry.theme = buildThemePayload(entry.theme, activeState);
      updateOutputs();
      updatePreview();
      markDirty();
    }
  }

  function handleMetaChange(target) {
    const entry = getEntryById(activeThemeId);
    if (!entry) {
      return;
    }
    if (target === metadataInputs.label) {
      const nextLabel = target.value.trim();
      entry.label = nextLabel.length > 0 ? nextLabel : entry.id;
      updateMetadataFields(entry);
      renderThemeList();
      markDirty();
    } else if (target === metadataInputs.description) {
      entry.description = target.value.trim();
      renderThemeList();
      markDirty();
    }
  }

  function serialiseCollection() {
    return {
      defaultThemeId: collection.defaultThemeId,
      themes: collection.themes.map((entry) => ({
        id: entry.id,
        label: entry.label,
        description: entry.description || '',
        theme: entry.theme
      })),
      pairings: Array.isArray(collection.pairings)
        ? collection.pairings.map((pair) => ({ ...pair }))
        : []
    };
  }

  async function handleSubmit(event) {
    event.preventDefault();
    if (isSaving) {
      return;
    }
    isSaving = true;
    setBusy(true);
    setStatus('Saving themes…', 'pending');

    const payload = serialiseCollection();

    try {
      const response = await fetch('theme_save.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
      });

      if (!response.ok) {
        const context = await response.json().catch(() => ({}));
        console.warn('Theme save failed', response.status, context);
        setStatus('Save failed — try again after fixing permissions.', 'warning');
        return;
      }

      const body = await response.json().catch(() => ({}));
      if (isPlainObject(body?.collection)) {
        collection = normaliseCollection(body.collection);
      } else {
        collection = normaliseCollection(payload);
      }
      originalCollection = cloneCollection(collection);
      stateCache.clear();
      activeState = ensureState(collection.defaultThemeId);
      setActiveTheme(collection.defaultThemeId, { silent: true });
      renderThemeList();
      const defaultEntry = getEntryById(collection.defaultThemeId);
      if (defaultEntry) {
        yoyo.commit(defaultEntry.theme, { persist: true });
      }
      window.__THEME_COLLECTION__ = cloneCollection(collection);
      clearDirty('Theme collection saved to /data/themes.json.', 'success');
    } catch (error) {
      console.error('Theme save error', error);
      setStatus('Save failed — try again after fixing permissions.', 'warning');
    } finally {
      isSaving = false;
      setBusy(false);
    }
  }

  function revertToSaved(event) {
    if (event) {
      event.preventDefault();
    }
    collection = cloneCollection(originalCollection);
    stateCache.clear();
    const entry = getEntryById(collection.defaultThemeId) || collection.themes[0];
    if (entry) {
      previewMode = 'base';
      activeState = ensureState(entry.id);
      setActiveTheme(entry.id, { silent: true });
      yoyo.commit(entry.theme);
    }
    renderThemeList();
    clearDirty('Reverted to last saved collection.', 'pending');
  }

  function moveTheme(themeId, delta) {
    const index = collection.themes.findIndex((entry) => entry.id === themeId);
    if (index === -1) {
      return;
    }
    const nextIndex = clamp(index + delta, 0, collection.themes.length - 1);
    if (index === nextIndex) {
      return;
    }
    const [moved] = collection.themes.splice(index, 1);
    collection.themes.splice(nextIndex, 0, moved);
    renderThemeList();
    markDirty('Theme order updated.');
  }

  function setDefaultTheme(themeId) {
    if (collection.defaultThemeId === themeId) {
      return;
    }
    const entry = getEntryById(themeId);
    if (!entry) {
      return;
    }
    collection.defaultThemeId = entry.id;
    renderThemeList();
    const activeEntry = getEntryById(activeThemeId);
    if (activeEntry) {
      updateMetadataFields(activeEntry);
    }
    markDirty('Default theme updated.');
  }

  function generateUniqueId(label) {
    const base = slugify(label) || 'theme';
    const used = new Set(collection.themes.map((entry) => entry.id));
    if (!used.has(base)) {
      return base;
    }
    let suffix = 2;
    while (used.has(`${base}-${suffix}`)) {
      suffix += 1;
    }
    return `${base}-${suffix}`;
  }

  function addTheme() {
    const templateEntry = getEntryById(activeThemeId) || collection.themes[0];
    const clonedTheme = cloneTheme(templateEntry?.theme || yoyo.getBaseTheme() || {});
    const label = templateEntry ? `${templateEntry.label} Copy` : 'New Theme';
    const id = generateUniqueId(label);
    const entry = {
      id,
      label,
      description: '',
      theme: clonedTheme
    };
    collection.themes.push(entry);
    collection.defaultThemeId = collection.defaultThemeId || id;
    stateCache.set(id, buildStateFromTheme(entry.theme));
    renderThemeList();
    setActiveTheme(id);
    markDirty('New theme added.');
  }

  function generateNegativeTheme() {
    const sourceEntry = getEntryById(activeThemeId);
    if (!sourceEntry) {
      return;
    }

    const sourceState = ensureState(sourceEntry.id);
    const variantState = structuredCloneIfPossible(sourceState);
    if (!variantState) {
      return;
    }

    variantState.hue = (variantState.hue + 180) % 360;
    variantState.accentHueA = (variantState.accentHueA + 180) % 360;
    variantState.accentHueB = (variantState.accentHueB + 180) % 360;
    variantState.lightness = clamp(100 - variantState.lightness, FIELD_RANGES.lightness.min, FIELD_RANGES.lightness.max);
    variantState.saturation = clamp(100 - variantState.saturation, FIELD_RANGES.saturation.min, FIELD_RANGES.saturation.max);

    const variantThemeBase = cloneTheme(sourceEntry.theme);
    const variantTheme = buildThemePayload(variantThemeBase, variantState);
    variantTheme.name = `${sourceEntry.theme?.name || sourceEntry.id}-negative`;

    const sourceColors = isPlainObject(sourceEntry.theme?.colors) ? sourceEntry.theme.colors : {};
    const variantColors = variantTheme.colors = { ...(variantTheme.colors || {}) };

    if (sourceColors.text && sourceColors.background) {
      variantColors.background = sourceColors.text;
      variantColors.text = sourceColors.background;
    }
    if (sourceColors.surface && sourceColors.textMuted) {
      variantColors.surface = sourceColors.textMuted;
      variantColors.textMuted = sourceColors.surface;
    }
    if (sourceColors.card && sourceColors.cardText) {
      variantColors.card = sourceColors.cardText;
      variantColors.cardText = sourceColors.card;
    } else {
      variantColors.card = variantColors.card || variantColors.background || sourceColors.card || sourceColors.background;
      variantColors.cardText = variantColors.cardText || variantColors.text || sourceColors.cardText || sourceColors.text;
    }
    if (sourceColors.bandOverlay && sourceColors.bandText) {
      variantColors.bandOverlay = sourceColors.bandText;
      variantColors.bandText = sourceColors.bandOverlay;
    }

    if (!variantColors.ctaText) {
      if (sourceColors.background) {
        variantColors.ctaText = sourceColors.background;
      } else if (variantColors.background) {
        variantColors.ctaText = variantColors.background;
      }
    }

    const baseLabel = sourceEntry.label || sourceEntry.id;
    const pairLabel = `${baseLabel} Night`;
    const description = `Automatically generated negative of ${baseLabel}.`;

    const existingPair = findPairForTheme(sourceEntry.id);
    let variantEntry = existingPair ? getEntryById(existingPair.variantThemeId) : null;
    let variantId = variantEntry ? variantEntry.id : generateUniqueId(`${sourceEntry.id}-night`);

    const sourceMeta = isPlainObject(sourceEntry.theme.meta) ? { ...sourceEntry.theme.meta } : {};
    sourceMeta.pairing = { type: 'negative', variantThemeId: variantId };
    sourceEntry.theme.meta = sourceMeta;

    const variantMeta = isPlainObject(variantTheme.meta) ? { ...variantTheme.meta } : {};
    variantMeta.pairing = { type: 'negative', sourceThemeId: sourceEntry.id };
    variantTheme.meta = variantMeta;

    ensurePairings();

    if (variantEntry) {
      variantEntry.label = pairLabel;
      variantEntry.description = description;
      variantEntry.theme = variantTheme;
      stateCache.set(variantId, variantState);
      existingPair.baseThemeId = sourceEntry.id;
      existingPair.variantThemeId = variantId;
      existingPair.label = pairLabel;
      existingPair.generated = true;
      previewMode = 'negative';
      setActiveTheme(variantId);
      markDirty('Regenerated negative variant from the active theme.');
      return;
    }

    const entry = {
      id: variantId,
      label: pairLabel,
      description,
      theme: variantTheme
    };

    const sourceIndex = collection.themes.findIndex((theme) => theme.id === sourceEntry.id);
    if (sourceIndex === -1 || sourceIndex === collection.themes.length - 1) {
      collection.themes.push(entry);
    } else {
      collection.themes.splice(sourceIndex + 1, 0, entry);
    }

    stateCache.set(variantId, variantState);
    removePairingsForTheme(sourceEntry.id);
    removePairingsForTheme(variantId);
    collection.pairings.push({
      type: 'negative',
      baseThemeId: sourceEntry.id,
      variantThemeId: variantId,
      label: pairLabel,
      generated: true
    });

    previewMode = 'negative';
    setActiveTheme(variantId);
    markDirty('Generated negative variant from the active theme.');
  }

  function handleListClick(event) {
    const target = event.target;
    if (!(target instanceof HTMLElement)) {
      return;
    }
    const actionEl = target.closest('[data-action]');
    if (!actionEl) {
      return;
    }
    const themeId = actionEl.dataset.themeId;
    if (!themeId) {
      return;
    }
    switch (actionEl.dataset.action) {
      case 'select-theme':
        setActiveTheme(themeId);
        break;
      case 'move-up':
        moveTheme(themeId, -1);
        break;
      case 'move-down':
        moveTheme(themeId, 1);
        break;
      case 'set-default':
        setDefaultTheme(themeId);
        break;
      default:
        break;
    }
  }

  function handleListChange(event) {
    const target = event.target;
    if (!(target instanceof HTMLInputElement)) {
      return;
    }
    if (target.dataset.action === 'set-default' && target.checked) {
      const themeId = target.dataset.themeId;
      if (themeId) {
        setDefaultTheme(themeId);
      }
    }
  }

  form.addEventListener('input', (event) => {
    const target = event.target;
    if (target instanceof HTMLInputElement || target instanceof HTMLSelectElement || target instanceof HTMLTextAreaElement) {
      if (target.dataset.field) {
        handleFieldChange(target);
      } else if (target.dataset.meta) {
        handleMetaChange(target);
      }
    }
  });
  form.addEventListener('change', (event) => {
    const target = event.target;
    if (target instanceof HTMLInputElement || target instanceof HTMLSelectElement || target instanceof HTMLTextAreaElement) {
      if (target.dataset.field) {
        handleFieldChange(target);
      } else if (target.dataset.meta) {
        handleMetaChange(target);
      }
    }
  });
  form.addEventListener('submit', handleSubmit);

  if (revertButton) {
    revertButton.addEventListener('click', revertToSaved);
  }

  if (addButton) {
    addButton.addEventListener('click', addTheme);
  }

  if (generateNegativeButton) {
    generateNegativeButton.addEventListener('click', generateNegativeTheme);
  }

  if (previewToggle) {
    previewToggle.addEventListener('click', (event) => {
      const target = event.target instanceof HTMLElement ? event.target.closest('[data-preview-variant]') : null;
      if (!target) {
        return;
      }
      const variant = target.dataset.previewVariant;
      if (variant !== 'base' && variant !== 'negative') {
        return;
      }
      if (previewMode === variant) {
        return;
      }
      previewMode = variant;
      updatePreviewToggle();
      updatePreview();
    });
  }

  list.addEventListener('click', handleListClick);
  list.addEventListener('change', handleListChange);

  window.addEventListener('beforeunload', (event) => {
    if (!hasUnsavedChanges) {
      return;
    }
    event.preventDefault();
    event.returnValue = '';
  });

  setActiveTheme(activeThemeId, { silent: true });
  renderThemeList();
  clearDirty('', '');
})();
