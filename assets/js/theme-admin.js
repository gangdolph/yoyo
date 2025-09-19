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
  const { min, max } = range;
  return clamp(next, min, max);
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

function cloneTheme(theme) {
  if (!theme) {
    return {};
  }
  if (typeof structuredClone === 'function') {
    try {
      return structuredClone(theme);
    } catch (error) {
      console.warn('Unable to structuredClone theme payload, falling back to JSON clone.', error);
    }
  }
  try {
    return JSON.parse(JSON.stringify(theme));
  } catch (error) {
    console.warn('Unable to deep clone theme payload.', error);
    return { ...theme };
  }
}

function buildStateFromTheme(theme) {
  const state = { ...DEFAULT_STATE, patterns: [] };
  if (!theme || typeof theme !== 'object') {
    state.patterns = [];
    return state;
  }

  const meta = theme.meta && typeof theme.meta === 'object' ? theme.meta : {};
  const controls = meta.controls && typeof meta.controls === 'object' ? meta.controls : {};

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

(function initialise() {
  const yoyo = window.yoyoTheme;
  const form = document.querySelector('[data-theme-form]');
  if (!form || !yoyo) {
    return;
  }

  let savedTheme = cloneTheme(yoyo.getBaseTheme() || {});
  let savedState = buildStateFromTheme(savedTheme);
  const state = { ...savedState, patterns: [...savedState.patterns] };

  let hasUnsavedChanges = false;
  let isSaving = false;

  const controls = Array.from(form.querySelectorAll('[data-field]'));
  const statusEl = form.querySelector('[data-theme-status]');
  const preview = {
    tagline: document.querySelector('[data-preview-tagline]'),
    style: document.querySelector('[data-preview-style]'),
    density: document.querySelector('[data-preview-density]'),
    glass: document.querySelector('[data-preview-glass]'),
    patternSummary: document.querySelector('[data-preview-pattern-summary]'),
    patternList: document.querySelector('[data-preview-patterns]')
  };
  const saveButton = form.querySelector('[data-action="save"]');
  const revertButton = form.querySelector('[data-action="revert"]');

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

  function overwriteState(next) {
    for (const key of Object.keys(DEFAULT_STATE)) {
      if (key === 'patterns') {
        state.patterns = Array.isArray(next.patterns) ? [...next.patterns] : [];
      } else if (Object.prototype.hasOwnProperty.call(next, key)) {
        state[key] = next[key];
      } else if (Object.prototype.hasOwnProperty.call(DEFAULT_STATE, key)) {
        state[key] = DEFAULT_STATE[key];
      }
    }
  }

  function updateOutputs() {
    for (const [field, formatter] of Object.entries(OUTPUT_FORMATTERS)) {
      const output = form.querySelector(`[data-output="${field}"]`);
      if (!output) {
        continue;
      }
      const value = state[field];
      if (typeof value === 'number') {
        output.textContent = formatter(value);
      }
    }
  }

  function applyStateToForm() {
    for (const element of controls) {
      const field = element.dataset.field;
      if (!field) {
        continue;
      }
      if (field === 'patterns') {
        element.checked = state.patterns.includes(element.value);
      } else if (field === 'style' && element.type === 'radio') {
        element.checked = element.value === state.style;
      } else if (FIELD_RANGES[field]) {
        element.value = String(state[field]);
      } else if (field === 'tagline') {
        element.value = state.tagline || '';
      } else if (field === 'density') {
        element.value = state.density;
      }
    }
  }

  function buildThemePayload() {
    const base = cloneTheme(savedTheme);
    base.name = state.style;

    const meta = base.meta && typeof base.meta === 'object' ? { ...base.meta } : {};
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

  function updatePreview(theme) {
    if (preview.style) {
      preview.style.textContent = STYLE_LABELS[state.style] || state.style;
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
    yoyo.preview(theme);
  }

  function markDirty() {
    hasUnsavedChanges = true;
    setStatus('Preview updated — remember to save.', 'pending');
  }

  function clearDirty(message, tone) {
    hasUnsavedChanges = false;
    setStatus(message || '', tone);
  }

  function handleFieldChange(target) {
    const field = target.dataset.field;
    if (!field) {
      return;
    }
    if (field === 'patterns') {
      const value = target.value;
      const exists = state.patterns.includes(value);
      if (target.checked && !exists) {
        state.patterns = normalisePatterns([...state.patterns, value]);
      } else if (!target.checked && exists) {
        state.patterns = state.patterns.filter((entry) => entry !== value);
      }
    } else if (field === 'style' && target.type === 'radio') {
      state.style = target.value;
    } else if (field === 'tagline') {
      state.tagline = target.value.slice(0, 120);
    } else if (field === 'density') {
      state.density = target.value;
    } else if (FIELD_RANGES[field]) {
      state[field] = toNumber(target.value, state[field], FIELD_RANGES[field]);
    }
    updateOutputs();
    const theme = buildThemePayload();
    updatePreview(theme);
    markDirty();
  }

  async function handleSubmit(event) {
    event.preventDefault();
    if (isSaving) {
      return;
    }
    isSaving = true;
    setBusy(true);
    setStatus('Saving theme…', 'pending');

    const theme = buildThemePayload();

    try {
      const response = await fetch('theme_save.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(theme)
      });

      if (!response.ok) {
        const context = await response.text().catch(() => '');
        console.warn('Theme save failed', response.status, context);
        yoyo.persist(theme);
        setStatus('Save failed — stored locally until permissions are fixed.', 'warning');
        return;
      }

      await response.json().catch(() => ({}));
      yoyo.commit(theme, { persist: true });
      savedTheme = cloneTheme(theme);
      savedState = buildStateFromTheme(savedTheme);
      overwriteState(savedState);
      applyStateToForm();
      updateOutputs();
      updatePreview(theme);
      clearDirty('Theme saved to /data/theme.json.', 'success');
    } catch (error) {
      console.error('Theme save error', error);
      yoyo.persist(theme);
      setStatus('Save failed — stored locally until permissions are fixed.', 'warning');
    } finally {
      isSaving = false;
      setBusy(false);
    }
  }

  function revertToSaved(event) {
    if (event) {
      event.preventDefault();
    }
    overwriteState(savedState);
    applyStateToForm();
    updateOutputs();
    const theme = buildThemePayload();
    yoyo.commit(savedTheme);
    clearDirty('Reverted to last saved theme.', 'pending');
    updatePreview(theme);
  }

  function handleFormEvent(event) {
    const target = event.target;
    if (!(target instanceof HTMLInputElement || target instanceof HTMLSelectElement || target instanceof HTMLTextAreaElement)) {
      return;
    }
    handleFieldChange(target);
  }

  form.addEventListener('input', handleFormEvent);
  form.addEventListener('change', handleFormEvent);
  form.addEventListener('submit', handleSubmit);
  if (revertButton) {
    revertButton.addEventListener('click', revertToSaved);
  }

  window.addEventListener('beforeunload', (event) => {
    if (!hasUnsavedChanges) {
      return;
    }
    event.preventDefault();
    event.returnValue = '';
  });

  applyStateToForm();
  updateOutputs();
  const initialTheme = buildThemePayload();
  updatePreview(initialTheme);
  clearDirty('', '');
})();
