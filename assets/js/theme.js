(function () {
  const root = document.documentElement;
  const STORAGE_KEY = '__THEME__';

  const DEFAULT_THEME = {
    name: 'legacy-vaporwave',
    colors: {
      background: '#2d1e59',
      surface: 'rgba(20, 12, 65, 0.72)',
      surfaceAlt: 'rgba(9, 0, 46, 0.68)',
      border: 'rgba(255, 255, 255, 0.12)',
      borderStrong: 'rgba(255, 255, 255, 0.24)',
      text: '#f8f9fa',
      textMuted: 'rgba(248, 249, 250, 0.68)',
      accent: '#ff71ce',
      accentAlt: '#01cdfe',
      accentSoft: 'rgba(255, 113, 206, 0.35)',
      highlight: '#05ffa1',
      cta: '#01cdfe',
      ctaText: '#041221',
      card: 'rgba(10, 5, 40, 0.72)',
      cardBorder: 'rgba(255, 255, 255, 0.18)',
      cardText: '#f8f9fa',
      bandText: '#f8f9fa',
      bandOverlay: 'rgba(9, 0, 46, 0.78)',
      gradient: 'linear-gradient(135deg, #ff71ce 0%, #01cdfe 100%)'
    },
    typography: {
      fontBody: "'Share Tech Mono', 'IBM Plex Mono', monospace",
      fontDisplay: "'Orbitron', 'Share Tech Mono', monospace",
      fontMono: "'Share Tech Mono', monospace",
      fontParagraph: "'Share Tech Mono', 'IBM Plex Mono', monospace",
      baseSize: '0.9rem',
      scaleSm: '0.8rem',
      scaleLg: '1.2rem',
      weightNormal: '400',
      weightBold: '600',
      lineHeight: '1.6'
    },
    radii: {
      xs: '4px',
      sm: '8px',
      md: '12px',
      lg: '18px',
      xl: '26px',
      pill: '999px'
    },
    spacing: {
      xxs: '0.25rem',
      xs: '0.5rem',
      sm: '0.75rem',
      md: '1rem',
      lg: '1.5rem',
      xl: '2rem',
      xxl: '3rem'
    },
    motion: {
      durationSm: '120ms',
      durationMd: '200ms',
      durationLg: '360ms',
      easing: 'cubic-bezier(0.4, 0, 0.2, 1)',
      spring: 'cubic-bezier(0.2, 0.8, 0.4, 1)'
    },
    effects: {
      vapors: ['#ff71ce', '#01cdfe', '#05ffa1'],
      glassBlur: '24px',
      glassOpacity: '0.78',
      glassBorder: '1px solid rgba(255, 255, 255, 0.18)',
      bandGradient: 'linear-gradient(120deg, rgba(255, 113, 206, 0.35), rgba(1, 205, 254, 0.35))',
      bandOverlay: 'rgba(8, 0, 46, 0.65)',
      ctaGradient: 'linear-gradient(45deg, #ff71ce, #01cdfe, #05ffa1)',
      patternHue: '310deg',
      patternSaturation: '100%',
      shadowSoft: '0 12px 32px -20px rgba(0, 0, 0, 0.85)',
      shadowMedium: '0 18px 45px -22px rgba(5, 0, 45, 0.55)',
      shadowStrong: '0 22px 65px -24px rgba(0, 0, 0, 0.9)',
      glassOverlayOpacity: '0.45',
      glassSheenOpacity: '0.3',
      glowStrength: '32px',
      noiseOpacity: '0.08',
      hoverLift: '3px'
    },
    depth: {
      header: '10px',
      nav: '12px',
      footer: '10px',
      button: '6px',
      cta: '20px',
      siteBorder: 'solid'
    },
    ui: {
      density: 'comfortable',
      hoverLift: '3px'
    },
    meta: {
      tagline: '',
      density: 'comfortable',
      patterns: []
    }
  };

  const CSS_VARIABLE_MAP = new Map([
    ['colors.background', ['--color-background', '--color-bg', '--bg']],
    ['colors.surface', ['--color-surface']],
    ['colors.surfaceAlt', ['--color-surface-alt']],
    ['colors.border', ['--color-border']],
    ['colors.borderStrong', ['--color-border-strong']],
    ['colors.text', ['--color-text', '--color-text-legacy', '--color-fg', '--fg']],
    ['colors.textMuted', ['--color-text-muted']],
    ['colors.accent', ['--color-accent', '--color-accent-legacy', '--accent']],
    ['colors.accentAlt', ['--color-accent-alt']],
    ['colors.accentSoft', ['--color-accent-soft']],
    ['colors.highlight', ['--color-highlight']],
    ['colors.cta', ['--color-cta-bg']],
    ['colors.ctaText', ['--color-cta-text', '--btn-text', '--btn-text-idle']],
    ['colors.card', ['--color-card-bg']],
    ['colors.cardBorder', ['--color-card-border']],
    ['colors.cardText', ['--color-card-text']],
    ['colors.bandText', ['--color-band-text']],
    ['colors.bandOverlay', ['--color-band-overlay']],
    ['colors.gradient', ['--gradient']],
    ['typography.fontBody', ['--font-body', '--font-family-base']],
    ['typography.fontDisplay', ['--font-display', '--font-header']],
    ['typography.fontMono', ['--font-mono']],
    ['typography.fontParagraph', ['--font-paragraph']],
    ['typography.baseSize', ['--font-size-base']],
    ['typography.scaleSm', ['--font-size-sm']],
    ['typography.scaleLg', ['--font-size-lg']],
    ['typography.weightNormal', ['--font-weight-regular']],
    ['typography.weightBold', ['--font-weight-bold']],
    ['typography.lineHeight', ['--line-height-base']],
    ['radii.xs', ['--radius-xs']],
    ['radii.sm', ['--radius-sm']],
    ['radii.md', ['--radius-md']],
    ['radii.lg', ['--radius-lg']],
    ['radii.xl', ['--radius-xl']],
    ['radii.pill', ['--radius-pill']],
    ['spacing.xxs', ['--space-2xs']],
    ['spacing.xs', ['--space-xs']],
    ['spacing.sm', ['--space-sm']],
    ['spacing.md', ['--space-md']],
    ['spacing.lg', ['--space-lg']],
    ['spacing.xl', ['--space-xl']],
    ['spacing.xxl', ['--space-2xl']],
    ['motion.durationSm', ['--motion-duration-sm']],
    ['motion.durationMd', ['--motion-duration-md']],
    ['motion.durationLg', ['--motion-duration-lg']],
    ['motion.easing', ['--motion-ease-standard']],
    ['motion.spring', ['--motion-ease-spring']],
    ['effects.vapors.0', ['--vap1']],
    ['effects.vapors.1', ['--vap2']],
    ['effects.vapors.2', ['--vap3']],
    ['effects.glassBlur', ['--glass-blur']],
    ['effects.glassOpacity', ['--glass-opacity']],
    ['effects.glassBorder', ['--glass-border']],
    ['effects.bandGradient', ['--band-gradient']],
    ['effects.bandOverlay', ['--band-overlay']],
    ['effects.ctaGradient', ['--cta-gradient']],
    ['effects.patternHue', ['--pattern-hue']],
    ['effects.patternSaturation', ['--pattern-sat']],
    ['effects.shadowSoft', ['--shadow-soft']],
    ['effects.shadowMedium', ['--shadow-medium']],
    ['effects.shadowStrong', ['--shadow-strong']],
    ['effects.glassOverlayOpacity', ['--glass-overlay-opacity']],
    ['effects.glassSheenOpacity', ['--glass-sheen-opacity']],
    ['effects.glowStrength', ['--glow-strength']],
    ['effects.noiseOpacity', ['--noise-opacity']],
    ['effects.hoverLift', ['--hover-lift']],
    ['depth.header', ['--header-depth']],
    ['depth.nav', ['--nav-depth']],
    ['depth.footer', ['--footer-depth']],
    ['depth.button', ['--btn-depth']],
    ['depth.cta', ['--cta-depth']],
    ['depth.siteBorder', ['--site-border']]
  ]);

  const reduceMotionQuery = window.matchMedia('(prefers-reduced-motion: reduce)');
  let baseTheme = DEFAULT_THEME;
  let activeTheme = DEFAULT_THEME;

  function structuredCloneIfPossible(value) {
    if (typeof window.structuredClone === 'function') {
      return window.structuredClone(value);
    }
    try {
      return JSON.parse(JSON.stringify(value));
    } catch (error) {
      console.warn('Unable to deep-clone theme payload, falling back to reference copy.', error);
      return value;
    }
  }

  function isPlainObject(value) {
    return Object.prototype.toString.call(value) === '[object Object]';
  }

  function deepMerge(base, overrides) {
    const output = Array.isArray(base) ? [...base] : { ...base };
    if (!isPlainObject(overrides) && !Array.isArray(overrides)) {
      return output;
    }

    const entries = Array.isArray(overrides)
      ? overrides.entries()
      : Object.entries(overrides);

    for (const [key, overrideValue] of entries) {
      const numericKey = Array.isArray(overrides) ? Number(key) : key;
      const baseValue = Array.isArray(base) ? base[numericKey] : base?.[numericKey];

      if (isPlainObject(overrideValue) && isPlainObject(baseValue)) {
        output[numericKey] = deepMerge(baseValue, overrideValue);
      } else if (Array.isArray(overrideValue) && Array.isArray(baseValue)) {
        output[numericKey] = overrideValue.slice();
      } else if (overrideValue !== undefined && overrideValue !== null) {
        output[numericKey] = overrideValue;
      }
    }

    return output;
  }

  function getThemeValue(theme, path) {
    return path.split('.').reduce((acc, segment) => {
      if (acc == null) {
        return undefined;
      }
      if (Array.isArray(acc)) {
        const index = Number(segment);
        return Number.isNaN(index) ? undefined : acc[index];
      }
      return acc[segment];
    }, theme);
  }

  function applyVariables(theme) {
    CSS_VARIABLE_MAP.forEach((variables, path) => {
      const value = getThemeValue(theme, path);
      if (value == null) {
        return;
      }
      for (const cssVar of variables) {
        root.style.setProperty(cssVar, String(value));
      }
    });

    if (theme.name) {
      root.dataset.theme = theme.name;
    }
  }

  function parseColor(color) {
    if (!color || typeof color !== 'string') {
      return null;
    }

    const hexMatch = color.trim().match(/^#([a-f0-9]{3,8})$/i);
    if (hexMatch) {
      let hex = hexMatch[1];
      if (hex.length === 3) {
        hex = hex.split('').map((char) => char + char).join('');
      } else if (hex.length === 4) {
        hex = hex
          .split('')
          .map((char, index) => (index === 3 ? char + char : char + char))
          .join('');
      }
      const intVal = parseInt(hex.slice(0, 6), 16);
      return {
        r: (intVal >> 16) & 255,
        g: (intVal >> 8) & 255,
        b: intVal & 255
      };
    }

    const rgbMatch = color.match(/rgba?\(([^)]+)\)/i);
    if (rgbMatch) {
      const [r, g, b] = rgbMatch[1]
        .split(',')
        .map((part) => Number(part.trim()))
        .slice(0, 3);
      if ([r, g, b].every((channel) => Number.isFinite(channel))) {
        return { r, g, b };
      }
    }

    return null;
  }

  function relativeLuminance(color) {
    const rgb = parseColor(color);
    if (!rgb) {
      return null;
    }
    const [r, g, b] = [rgb.r, rgb.g, rgb.b].map((value) => {
      const channel = value / 255;
      return channel <= 0.03928 ? channel / 12.92 : Math.pow((channel + 0.055) / 1.055, 2.4);
    });
    return 0.2126 * r + 0.7152 * g + 0.0722 * b;
  }

  function contrastRatio(background, foreground) {
    const bgLum = relativeLuminance(background);
    const fgLum = relativeLuminance(foreground);
    if (bgLum == null || fgLum == null) {
      return null;
    }
    const light = Math.max(bgLum, fgLum) + 0.05;
    const dark = Math.min(bgLum, fgLum) + 0.05;
    return light / dark;
  }

  function enforceContrast() {
    const computed = getComputedStyle(root);
    const ctaBackground = computed.getPropertyValue('--color-cta-bg').trim();
    const ctaText = computed.getPropertyValue('--color-cta-text').trim();
    const cardBackground = computed.getPropertyValue('--color-card-bg').trim();
    const cardText = computed.getPropertyValue('--color-card-text').trim() || computed.getPropertyValue('--color-text').trim();

    adjustContrast('--color-cta-text', ctaBackground, ctaText);
    adjustContrast('--color-card-text', cardBackground, cardText);
  }

  function adjustContrast(variable, background, text) {
    const ratio = contrastRatio(background, text);
    if (ratio != null && ratio < 4.5) {
      const darkRatio = contrastRatio(background, '#000000') ?? 0;
      const lightRatio = contrastRatio(background, '#ffffff') ?? 0;
      const replacement = lightRatio >= darkRatio ? '#ffffff' : '#000000';
      root.style.setProperty(variable, replacement);
    }
  }

  function applyMotionPreference(reduced) {
    if (reduced) {
      root.dataset.motion = 'reduced';
      root.style.setProperty('--motion-duration-sm', '0ms');
      root.style.setProperty('--motion-duration-md', '0ms');
      root.style.setProperty('--motion-duration-lg', '0ms');
      root.style.setProperty('--motion-ease-standard', 'linear');
      root.style.setProperty('--motion-ease-spring', 'linear');
    } else {
      root.dataset.motion = 'standard';
      const motion = activeTheme.motion || {};
      if (motion.durationSm) root.style.setProperty('--motion-duration-sm', motion.durationSm);
      if (motion.durationMd) root.style.setProperty('--motion-duration-md', motion.durationMd);
      if (motion.durationLg) root.style.setProperty('--motion-duration-lg', motion.durationLg);
      if (motion.easing) root.style.setProperty('--motion-ease-standard', motion.easing);
      if (motion.spring) root.style.setProperty('--motion-ease-spring', motion.spring);
    }
  }

  function dispatchAppliedEvent() {
    const detail = structuredCloneIfPossible(activeTheme);
    window.dispatchEvent(new CustomEvent('theme:applied', { detail }));
  }

  function syncTheme(options = {}) {
    const { persistBase = false } = options;
    window.__THEME__ = structuredCloneIfPossible(activeTheme);
    applyVariables(activeTheme);
    enforceContrast();
    applyMotionPreference(reduceMotionQuery.matches);
    dispatchAppliedEvent();
    if (persistBase) {
      persistTheme(baseTheme);
    }
  }

  function setBaseTheme(nextTheme, options = {}) {
    const { persist = false } = options;
    baseTheme = deepMerge(DEFAULT_THEME, nextTheme || {});
    activeTheme = structuredCloneIfPossible(baseTheme);
    syncTheme({ persistBase: persist });
  }

  function previewTheme(overrides) {
    if (!isPlainObject(overrides)) {
      activeTheme = structuredCloneIfPossible(baseTheme);
    } else {
      activeTheme = deepMerge(structuredCloneIfPossible(baseTheme), overrides);
    }
    syncTheme();
  }

  function updateBaseTheme(overrides, options = {}) {
    const { persist = false } = options;
    baseTheme = deepMerge(baseTheme, overrides || {});
    activeTheme = structuredCloneIfPossible(baseTheme);
    syncTheme({ persistBase: persist });
  }

  function resolveStorage() {
    try {
      return window.localStorage;
    } catch (error) {
      return null;
    }
  }

  function persistTheme(theme) {
    const storage = resolveStorage();
    if (!storage) {
      return false;
    }
    try {
      const payload = JSON.stringify(theme ?? baseTheme);
      storage.setItem(STORAGE_KEY, payload);
      return true;
    } catch (error) {
      console.warn('Unable to persist theme overrides.', error);
      return false;
    }
  }

  function clearPersistedTheme() {
    const storage = resolveStorage();
    if (!storage) {
      return false;
    }
    try {
      storage.removeItem(STORAGE_KEY);
      return true;
    } catch (error) {
      console.warn('Unable to clear stored theme overrides.', error);
      return false;
    }
  }

  function readStoredTheme() {
    const storage = resolveStorage();
    if (!storage) {
      return null;
    }
    try {
      const raw = storage.getItem(STORAGE_KEY);
      if (!raw) {
        return null;
      }
      const parsed = JSON.parse(raw);
      return isPlainObject(parsed) ? parsed : null;
    } catch (error) {
      console.warn('Unable to parse stored theme override', error);
      return null;
    }
  }

  const initialPayload = isPlainObject(window.__THEME__) ? window.__THEME__ : {};
  baseTheme = deepMerge(DEFAULT_THEME, initialPayload);

  const storedTheme = readStoredTheme();
  if (storedTheme) {
    baseTheme = deepMerge(baseTheme, storedTheme);
  }

  activeTheme = structuredCloneIfPossible(baseTheme);
  syncTheme();

  if (typeof reduceMotionQuery.addEventListener === 'function') {
    reduceMotionQuery.addEventListener('change', (event) => {
      applyMotionPreference(event.matches);
    });
  } else if (typeof reduceMotionQuery.addListener === 'function') {
    reduceMotionQuery.addListener((event) => {
      applyMotionPreference(event.matches);
    });
  }

  window.addEventListener('theme:update', (event) => {
    if (!event || !isPlainObject(event.detail)) {
      return;
    }
    updateBaseTheme(event.detail);
  });

  window.addEventListener('message', (event) => {
    if (!event || !event.data) {
      return;
    }
    const { type, payload } = event.data;
    if (type === 'theme:update' && isPlainObject(payload)) {
      updateBaseTheme(payload);
    }
  });

  window.addEventListener('storage', (event) => {
    if (event.key !== STORAGE_KEY || !event.newValue) {
      return;
    }
    try {
      const parsed = JSON.parse(event.newValue);
      if (isPlainObject(parsed)) {
        setBaseTheme(parsed);
      }
    } catch (error) {
      console.warn('Unable to parse stored theme override', error);
    }
  });

  window.__applyThemePreview = function (nextTheme) {
    previewTheme(nextTheme);
  };

  window.yoyoTheme = {
    getActiveTheme: () => structuredCloneIfPossible(activeTheme),
    getBaseTheme: () => structuredCloneIfPossible(baseTheme),
    preview: (nextTheme) => {
      previewTheme(nextTheme);
    },
    commit: (nextTheme, options) => {
      setBaseTheme(nextTheme, options);
    },
    apply: (nextTheme, options) => {
      setBaseTheme(nextTheme, options);
    },
    update: (overrides, options) => {
      updateBaseTheme(overrides, options);
    },
    resetPreview: () => {
      previewTheme(null);
    },
    persist: (theme) => persistTheme(theme),
    clearPersisted: () => clearPersistedTheme()
  };
})();
