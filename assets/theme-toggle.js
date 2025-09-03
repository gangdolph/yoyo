document.body.classList.add('vap-lines');
const root = document.documentElement;
const modal = document.getElementById('theme-modal');
const openBtn = document.getElementById('theme-toggle');
const closeBtn = document.getElementById('theme-close');
const preview = document.getElementById('theme-preview');
const optionsContainer = modal ? modal.querySelector('.theme-options') : null;
let borderContainer = null;
const errorMsg = modal ? modal.querySelector('.theme-error') : null;
let themes = {};
let borders = {};

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
    if (t.pattern) {
      window.generateVaporwavePattern(t.pattern);
    } else {
      window.generateVaporwavePattern({});
    }
  }
  localStorage.setItem('theme', name);
  setActiveButton(name);
}

function buildOptions() {
  if (!optionsContainer) return;
  optionsContainer.innerHTML = '';
  Object.keys(themes).forEach(name => {
    const btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'btn';
    btn.dataset.theme = name;
    btn.textContent = themes[name].label || name;
    btn.setAttribute('aria-pressed', 'false');
    btn.addEventListener('click', () => applyTheme(name));
    btn.addEventListener('focus', () => { if (preview) preview.setAttribute('data-theme', name); });
    btn.addEventListener('mouseenter', () => { if (preview) preview.setAttribute('data-theme', name); });
    optionsContainer.appendChild(btn);
  });
}

function setActiveBorderButton(name) {
  if (!borderContainer) return;
  borderContainer.querySelectorAll('button').forEach(btn => {
    const active = btn.dataset.border === name;
    btn.classList.toggle('active', active);
    btn.setAttribute('aria-pressed', active ? 'true' : 'false');
  });
}

function applyBorder(name) {
  const b = borders[name];
  if (!b) return;
  const svg = `<svg xmlns="http://www.w3.org/2000/svg" width="8" height="8"><text x="0" y="8" font-family="monospace" font-size="8">${b.char}</text></svg>`;
  const encoded = encodeURIComponent(svg);
  const value = `url("data:image/svg+xml,${encoded}") 8 repeat`;
  root.style.setProperty('--border-style', value);
  if (preview) preview.style.setProperty('--border-style', value);
  localStorage.setItem('border', name);
  setActiveBorderButton(name);
}

function buildBorderOptions() {
  if (!modal) return;
  if (!borderContainer) {
    borderContainer = document.createElement('div');
    borderContainer.className = 'border-options';
    const content = modal.querySelector('.modal-content');
    if (content && optionsContainer) {
      content.insertBefore(borderContainer, optionsContainer.nextSibling);
    }
  }
  borderContainer.innerHTML = '';
  Object.keys(borders).forEach(name => {
    const btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'btn';
    btn.dataset.border = name;
    btn.textContent = borders[name].char;
    btn.setAttribute('aria-pressed', 'false');
    btn.addEventListener('click', () => applyBorder(name));
    borderContainer.appendChild(btn);
  });
}

async function initThemes() {
  try {
    const res = await fetch('/assets/themes.json');
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
  buildBorderOptions();
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
  if (storedBorder && borders[storedBorder]) {
    applyBorder(storedBorder);
  } else {
    const firstBorder = Object.keys(borders)[0];
    if (firstBorder) applyBorder(firstBorder);
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
