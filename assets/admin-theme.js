document.addEventListener('DOMContentLoaded', () => {
  const root = document.documentElement;
  const form = document.querySelector('form');
  if (!form) return;

  const gradientInput = document.getElementById('gradient');
  document.querySelectorAll('.gradient-preset').forEach(btn => {
    btn.addEventListener('click', () => {
      gradientInput.value = btn.dataset.gradient;
      document.querySelectorAll('.gradient-preset').forEach(b => b.classList.remove('selected'));
      btn.classList.add('selected');
      preview();
    });
    if (gradientInput && gradientInput.value === btn.dataset.gradient) {
      btn.classList.add('selected');
    }
  });

  form.querySelectorAll('input[type="range"]').forEach(inp => {
    const display = document.getElementById(inp.id + '_val');
    const update = () => { if (display) display.textContent = inp.value; };
    inp.addEventListener('input', update);
    update();
  });

  const patternToggle = document.getElementById('pattern_toggle');
  const patternSettings = document.getElementById('pattern_settings');
  if (patternToggle && patternSettings) {
    patternToggle.addEventListener('change', () => {
      patternSettings.style.display = patternToggle.checked ? 'block' : 'none';
      preview();
    });
  }

  function preview() {
    const bg = form.querySelector('[name="bg"]');
    const fg = form.querySelector('[name="fg"]');
    const accent = form.querySelector('[name="accent"]');
    if (bg) root.style.setProperty('--bg', bg.value);
    if (fg) root.style.setProperty('--fg', fg.value);
    if (accent) root.style.setProperty('--accent', accent.value);
    if (gradientInput) root.style.setProperty('--gradient', gradientInput.value);
    ['vap1', 'vap2', 'vap3'].forEach(v => {
      const el = form.querySelector(`[name="${v}"]`);
      if (el) root.style.setProperty(`--${v}`, el.value);
    });
    if (patternToggle && patternToggle.checked) {
      const s = {
        frequency: form.pattern_freq.value,
        amplitude: form.pattern_amp.value,
        poly: form.pattern_poly.value.split(',').map(Number).filter(n => !isNaN(n)),
        hue: form.pattern_hue.value,
        sat: form.pattern_sat.value,
      };
      if (window.generateVaporwavePattern) {
        window.generateVaporwavePattern(s);
      }
    } else if (window.generateVaporwavePattern) {
      window.generateVaporwavePattern(null);
    }
  }

  form.querySelectorAll('input').forEach(inp => inp.addEventListener('input', preview));
  preview();

  form.addEventListener('submit', e => {
    if (gradientInput && gradientInput.value.trim() && !CSS.supports('background', gradientInput.value)) {
      alert('Invalid gradient CSS');
      e.preventDefault();
    }
  });
});
