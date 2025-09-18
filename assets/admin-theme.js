document.addEventListener('DOMContentLoaded', () => {
  const root = document.documentElement;
  const form = document.querySelector('form');
  if (!form) return;

  const gradientInput = document.getElementById('gradient');
  const ctaGradientInput = form.querySelector('[name="cta_gradient"]');
  const ctaDepthInput = form.querySelector('[name="cta_depth"]');
  const btnTextInput = form.querySelector('[name="btn_text"]');
  const btnTextIdleInput = form.querySelector('[name="btn_text_idle"]');

  const selectPairs = new Map();

  function normalizeValue(value, mode = 'css') {
    if (!value) return '';
    if (mode === 'numbers') {
      return value
        .split(',')
        .map(v => Number(v.trim()))
        .filter(n => Number.isFinite(n))
        .join(',');
    }
    return value.toLowerCase().replace(/['"\s]+/g, '');
  }

  function attachSelectInputPair(select) {
    const targetName = select.dataset.syncTarget;
    if (!targetName) return;
    const input = form.querySelector(`[name="${targetName}"]`);
    if (!input) return;
    const normalizeMode = select.dataset.syncNormalize || 'css';
    const customValue = select.dataset.customValue || '__custom__';
    const options = Array.from(select.options).map(option => ({
      option,
      value: option.dataset.value ?? option.value,
    }));

    function updateSelectFromInput() {
      const currentValue = normalizeValue(input.value, normalizeMode);
      let matched = false;
      options.forEach(({ option, value }) => {
        if (option.value === customValue) {
          return;
        }
        if (normalizeValue(value, normalizeMode) === currentValue) {
          option.selected = true;
          matched = true;
        } else {
          option.selected = false;
        }
      });
      if (!matched) {
        const customOption = select.querySelector(`option[value="${customValue}"]`);
        if (customOption) {
          customOption.selected = true;
        }
      }
    }

    function updateInputFromSelect() {
      const selectedOption = select.options[select.selectedIndex];
      if (!selectedOption || selectedOption.value === customValue) {
        return;
      }
      const nextValue = selectedOption.dataset.value ?? selectedOption.value;
      if (typeof input.value === 'string') {
        input.value = nextValue;
      }
      input.dispatchEvent(new Event('input', { bubbles: true }));
    }

    select.addEventListener('change', () => {
      updateInputFromSelect();
      preview();
    });

    input.addEventListener('input', () => {
      updateSelectFromInput();
    });

    updateSelectFromInput();
    selectPairs.set(targetName, { updateSelectFromInput });
  }

  document.querySelectorAll('.gradient-preset').forEach(btn => {
    btn.addEventListener('click', () => {
      if (gradientInput) {
        gradientInput.value = btn.dataset.gradient;
        const pair = selectPairs.get('gradient');
        if (pair) {
          pair.updateSelectFromInput();
        }
      }
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
  let patternPresets = {};
  let featureTargets = {};
  let featureCheckboxes = [];
  let presetRadios = [];

  if (patternSettings) {
    try {
      patternPresets = patternSettings.dataset.presets ? JSON.parse(patternSettings.dataset.presets) : {};
    } catch (err) {
      patternPresets = {};
      console.error('Failed to parse wave presets', err);
    }
    featureTargets = Array.from(patternSettings.querySelectorAll('[data-feature]')).reduce((acc, wrapper) => {
      const feature = wrapper.getAttribute('data-feature');
      if (!feature) return acc;
      const inputs = Array.from(wrapper.querySelectorAll('input, textarea, select'));
      acc[feature] = { wrapper, inputs };
      return acc;
    }, {});
    featureCheckboxes = Array.from(patternSettings.querySelectorAll('input[name="pattern_features[]"]'));
    presetRadios = Array.from(patternSettings.querySelectorAll('input[name="pattern_preset"]'));
  }

  form.querySelectorAll('select[data-sync-target]').forEach(select => attachSelectInputPair(select));

  const isPatternEnabled = () => !patternToggle || patternToggle.checked;

  function toggleFeature(feature, enabled) {
    const target = featureTargets[feature];
    if (!target) return;
    const active = enabled && isPatternEnabled();
    target.inputs.forEach(input => {
      input.disabled = !active;
    });
    if (target.wrapper) {
      target.wrapper.classList.toggle('feature-disabled', !active);
    }
  }

  function syncFeatures() {
    featureCheckboxes.forEach(cb => toggleFeature(cb.value, cb.checked));
  }

  function readSelectedFeatures() {
    const selections = {};
    featureCheckboxes.forEach(cb => {
      selections[cb.value] = cb.checked && isPatternEnabled();
    });
    return selections;
  }

  function applyPreset(presetKey) {
    const preset = patternPresets[presetKey];
    if (!preset) return;
    if (form.pattern_freq) {
      form.pattern_freq.value = preset.frequency;
      form.pattern_freq.dispatchEvent(new Event('input', { bubbles: true }));
    }
    if (form.pattern_amp) {
      form.pattern_amp.value = preset.amplitude;
      form.pattern_amp.dispatchEvent(new Event('input', { bubbles: true }));
    }
    if (form.pattern_poly) {
      form.pattern_poly.value = Array.isArray(preset.poly) ? preset.poly.join(',') : '';
      const pair = selectPairs.get('pattern_poly');
      if (pair) {
        pair.updateSelectFromInput();
      }
    }
    if (form.pattern_hue) {
      form.pattern_hue.value = typeof preset.hue === 'number' ? preset.hue : 0;
      form.pattern_hue.dispatchEvent(new Event('input', { bubbles: true }));
    }
    if (form.pattern_sat) {
      form.pattern_sat.value = typeof preset.sat === 'number' ? preset.sat : 100;
      form.pattern_sat.dispatchEvent(new Event('input', { bubbles: true }));
    }
    if (preset.features) {
      featureCheckboxes.forEach(cb => {
        if (Object.prototype.hasOwnProperty.call(preset.features, cb.value)) {
          cb.checked = !!preset.features[cb.value];
        }
      });
    }
    if (form.pattern_function && preset.function) {
      form.pattern_function.value = preset.function;
    }
    syncFeatures();
    preview();
  }

  if (patternToggle && patternSettings) {
    patternToggle.addEventListener('change', () => {
      patternSettings.style.display = patternToggle.checked ? 'block' : 'none';
      syncFeatures();
      preview();
    });
  }

  if (featureCheckboxes.length) {
    featureCheckboxes.forEach(cb => {
      toggleFeature(cb.value, cb.checked);
      cb.addEventListener('change', () => {
        toggleFeature(cb.value, cb.checked);
        preview();
      });
    });
  }

  if (presetRadios.length) {
    presetRadios.forEach(radio => {
      radio.addEventListener('change', () => {
        if (radio.checked && radio.value !== 'custom') {
          applyPreset(radio.value);
        } else {
          preview();
        }
      });
    });
    const presetFromData = patternSettings ? patternSettings.dataset.currentPreset : null;
    if (presetFromData && presetFromData !== 'custom') {
      const activeRadio = presetRadios.find(r => r.value === presetFromData);
      if (activeRadio && activeRadio.checked && isPatternEnabled()) {
        applyPreset(presetFromData);
      }
    } else {
      syncFeatures();
    }
  }

  function preview() {
    const bg = form.querySelector('[name="bg"]');
    const fg = form.querySelector('[name="fg"]');
    const accent = form.querySelector('[name="accent"]');
    if (bg) root.style.setProperty('--bg', bg.value);
    if (fg) root.style.setProperty('--fg', fg.value);
    if (accent) root.style.setProperty('--accent', accent.value);
    if (gradientInput) root.style.setProperty('--gradient', gradientInput.value);
    if (ctaGradientInput) root.style.setProperty('--cta-gradient', ctaGradientInput.value);
    if (ctaDepthInput) root.style.setProperty('--cta-depth', `${ctaDepthInput.value}px`);
    if (btnTextInput) root.style.setProperty('--btn-text', btnTextInput.value);
    if (btnTextIdleInput) root.style.setProperty('--btn-text-idle', btnTextIdleInput.value);
    ['vap1', 'vap2', 'vap3'].forEach(v => {
      const el = form.querySelector(`[name="${v}"]`);
      if (el) root.style.setProperty(`--${v}`, el.value);
    });
    [['font_header', '--font-header'], ['font_body', '--font-body'], ['font_paragraph', '--font-paragraph']].forEach(([name, varName]) => {
      const el = form.querySelector(`[name="${name}"]`);
      if (el) root.style.setProperty(varName, el.value);
    });

    if (isPatternEnabled()) {
      const features = readSelectedFeatures();
      let preset = 'custom';
      if (patternSettings) {
        const selectedPresetInput = patternSettings.querySelector('input[name="pattern_preset"]:checked');
        if (selectedPresetInput && selectedPresetInput.value) {
          preset = selectedPresetInput.value;
        }
      }
      const data = {
        preset,
        frequency: form.pattern_freq ? form.pattern_freq.value : 0,
        amplitude: form.pattern_amp ? form.pattern_amp.value : 0,
        poly: features.poly && form.pattern_poly ? form.pattern_poly.value.split(',').map(Number).filter(n => !Number.isNaN(n)) : [],
        hue: features.hue && form.pattern_hue ? form.pattern_hue.value : 0,
        sat: features.sat && form.pattern_sat ? form.pattern_sat.value : 100,
        function: form.pattern_function ? form.pattern_function.value : 'sine',
        features,
      };
      if (window.generateVaporwavePattern) {
        window.generateVaporwavePattern(data);
      }
    } else if (window.generateVaporwavePattern) {
      window.generateVaporwavePattern(null);
    }
  }

  form.querySelectorAll('input').forEach(inp => inp.addEventListener('input', preview));
  if (form.pattern_function) {
    form.pattern_function.addEventListener('change', preview);
  }
  form.querySelectorAll('select:not([data-sync-target])').forEach(sel => {
    sel.addEventListener('change', preview);
  });
  preview();

  form.addEventListener('submit', e => {
    if (gradientInput && gradientInput.value.trim() && !CSS.supports('background', gradientInput.value)) {
      alert('Invalid gradient CSS');
      e.preventDefault();
    }
    if (ctaGradientInput && ctaGradientInput.value.trim() && !CSS.supports('background', ctaGradientInput.value)) {
      alert('Invalid CTA gradient CSS');
      e.preventDefault();
    }
  });
});
