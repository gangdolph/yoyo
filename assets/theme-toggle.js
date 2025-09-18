document.body.classList.add('vap-lines');
const root = document.documentElement;
const modal = document.getElementById('theme-modal');
const openBtn = document.getElementById('theme-toggle');
const closeBtn = document.getElementById('theme-close');
const preview = document.getElementById('theme-preview');
const optionsContainer = modal ? modal.querySelector('.theme-options') : null;
const errorMsg = modal ? modal.querySelector('.theme-error') : null;
let themes = {};
let borders = {};

function normalizePattern(pattern) {
  if (!pattern || typeof pattern !== 'object') {
    return null;
  }
  const rawFeatures = pattern.features && typeof pattern.features === 'object' ? pattern.features : {};
  const features = {
    poly: !!rawFeatures.poly,
    hue: !!rawFeatures.hue,
    sat: !!rawFeatures.sat,
  };
  const frequency = Number(pattern.frequency);
  const amplitude = Number(pattern.amplitude);
  const polyValues = features.poly && Array.isArray(pattern.poly)
    ? pattern.poly.map(Number).filter(n => Number.isFinite(n))
    : [];
  const hue = features.hue ? Number(pattern.hue) || 0 : 0;
  const sat = features.sat ? Number(pattern.sat) || 100 : 100;
  const preset = typeof pattern.preset === 'string' ? pattern.preset : 'custom';
  return {
    preset,
    frequency: Number.isFinite(frequency) ? frequency : 0,
    amplitude: Number.isFinite(amplitude) ? amplitude : 0,
    poly: polyValues,
    hue,
    sat,
    features,
  };
}

function setActiveButton(name) {
  if (!optionsContainer) return;
  optionsContainer.querySelectorAll('button').forEach(btn => {
    const active = btn.dataset.theme === name;
    btn.classList.toggle('active', active);
    btn.setAttribute('aria-pressed', active ? 'true' : 'false');
  });
}

function applyTheme(name) {
  const t = themes[name];
  if (!t) return;
  root.setAttribute('data-theme', name);
  if (preview) preview.setAttribute('data-theme', name);
  Object.keys(t.vars || {}).forEach(k => root.style.setProperty(k, t.vars[k]));
  if (window.generateVaporwavePattern) {
    const normalized = normalizePattern(t.pattern);
    if (normalized) {
      window.generateVaporwavePattern(normalized);
    } else {
      window.generateVaporwavePattern(null);
    }
  }
  localStorage.setItem('theme', name);
  setActiveButton(name);
}

function setActiveBorder(ch) {
  const container = modal ? modal.querySelector('.border-options') : null;
  if (!container) return;
  container.querySelectorAll('button').forEach(btn => {
    const active = btn.dataset.border === ch;
    btn.classList.toggle('active', active);
    btn.setAttribute('aria-pressed', active ? 'true' : 'false');
  });
}

function applyBorder(ch) {
  if (!ch) {
    document.body.classList.remove('site-frame');
    root.style.removeProperty('--site-border');
    localStorage.removeItem('border');
    setActiveBorder('');
    return;
  }
  document.body.classList.add('site-frame');
  const style = borders[ch] || ch;
  root.style.setProperty('--site-border', style);
  localStorage.setItem('border', ch);
  setActiveBorder(ch);
}

function buildOptions() {
  if (!optionsContainer || !modal) return;
  optionsContainer.innerHTML = '';
  Object.keys(themes).forEach(name => {
    const btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'btn';
    btn.dataset.theme = name;
    btn.textContent = themes[name].label || name;
    btn.setAttribute('aria-pressed', 'false');
    btn.addEventListener('click', () => applyTheme(name));
    btn.addEventListener('focus', () => {
      if (preview) preview.setAttribute('data-theme', name);
    });
    btn.addEventListener('mouseenter', () => {
      if (preview) preview.setAttribute('data-theme', name);
    });
    optionsContainer.appendChild(btn);
  });

  let borderHeading = modal.querySelector('.border-heading');
  let borderContainer = modal.querySelector('.border-options');
  if (!borderHeading) {
    borderHeading = document.createElement('h3');
    borderHeading.className = 'border-heading';
    borderHeading.textContent = 'Borders';
    optionsContainer.insertAdjacentElement('afterend', borderHeading);
  }
  if (!borderContainer) {
    borderContainer = document.createElement('div');
    borderContainer.className = 'border-options';
    borderHeading.insertAdjacentElement('afterend', borderContainer);
  }
  borderContainer.innerHTML = '';
  Object.keys(borders).forEach(ch => {
    const btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'btn';
    btn.dataset.border = ch;
    btn.setAttribute('aria-pressed', 'false');
    btn.setAttribute('aria-label', `${borders[ch]} border`);
    btn.style.borderStyle = borders[ch];
    btn.textContent = '';
    btn.addEventListener('click', () => applyBorder(ch));
    borderContainer.appendChild(btn);
  });
}

async function initThemes() {
  try {
    const res = await fetch(`/assets/themes.json?ts=${Date.now()}`);
    if (!res.ok) throw new Error(`HTTP ${res.status}`);
    const data = await res.json();
    if (data && typeof data === 'object' && !Array.isArray(data)) {
      borders = data.borders || {};
      delete data.borders;
      themes = data;
    } else {
      throw new Error('Invalid theme JSON');
    }
  } catch (e) {
    console.error('Theme load failed', e);
    if (errorMsg) errorMsg.textContent = 'Failed to load themes';
    return;
  }
  buildOptions();
  const stored = localStorage.getItem('theme');
  if (stored && themes[stored]) {
    applyTheme(stored);
  } else if (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches && themes['dark']) {
    applyTheme('dark');
  } else {
    const first = Object.keys(themes)[0];
    if (first) applyTheme(first);
  }
  const storedBorder = localStorage.getItem('border');
  if (storedBorder) {
    applyBorder(storedBorder);
  } else {
    applyBorder('solid');
  }
}

let focusableEls = [];
function handleKeyDown(e) {
  if (e.key === 'Escape') {
    closeModal();
    return;
  }
  if (e.key !== 'Tab' || !focusableEls.length) return;
  const first = focusableEls[0];
  const last = focusableEls[focusableEls.length - 1];
  if (e.shiftKey) {
    if (document.activeElement === first) {
      e.preventDefault();
      last.focus();
    }
  } else {
    if (document.activeElement === last) {
      e.preventDefault();
      first.focus();
    }
  }
}

function openModal() {
  if (!modal) return;
  modal.classList.add('open');
  modal.removeAttribute('hidden');
  if (openBtn) openBtn.setAttribute('aria-expanded', 'true');
  focusableEls = modal.querySelectorAll('button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])');
  if (focusableEls.length) {
    focusableEls[0].focus();
  } else {
    modal.focus();
  }
  document.addEventListener('keydown', handleKeyDown);
}

function closeModal() {
  if (!modal) return;
  modal.classList.remove('open');
  modal.setAttribute('hidden', '');
  document.removeEventListener('keydown', handleKeyDown);
  if (openBtn) {
    openBtn.setAttribute('aria-expanded', 'false');
    openBtn.focus();
  }
}

if (openBtn) openBtn.addEventListener('click', openModal);
if (closeBtn) closeBtn.addEventListener('click', closeModal);
if (modal) {
  modal.addEventListener('click', e => {
    if (e.target === modal) closeModal();
  });
}

initThemes();
